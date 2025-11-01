<?php
/**
 * Facty Pro Perplexity Analyzer
 * Deep research fact-checking using Perplexity with claim-by-claim analysis and sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Perplexity {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Main analysis method
     */
    public function analyze($content, $job_id = null) {
        $api_key = $this->options['perplexity_api_key'];
        $model = isset($this->options['perplexity_model']) ? $this->options['perplexity_model'] : 'sonar-pro';
        
        if (empty($api_key)) {
            throw new Exception('Perplexity API key not configured');
        }
        
        if ($job_id) {
            Facty_Pro_Action_Scheduler::update_job_status($job_id, 15, 'analyzing', 'Starting deep research...');
        }
        
        $current_date = current_time('F j, Y');
        $recency_filter = isset($this->options['recency_filter']) ? $this->options['recency_filter'] : 'week';
        
        // Build comprehensive fact-check prompt
        $prompt = $this->build_editor_fact_check_prompt($content, $current_date, $recency_filter);
        
        if ($job_id) {
            Facty_Pro_Action_Scheduler::update_job_status($job_id, 30, 'researching', 'Researching claims with real-time sources...');
        }
        
        // Call Perplexity API
        $result = $this->call_perplexity_api($prompt, $model, $api_key, $recency_filter);
        
        if ($job_id) {
            Facty_Pro_Action_Scheduler::update_job_status($job_id, 55, 'compiling', 'Compiling detailed report...');
        }
        
        return $result;
    }
    
    /**
     * Build comprehensive prompt for editorial fact-checking
     */
    private function build_editor_fact_check_prompt($content, $current_date, $recency_filter) {
        return "You are an expert fact-checker helping EDITORS verify article accuracy BEFORE publication. Today is {$current_date}.

**YOUR MISSION**: Provide a detailed, actionable report that helps editors fix issues and improve their article.

**ARTICLE TO FACT-CHECK**:
{$content}

**INSTRUCTIONS**:

1. **DETECT SATIRE FIRST**: If this is clearly satirical content (absurd scenarios, obvious jokes), immediately return:
```json
{
    \"score\": 100,
    \"status\": \"Satire\",
    \"description\": \"This is satirical content.\",
    \"claims\": [],
    \"issues\": [],
    \"verified_facts\": [],
    \"sources\": []
}
```

2. **FOR REAL ARTICLES**: Identify 5-15 key FACTUAL claims (skip opinions/predictions) and verify each one using real-time web search.

3. **CRITICAL VERIFICATION RULES**:
   - ✅ Use ONLY sources from the last {$recency_filter} (prioritize last few days for current events)
   - ✅ For current events/office holders: VERIFY as of {$current_date} with RECENT sources
   - ✅ Cross-reference multiple credible sources before marking as false
   - ⚠️ **NEVER mark as \"Factual Error\" unless you have STRONG contradicting evidence**
   - ⚠️ Lack of sources = \"Unverified\", NOT \"Factual Error\"
   - ⚠️ Unverified ≠ False (if you can't verify, say that, don't call it false)

4. **SCORING GUIDE** (Be precise - use full 0-100 range):
   - **95-100**: Completely accurate, well-sourced, current
   - **85-94**: Accurate with minor issues or some unverified claims
   - **70-84**: Mostly accurate, some problems
   - **50-69**: Mixed accuracy, significant concerns
   - **30-49**: Mostly inaccurate or outdated
   - **0-29**: False or highly misleading

   **IMPORTANT**: Unverified claims should NOT heavily penalize the score. Good articles with some unverified claims can still score 80-90.

5. **RETURN THIS EXACT JSON**:
```json
{
    \"score\": <0-100 integer>,
    \"status\": \"Verified\" | \"Mostly Accurate\" | \"Needs Review\" | \"Multiple Errors\" | \"False\" | \"Satire\",
    \"description\": \"One clear sentence for editors explaining overall accuracy\",
    \"claims\": [
        {
            \"claim\": \"Exact quote or paraphrase from article\",
            \"verdict\": \"Accurate\" | \"Partially True\" | \"False\" | \"Unverified\",
            \"confidence\": \"high\" | \"medium\" | \"low\",
            \"explanation\": \"Clear explanation for editors\",
            \"sources\": [
                {
                    \"title\": \"Source name\",
                    \"url\": \"https://...\",
                    \"date\": \"2025-11-01\",
                    \"credibility\": \"high\" | \"medium\"
                }
            ]
        }
    ],
    \"issues\": [
        {
            \"claim\": \"Exact quote from article\",
            \"type\": \"Factual Error\" | \"Outdated\" | \"Misleading\" | \"Unverified\" | \"Missing Context\",
            \"severity\": \"high\" | \"medium\" | \"low\",
            \"what_article_says\": \"The problematic claim\",
            \"the_problem\": \"Why it's wrong/misleading/unverified\",
            \"actual_facts\": \"What's actually true (with sources)\",
            \"how_to_fix\": \"Specific suggestion for editor\",
            \"sources\": [
                {
                    \"title\": \"Source\",
                    \"url\": \"https://...\",
                    \"date\": \"2025-11-01\"
                }
            ]
        }
    ],
    \"verified_facts\": [
        {
            \"claim\": \"Accurate claim from article\",
            \"confidence\": \"high\" | \"medium\",
            \"sources\": [
                {
                    \"title\": \"Source\",
                    \"url\": \"https://...\"
                }
            ]
        }
    ],
    \"sources\": [
        {
            \"title\": \"Source name\",
            \"url\": \"https://...\",
            \"credibility\": \"high\" | \"medium\",
            \"date\": \"2025-11-01\"
        }
    ]
}
```

**ISSUE TYPE GUIDE**:
- **\"Factual Error\"**: Use ONLY when you have clear evidence that CONTRADICTS the claim from multiple recent, credible sources
- **\"Unverified\"**: Use when you cannot find sources (lack of evidence ≠ false)
- **\"Outdated\"**: Use when claim was true but is no longer accurate as of {$current_date}
- **\"Misleading\"**: Use when technically true but missing critical context
- **\"Missing Context\"**: Use when needs additional information

**CRITICAL REQUIREMENTS**:
- Include specific sources for EACH claim/issue
- For unverified claims, suggest where editors might find verification
- Prioritize sources dated closest to {$current_date}
- Use YOUR real-time web search to verify current facts
- Write for editors who will ACT on this feedback
- Return ONLY valid JSON (no markdown formatting)";
    }
    
    /**
     * Call Perplexity API
     */
    private function call_perplexity_api($prompt, $model, $api_key, $recency_filter) {
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a precise fact-checker for editors. Return only valid JSON. CRITICAL: Always prioritize MOST RECENT sources. NEVER mark as false unless you have strong contradicting evidence - if uncertain, mark as UNVERIFIED.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.2,
                'max_tokens' => 6000,
                'return_citations' => true,
                'search_recency_filter' => $recency_filter
            )),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            throw new Exception('Perplexity API Error (' . $http_code . '): ' . $error_message);
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }
        
        $ai_content = trim($data['choices'][0]['message']['content']);
        
        // Clean up JSON response
        $ai_content = preg_replace('/^```json\s*/m', '', $ai_content);
        $ai_content = preg_replace('/\s*```$/m', '', $ai_content);
        $ai_content = trim($ai_content);
        
        $result = json_decode($ai_content, true);
        
        if (!$result || !is_array($result)) {
            // Fallback if JSON parsing fails
            return array(
                'score' => 50,
                'status' => 'Analysis Incomplete',
                'description' => 'Analysis completed but response format was invalid.',
                'claims' => array(),
                'issues' => array(),
                'verified_facts' => array(),
                'sources' => $this->extract_citations($data)
            );
        }
        
        // Ensure required fields exist
        $result = array_merge(array(
            'score' => 0,
            'status' => 'Unknown',
            'description' => 'No description provided',
            'claims' => array(),
            'issues' => array(),
            'verified_facts' => array(),
            'sources' => array()
        ), $result);
        
        // Validate and clamp score
        $result['score'] = max(0, min(100, intval($result['score'])));
        
        // Extract citations from Perplexity and merge
        $citations = $this->extract_citations($data);
        if (!empty($citations)) {
            $result['sources'] = array_merge($citations, $result['sources']);
            $result['sources'] = array_values(array_unique($result['sources'], SORT_REGULAR));
            $result['sources'] = array_slice($result['sources'], 0, 20);
        }
        
        return $result;
    }
    
    /**
     * Extract citations from Perplexity API response
     */
    private function extract_citations($api_response_data) {
        $sources = array();
        
        if (isset($api_response_data['citations']) && is_array($api_response_data['citations'])) {
            foreach ($api_response_data['citations'] as $citation) {
                if (isset($citation['url'])) {
                    $sources[] = array(
                        'title' => isset($citation['title']) ? $citation['title'] : parse_url($citation['url'], PHP_URL_HOST),
                        'url' => $citation['url'],
                        'credibility' => 'high'
                    );
                }
            }
        }
        
        return $sources;
    }
}
