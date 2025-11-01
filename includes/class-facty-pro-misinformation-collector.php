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
            try {
                $google_claims = $this->collect_from_google();
                $all_claims = array_merge($all_claims, $google_claims);
                error_log('Facty Pro: Google collection successful - ' . count($google_claims) . ' claims');
            } catch (Exception $e) {
                error_log('Facty Pro: Google collection failed - ' . $e->getMessage());
            }
        } else {
            error_log('Facty Pro: Google API key not configured, skipping Google collection');
        }
        
        // Collect from Full Fact RSS (always try this - no API key needed)
        try {
            $fullfact_claims = $this->collect_from_full_fact();
            $all_claims = array_merge($all_claims, $fullfact_claims);
            error_log('Facty Pro: Full Fact collection successful - ' . count($fullfact_claims) . ' claims');
        } catch (Exception $e) {
            error_log('Facty Pro: Full Fact collection failed - ' . $e->getMessage());
        }
        
        // Collect from Perplexity search
        if (!empty($this->perplexity_api_key)) {
            try {
                $perplexity_claims = $this->collect_from_perplexity();
                $all_claims = array_merge($all_claims, $perplexity_claims);
                error_log('Facty Pro: Perplexity collection successful - ' . count($perplexity_claims) . ' claims');
            } catch (Exception $e) {
                error_log('Facty Pro: Perplexity collection failed - ' . $e->getMessage());
            }
        } else {
            error_log('Facty Pro: Perplexity API key not configured, skipping Perplexity collection');
        }
        
        error_log('Facty Pro: Total claims collected from all sources: ' . count($all_claims));
        
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
            error_log('Facty Pro: Google API searching with keyword: ' . $keyword);
            
            $api_url = add_query_arg(array(
                'query' => $keyword,
                'languageCode' => 'en-GB',
                'maxAgeDays' => 7,
                'pageSize' => 10,
                'key' => $this->google_api_key
            ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'user-agent' => 'Facty Pro WordPress Plugin/1.4'
            ));
            
            if (is_wp_error($response)) {
                error_log('Facty Pro: Google API error - ' . $response->get_error_message());
                return $claims;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                error_log('Facty Pro: Google API returned HTTP ' . $http_code);
                error_log('Facty Pro: Response body: ' . substr($body, 0, 500));
                return $claims;
            }
            
            $data = json_decode($body, true);
            
            if (!$data) {
                error_log('Facty Pro: Failed to parse Google API response as JSON');
                return $claims;
            }
            
            if (isset($data['error'])) {
                error_log('Facty Pro: Google API error response: ' . print_r($data['error'], true));
                return $claims;
            }
            
            if (!isset($data['claims'])) {
                error_log('Facty Pro: Google API returned no claims for keyword: ' . $keyword);
                return $claims;
            }
            
            if (!is_array($data['claims'])) {
                error_log('Facty Pro: Google API claims data is not an array');
                return $claims;
            }
            
            $total_claims = count($data['claims']);
            error_log('Facty Pro: Google API returned ' . $total_claims . ' claims');
            
            $false_claim_count = 0;
            
            foreach ($data['claims'] as $claim_data) {
                // Only process claims rated as false or misleading
                $rating = $this->extract_rating($claim_data);
                if (!$this->is_false_or_misleading($rating)) {
                    error_log('Facty Pro: Skipping claim with rating: ' . $rating);
                    continue;
                }
                
                $false_claim_count++;
                
                // Validate claim text
                if (!isset($claim_data['text']) || empty($claim_data['text'])) {
                    error_log('Facty Pro: Skipping claim with empty text');
                    continue;
                }
                
                // Extract claim details
                $claim = array(
                    'claim_text' => $claim_data['text'],
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
            
            error_log('Facty Pro: Google API - Found ' . $false_claim_count . ' false/misleading claims out of ' . $total_claims . ' total');
            
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
            $response = wp_remote_get($rss_url, array(
                'timeout' => 30,
                'user-agent' => 'Facty Pro WordPress Plugin/1.4'
            ));
            
            if (is_wp_error($response)) {
                error_log('Facty Pro: Full Fact RSS error - ' . $response->get_error_message());
                return $claims;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('Facty Pro: Full Fact RSS returned HTTP ' . $http_code);
                return $claims;
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                error_log('Facty Pro: Full Fact RSS returned empty body');
                return $claims;
            }
            
            // Suppress XML parsing errors
            libxml_use_internal_errors(true);
            
            // Parse RSS feed
            $rss = simplexml_load_string($body);
            
            if ($rss === false) {
                $errors = libxml_get_errors();
                error_log('Facty Pro: Failed to parse Full Fact RSS. Errors: ' . print_r($errors, true));
                libxml_clear_errors();
                return $claims;
            }
            
            libxml_clear_errors();
            
            // Check if we have items
            if (!isset($rss->channel->item)) {
                error_log('Facty Pro: Full Fact RSS has no items');
                return $claims;
            }
            
            $item_count = count($rss->channel->item);
            error_log('Facty Pro: Full Fact RSS has ' . $item_count . ' items');
            
            // Process only recent items (last 7 days)
            $week_ago = strtotime('-7 days');
            $processed_count = 0;
            
            foreach ($rss->channel->item as $item) {
                $pub_date = strtotime((string)$item->pubDate);
                
                if ($pub_date < $week_ago) {
                    continue; // Skip old items
                }
                
                $title = (string)$item->title;
                $description = (string)$item->description;
                $link = (string)$item->link;
                
                // Skip if title or description is empty
                if (empty($title) || empty($description)) {
                    continue;
                }
                
                // Extract claim from title/description
                $claim_text = $this->extract_claim_from_full_fact($title, $description);
                
                // Skip if claim is too short
                if (strlen($claim_text) < 20) {
                    continue;
                }
                
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
                $processed_count++;
            }
            
            error_log('Facty Pro: Full Fact - Processed ' . $processed_count . ' recent items, created ' . count($claims) . ' claims');
            
        } catch (Exception $e) {
            error_log('Facty Pro: Full Fact collection error - ' . $e->getMessage());
        }
        
        return $claims;
    }
    
    /**
     * Collect from Perplexity AI search
     */
    private function collect_from_perplexity() {
        error_log('Facty Pro: Collecting from Perplexity AI search');
        $claims = array();
        
        try {
            $current_date = current_time('F j, Y');
            
            $prompt = "Today is {$current_date}. Search for FALSE claims and MISINFORMATION currently spreading in UK media, social media, and news in the past 7 days.

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
            
            error_log('Facty Pro: Sending request to Perplexity API');
            
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
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                error_log('Facty Pro: Perplexity API returned HTTP ' . $http_code);
                error_log('Facty Pro: Response body: ' . substr($body, 0, 500));
                return $claims;
            }
            
            $data = json_decode($body, true);
            
            if (!$data) {
                error_log('Facty Pro: Failed to parse Perplexity response as JSON');
                return $claims;
            }
            
            if (isset($data['error'])) {
                error_log('Facty Pro: Perplexity API error: ' . print_r($data['error'], true));
                return $claims;
            }
            
            if (!isset($data['choices'][0]['message']['content'])) {
                error_log('Facty Pro: Perplexity response missing expected content');
                return $claims;
            }
            
            $content = trim($data['choices'][0]['message']['content']);
            error_log('Facty Pro: Perplexity response length: ' . strlen($content) . ' chars');
            
            // Clean up JSON response
            $content = preg_replace('/^```json\s*/m', '', $content);
            $content = preg_replace('/\s*```$/m', '', $content);
            $content = trim($content);
            
            $perplexity_claims = json_decode($content, true);
            
            if (!is_array($perplexity_claims)) {
                error_log('Facty Pro: Perplexity response is not a valid JSON array');
                error_log('Facty Pro: Content was: ' . substr($content, 0, 500));
                return $claims;
            }
            
            error_log('Facty Pro: Perplexity returned ' . count($perplexity_claims) . ' potential claims');
            
            foreach ($perplexity_claims as $claim_data) {
                if (!isset($claim_data['claim']) || empty($claim_data['claim'])) {
                    error_log('Facty Pro: Skipping Perplexity claim with empty text');
                    continue;
                }
                
                // Skip claims that are too short
                if (strlen($claim_data['claim']) < 20) {
                    error_log('Facty Pro: Skipping too-short claim: ' . $claim_data['claim']);
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
            
            error_log('Facty Pro: Created ' . count($claims) . ' valid claims from Perplexity');
            
        } catch (Exception $e) {
            error_log('Facty Pro: Perplexity collection error - ' . $e->getMessage());
            error_log('Facty Pro: Stack trace: ' . $e->getTraceAsString());
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