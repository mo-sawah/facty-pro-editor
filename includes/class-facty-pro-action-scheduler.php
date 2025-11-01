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
add_action('facty_pro_process_fact_check', 'facty_pro_process_fact_check_handler', 10, 3);

function facty_pro_process_fact_check_handler($post_id, $job_id, $options) {
    // Handle both Action Scheduler and direct call formats
    if (is_array($post_id)) {
        // Arguments passed as array (Action Scheduler format)
        $args = $post_id;
        $post_id = isset($args['post_id']) ? intval($args['post_id']) : 0;
        $job_id = isset($args['job_id']) ? $args['job_id'] : '';
        $options = isset($args['options']) ? $args['options'] : array();
    }
    
    // Validate post_id
    if (empty($post_id) || !is_numeric($post_id)) {
        error_log('Facty Pro Error: Invalid post_id received: ' . print_r($post_id, true));
        if (!empty($job_id)) {
            Facty_Pro_Action_Scheduler::fail_job($job_id, 'Invalid post ID');
        }
        return;
    }
    
    $post_id = intval($post_id);
    
    // Log for debugging
    error_log('Facty Pro: Processing fact check for post ' . $post_id . ' with job ' . $job_id);
    
    try {
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 5, 'starting', 'Initializing fact-check...');
        
        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            error_log('Facty Pro Error: Post not found - ID: ' . $post_id);
            throw new Exception('Post not found (ID: ' . $post_id . ')');
        }
        
        error_log('Facty Pro: Retrieved post - Title: ' . $post->post_title);
        
        // Prepare content for analysis
        $content = $post->post_title . "\n\n" . $post->post_content;
        
        // Apply WordPress content filters to expand shortcodes, etc.
        $content = apply_filters('the_content', $content);
        
        // Strip HTML tags but keep text
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = trim(preg_replace('/\s+/', ' ', $content));
        
        $word_count = str_word_count($content);
        error_log('Facty Pro: Content prepared - ' . strlen($content) . ' characters, ' . $word_count . ' words');
        
        if (empty(trim($content))) {
            error_log('Facty Pro Error: Post content is empty');
            throw new Exception('Post content is empty');
        }
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 10, 'analyzing', 'Extracting content...');
        error_log('Facty Pro: Starting fact-check analysis');
        
        // Choose analyzer based on settings
        $use_multistep = !empty($options['use_multistep_analyzer']);
        
        if ($use_multistep) {
            error_log('Facty Pro: Using multi-step analyzer for enhanced accuracy');
            $analyzer = new Facty_Pro_Perplexity_MultiStep_Analyzer($options);
            $fact_check_result = $analyzer->analyze($content, $job_id);
        } else {
            error_log('Facty Pro: Using single-step analyzer');
            $perplexity = new Facty_Pro_Perplexity($options);
            $fact_check_result = $perplexity->analyze($content, $job_id);
        }
        
        error_log('Facty Pro: Fact-check analysis complete');
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 60, 'seo', 'Analyzing SEO...');
        
        // Run SEO analysis (if enabled)
        $seo_result = array();
        if (!empty($options['enable_seo_analysis'])) {
            error_log('Facty Pro: Starting SEO analysis');
            $seo_analyzer = new Facty_Pro_SEO_Analyzer($options);
            $seo_result = $seo_analyzer->analyze($post, $job_id);
        }
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 75, 'style', 'Analyzing style...');
        
        // Run style analysis (if enabled)
        $style_result = array();
        if (!empty($options['enable_style_analysis'])) {
            error_log('Facty Pro: Starting style analysis');
            $style_analyzer = new Facty_Pro_Style_Analyzer($options);
            $style_result = $style_analyzer->analyze($content, $job_id);
        }
        
        Facty_Pro_Action_Scheduler::update_job_status($job_id, 90, 'saving', 'Saving report...');
        error_log('Facty Pro: Compiling report');
        
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
        
        error_log('Facty Pro: Scores - Fact: ' . $fact_check_score . ', SEO: ' . $seo_score . ', Readability: ' . $readability_score);
        
        // Save report to database
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        $content_hash = hash('sha256', $content);
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('Facty Pro Error: Reports table does not exist');
            throw new Exception('Database table not found. Please deactivate and reactivate the plugin.');
        }
        
        $insert_result = $wpdb->insert(
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
        
        if ($insert_result === false) {
            error_log('Facty Pro Error: Failed to insert report - ' . $wpdb->last_error);
            throw new Exception('Failed to save report to database: ' . $wpdb->last_error);
        }
        
        $report_id = $wpdb->insert_id;
        error_log('Facty Pro: Report saved with ID: ' . $report_id);
        
        // Update post meta for quick access
        update_post_meta($post_id, '_facty_pro_last_report', $report_id);
        update_post_meta($post_id, '_facty_pro_last_check', current_time('mysql'));
        update_post_meta($post_id, '_facty_pro_fact_score', $fact_check_score);
        
        Facty_Pro_Action_Scheduler::complete_job($job_id, $report_id);
        error_log('Facty Pro: Job completed successfully');
        
    } catch (Exception $e) {
        error_log('Facty Pro Error: ' . $e->getMessage());
        error_log('Facty Pro Error Stack Trace: ' . $e->getTraceAsString());
        Facty_Pro_Action_Scheduler::fail_job($job_id, $e->getMessage());
    }
}
