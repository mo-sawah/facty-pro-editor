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
                        <?php echo $report ? 'Recheck Article' : 'Start Fact Check'; ?>
                    </button>
                </div>
            </div>
            
            <?php if ($report): ?>
            <!-- Existing Report Summary -->
            <div class="facty-pro-existing-report">
                <div class="report-summary">
                    <div class="report-summary-item">
                        <div class="report-summary-label">Last Checked</div>
                        <div class="report-summary-value">
                            <?php 
                            // Get the created_at timestamp from database (in UTC)
                            $report_time = strtotime($report->created_at);
                            $current_time = time();
                            $time_diff = $current_time - $report_time;
                            
                            // Format time difference
                            if ($time_diff < 60) {
                                echo 'Just now';
                            } else {
                                echo human_time_diff($report_time, $current_time) . ' ago';
                            }
                            ?>
                        </div>
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
                
                <!-- Display Full Report -->
                <?php 
                $report_data = json_decode($report->report, true);
                $fact_check = isset($report_data['fact_check']) ? $report_data['fact_check'] : array();
                $seo = isset($report_data['seo']) ? $report_data['seo'] : array();
                $style = isset($report_data['style']) ? $report_data['style'] : array();
                
                if (!empty($fact_check)): ?>
                <div class="facty-pro-report-detailed">
                    <h3 style="margin: 20px 0 10px 0; font-size: 16px;">📋 Detailed Fact-Check Report</h3>
                    
                    <?php if (!empty($fact_check['issues'])): ?>
                    <div class="report-section">
                        <h4 style="color: #dc3545; margin: 15px 0 10px 0;">⚠️ Issues Found (<?php echo count($fact_check['issues']); ?>)</h4>
                        <?php foreach ($fact_check['issues'] as $issue): ?>
                        <div class="issue-item-detailed severity-<?php echo esc_attr($issue['severity']); ?>" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #dc3545; border-radius: 4px;">
                            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">
                                "<?php echo esc_html($issue['claim']); ?>"
                            </div>
                            
                            <div style="margin: 10px 0; padding: 10px; background: #fff; border-radius: 4px;">
                                <strong style="color: #dc3545;">❌ Problem:</strong>
                                <p style="margin: 5px 0 0 0;"><?php echo esc_html($issue['the_problem']); ?></p>
                            </div>
                            
                            <?php if (!empty($issue['actual_facts'])): ?>
                            <div style="margin: 10px 0; padding: 10px; background: #fff; border-radius: 4px;">
                                <strong style="color: #10b981;">✓ Actual Facts:</strong>
                                <p style="margin: 5px 0 0 0;"><?php echo esc_html($issue['actual_facts']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($issue['how_to_fix'])): ?>
                            <div style="margin: 10px 0; padding: 10px; background: #e8f5e9; border-radius: 4px;">
                                <strong style="color: #2e7d32;">💡 Suggested Fix:</strong>
                                <p style="margin: 5px 0 0 0;"><?php echo esc_html($issue['how_to_fix']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($issue['sources']) || !empty($issue['source'])): 
                                $sources = !empty($issue['sources']) ? $issue['sources'] : (isset($issue['source']) ? [$issue['source']] : []);
                                if (!empty($sources)):
                            ?>
                            <div style="margin: 10px 0; padding: 10px; background: #f0f9ff; border-radius: 4px;">
                                <strong style="color: #0369a1;">📚 Sources:</strong>
                                <ul style="margin: 5px 0 0 20px; padding: 0;">
                                    <?php foreach ($sources as $source): 
                                        if (is_array($source) && isset($source['url'])):
                                    ?>
                                    <li style="margin: 3px 0;">
                                        <a href="<?php echo esc_url($source['url']); ?>" target="_blank" style="color: #0369a1; text-decoration: none;">
                                            <?php echo esc_html(isset($source['title']) ? $source['title'] : $source['url']); ?>
                                        </a>
                                        <?php if (isset($source['date'])): ?>
                                        <span style="color: #64748b; font-size: 12px;">(<?php echo esc_html($source['date']); ?>)</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php 
                                        endif;
                                    endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($fact_check['verified_facts'])): ?>
                    <div class="report-section" style="margin-top: 20px;">
                        <h4 style="color: #10b981; margin: 15px 0 10px 0;">✅ Verified Facts (<?php echo count($fact_check['verified_facts']); ?>)</h4>
                        <?php foreach ($fact_check['verified_facts'] as $fact): ?>
                        <div style="margin-bottom: 10px; padding: 12px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 4px;">
                            <div style="font-weight: 500;">
                                "<?php echo esc_html($fact['claim']); ?>"
                            </div>
                            <div style="margin-top: 5px; font-size: 13px; color: #059669;">
                                Confidence: <?php echo ucfirst(esc_html($fact['confidence'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($seo['recommendations'])): ?>
                    <div class="report-section" style="margin-top: 20px;">
                        <h4 style="margin: 15px 0 10px 0;">🔍 SEO Recommendations</h4>
                        <ul style="margin: 5px 0 0 20px; padding: 0;">
                            <?php foreach (array_slice($seo['recommendations'], 0, 5) as $rec): ?>
                            <li style="margin: 5px 0; color: #475569;"><?php echo esc_html($rec); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
