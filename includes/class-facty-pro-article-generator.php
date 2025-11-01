<?php
/**
 * Facty Pro Article Generator
 * Generates comprehensive fact-check articles from misinformation claims using Perplexity AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Article_Generator {
    
    private $options;
    private $perplexity_api_key;
    
    public function __construct($options) {
        $this->options = $options;
        $this->perplexity_api_key = isset($options['perplexity_api_key']) ? $options['perplexity_api_key'] : '';
    }
    
    /**
     * Generate article from claim
     */
    public function generate_article($claim) {
        if (empty($this->perplexity_api_key)) {
            throw new Exception('Perplexity API key not configured');
        }
        
        error_log('Facty Pro: Generating article for claim: ' . substr($claim->claim_text, 0, 100));
        
        // Step 1: Research the claim
        $research = $this->research_claim($claim);
        
        // Step 2: Generate article
        $article = $this->generate_article_content($claim, $research);
        
        // Step 3: Create WordPress post
        $post_id = $this->create_wordpress_post($claim, $article);
        
        return $post_id;
    }
    
    /**
     * Research the claim using Perplexity
     */
    private function research_claim($claim) {
        error_log('Facty Pro: Researching claim');
        
        $current_date = current_time('F j, Y');
        $metadata = json_decode($claim->metadata, true);
        
        $prompt = "Research this FALSE CLAIM that has been spreading in the UK: \"{$claim->claim_text}\"

Today is {$current_date}. This claim has been rated as: {$claim->rating}

Provide comprehensive research with SPECIFIC SOURCES:

1. **Origin & Context**: Where did this claim originate? Who is spreading it? What's the full context?

2. **Why It's False**: Detailed explanation with specific evidence, facts, and data that prove this is false or misleading.

3. **The Actual Truth**: What are the real facts? Include specific numbers, dates, and verified information.

4. **Credible Sources**: List SPECIFIC sources with:
   - Official government sources (gov.uk, NHS, etc.)
   - Fact-checking organizations (Full Fact, BBC Reality Check, etc.)
   - News organizations (BBC, Guardian, Telegraph, etc.)
   - Academic/research institutions
   - Include URLs where possible

5. **Impact**: Why does this false claim matter? Who is it harming? What are the real-world consequences?

6. **Related Misinformation**: Are there related false claims circulating?

Focus on UK perspectives and sources. Be specific, detailed, and cite exact evidence.

IMPORTANT: Provide actual source URLs and names, not generic references.

Return your research in a structured format with clear source citations.";
        
        $result = $this->call_perplexity($prompt, 'sonar-pro');
        
        return $result;
    }
    
    /**
     * Generate article content
     */
    private function generate_article_content($claim, $research) {
        error_log('Facty Pro: Generating article content');
        
        $current_date = current_time('F j, Y');
        $category_name = $this->get_category_name($claim->category);
        
        $prompt = "Write a comprehensive, professional fact-check article about this FALSE CLAIM:

**THE FALSE CLAIM**: \"{$claim->claim_text}\"

**RESEARCH FINDINGS**:
{$research}

**ARTICLE REQUIREMENTS**:

Write a complete fact-check article (800-1200 words) with this exact structure:

# [Compelling Headline That Clearly States This Is False]

## The Claim

[Explain what is being claimed, where it's spreading, who is sharing it. Make it clear what people are saying.]

## The Facts

[This is THE TRUTH. What actually happened? What are the real facts? Be specific with numbers, dates, sources.]

## Why This Is False

[Detailed explanation of exactly why the claim is false or misleading. Break down each part. Use evidence.]

## The Evidence

[Present the proof: official statistics, expert statements, credible sources, verified data. Each piece of evidence should be specific and cited with [1], [2], etc.]

## Why This Matters

[Explain the real-world impact. Why should UK readers care? Who is being harmed by this misinformation?]

## Our Verdict

[Clear, definitive statement: False/Misleading/Debunked with brief summary]

## Sources

[List all sources used, numbered to match citations in text:
1. [Source name] - [URL]
2. [Source name] - [URL]
etc.]

---

**WRITING STYLE**:
- Professional but accessible
- Clear and direct - no jargon
- Specific details (exact numbers, dates, names)
- Authoritative tone
- UK-focused perspective
- Cite sources with [1], [2], [3] etc. and list them in Sources section

**CRITICAL**:
- Do NOT invent sources or quotes
- Do NOT add fictional details
- Base everything on the research provided
- Be factual and verifiable
- Today's date is {$current_date}
- MUST include numbered Sources section at the end

Return ONLY the article content, no meta-commentary.";
        
        $article_content = $this->call_perplexity($prompt, 'sonar-pro');
        
        return $article_content;
    }
    
    /**
     * Call Perplexity API
     */
    private function call_perplexity($prompt, $model) {
        $request_body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert UK fact-checker. Provide detailed, accurate, well-researched information. Always cite sources and be specific.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.3,
            'max_tokens' => 8000,
            'return_citations' => true,
            'search_recency_filter' => 'week'
        );
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->perplexity_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Perplexity API error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            throw new Exception('Perplexity API returned error: ' . $http_code);
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid Perplexity API response');
        }
        
        // Get content
        $content = trim($data['choices'][0]['message']['content']);
        
        // Extract citations if available
        $citations = array();
        if (isset($data['citations']) && is_array($data['citations'])) {
            $citations = $data['citations'];
        }
        
        // If content doesn't have a Sources section and we have citations, add them
        if (!empty($citations) && stripos($content, '## Sources') === false) {
            $content .= "\n\n## Sources\n\n";
            $i = 1;
            foreach ($citations as $citation) {
                if (isset($citation['url'])) {
                    $title = isset($citation['title']) ? $citation['title'] : parse_url($citation['url'], PHP_URL_HOST);
                    $content .= $i . ". " . $title . " - " . $citation['url'] . "\n";
                    $i++;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Create WordPress post from article
     */
    private function create_wordpress_post($claim, $article_content) {
        // Extract headline from article (first line with #)
        $lines = explode("\n", $article_content);
        $headline = '';
        $content = '';
        
        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/', $line, $matches)) {
                $headline = trim($matches[1]);
                break;
            }
        }
        
        // If no headline found, generate one
        if (empty($headline)) {
            $headline = 'Fact Check: ' . wp_trim_words($claim->claim_text, 10, '...');
        }
        
        // Convert markdown to HTML
        $content = $this->markdown_to_html($article_content);
        
        // Prepare post data
        $post_data = array(
            'post_title' => $headline,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        );
        
        // Create post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create post: ' . $post_id->get_error_message());
        }
        
        // Set category
        $category_id = $this->get_or_create_category($claim->category);
        if ($category_id) {
            wp_set_post_categories($post_id, array($category_id));
        }
        
        // Add tags
        $tags = array('fact-check', 'misinformation', $claim->category);
        wp_set_post_tags($post_id, $tags);
        
        // Add post meta
        update_post_meta($post_id, '_facty_pro_claim_id', $claim->id);
        update_post_meta($post_id, '_facty_pro_original_claim', $claim->claim_text);
        update_post_meta($post_id, '_facty_pro_claim_source', $claim->source);
        update_post_meta($post_id, '_facty_pro_claim_rating', $claim->rating);
        
        if (!empty($claim->source_url)) {
            update_post_meta($post_id, '_facty_pro_source_url', $claim->source_url);
        }
        
        error_log('Facty Pro: Article created successfully - Post ID: ' . $post_id);
        
        return $post_id;
    }
    
    /**
     * Simple markdown to HTML converter
     */
    private function markdown_to_html($markdown) {
        // Convert headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Convert bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        
        // Convert italic
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // Convert numbered lists (for sources section)
        $html = preg_replace_callback('/^(\d+)\.\s+(.+)$/m', function($matches) {
            $text = $matches[2];
            // Convert URLs to links
            $text = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" rel="nofollow noopener">$1</a>', $text);
            return '<li>' . $text . '</li>';
        }, $html);
        
        // Wrap consecutive <li> in <ol>
        $html = preg_replace('/(<li>.*?<\/li>\s*)+/s', '<ol>$0</ol>', $html);
        
        // Convert unordered lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*?<\/li>\n?)+(?!<\/ol>)/s', '<ul>$0</ul>', $html);
        
        // Convert line breaks to paragraphs
        $paragraphs = explode("\n\n", $html);
        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (!empty($para) && !preg_match('/^<[hoult]/', $para)) {
                $html .= '<p>' . $para . '</p>' . "\n";
            } else {
                $html .= $para . "\n";
            }
        }
        
        return $html;
    }
    
    /**
     * Get category name from slug
     */
    private function get_category_name($category_slug) {
        $names = array(
            'health' => 'Health & Medicine',
            'politics' => 'Politics & Government',
            'economy' => 'Economy & Finance',
            'immigration' => 'Immigration',
            'climate' => 'Climate & Environment',
            'covid' => 'COVID-19',
            'crime' => 'Crime & Justice',
            'international' => 'International Affairs'
        );
        
        return isset($names[$category_slug]) ? $names[$category_slug] : 'Fact Check';
    }
    
    /**
     * Get or create WordPress category
     */
    private function get_or_create_category($category_slug) {
        $category_name = $this->get_category_name($category_slug);
        
        // Check if category exists
        $term = get_term_by('name', $category_name, 'category');
        
        if ($term) {
            return $term->term_id;
        }
        
        // Create category
        $result = wp_insert_term($category_name, 'category');
        
        if (is_wp_error($result)) {
            return false;
        }
        
        return $result['term_id'];
    }
}