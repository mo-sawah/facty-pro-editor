<?php
/**
 * Facty Pro Misinformation Collector
 * Collects false claims and misinformation from multiple sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Misinformation_Collector {
    
    private $options;
    private $google_api_key;
    private $perplexity_api_key;
    
    // UK-specific search keywords
    private $uk_keywords = array(
        'UK', 'Britain', 'British', 'England', 'Scotland', 'Wales', 
        'NHS', 'Parliament', 'Westminster', 'London', 'Boris Johnson',
        'Rishi Sunak', 'Keir Starmer', 'Brexit', 'Downing Street'
    );
    
    public function __construct($options) {
        $this->options = $options;
        $this->google_api_key = isset($options['google_factcheck_api_key']) ? $options['google_factcheck_api_key'] : '';
        $this->perplexity_api_key = isset($options['perplexity_api_key']) ? $options['perplexity_api_key'] : '';
    }
    
    /**
     * Collect from all sources
     */
    public function collect_all() {
        $all_claims = array();
        
        // Collect from Google Fact Check API
        if (!empty($this->google_api_key)) {
            $google_claims = $this->collect_from_google();
            $all_claims = array_merge($all_claims, $google_claims);
        }
        
        // Collect from Full Fact RSS
        $fullfact_claims = $this->collect_from_full_fact();
        $all_claims = array_merge($all_claims, $fullfact_claims);
        
        // Collect from Perplexity search
        if (!empty($this->perplexity_api_key)) {
            $perplexity_claims = $this->collect_from_perplexity();
            $all_claims = array_merge($all_claims, $perplexity_claims);
        }
        
        return $all_claims;
    }
    
    /**
     * Collect from Google Fact Check API
     */
    private function collect_from_google() {
        error_log('Facty Pro: Collecting from Google Fact Check API');
        $claims = array();
        
        try {
            // Search with UK-specific keywords (rotate through them)
            $keyword = $this->uk_keywords[array_rand($this->uk_keywords)];
            
            $api_url = add_query_arg(array(
                'query' => $keyword,
                'languageCode' => 'en-GB',
                'maxAgeDays' => 7,
                'pageSize' => 10,
                'key' => $this->google_api_key
            ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
            
            $response = wp_remote_get($api_url, array('timeout' => 30));
            
            if (is_wp_error($response)) {
                error_log('Facty Pro: Google API error - ' . $response->get_error_message());
                return $claims;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['claims']) && is_array($data['claims'])) {
                foreach ($data['claims'] as $claim_data) {
                    // Only process claims rated as false or misleading
                    $rating = $this->extract_rating($claim_data);
                    if (!$this->is_false_or_misleading($rating)) {
                        continue;
                    }
                    
                    // Extract claim details
                    $claim = array(
                        'claim_text' => isset($claim_data['text']) ? $claim_data['text'] : 'Unknown claim',
                        'source' => 'google_factcheck',
                        'source_url' => $this->extract_source_url($claim_data),
                        'category' => $this->categorize_claim($claim_data['text']),
                        'rating' => $rating,
                        'fact_checker' => $this->extract_fact_checker($claim_data),
                        'metadata' => array(
                            'claimant' => isset($claim_data['claimant']) ? $claim_data['claimant'] : '',
                            'claim_date' => isset($claim_data['claimDate']) ? $claim_data['claimDate'] : '',
                            'review_date' => $this->extract_review_date($claim_data)
                        )
                    );
                    
                    $claims[] = $claim;
                }
                
                error_log('Facty Pro: Found ' . count($claims) . ' claims from Google API');
            }
            
        } catch (Exception $e) {
            error_log('Facty Pro: Google collection error - ' . $e->getMessage());
        }
        
        return $claims;
    }
    
    /**
     * Collect from Full Fact RSS feed
     */
    private function collect_from_full_fact() {
        error_log('Facty Pro: Collecting from Full Fact RSS');
        $claims = array();
        
        try {
            $rss_url = 'https://fullfact.org/feed/';
            $response = wp_remote_get($rss_url, array('timeout' => 30));
            
            if (is_wp_error($response)) {
                error_log('Facty Pro: Full Fact RSS error - ' . $response->get_error_message());
                return $claims;
            }
            
            $body = wp_remote_retrieve_body($response);
            
            // Parse RSS feed
            $rss = simplexml_load_string($body);
            
            if ($rss === false) {
                error_log('Facty Pro: Failed to parse Full Fact RSS');
                return $claims;
            }
            
            // Process only recent items (last 7 days)
            $week_ago = strtotime('-7 days');
            
            foreach ($rss->channel->item as $item) {
                $pub_date = strtotime((string)$item->pubDate);
                
                if ($pub_date < $week_ago) {
                    continue; // Skip old items
                }
                
                $title = (string)$item->title;
                $description = (string)$item->description;
                $link = (string)$item->link;
                
                // Extract claim from title/description
                $claim_text = $this->extract_claim_from_full_fact($title, $description);
                
                $claim = array(
                    'claim_text' => $claim_text,
                    'source' => 'full_fact_rss',
                    'source_url' => $link,
                    'category' => $this->categorize_claim($claim_text),
                    'rating' => 'False', // Full Fact articles are about false claims
                    'fact_checker' => 'Full Fact',
                    'metadata' => array(
                        'title' => $title,
                        'description' => strip_tags($description),
                        'pub_date' => date('Y-m-d H:i:s', $pub_date)
                    )
                );
                
                $claims[] = $claim;
            }
            
            error_log('Facty Pro: Found ' . count($claims) . ' claims from Full Fact RSS');
            
        } catch (Exception $e) {
            error_log('Facty Pro: Full Fact collection error - ' . $e->getMessage());
        }
        
        return $claims;
    }
    
    /**
     * Collect from Perplexity AI search
     */
    private function collect_from_perplexity() {
        error_log('Facty Pro: Collecting from Perplexity AI');
        $claims = array();
        
        try {
            $prompt = "Search for the latest FALSE CLAIMS and MISINFORMATION that have been spreading in UK news and social media in the past 24 hours. 

Focus ONLY on claims that have been identified as FALSE or MISLEADING by fact-checkers.

For each false claim, provide:
1. The exact false claim being spread
2. Where it's being spread (source)
3. Why it's false (brief explanation)
4. Category (health, politics, economy, immigration, climate, covid, crime, international)

Return ONLY a JSON array with this format:
[
    {
        \"claim\": \"exact false claim text\",
        \"source\": \"where it's spreading\",
        \"explanation\": \"why it's false\",
        \"category\": \"category name\"
    }
]

Return ONLY the JSON array, no other text.";
            
            $request_body = array(
                'model' => isset($this->options['perplexity_model']) ? $this->options['perplexity_model'] : 'sonar-pro',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a UK fact-checking assistant. Return only valid JSON.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.2,
                'max_tokens' => 4000,
                'search_recency_filter' => 'day'
            );
            
            $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->perplexity_api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_body),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                error_log('Facty Pro: Perplexity API error - ' . $response->get_error_message());
                return $claims;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                $content = trim($data['choices'][0]['message']['content']);
                
                // Clean up JSON response
                $content = preg_replace('/^```json\s*/m', '', $content);
                $content = preg_replace('/\s*```$/m', '', $content);
                $content = trim($content);
                
                $perplexity_claims = json_decode($content, true);
                
                if (is_array($perplexity_claims)) {
                    foreach ($perplexity_claims as $claim_data) {
                        if (!isset($claim_data['claim'])) {
                            continue;
                        }
                        
                        $claim = array(
                            'claim_text' => $claim_data['claim'],
                            'source' => 'perplexity_search',
                            'source_url' => '',
                            'category' => isset($claim_data['category']) ? $claim_data['category'] : 'uncategorized',
                            'rating' => 'False',
                            'fact_checker' => 'Perplexity AI',
                            'metadata' => array(
                                'source_description' => isset($claim_data['source']) ? $claim_data['source'] : '',
                                'explanation' => isset($claim_data['explanation']) ? $claim_data['explanation'] : ''
                            )
                        );
                        
                        $claims[] = $claim;
                    }
                }
                
                error_log('Facty Pro: Found ' . count($claims) . ' claims from Perplexity');
            }
            
        } catch (Exception $e) {
            error_log('Facty Pro: Perplexity collection error - ' . $e->getMessage());
        }
        
        return $claims;
    }
    
    /**
     * Extract rating from Google API claim data
     */
    private function extract_rating($claim_data) {
        if (isset($claim_data['claimReview'][0]['textualRating'])) {
            return $claim_data['claimReview'][0]['textualRating'];
        }
        return 'Unknown';
    }
    
    /**
     * Check if rating indicates false or misleading
     */
    private function is_false_or_misleading($rating) {
        $false_indicators = array(
            'false', 'fake', 'incorrect', 'misleading', 'pants on fire',
            'mostly false', 'unsubstantiated', 'debunked', 'misinformation'
        );
        
        $rating_lower = strtolower($rating);
        
        foreach ($false_indicators as $indicator) {
            if (strpos($rating_lower, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract source URL from Google API claim data
     */
    private function extract_source_url($claim_data) {
        if (isset($claim_data['claimReview'][0]['url'])) {
            return $claim_data['claimReview'][0]['url'];
        }
        return '';
    }
    
    /**
     * Extract fact checker from Google API claim data
     */
    private function extract_fact_checker($claim_data) {
        if (isset($claim_data['claimReview'][0]['publisher']['name'])) {
            return $claim_data['claimReview'][0]['publisher']['name'];
        }
        return 'Unknown';
    }
    
    /**
     * Extract review date from Google API claim data
     */
    private function extract_review_date($claim_data) {
        if (isset($claim_data['claimReview'][0]['reviewDate'])) {
            return $claim_data['claimReview'][0]['reviewDate'];
        }
        return '';
    }
    
    /**
     * Extract claim from Full Fact title and description
     */
    private function extract_claim_from_full_fact($title, $description) {
        // Full Fact titles often describe the false claim
        // Clean up HTML and extract meaningful text
        $text = strip_tags($description);
        
        // If description is too long, use title
        if (strlen($text) > 300) {
            return substr($title, 0, 250);
        }
        
        return substr($text, 0, 250);
    }
    
    /**
     * Categorize claim using simple keyword matching
     */
    private function categorize_claim($text) {
        $text_lower = strtolower($text);
        
        $categories = array(
            'health' => array('health', 'nhs', 'hospital', 'doctor', 'medicine', 'vaccine', 'disease', 'cancer', 'medical'),
            'politics' => array('government', 'parliament', 'minister', 'mp', 'election', 'vote', 'party', 'labour', 'conservative', 'tory'),
            'economy' => array('economy', 'tax', 'budget', 'inflation', 'unemployment', 'gdp', 'finance', 'pension', 'benefit'),
            'immigration' => array('immigration', 'immigrant', 'refugee', 'asylum', 'border', 'migrant'),
            'climate' => array('climate', 'environment', 'carbon', 'emission', 'green', 'renewable', 'pollution'),
            'covid' => array('covid', 'coronavirus', 'pandemic', 'lockdown', 'mask'),
            'crime' => array('crime', 'police', 'prison', 'criminal', 'murder', 'theft', 'violence'),
            'international' => array('ukraine', 'russia', 'china', 'usa', 'europe', 'war', 'nato')
        );
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'uncategorized';
    }
}
