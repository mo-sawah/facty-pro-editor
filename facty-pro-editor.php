<?php
/**
 * Plugin Name: Facty Pro Editor
 * Description: Advanced AI-powered editorial fact-checking with deep research, SEO analysis, and style suggestions for WordPress editors
 * Version: 1.0.3
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: facty-pro-editor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FACTY_PRO_VERSION', '1.0.3');
define('FACTY_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FACTY_PRO_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-action-scheduler.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-perplexity.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-seo-analyzer.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-style-analyzer.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-meta-box.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-gutenberg.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-schema.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-admin.php';
require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-core.php';

// Initialize the plugin
function facty_pro_init() {
    new Facty_Pro_Core();
}
add_action('plugins_loaded', 'facty_pro_init');

// Activation hook
register_activation_hook(__FILE__, 'facty_pro_activate');

function facty_pro_activate() {
    global $wpdb;
    
    // Create fact-check reports table
    $reports_table = $wpdb->prefix . 'facty_pro_reports';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $reports_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        content_hash varchar(64) NOT NULL,
        report longtext NOT NULL,
        seo_score int(3) DEFAULT 0,
        readability_score int(3) DEFAULT 0,
        fact_check_score int(3) DEFAULT 0,
        status varchar(20) DEFAULT 'pending',
        verified_by bigint(20) DEFAULT NULL,
        verified_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY post_content (post_id, content_hash),
        KEY status (status),
        KEY post_id (post_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Initialize Action Scheduler
    if (!class_exists('ActionScheduler')) {
        // Action Scheduler will be loaded from vendor if needed
    }
    
    // Set default options
    $default_options = array(
        'perplexity_api_key' => '',
        'perplexity_model' => 'sonar-pro',
        'enable_seo_analysis' => true,
        'enable_style_analysis' => true,
        'enable_readability_analysis' => true,
        'show_frontend_badge' => true,
        'require_verification' => true,
        'add_schema_markup' => true,
        'recency_filter' => 'week'
    );
    
    if (!get_option('facty_pro_options')) {
        update_option('facty_pro_options', $default_options);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'facty_pro_deactivate');

function facty_pro_deactivate() {
    // Clean up scheduled actions
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('facty_pro_process_fact_check');
    }
    
    flush_rewrite_rules();
}
