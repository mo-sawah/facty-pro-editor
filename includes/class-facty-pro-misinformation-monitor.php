<?php
/**
 * Facty Pro Misinformation Monitor
 * Main class for monitoring and managing misinformation/false claims from various sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Misinformation_Monitor {
    
    private $options;
    private $table_name;
    
    public function __construct($options) {
        $this->options = $options;
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'facty_misinformation_queue';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_facty_pro_generate_article', array($this, 'ajax_generate_article'));
        add_action('wp_ajax_facty_pro_dismiss_claim', array($this, 'ajax_dismiss_claim'));
        add_action('wp_ajax_facty_pro_refresh_claims', array($this, 'ajax_refresh_claims'));
        add_action('wp_ajax_facty_pro_get_claims', array($this, 'ajax_get_claims'));
        
        // Cron hooks
        add_action('facty_pro_collect_misinformation', array($this, 'collect_misinformation'));
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'facty_misinformation_queue';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            claim_text TEXT NOT NULL,
            source VARCHAR(255) NOT NULL,
            source_url TEXT,
            category VARCHAR(100),
            rating VARCHAR(50),
            fact_checker VARCHAR(100),
            discovered_date DATETIME NOT NULL,
            region VARCHAR(10) DEFAULT 'UK',
            status VARCHAR(20) DEFAULT 'pending',
            content_hash VARCHAR(64) UNIQUE,
            metadata JSON,
            post_id BIGINT(20) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY category (category),
            KEY discovered_date (discovered_date),
            KEY content_hash (content_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule cron job
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('facty_pro_collect_misinformation')) {
            wp_schedule_event(time(), 'hourly', 'facty_pro_collect_misinformation');
        }
    }
    
    /**
     * Unschedule cron job
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled('facty_pro_collect_misinformation');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'facty_pro_collect_misinformation');
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            'Misinformation Monitor',
            'Misinformation',
            'edit_posts',
            'facty-pro-misinformation',
            array($this, 'render_dashboard'),
            'dashicons-warning',
            30
        );
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if ($hook !== 'toplevel_page_facty-pro-misinformation') {
            return;
        }
        
        wp_enqueue_style(
            'facty-pro-misinformation',
            FACTY_PRO_PLUGIN_URL . 'assets/css/misinformation-dashboard.css',
            array(),
            FACTY_PRO_VERSION
        );
        
        wp_enqueue_script(
            'facty-pro-misinformation',
            FACTY_PRO_PLUGIN_URL . 'assets/js/misinformation-dashboard.js',
            array('jquery'),
            FACTY_PRO_VERSION,
            true
        );
        
        wp_localize_script('facty-pro-misinformation', 'factyProMisinfo', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('facty_pro_misinfo_nonce')
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $stats = $this->get_stats();
        ?>
        <div class="wrap facty-pro-misinfo-dashboard">
            <div class="dashboard-header">
                <h1>
                    <span class="dashicons dashicons-warning"></span>
                    UK Misinformation Monitor
                </h1>
                <p class="subtitle">Track and debunk false claims spreading in UK media and social networks</p>
            </div>
            
            <?php
            // Show configuration warnings
            $warnings = array();
            if (empty($this->options['perplexity_api_key'])) {
                $warnings[] = 'Perplexity API key not configured - article generation will not work';
            }
            if (empty($this->options['google_factcheck_api_key'])) {
                $warnings[] = 'Google Fact Check API key not configured - Google source will be skipped';
            }
            
            if (!empty($warnings)) {
                ?>
                <div class="facty-pro-notice error" style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; margin: 20px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 12px 0; color: #991b1b;">⚠️ Configuration Required</h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($warnings as $warning): ?>
                            <li style="color: #991b1b;"><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin: 12px 0 0 0;">
                        <a href="<?php echo admin_url('options-general.php?page=facty-pro-settings'); ?>" class="button button-primary">
                            Configure API Keys
                        </a>
                    </p>
                </div>
                <?php
            }
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['pending']); ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon generated">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['generated']); ?></div>
                        <div class="stat-label">Articles Generated</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon published">
                        <span class="dashicons dashicons-yes"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['published']); ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon total">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
                        <div class="stat-label">Total Tracked</div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-controls">
                <div class="controls-left">
                    <button type="button" class="button button-primary" id="refresh-claims">
                        <span class="dashicons dashicons-update"></span>
                        <span>Refresh Now</span>
                    </button>
                    
                    <div class="filter-group">
                        <select id="filter-status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="article_generated">Article Generated</option>
                            <option value="published">Published</option>
                            <option value="ignored">Ignored</option>
                        </select>
                        
                        <select id="filter-category" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="health">Health & Medicine</option>
                            <option value="politics">Politics & Government</option>
                            <option value="economy">Economy & Finance</option>
                            <option value="immigration">Immigration</option>
                            <option value="climate">Climate & Environment</option>
                            <option value="covid">COVID-19</option>
                            <option value="crime">Crime & Justice</option>
                            <option value="international">International Affairs</option>
                        </select>
                        
                        <select id="filter-source" class="filter-select">
                            <option value="">All Sources</option>
                            <option value="google_factcheck">Google Fact Check</option>
                            <option value="full_fact_rss">Full Fact RSS</option>
                            <option value="perplexity_search">Perplexity Search</option>
                        </select>
                    </div>
                </div>
                
                <div class="controls-right">
                    <span class="last-update">Last updated: <span id="last-update-time">-</span></span>
                </div>
            </div>
            
            <div class="claims-table-container">
                <div id="loading-spinner" class="loading-spinner" style="display: none;">
                    <div class="spinner"></div>
                    <p>Loading claims...</p>
                </div>
                
                <table class="wp-list-table widefat fixed striped" id="claims-table">
                    <thead>
                        <tr>
                            <th class="column-claim">Claim</th>
                            <th class="column-category">Category</th>
                            <th class="column-source">Source</th>
                            <th class="column-rating">Rating</th>
                            <th class="column-date">Discovered</th>
                            <th class="column-status">Status</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="claims-tbody">
                        <!-- Claims will be loaded via AJAX -->
                    </tbody>
                </table>
                
                <div id="no-claims-message" style="display: none;">
                    <p>No claims found. The system will automatically collect misinformation every hour.</p>
                    <p>Click "Refresh Now" to manually trigger collection.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_stats() {
        global $wpdb;
        
        return array(
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'"),
            'generated' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'article_generated'"),
            'published' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'published'"),
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}")
        );
    }
    
    /**
     * AJAX: Get claims for table
     */
    public function ajax_get_claims() {
        check_ajax_referer('facty_pro_misinfo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $filters = array(
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'category' => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'source' => isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '',
            'limit' => 50
        );
        
        $claims = $this->get_claims($filters);
        
        wp_send_json_success(array(
            'claims' => $claims,
            'count' => count($claims)
        ));
    }
    
    /**
     * Get claims from database with filters
     */
    private function get_claims($filters = array()) {
        global $wpdb;
        
        $where = array('1=1');
        
        if (!empty($filters['status'])) {
            $where[] = $wpdb->prepare('status = %s', $filters['status']);
        }
        
        if (!empty($filters['category'])) {
            $where[] = $wpdb->prepare('category = %s', $filters['category']);
        }
        
        if (!empty($filters['source'])) {
            $where[] = $wpdb->prepare('source = %s', $filters['source']);
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY discovered_date DESC 
                LIMIT {$limit}";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * AJAX: Refresh claims (trigger collection)
     */
    public function ajax_refresh_claims() {
        check_ajax_referer('facty_pro_misinfo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Trigger collection
        $this->collect_misinformation();
        
        // Get collection status
        $last_collection = get_option('facty_pro_last_collection', array());
        
        $message = 'Collection completed';
        if (isset($last_collection['error'])) {
            $message = 'Collection failed: ' . $last_collection['error'];
        } elseif (isset($last_collection['total_stored'])) {
            $message = 'Found ' . $last_collection['total_stored'] . ' new claims';
            if ($last_collection['total_stored'] === 0 && $last_collection['total_found'] > 0) {
                $message .= ' (all duplicates)';
            }
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'debug' => $last_collection
        ));
    }
    
    /**
     * Collect misinformation from all sources
     */
    public function collect_misinformation() {
        error_log('Facty Pro: Starting misinformation collection');
        error_log('Facty Pro: API Keys configured - Perplexity: ' . (!empty($this->options['perplexity_api_key']) ? 'YES' : 'NO') . ', Google: ' . (!empty($this->options['google_factcheck_api_key']) ? 'YES' : 'NO'));
        
        // Clean up old claims first
        $this->cleanup_old_claims();
        
        $results_summary = array(
            'google' => 0,
            'full_fact' => 0,
            'perplexity' => 0,
            'errors' => array(),
            'duplicates_filtered' => 0
        );
        
        try {
            // Initialize collector
            require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-misinformation-collector.php';
            $collector = new Facty_Pro_Misinformation_Collector($this->options);
            
            // Collect from all sources
            $results = $collector->collect_all();
            
            error_log('Facty Pro: Collection returned ' . count($results) . ' total claims from all sources');
            
            if (count($results) === 0) {
                error_log('Facty Pro: WARNING - No claims were collected from any source. Check API keys and network connectivity.');
            }
            
            // Store results
            $stored_count = 0;
            $duplicate_count = 0;
            
            foreach ($results as $claim) {
                if ($this->store_claim($claim)) {
                    $stored_count++;
                    
                    // Track by source
                    if (isset($claim['source'])) {
                        if ($claim['source'] === 'google_factcheck') {
                            $results_summary['google']++;
                        } elseif ($claim['source'] === 'full_fact_rss') {
                            $results_summary['full_fact']++;
                        } elseif ($claim['source'] === 'perplexity_search') {
                            $results_summary['perplexity']++;
                        }
                    }
                } else {
                    $duplicate_count++;
                }
            }
            
            $results_summary['duplicates_filtered'] = $duplicate_count;
            
            error_log('Facty Pro: Storage complete - Stored: ' . $stored_count . ', Duplicates filtered: ' . $duplicate_count);
            error_log('Facty Pro: By source - Google: ' . $results_summary['google'] . ', Full Fact: ' . $results_summary['full_fact'] . ', Perplexity: ' . $results_summary['perplexity']);
            
            // Store last collection status
            update_option('facty_pro_last_collection', array(
                'timestamp' => current_time('mysql'),
                'total_found' => count($results),
                'total_stored' => $stored_count,
                'duplicates_filtered' => $duplicate_count,
                'sources' => $results_summary
            ));
            
        } catch (Exception $e) {
            error_log('Facty Pro: Collection error - ' . $e->getMessage());
            error_log('Facty Pro: Error trace - ' . $e->getTraceAsString());
            
            // Store error status
            update_option('facty_pro_last_collection', array(
                'timestamp' => current_time('mysql'),
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Store claim in database (with smart duplicate check)
     */
    private function store_claim($claim) {
        global $wpdb;
        
        // Generate content hash
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $claim['claim_text'])));
        $content_hash = hash('sha256', $normalized);
        
        // Check for exact duplicates in last 30 days only (not forever)
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE content_hash = %s 
             AND discovered_date > %s",
            $content_hash,
            $thirty_days_ago
        ));
        
        if ($existing) {
            error_log('Facty Pro: Duplicate claim found (exact match within 30 days): ' . substr($claim['claim_text'], 0, 50));
            return false; // Already exists
        }
        
        // Check for similar claims using fuzzy matching (only in last 14 days)
        $fourteen_days_ago = date('Y-m-d H:i:s', strtotime('-14 days'));
        $recent_claims = $wpdb->get_results($wpdb->prepare(
            "SELECT id, claim_text FROM {$this->table_name} 
             WHERE discovered_date > %s 
             AND category = %s
             LIMIT 50",
            $fourteen_days_ago,
            isset($claim['category']) ? $claim['category'] : 'uncategorized'
        ));
        
        // Check similarity with recent claims
        foreach ($recent_claims as $recent) {
            $similarity = $this->calculate_similarity($normalized, strtolower($recent->claim_text));
            if ($similarity > 85) { // 85% similar = likely duplicate
                error_log('Facty Pro: Similar claim found (' . $similarity . '% match): ' . substr($claim['claim_text'], 0, 50));
                return false;
            }
        }
        
        // Insert new claim
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'claim_text' => $claim['claim_text'],
                'source' => $claim['source'],
                'source_url' => isset($claim['source_url']) ? $claim['source_url'] : '',
                'category' => isset($claim['category']) ? $claim['category'] : 'uncategorized',
                'rating' => isset($claim['rating']) ? $claim['rating'] : 'Unknown',
                'fact_checker' => isset($claim['fact_checker']) ? $claim['fact_checker'] : '',
                'discovered_date' => current_time('mysql'),
                'region' => 'UK',
                'status' => 'pending',
                'content_hash' => $content_hash,
                'metadata' => json_encode(isset($claim['metadata']) ? $claim['metadata'] : array())
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            error_log('Facty Pro: NEW claim stored successfully: ' . substr($claim['claim_text'], 0, 80));
        }
        
        return $result !== false;
    }
    
    /**
     * Calculate similarity between two strings (0-100)
     * Uses similar_text for fuzzy matching
     */
    private function calculate_similarity($str1, $str2) {
        similar_text($str1, $str2, $percent);
        return round($percent, 2);
    }
    
    /**
     * Clean up old claims (older than 90 days)
     * Called automatically during collection
     */
    private function cleanup_old_claims() {
        global $wpdb;
        
        // Delete claims older than 90 days that are pending or ignored
        $ninety_days_ago = date('Y-m-d H:i:s', strtotime('-90 days'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE discovered_date < %s 
             AND status IN ('pending', 'ignored')",
            $ninety_days_ago
        ));
        
        if ($deleted > 0) {
            error_log('Facty Pro: Cleaned up ' . $deleted . ' old claims (older than 90 days)');
        }
        
        return $deleted;
    }
    
    /**
     * AJAX: Generate article from claim
     */
    public function ajax_generate_article() {
        check_ajax_referer('facty_pro_misinfo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $claim_id = intval($_POST['claim_id']);
        
        global $wpdb;
        $claim = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $claim_id
        ));
        
        if (!$claim) {
            wp_send_json_error('Claim not found');
        }
        
        try {
            // Generate article
            require_once FACTY_PRO_PLUGIN_PATH . 'includes/class-facty-pro-article-generator.php';
            $generator = new Facty_Pro_Article_Generator($this->options);
            
            $post_id = $generator->generate_article($claim);
            
            // Update claim status
            $wpdb->update(
                $this->table_name,
                array(
                    'status' => 'article_generated',
                    'post_id' => $post_id
                ),
                array('id' => $claim_id),
                array('%s', '%d'),
                array('%d')
            );
            
            wp_send_json_success(array(
                'message' => 'Article generated successfully',
                'post_id' => $post_id,
                'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Dismiss claim
     */
    public function ajax_dismiss_claim() {
        check_ajax_referer('facty_pro_misinfo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $claim_id = intval($_POST['claim_id']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'ignored'),
            array('id' => $claim_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Claim dismissed'));
        } else {
            wp_send_json_error('Failed to dismiss claim');
        }
    }
}