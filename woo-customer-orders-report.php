<?php
/**
 * Plugin Name: WooCommerce Customer Orders Report
 * Plugin URI: https://github.com/RisePlugins/woo-customer-orders-report
 * GitHub Plugin URI: RisePlugins/woo-customer-orders-report
 * Description: Comprehensive customer orders reporting tool for WooCommerce with advanced filtering, analytics, and export capabilities.
 * Version: 1.0.2
 * Update URI: https://github.com/RisePlugins/woo-customer-orders-report
 * Author: Ryan Moreno
 * Author URI: https://tonicsiteshop.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-customer-orders-report
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_COR_VERSION', '1.0.2');
define('WOO_COR_PLUGIN_FILE', __FILE__);
define('WOO_COR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_COR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function woo_cor_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'woo_cor_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display notice if WooCommerce is not active
 */
function woo_cor_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WooCommerce Customer Orders Report requires WooCommerce to be installed and activated.', 'woo-customer-orders-report'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function woo_cor_init() {
    if (!woo_cor_check_woocommerce()) {
        return;
    }
    
    // Include the main class file
require_once WOO_COR_PLUGIN_DIR . 'includes/class-woo-customer-orders-report.php';

// Include the updater class
require_once WOO_COR_PLUGIN_DIR . 'includes/class-plugin-updater.php';

// Initialize the main class
new WooCustomerOrdersReport();

// Initialize the updater
new WooCorPluginUpdater(__FILE__, 'RisePlugins', 'woo-customer-orders-report', '1.0.2');
}

/**
 * Plugin activation hook
 */
function woo_cor_activate() {
    if (!woo_cor_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and activated.', 'woo-customer-orders-report'));
    }
    
    // Set plugin version
    update_option('woo_cor_version', WOO_COR_VERSION);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
function woo_cor_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
}

/**
 * Plugin uninstall hook
 */
function woo_cor_uninstall() {
    // Clean up options if needed
    delete_option('woo_cor_version');
    
    // Note: Be careful about deleting data - users might want to keep their settings
    // Only uncomment if you want to completely remove all traces
    // delete_option('woo_cor_settings');
}

// Register hooks
register_activation_hook(__FILE__, 'woo_cor_activate');
register_deactivation_hook(__FILE__, 'woo_cor_deactivate');
register_uninstall_hook(__FILE__, 'woo_cor_uninstall');

// Initialize plugin after WordPress loads
add_action('plugins_loaded', 'woo_cor_init');

/**
 * Main plugin class
 */

// ... existing code ...