<?php
/**
 * Facty Pro Admin
 * Plugin settings page and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Admin {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Facty Pro Editor Settings',
            'Facty Pro Editor',
            'manage_options',
            'facty-pro-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('facty_pro_options_group', 'facty_pro_options');
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_facty-pro-settings') {
            return;
        }
        
        wp_enqueue_style(
            'facty-pro-admin',
            FACTY_PRO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FACTY_PRO_VERSION
        );
    }
    
    public function render_settings_page() {
        if (isset($_POST['facty_pro_save'])) {
            check_admin_referer('facty_pro_settings');
            
            $options = array(
                'perplexity_api_key' => sanitize_text_field($_POST['perplexity_api_key']),
                'perplexity_model' => sanitize_text_field($_POST['perplexity_model']),
                'enable_seo_analysis' => isset($_POST['enable_seo_analysis']),
                'enable_style_analysis' => isset($_POST['enable_style_analysis']),
                'enable_readability_analysis' => isset($_POST['enable_readability_analysis']),
                'show_frontend_badge' => isset($_POST['show_frontend_badge']),
                'require_verification' => isset($_POST['require_verification']),
                'add_schema_markup' => isset($_POST['add_schema_markup']),
                'recency_filter' => sanitize_text_field($_POST['recency_filter'])
            );
            
            update_option('facty_pro_options', $options);
            echo '<div class="updated"><p><strong>Settings saved successfully!</strong></p></div>';
            
            $this->options = $options;
        }
        
        ?>
        <div class="wrap facty-pro-admin-wrap">
            <h1>⚡ Facty Pro Editor Settings</h1>
            <p class="subtitle">Configure your editorial fact-checking system</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('facty_pro_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2"><h2>API Configuration</h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">Perplexity API Key *</th>
                        <td>
                            <input type="text" name="perplexity_api_key" 
                                   value="<?php echo esc_attr($this->options['perplexity_api_key']); ?>" 
                                   class="regular-text" required>
                            <p class="description">Get your API key from <a href="https://www.perplexity.ai/settings/api" target="_blank">Perplexity AI Settings</a></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <select name="perplexity_model">
                                <option value="sonar-pro" <?php selected($this->options['perplexity_model'], 'sonar-pro'); ?>>Sonar Pro (Recommended)</option>
                                <option value="sonar" <?php selected($this->options['perplexity_model'], 'sonar'); ?>>Sonar</option>
                            </select>
                            <p class="description">Sonar Pro provides better accuracy for fact-checking</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Search Recency Filter</th>
                        <td>
                            <select name="recency_filter">
                                <option value="hour" <?php selected($this->options['recency_filter'], 'hour'); ?>>Last Hour</option>
                                <option value="day" <?php selected($this->options['recency_filter'], 'day'); ?>>Last Day</option>
                                <option value="week" <?php selected($this->options['recency_filter'], 'week'); ?>>Last Week (Recommended)</option>
                                <option value="month" <?php selected($this->options['recency_filter'], 'month'); ?>>Last Month</option>
                                <option value="year" <?php selected($this->options['recency_filter'], 'year'); ?>>Last Year</option>
                            </select>
                            <p class="description">How far back to search for sources (recent sources are prioritized)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>Analysis Features</h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable SEO Analysis</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_seo_analysis" value="1" 
                                       <?php checked($this->options['enable_seo_analysis']); ?>>
                                Analyze content for SEO best practices
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Style Analysis</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_style_analysis" value="1" 
                                       <?php checked($this->options['enable_style_analysis']); ?>>
                                Analyze writing style and provide suggestions
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Readability Analysis</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_readability_analysis" value="1" 
                                       <?php checked($this->options['enable_readability_analysis']); ?>>
                                Calculate readability scores (Flesch Reading Ease)
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>Frontend Display</h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">Show Verification Badge</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_frontend_badge" value="1" 
                                       <?php checked($this->options['show_frontend_badge']); ?>>
                                Display fact-checked badge on verified articles
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Add ClaimReview Schema</th>
                        <td>
                            <label>
                                <input type="checkbox" name="add_schema_markup" value="1" 
                                       <?php checked($this->options['add_schema_markup']); ?>>
                                Add ClaimReview structured data for Google
                            </label>
                            <p class="description">Helps Google recognize fact-checked content</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Require Verification</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_verification" value="1" 
                                       <?php checked($this->options['require_verification']); ?>>
                                Require editor verification before showing badge
                            </label>
                            <p class="description">Editors must manually verify articles before public display</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="facty_pro_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <?php $this->render_stats(); ?>
            
            <div class="facty-pro-help">
                <h3>🚀 Quick Start Guide</h3>
                <ol>
                    <li><strong>Get API Key:</strong> Sign up at <a href="https://www.perplexity.ai/settings/api" target="_blank">Perplexity AI</a> and get your API key</li>
                    <li><strong>Configure Settings:</strong> Enter your API key above and enable desired features</li>
                    <li><strong>Start Fact-Checking:</strong> Edit any post/page and use the "Facty Pro" meta box</li>
                    <li><strong>Review Report:</strong> Get comprehensive fact-checking, SEO, and style analysis</li>
                    <li><strong>Verify & Publish:</strong> Mark articles as verified to display badges</li>
                </ol>
                
                <h3>📚 Documentation</h3>
                <ul>
                    <li><a href="https://docs.perplexity.ai/" target="_blank">Perplexity AI Documentation</a></li>
                    <li><a href="https://developers.google.com/search/docs/appearance/structured-data/factcheck" target="_blank">Google ClaimReview Guidelines</a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    private function render_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'facty_pro_reports';
        
        $total_checks = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $verified_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'verified'");
        $avg_fact_score = $wpdb->get_var("SELECT AVG(fact_check_score) FROM $table");
        
        ?>
        <div class="facty-pro-stats">
            <h2>📊 Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_checks); ?></div>
                    <div class="stat-label">Total Fact Checks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($verified_count); ?></div>
                    <div class="stat-label">Verified Articles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo round($avg_fact_score); ?>/100</div>
                    <div class="stat-label">Average Accuracy</div>
                </div>
            </div>
        </div>
        <?php
    }
}
