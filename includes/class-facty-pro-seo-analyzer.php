<?php
/**
 * Facty Pro SEO Analyzer
 * Analyzes posts for SEO best practices and provides actionable recommendations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_SEO_Analyzer {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Analyze post for SEO
     */
    public function analyze($post, $job_id = null) {
        $issues = array();
        $score = 100;
        $recommendations = array();
        
        // Analyze title
        $title_analysis = $this->analyze_title($post->post_title);
        $issues = array_merge($issues, $title_analysis['issues']);
        $score -= $title_analysis['penalty'];
        $recommendations = array_merge($recommendations, $title_analysis['recommendations']);
        
        // Analyze content
        $content_analysis = $this->analyze_content($post->post_content);
        $issues = array_merge($issues, $content_analysis['issues']);
        $score -= $content_analysis['penalty'];
        $recommendations = array_merge($recommendations, $content_analysis['recommendations']);
        
        // Analyze meta description
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: 
                     get_post_meta($post->ID, '_aioseop_description', true) ?: '';
        $meta_analysis = $this->analyze_meta_description($meta_desc);
        $issues = array_merge($issues, $meta_analysis['issues']);
        $score -= $meta_analysis['penalty'];
        $recommendations = array_merge($recommendations, $meta_analysis['recommendations']);
        
        // Analyze headings
        $heading_analysis = $this->analyze_headings($post->post_content);
        $issues = array_merge($issues, $heading_analysis['issues']);
        $score -= $heading_analysis['penalty'];
        $recommendations = array_merge($recommendations, $heading_analysis['recommendations']);
        
        // Analyze images
        $image_analysis = $this->analyze_images($post->post_content);
        $issues = array_merge($issues, $image_analysis['issues']);
        $score -= $image_analysis['penalty'];
        $recommendations = array_merge($recommendations, $image_analysis['recommendations']);
        
        // Analyze links
        $link_analysis = $this->analyze_links($post->post_content);
        $issues = array_merge($issues, $link_analysis['issues']);
        $score -= $link_analysis['penalty'];
        $recommendations = array_merge($recommendations, $link_analysis['recommendations']);
        
        // Ensure score doesn't go below 0
        $score = max(0, $score);
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'analyzed_at' => current_time('mysql')
        );
    }
    
    /**
     * Analyze title
     */
    private function analyze_title($title) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        $title_length = strlen($title);
        
        // Title length check
        if ($title_length < 30) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Title is too short (< 30 characters)',
                'severity' => 'medium'
            );
            $recommendations[] = 'Expand your title to 50-60 characters for better SEO. Include your main keyword and make it compelling.';
            $penalty += 10;
        } elseif ($title_length > 70) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Title is too long (> 70 characters), may be truncated in search results',
                'severity' => 'medium'
            );
            $recommendations[] = 'Shorten your title to 50-60 characters to avoid truncation in search results.';
            $penalty += 5;
        }
        
        // Check for power words
        $power_words = array('best', 'guide', 'ultimate', 'complete', 'essential', 'proven', 'powerful', 'effective', 'how to', 'tips', 'strategies');
        $has_power_word = false;
        foreach ($power_words as $word) {
            if (stripos($title, $word) !== false) {
                $has_power_word = true;
                break;
            }
        }
        
        if (!$has_power_word) {
            $recommendations[] = 'Consider adding power words like "Ultimate", "Complete", "Essential" to make your title more compelling.';
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Analyze content
     */
    private function analyze_content($content) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        
        $clean_content = wp_strip_all_tags($content);
        $word_count = str_word_count($clean_content);
        
        // Word count check
        if ($word_count < 300) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'Content is too thin (< 300 words)',
                'severity' => 'high'
            );
            $recommendations[] = 'Expand your content to at least 300 words. Aim for 1000-2000 words for comprehensive coverage and better SEO.';
            $penalty += 20;
        } elseif ($word_count < 600) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Content is relatively short (< 600 words)',
                'severity' => 'medium'
            );
            $recommendations[] = 'Consider expanding your content. Articles with 1000-2000 words tend to perform better in search results.';
            $penalty += 10;
        }
        
        // Paragraph length check
        $paragraphs = explode("\n\n", $clean_content);
        $long_paragraphs = 0;
        foreach ($paragraphs as $paragraph) {
            $para_words = str_word_count($paragraph);
            if ($para_words > 150) {
                $long_paragraphs++;
            }
        }
        
        if ($long_paragraphs > 0) {
            $issues[] = array(
                'type' => 'info',
                'message' => "Found {$long_paragraphs} paragraph(s) with > 150 words",
                'severity' => 'low'
            );
            $recommendations[] = 'Break up long paragraphs into smaller chunks (50-100 words) for better readability.';
            $penalty += 3;
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Analyze meta description
     */
    private function analyze_meta_description($meta_desc) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        
        if (empty($meta_desc)) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'No meta description set',
                'severity' => 'high'
            );
            $recommendations[] = 'Add a compelling meta description (150-160 characters) that summarizes your content and encourages clicks.';
            $penalty += 15;
        } else {
            $length = strlen($meta_desc);
            if ($length < 120) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => 'Meta description is too short (< 120 characters)',
                    'severity' => 'medium'
                );
                $recommendations[] = 'Expand your meta description to 150-160 characters for optimal display in search results.';
                $penalty += 8;
            } elseif ($length > 170) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => 'Meta description is too long (> 170 characters)',
                    'severity' => 'medium'
                );
                $recommendations[] = 'Shorten your meta description to 150-160 characters to avoid truncation.';
                $penalty += 5;
            }
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Analyze headings
     */
    private function analyze_headings($content) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        
        // Count H2s
        preg_match_all('/<h2[^>]*>/i', $content, $h2_matches);
        $h2_count = count($h2_matches[0]);
        
        if ($h2_count === 0) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'No H2 headings found',
                'severity' => 'high'
            );
            $recommendations[] = 'Add H2 headings to structure your content. Use them to break up sections and include relevant keywords.';
            $penalty += 15;
        }
        
        // Check for H1
        preg_match_all('/<h1[^>]*>/i', $content, $h1_matches);
        $h1_count = count($h1_matches[0]);
        
        if ($h1_count > 1) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Multiple H1 headings found (should only use one)',
                'severity' => 'medium'
            );
            $recommendations[] = 'Use only one H1 heading per page (typically your title). Change additional H1s to H2 or H3.';
            $penalty += 8;
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Analyze images
     */
    private function analyze_images($content) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        
        preg_match_all('/<img[^>]+>/i', $content, $images);
        $image_count = count($images[0]);
        
        if ($image_count === 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'No images found in content',
                'severity' => 'medium'
            );
            $recommendations[] = 'Add relevant images to your content. Visual content improves engagement and helps with SEO.';
            $penalty += 10;
        } else {
            // Check for alt text
            $missing_alt = 0;
            foreach ($images[0] as $image) {
                if (!preg_match('/alt=["\'][^"\']*["\']/', $image)) {
                    $missing_alt++;
                }
            }
            
            if ($missing_alt > 0) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => "{$missing_alt} image(s) missing alt text",
                    'severity' => 'medium'
                );
                $recommendations[] = 'Add descriptive alt text to all images for accessibility and SEO. Include relevant keywords naturally.';
                $penalty += 10;
            }
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Analyze links
     */
    private function analyze_links($content) {
        $issues = array();
        $penalty = 0;
        $recommendations = array();
        
        // Count internal and external links
        preg_match_all('/<a [^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
        $total_links = count($links[1]);
        
        if ($total_links === 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'No links found in content',
                'severity' => 'medium'
            );
            $recommendations[] = 'Add both internal links (to related content) and external links (to authoritative sources) to improve SEO and user experience.';
            $penalty += 8;
        }
        
        return array(
            'issues' => $issues,
            'penalty' => $penalty,
            'recommendations' => $recommendations
        );
    }
}
