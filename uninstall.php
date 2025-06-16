<?php
/**
 * Uninstall WooCommerce Customer Orders Report Plugin
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It cleans up any data created by the plugin.
 *
 * @package WooCustomerOrdersReport
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function woo_cor_uninstall_cleanup() {
    // Remove plugin options
    delete_option('woo_cor_version');
    
    // Remove any transients
    delete_transient('woo_cor_analytics_cache');
    delete_transient('woo_cor_products_cache');
    delete_transient('woo_cor_categories_cache');
    
    // Clean up any scheduled events
    wp_clear_scheduled_hook('woo_cor_cleanup_event');
    
    // Remove user meta if any
    delete_metadata('user', 0, 'woo_cor_preferences', '', true);
    
    // Note: We don't delete actual order data as that belongs to WooCommerce
    // We only clean up data specific to this plugin
}

// Run cleanup
woo_cor_uninstall_cleanup(); 