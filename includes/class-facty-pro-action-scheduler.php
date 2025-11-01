<?php
/**
 * Facty Pro Action Scheduler
 * Handles background processing using Action Scheduler to avoid timeouts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Action_Scheduler {
    
    /**
     * Schedule a fact-check job
     */
    public static function schedule_fact_check($post_id, $options) {
        // Generate unique job ID
        $job_id = 'facty_pro_' . $post_id . '_' . time();
        
        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Create initial status
        set_transient($job_id, array(
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'initializing',
            'message' => 'Job queued...'
        ), 3600); // 1 hour
        
        // Schedule the action
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                'facty_pro_process_fact_check',
                array(
                    'post_id' => $post_id,
                    'job_id' => $job_id,
                    'options' => $options
                ),
                'facty-pro'
            );
        } else {
            // Fallback to wp_schedule_single_event
            wp_schedule_single_event(time(), 'facty_pro_process_fact_check', array(
                'post_id' => $post_id,
                'job_id' => $job_id,
                'options' => $options
            ));
        }
        
        return $job_id;
    }
    
    /**
     * Get job status
     */
    public static function get_job_status($job_id) {
        $status = get_transient($job_id);
        
        if (!$status) {
            return array(
                'status' => 'unknown',
                'progress' => 0,
                'stage' => 'unknown',
                'message' => 'Job not found'
            );
        }
        
        return $status;
    }
    
    /**
     * Update job status
     */
    public static function update_job_status($job_id, $progress, $stage, $message) {
        set_transient($job_id, array(
            'status' => 'processing',
            'progress' => $progress,
            'stage' => $stage,
            'message' => $message
        ), 3600);
    }
    
    /**
     * Complete job
     */
    public static function complete_job($job_id, $report_id) {
        set_transient($job_id, array(
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'complete',
            'message' => 'Fact-check completed',
            'report_id' => $report_id
        ), 3600);
    }
    
    /**
     * Fail job
     */
    public static function fail_job($job_id, $error_message) {
        set_transient($job_id, array(
            'status' => 'failed',
            'progress' => 0,
            'stage' => 'error',
            'message' => $error_message
        ), 3600);
    }
}

// Register the action handler
add_action('facty_pro_process_fact_check', 'facty_pro_process_fact_check_handler', 10, 1);

function facty_pro_process_fact_check_handler($args) {
    $post_id = $args['post_id'];
    $job_id = $args['job_id'];
    $options = $args['options'];
    
    try {
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 5, 'starting', 'Initializing fact-check...');
        
        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            throw new Exception('Post not found');
        }
        
        $content = $post->post_title . "\n\n" . $post->post_content;
        $content = wp_strip_all_tags($content);
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 10, 'analyzing', 'Extracting content...');
        
        // Run fact-check analysis
        $perplexity = new Facty_Pro_Perplexity($options);
        $fact_check_result = $perplexity->analyze($content, $job_id);
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 60, 'seo', 'Analyzing SEO...');
        
        // Run SEO analysis (if enabled)
        $seo_result = array();
        if ($options['enable_seo_analysis']) {
            $seo_analyzer = new Facty_Pro_SEO_Analyzer($options);
            $seo_result = $seo_analyzer->analyze($post, $job_id);
        }
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 75, 'style', 'Analyzing style...');
        
        // Run style analysis (if enabled)
        $style_result = array();
        if ($options['enable_style_analysis']) {
            $style_analyzer = new Facty_Pro_Style_Analyzer($options);
            $style_result = $style_analyzer->analyze($content, $job_id);
        }
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 90, 'saving', 'Saving report...');
        
        // Combine all results
        $full_report = array(
            'fact_check' => $fact_check_result,
            'seo' => $seo_result,
            'style' => $style_result,
            'analyzed_at' => current_time('mysql'),
            'analyzed_by' => get_current_user_id()
        );
        
        // Calculate aggregate scores
        $fact_check_score = isset($fact_check_result['score']) ? intval($fact_check_result['score']) : 0;
        $seo_score = isset($seo_result['score']) ? intval($seo_result['score']) : 0;
        $readability_score = isset($style_result['readability_score']) ? intval($style_result['readability_score']) : 0;
        
        // Save report to database
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        $content_hash = hash('sha256', $content);
        
        $wpdb->insert(
            $table,
            array(
                'post_id' => $post_id,
                'content_hash' => $content_hash,
                'report' => json_encode($full_report),
                'fact_check_score' => $fact_check_score,
                'seo_score' => $seo_score,
                'readability_score' => $readability_score,
                'status' => 'completed'
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%s')
        );
        
        $report_id = $wpdb->insert_id;
        
        // Update post meta for quick access
        update_post_meta($post_id, '_facty_pro_last_report', $report_id);
        update_post_meta($post_id, '_facty_pro_last_check', current_time('mysql'));
        update_post_meta($post_id, '_facty_pro_fact_score', $fact_check_score);
        
        Facty_Pro_Action_Scheduler::complete_job($job_id, $report_id);
        
    } catch (Exception $e) {
        error_log('Facty Pro Error: ' . $e->getMessage());
        Facty_Pro_Action_Scheduler::fail_job($job_id, $e->getMessage());
    }
}
