<?php
/**
 * Facty Pro Schema Markup
 * Adds ClaimReview structured data to verified articles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Schema {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_head', array($this, 'add_claimreview_schema'), 10);
    }
    
    public function add_claimreview_schema() {
        if (!is_single()) {
            return;
        }
        
        $post_id = get_the_ID();
        
        // Check if post is verified
        if (!get_post_meta($post_id, '_facty_pro_verified', true)) {
            return;
        }
        
        // Get latest report
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d AND status = 'verified' ORDER BY created_at DESC LIMIT 1",
            $post_id
        ));
        
        if (!$report) {
            return;
        }
        
        $report_data = json_decode($report->report, true);
        $fact_check = isset($report_data['fact_check']) ? $report_data['fact_check'] : array();
        
        $score = intval($report->fact_check_score);
        $rating_value = max(1, min(5, round($score / 20)));
        
        // Determine truth rating
        $truth_rating = 'Mixture';
        if ($score >= 95) {
            $truth_rating = 'True';
        } elseif ($score >= 85) {
            $truth_rating = 'Mostly True';
        } elseif ($score >= 70) {
            $truth_rating = 'Mixture';
        } elseif ($score >= 50) {
            $truth_rating = 'Mostly False';
        } else {
            $truth_rating = 'False';
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ClaimReview',
            'url' => get_permalink($post_id),
            'claimReviewed' => get_the_title($post_id),
            'itemReviewed' => array(
                '@type' => 'CreativeWork',
                'author' => array(
                    '@type' => 'Person',
                    'name' => get_the_author_meta('display_name')
                ),
                'datePublished' => get_the_date('c', $post_id),
                'name' => get_the_title($post_id)
            ),
            'author' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            ),
            'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => $rating_value,
                'bestRating' => 5,
                'worstRating' => 1,
                'alternateName' => $truth_rating
            ),
            'datePublished' => get_the_modified_date('c', $post_id)
        );
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }
}
