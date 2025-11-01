<?php
/**
 * Facty Perplexity Multi-Step Analyzer
 * PREMIUM: Highest accuracy fact-checking with separate API calls per claim
 * Step 1: Extract claims from article
 * Step 2: Verify each claim individually with dedicated web search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Perplexity_MultiStep_Analyzer {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Main analysis method - Multi-step for maximum accuracy
     */
    public function analyze($content, $task_id = null) {
        $api_key = $this->options['perplexity_api_key'];
        $model = isset($this->options['perplexity_model']) ? $this->options['perplexity_model'] : 'sonar-pro';
        
        if (empty($api_key)) {
            throw new Exception('Perplexity API key not configured');
        }
        
        if ($task_id) {
            $this->update_progress($task_id, 10, 'analyzing', 'Starting multi-step analysis...');
        }
        
        $current_date = current_time('F j, Y');
        
        // Check for satire first (quick check)
        if ($this->is_satire($content)) {
            if ($task_id) {
                $this->update_progress($task_id, 100, 'complete', 'Satire detected');
            }
            
            return array(
                'score' => 100,
                'status' => 'Satire',
                'description' => 'This is satirical content meant for entertainment.',
                'issues' => array(),
                'verified_facts' => array(),
                'sources' => array(),
                'mode' => 'perplexity-multistep'
            );
        }
        
        // STEP 1: Extract claims from article
        if ($task_id) {
            $this->update_progress($task_id, 20, 'extracting', 'Extracting factual claims...');
        }
        
        $claims = $this->extract_claims($content, $current_date, $model, $api_key);
        
        if (empty($claims)) {
            return array(
                'score' => 75,
                'status' => 'Needs Review',
                'description' => 'No specific factual claims found to verify in this article.',
                'issues' => array(),
                'verified_facts' => array(),
                'sources' => array(),
                'mode' => 'perplexity-multistep'
            );
        }
        
        if ($task_id) {
            $this->update_progress($task_id, 30, 'verifying', 'Found ' . count($claims) . ' claims. Verifying each claim...');
        }
        
        // STEP 2: Verify each claim individually
        $verification_results = array();
        $total_claims = count($claims);
        
        foreach ($claims as $index => $claim) {
            if ($task_id) {
                $percent = 30 + (($index + 1) / $total_claims * 60);
                $this->update_progress($task_id, $percent, 'verifying', 'Verifying claim ' . ($index + 1) . ' of ' . $total_claims . '...');
            }
            
            $result = $this->verify_single_claim($claim, $current_date, $model, $api_key);
            $verification_results[] = $result;
            
            // Small delay to avoid rate limits
            usleep(300000); // 0.3 seconds
        }
        
        if ($task_id) {
            $this->update_progress($task_id, 95, 'generating', 'Compiling comprehensive report...');
        }
        
        // STEP 3: Compile all results into final report
        $final_report = $this->compile_final_report($verification_results, $total_claims);
        $final_report['mode'] = 'perplexity-multistep';
        
        return $final_report;
    }
    
    /**
     * STEP 1: Extract factual claims from article
     */
    private function extract_claims($content, $current_date, $model, $api_key) {
        $max_claims = isset($this->options['perplexity_multistep_max_claims']) ? intval($this->options['perplexity_multistep_max_claims']) : 10;
        
        $prompt = "You are extracting factual claims from an article for verification. Today is {$current_date}.

**TASK:** Extract ONLY factual claims that can be verified (up to {$max_claims} maximum).

**WHAT TO EXTRACT:**
- Specific facts, statistics, numbers, dates
- Claims about events, people, places, policies
- Statements that can be true or false
- Current office holders, appointments, positions

**WHAT TO SKIP:**
- Opinions, predictions, speculation
- Questions or hypotheticals
- General observations
- Obvious common knowledge

**ARTICLE:**
{$content}

**RETURN EXACTLY THIS JSON FORMAT:**
```json
{
    \"claims\": [
        {
            \"claim\": \"Exact quote from article\",
            \"type\": \"statistic\" | \"event\" | \"appointment\" | \"policy\" | \"general_fact\",
            \"priority\": \"high\" | \"medium\" | \"low\"
        }
    ]
}
```

Return ONLY the JSON with up to {$max_claims} most important factual claims to verify. No other text.";

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
                        'content' => 'You are a claim extraction specialist. Return only valid JSON.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.1,
                'max_tokens' => 2000
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Perplexity Multi-Step - Claim Extraction Error: ' . $response->get_error_message());
            return array();
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            error_log('Perplexity Multi-Step - Claim Extraction HTTP Error: ' . $http_code);
            return array();
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            error_log('Perplexity Multi-Step - Invalid claim extraction response');
            return array();
        }
        
        $ai_content = trim($data['choices'][0]['message']['content']);
        $ai_content = preg_replace('/^```json\s*/m', '', $ai_content);
        $ai_content = preg_replace('/\s*```$/m', '', $ai_content);
        $ai_content = trim($ai_content);
        
        $result = json_decode($ai_content, true);
        
        if (!$result || !isset($result['claims']) || !is_array($result['claims'])) {
            error_log('Perplexity Multi-Step - Failed to parse claims JSON');
            return array();
        }
        
        // Limit to configured max claims
        return array_slice($result['claims'], 0, $max_claims);
    }
    
    /**
     * STEP 2: Verify a single claim with dedicated web search
     */
    private function verify_single_claim($claim_data, $current_date, $model, $api_key) {
        $claim = isset($claim_data['claim']) ? $claim_data['claim'] : '';
        $type = isset($claim_data['type']) ? $claim_data['type'] : 'general_fact';
        
        if (empty($claim)) {
            return array(
                'claim' => 'Unknown claim',
                'is_accurate' => false,
                'confidence' => 'low',
                'explanation' => 'Claim was empty or invalid',
                'sources' => array()
            );
        }
        
        // Get recency settings
        $recency_value = isset($this->options['perplexity_search_recency_value']) ? intval($this->options['perplexity_search_recency_value']) : 1;
        $recency_unit = isset($this->options['perplexity_search_recency_unit']) ? $this->options['perplexity_search_recency_unit'] : 'week';
        $recency_description = $recency_value . ' ' . $recency_unit . ($recency_value > 1 ? 's' : '');
        $search_filter = $recency_unit;
        
        $prompt = "You are fact-checking a SINGLE specific claim. Today is {$current_date}.

**SMART RECENCY:** You have access to sources from the past {$recency_description}. ALWAYS PRIORITIZE THE MOST RECENT sources (last few days) for current events. Use older sources only for historical context.

**CLAIM TO VERIFY:**
\"{$claim}\"

**CLAIM TYPE:** {$type}

**YOUR TASK:**
1. Use real-time web search - PRIORITIZE MOST RECENT sources for current events
2. For political/current events: Verify current office holders AS OF {$current_date} using RECENT sources
3. Cross-reference at least 2-3 reliable sources (prefer newer sources)
4. Determine if the claim is accurate, outdated, misleading, or false

**VERIFICATION CHECKLIST:**
- **CRITICAL: NEVER mark as \"factual_error\" unless you have STRONG, RECENT contradicting evidence from credible sources**
- **Lack of sources = \"unverified\", NOT \"factual_error\"**
- If claim mentions current president/officials: Verify who holds position AS OF {$current_date} using sources from last few days
- Check dates and timelines match {$current_date}
- Look for updates or corrections to the claim
- Assess if claim needs additional context
- Prioritize sources dated closer to {$current_date}
- For events from the last 1-2 hours: It's acceptable to mark as \"unverified\" with explanation \"Very recent event - sources may not be indexed yet\"
- When in doubt between \"unverified\" and \"factual_error\": Choose \"unverified\"

**RETURN THIS EXACT JSON:**
```json
{
    \"claim\": \"The claim being verified\",
    \"is_accurate\": true | false,
    \"confidence\": \"high\" | \"medium\" | \"low\",
    \"issue_type\": \"none\" | \"factual_error\" | \"outdated\" | \"misleading\" | \"unverified\" | \"missing_context\",
    \"explanation\": \"Brief explanation of accuracy status with current facts as of {$current_date}\",
    \"actual_facts\": \"What is actually true as of {$current_date} (if claim is inaccurate)\",
    \"why_it_matters\": \"Why this matters to readers (if inaccurate)\",
    \"sources\": [
        {
            \"title\": \"Source title\",
            \"url\": \"https://...\",
            \"date\": \"Recent date if available\",
            \"credibility\": \"high\" | \"medium\"
        }
    ]
}
```

**CRITICAL:** Verify current information as of {$current_date}. Prioritize MOST RECENT sources. Return ONLY valid JSON.";

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
                        'content' => "You are a precise fact-checker. You have access to sources from the past {$recency_description}. CRITICAL: Always PRIORITIZE the MOST RECENT sources (last few days) when verifying current information. NEVER mark a claim as factual_error unless you have strong contradicting evidence - if you can't find sources, mark it as unverified. Return only valid JSON."
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.2,
                'max_tokens' => 1500,
                'return_citations' => true,
                'search_recency_filter' => $search_filter
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Perplexity Multi-Step - Verification Error for claim: ' . $claim);
            return array(
                'claim' => $claim,
                'is_accurate' => false,
                'confidence' => 'low',
                'issue_type' => 'unverified',
                'explanation' => 'API error during verification',
                'sources' => array()
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            error_log('Perplexity Multi-Step - Verification HTTP Error: ' . $http_code);
            return array(
                'claim' => $claim,
                'is_accurate' => false,
                'confidence' => 'low',
                'issue_type' => 'unverified',
                'explanation' => 'HTTP error during verification',
                'sources' => array()
            );
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return array(
                'claim' => $claim,
                'is_accurate' => false,
                'confidence' => 'low',
                'issue_type' => 'unverified',
                'explanation' => 'Invalid response format',
                'sources' => array()
            );
        }
        
        $ai_content = trim($data['choices'][0]['message']['content']);
        $ai_content = preg_replace('/^```json\s*/m', '', $ai_content);
        $ai_content = preg_replace('/\s*```$/m', '', $ai_content);
        $ai_content = trim($ai_content);
        
        $result = json_decode($ai_content, true);
        
        if (!$result || !is_array($result)) {
            return array(
                'claim' => $claim,
                'is_accurate' => false,
                'confidence' => 'low',
                'issue_type' => 'unverified',
                'explanation' => 'Failed to parse verification result',
                'sources' => array()
            );
        }
        
        // Extract citations from Perplexity response
        $citations = $this->extract_citations($data);
        if (!empty($citations) && isset($result['sources'])) {
            $result['sources'] = array_merge($citations, $result['sources']);
            $result['sources'] = array_slice(array_unique($result['sources'], SORT_REGULAR), 0, 5);
        }
        
        // Ensure required fields
        $result = array_merge(array(
            'claim' => $claim,
            'is_accurate' => false,
            'confidence' => 'low',
            'issue_type' => 'unverified',
            'explanation' => 'No explanation provided',
            'sources' => array()
        ), $result);
        
        return $result;
    }
    
    /**
     * STEP 3: Compile all verification results into final report
     */
    private function compile_final_report($verification_results, $total_claims) {
        $accurate_count = 0;
        $inaccurate_count = 0;
        $high_severity_issues = 0;
        $unverified_count = 0;
        $misleading_count = 0;
        $false_count = 0;
        
        $issues = array();
        $verified_facts = array();
        $all_sources = array();
        
        foreach ($verification_results as $result) {
            $is_accurate = isset($result['is_accurate']) ? $result['is_accurate'] : false;
            $confidence = isset($result['confidence']) ? $result['confidence'] : 'low';
            $issue_type = isset($result['issue_type']) ? $result['issue_type'] : 'unverified';
            
            if ($is_accurate && $confidence !== 'low') {
                $accurate_count++;
                $verified_facts[] = array(
                    'claim' => $result['claim'],
                    'confidence' => $confidence
                );
            } else {
                $inaccurate_count++;
                
                // Count by issue type for better scoring
                switch ($issue_type) {
                    case 'factual_error':
                        $false_count++;
                        break;
                    case 'outdated':
                    case 'misleading':
                    case 'missing_context':
                        $misleading_count++;
                        break;
                    case 'unverified':
                        $unverified_count++;
                        break;
                }
                
                // Map issue type to severity
                $severity = 'medium';
                $type_label = 'Unverified';
                
                switch ($issue_type) {
                    case 'factual_error':
                        $severity = 'high';
                        $type_label = 'Factual Error';
                        $high_severity_issues++;
                        break;
                    case 'outdated':
                        $severity = 'medium';
                        $type_label = 'Outdated';
                        break;
                    case 'misleading':
                        $severity = 'medium';
                        $type_label = 'Misleading';
                        break;
                    case 'missing_context':
                        $severity = 'low';
                        $type_label = 'Missing Context';
                        break;
                    case 'unverified':
                        $severity = 'low';
                        $type_label = 'Unverified';
                        break;
                }
                
                $issues[] = array(
                    'claim' => $result['claim'],
                    'type' => $type_label,
                    'severity' => $severity,
                    'what_article_says' => $result['claim'],
                    'the_problem' => isset($result['explanation']) ? $result['explanation'] : 'Could not verify this claim',
                    'actual_facts' => isset($result['actual_facts']) ? $result['actual_facts'] : 'See explanation',
                    'why_it_matters' => isset($result['why_it_matters']) ? $result['why_it_matters'] : 'Accuracy is important for reader trust'
                );
            }
            
            // Collect sources
            if (!empty($result['sources'])) {
                foreach ($result['sources'] as $source) {
                    if (isset($source['url'])) {
                        $all_sources[] = array(
                            'title' => isset($source['title']) ? $source['title'] : 'Source',
                            'url' => $source['url'],
                            'credibility' => isset($source['credibility']) ? $source['credibility'] : 'medium'
                        );
                    }
                }
            }
        }
        
        // Calculate score using WEIGHTED system
        // Unverified claims shouldn't hurt score as much as false claims
        if ($total_claims > 0) {
            $weighted_score = (
                ($accurate_count * 100) +      // Accurate = 100%
                ($unverified_count * 80) +     // Unverified = 80% (not as bad as false)
                ($misleading_count * 50) +     // Misleading/Outdated = 50%
                ($false_count * 0)             // False = 0%
            ) / $total_claims;
            
            $score = round($weighted_score);
        } else {
            $score = 0;
        }
        
        // Additional penalty for high severity issues (false claims)
        if ($high_severity_issues > 0) {
            $score = max(0, $score - ($high_severity_issues * 5)); // Reduced from 10 to 5
        }
        
        // Determine status
        $status = 'Verified';
        if ($score >= 95) {
            $status = 'Verified';
        } elseif ($score >= 85) {
            $status = 'Mostly Accurate';
        } elseif ($score >= 70) {
            $status = 'Needs Review';
        } elseif ($score >= 50) {
            $status = 'Multiple Errors';
        } else {
            $status = 'False';
        }
        
        $description = "Multi-step verification analyzed {$total_claims} claims: {$accurate_count} verified accurate, {$inaccurate_count} with issues.";
        
        return array(
            'score' => max(0, min(100, $score)),
            'status' => $status,
            'description' => $description,
            'issues' => $issues,
            'verified_facts' => $verified_facts,
            'sources' => array_slice(array_unique($all_sources, SORT_REGULAR), 0, 20)
        );
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
    
    /**
     * Quick satire detection
     */
    private function is_satire($content) {
        $satire_indicators = array(
            '/\b(satire|satirical|parody|joke|humor|humorous|comedy|comedic|onion|babylonbee)\b/i',
            '/\b(not to be taken seriously|for entertainment purposes|fictional account)\b/i'
        );
        
        foreach ($satire_indicators as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update progress for background processing
     */
    private function update_progress($task_id, $percentage, $stage, $message) {
        set_transient($task_id, array(
            'status' => 'processing',
            'progress' => $percentage,
            'stage' => $stage,
            'message' => $message
        ), 600);
    }
}