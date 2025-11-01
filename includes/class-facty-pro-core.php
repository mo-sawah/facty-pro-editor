<?php
/**
 * Facty Pro Core
 * Main plugin class that initializes all components
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Core {
    
    private $options;
    private $meta_box;
    private $gutenberg;
    private $schema;
    private $admin;
    private $misinformation_monitor;
    
    public function __construct() {
        $this->load_options();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Load plugin options
     */
    private function load_options() {
        $default_options = array(
            'perplexity_api_key' => '',
            'perplexity_model' => 'sonar-pro',
            'google_factcheck_api_key' => '',
            'enable_seo_analysis' => true,
            'enable_style_analysis' => true,
            'enable_readability_analysis' => true,
            'show_frontend_badge' => true,
            'require_verification' => true,
            'add_schema_markup' => true,
            'recency_filter' => 'week',
            'use_multistep_analyzer' => false,
            'perplexity_multistep_max_claims' => 10
        );
        
        $saved_options = get_option('facty_pro_options', array());
        $this->options = array_merge($default_options, $saved_options);
    }
    
    /**
     * Initialize all components
     */
    private function init_components() {
        // Admin interface
        if (is_admin()) {
            $this->admin = new Facty_Pro_Admin($this->options);
        }
        
        // Editor interfaces (Classic + Gutenberg)
        if (is_admin()) {
            $this->meta_box = new Facty_Pro_Meta_Box($this->options);
            $this->gutenberg = new Facty_Pro_Gutenberg($this->options);
        }
        
        // Schema markup for frontend
        if ($this->options['add_schema_markup']) {
            $this->schema = new Facty_Pro_Schema($this->options);
        }
        
        // Misinformation monitor
        if (is_admin()) {
            $this->misinformation_monitor = new Facty_Pro_Misinformation_Monitor($this->options);
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Frontend badge - integrate with post meta instead of content
        if ($this->options['show_frontend_badge']) {
            add_filter('bunyad_post_meta_item', array($this, 'add_badge_to_meta'), 10, 2);
            add_filter('bunyad_post_meta_below_items', array($this, 'add_facty_badge_item'), 10, 1);
        }
        
        // AJAX handlers
        add_action('wp_ajax_facty_pro_start_fact_check', array($this, 'ajax_start_fact_check'));
        add_action('wp_ajax_facty_pro_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_facty_pro_get_report', array($this, 'ajax_get_report'));
        add_action('wp_ajax_facty_pro_verify_post', array($this, 'ajax_verify_post'));
        add_action('wp_ajax_facty_pro_unverify_post', array($this, 'ajax_unverify_post'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!is_single()) {
            return;
        }
        
        wp_enqueue_style(
            'facty-pro-badge',
            FACTY_PRO_PLUGIN_URL . 'assets/css/badge.css',
            array(),
            FACTY_PRO_VERSION
        );
    }
    
    /**
     * Add facty_badge to post meta items list
     */
    public function add_facty_badge_item($items) {
        if (!is_single()) {
            return $items;
        }
        
        $post_id = get_the_ID();
        $report = $this->get_latest_report($post_id);
        
        // Only add badge item if article is verified
        if ($report && $report->status === 'verified') {
            $items[] = 'facty_badge';
        }
        
        return $items;
    }
    
    /**
     * Render the badge as a post meta item
     */
    public function add_badge_to_meta($output, $item) {
        if ($item !== 'facty_badge' || !is_single()) {
            return $output;
        }
        
        $post_id = get_the_ID();
        $report = $this->get_latest_report($post_id);
        
        if (!$report || $report->status !== 'verified') {
            return $output;
        }
        
        $score = intval($report->fact_check_score);
        $badge_class = $this->get_badge_class($score);
        $status_text = $this->get_status_text($score);
        
        return sprintf(
            '<span class="meta-item facty-pro-badge-inline %s" title="Fact-checked and verified">
                <i class="tsi tsi-check-circle"></i>
                <span class="badge-text">Fact-Checked</span>
                <span class="badge-score">%d/100</span>
            </span>',
            esc_attr($badge_class),
            $score
        );
    }
    
    /**
     * AJAX: Start fact-checking process
     */
    public function ajax_start_fact_check() {
        check_ajax_referer('facty_pro_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        // Schedule background job
        $job_id = Facty_Pro_Action_Scheduler::schedule_fact_check($post_id, $this->options);
        
        wp_send_json_success(array(
            'job_id' => $job_id,
            'message' => 'Fact-check started in background'
        ));
    }
    
    /**
     * AJAX: Check fact-checking status
     */
    public function ajax_check_status() {
        check_ajax_referer('facty_pro_nonce', 'nonce');
        
        $job_id = sanitize_text_field($_POST['job_id']);
        $status = Facty_Pro_Action_Scheduler::get_job_status($job_id);
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX: Get report details
     */
    public function ajax_get_report() {
        check_ajax_referer('facty_pro_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $report_id = intval($_POST['report_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $report_id
        ));
        
        if (!$report) {
            wp_send_json_error('Report not found');
        }
        
        $report_data = json_decode($report->report, true);
        
        wp_send_json_success($report_data);
    }
    
    /**
     * AJAX: Verify post
     */
    public function ajax_verify_post() {
        check_ajax_referer('facty_pro_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $report_id = intval($_POST['report_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        
        $wpdb->update(
            $table,
            array(
                'status' => 'verified',
                'verified_by' => get_current_user_id(),
                'verified_at' => current_time('mysql')
            ),
            array('id' => $report_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        // Add post meta for quick checks
        update_post_meta($post_id, '_facty_pro_verified', 1);
        update_post_meta($post_id, '_facty_pro_verified_at', current_time('mysql'));
        update_post_meta($post_id, '_facty_pro_verified_by', get_current_user_id());
        
        wp_send_json_success(array(
            'message' => 'Post marked as verified'
        ));
    }
    
    /**
     * AJAX: Unverify post
     */
    public function ajax_unverify_post() {
        check_ajax_referer('facty_pro_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $report_id = intval($_POST['report_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        
        $wpdb->update(
            $table,
            array(
                'status' => 'completed',
                'verified_by' => null,
                'verified_at' => null
            ),
            array('id' => $report_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        // Remove post meta
        delete_post_meta($post_id, '_facty_pro_verified');
        delete_post_meta($post_id, '_facty_pro_verified_at');
        delete_post_meta($post_id, '_facty_pro_verified_by');
        
        wp_send_json_success(array(
            'message' => 'Verification removed'
        ));
    }
    
    /**
     * Get latest report for a post
     */
    public function get_latest_report($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
            $post_id
        ));
    }
    
    /**
     * Get badge CSS class based on score
     */
    private function get_badge_class($score) {
        if ($score >= 90) return 'facty-pro-badge-excellent';
        if ($score >= 75) return 'facty-pro-badge-good';
        if ($score >= 60) return 'facty-pro-badge-fair';
        return 'facty-pro-badge-poor';
    }
    
    /**
     * Get status text based on score
     */
    private function get_status_text($score) {
        if ($score >= 90) return 'Verified & Accurate';
        if ($score >= 75) return 'Mostly Accurate';
        if ($score >= 60) return 'Needs Review';
        return 'Multiple Issues';
    }
}
