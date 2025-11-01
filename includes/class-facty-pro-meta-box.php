<?php
/**
 * Facty Pro Meta Box
 * Editor interface for Classic Editor and Gutenberg compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Meta_Box {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add meta box to posts and pages
     */
    public function add_meta_box() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'facty_pro_fact_checker',
                '✓ Facty Pro: Editorial Fact Checker',
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high',
                array(
                    '__block_editor_compatible_meta_box' => true,
                    '__back_compat_meta_box' => false
                )
            );
        }
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_style(
            'facty-pro-editor',
            FACTY_PRO_PLUGIN_URL . 'assets/css/editor.css',
            array(),
            FACTY_PRO_VERSION
        );
        
        wp_enqueue_script(
            'facty-pro-editor',
            FACTY_PRO_PLUGIN_URL . 'assets/js/editor.js',
            array('jquery'),
            FACTY_PRO_VERSION,
            true
        );
        
        wp_localize_script('facty-pro-editor', 'factyProEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('facty_pro_nonce'),
            'postId' => get_the_ID()
        ));
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        if (empty($this->options['perplexity_api_key'])) {
            echo '<div class="facty-pro-notice error"><p><strong>⚠️ Configuration Required:</strong> Please add your Perplexity API key in the <a href="' . admin_url('options-general.php?page=facty-pro-settings') . '">plugin settings</a>.</p></div>';
            return;
        }
        
        // Get latest report if exists
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
            $post->ID
        ));
        
        ?>
        <div class="facty-pro-meta-box">
            <!-- Header -->
            <div class="facty-pro-header">
                <div class="facty-pro-header-left">
                    <div class="facty-pro-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h3>Editorial Fact Checker</h3>
                        <p class="description">Comprehensive fact-checking, SEO analysis, and style recommendations</p>
                    </div>
                </div>
                <div class="facty-pro-header-right">
                    <button type="button" class="button button-primary button-large" id="facty-pro-start-check">
                        <span class="dashicons dashicons-search"></span>
                        Start Fact Check
                    </button>
                </div>
            </div>
            
            <?php if ($report): ?>
            <!-- Existing Report Summary -->
            <div class="facty-pro-existing-report">
                <div class="report-summary">
                    <div class="report-summary-item">
                        <div class="report-summary-label">Last Checked</div>
                        <div class="report-summary-value"><?php echo human_time_diff(strtotime($report->created_at), current_time('timestamp')) . ' ago'; ?></div>
                    </div>
                    <div class="report-summary-item">
                        <div class="report-summary-label">Fact Check Score</div>
                        <div class="report-summary-value score-<?php echo $this->get_score_class($report->fact_check_score); ?>">
                            <?php echo $report->fact_check_score; ?>/100
                        </div>
                    </div>
                    <div class="report-summary-item">
                        <div class="report-summary-label">SEO Score</div>
                        <div class="report-summary-value score-<?php echo $this->get_score_class($report->seo_score); ?>">
                            <?php echo $report->seo_score; ?>/100
                        </div>
                    </div>
                    <div class="report-summary-item">
                        <div class="report-summary-label">Readability</div>
                        <div class="report-summary-value score-<?php echo $this->get_score_class($report->readability_score); ?>">
                            <?php echo $report->readability_score; ?>/100
                        </div>
                    </div>
                    <div class="report-summary-item">
                        <div class="report-summary-label">Status</div>
                        <div class="report-summary-value">
                            <span class="status-badge status-<?php echo esc_attr($report->status); ?>">
                                <?php echo ucfirst($report->status); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="report-actions">
                    <button type="button" class="button" id="facty-pro-view-report" data-report-id="<?php echo $report->id; ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        View Full Report
                    </button>
                    
                    <?php if ($report->status === 'completed' && current_user_can('publish_posts')): ?>
                    <button type="button" class="button button-primary" id="facty-pro-verify-post" data-report-id="<?php echo $report->id; ?>">
                        <span class="dashicons dashicons-yes-alt"></span>
                        Mark as Verified
                    </button>
                    <?php elseif ($report->status === 'verified'): ?>
                    <button type="button" class="button" id="facty-pro-unverify-post" data-report-id="<?php echo $report->id; ?>">
                        <span class="dashicons dashicons-dismiss"></span>
                        Remove Verification
                    </button>
                    <div class="verification-info">
                        ✓ Verified by <?php echo get_userdata($report->verified_by)->display_name; ?> 
                        on <?php echo date('M j, Y', strtotime($report->verified_at)); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Progress Container (hidden by default) -->
            <div id="facty-pro-progress" class="facty-pro-progress" style="display: none;">
                <div class="progress-header">
                    <div class="progress-title">
                        <span class="progress-spinner"></span>
                        <span>Analyzing Article...</span>
                    </div>
                    <div class="progress-percentage">0%</div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%;"></div>
                </div>
                
                <div class="progress-message">Starting analysis...</div>
            </div>
            
            <!-- Report Container (hidden by default) -->
            <div id="facty-pro-report" class="facty-pro-report" style="display: none;">
                <!-- Report will be dynamically inserted here -->
            </div>
            
            <!-- Features Info -->
            <div class="facty-pro-features">
                <div class="feature-item">
                    <span class="dashicons dashicons-yes"></span>
                    <strong>Deep Research:</strong> Uses Perplexity AI with real-time web search
                </div>
                <div class="feature-item">
                    <span class="dashicons dashicons-yes"></span>
                    <strong>Claim Analysis:</strong> Verifies each factual claim with sources
                </div>
                <div class="feature-item">
                    <span class="dashicons dashicons-yes"></span>
                    <strong>SEO Optimization:</strong> Comprehensive SEO analysis and recommendations
                </div>
                <div class="feature-item">
                    <span class="dashicons dashicons-yes"></span>
                    <strong>Style & Readability:</strong> Improves writing quality and engagement
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get CSS class for score
     */
    private function get_score_class($score) {
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        return 'poor';
    }
}
