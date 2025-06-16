<?php
/**
 * Plugin Name: WooCommerce Customer Orders Report
 * Plugin URI: https://yourwebsite.com/plugins/woo-customer-orders-report
 * Description: Comprehensive customer orders reporting tool for WooCommerce with advanced filtering, analytics, and export capabilities.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-customer-orders-report
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package WooCustomerOrdersReport
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_COR_VERSION', '1.0.0');
define('WOO_COR_PLUGIN_FILE', __FILE__);
define('WOO_COR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_COR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_COR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class WooCustomerOrdersReportPlugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->check_woocommerce()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('woo-customer-orders-report', false, dirname(WOO_COR_PLUGIN_BASENAME) . '/languages');
        
        // Initialize the main report class
        require_once WOO_COR_PLUGIN_DIR . 'includes/class-woo-customer-orders-report.php';
        new WooCustomerOrdersReport();
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WOO_COR_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function check_woocommerce() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Display notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Customer Orders Report requires WooCommerce to be installed and activated.', 'woo-customer-orders-report'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        if (!$this->check_woocommerce()) {
            deactivate_plugins(WOO_COR_PLUGIN_BASENAME);
            wp_die(__('This plugin requires WooCommerce to be installed and activated.', 'woo-customer-orders-report'));
        }
        
        // Set plugin version
        update_option('woo_cor_version', WOO_COR_VERSION);
        
        // Create necessary database tables or options if needed
        $this->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data if needed
        flush_rewrite_rules();
    }
    
    /**
     * Create necessary database tables or options
     */
    private function create_tables() {
        // Add any database tables or default options here if needed
        // For this plugin, we don't need additional tables as we use WooCommerce data
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=customer-orders-report') . '">' . __('Reports', 'woo-customer-orders-report') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
WooCustomerOrdersReportPlugin::get_instance(); 