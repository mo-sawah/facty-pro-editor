<?php
/**
 * Facty Pro Gutenberg Integration
 * Adds sidebar panel to block editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Gutenberg {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_gutenberg_assets'));
    }
    
    public function enqueue_gutenberg_assets() {
        // Enqueue same assets as Classic Editor - meta box works in both!
        wp_enqueue_script(
            'facty-pro-gutenberg',
            FACTY_PRO_PLUGIN_URL . 'assets/js/gutenberg.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'),
            FACTY_PRO_VERSION,
            true
        );
        
        wp_localize_script('facty-pro-gutenberg', 'factyProGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('facty_pro_nonce')
        ));
    }
}
