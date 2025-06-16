<?php
/**
 * WooCommerce Customer Orders Report Main Class
 *
 * @package WooCustomerOrdersReport
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooCustomerOrdersReport {
    
    private $per_page = 50; // Pagination limit to prevent memory issues
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'handle_csv_export'));
        add_action('wp_ajax_get_products_by_category', array($this, 'ajax_get_products_by_category'));
        add_action('admin_notices', array($this, 'show_version_notice'));
        add_action('wp_ajax_cor_check_updates', array($this, 'ajax_check_updates'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Customer Orders Report', 'woo-customer-orders-report'),
            __('Customer Orders Report', 'woo-customer-orders-report'),
            'manage_woocommerce',
            'customer-orders-report',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_customer-orders-report') {
            return;
        }
        
        // Enqueue jQuery UI datepicker and its CSS
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css', array(), '1.12.1');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
        
        // Enqueue plugin-specific styles and scripts
        wp_enqueue_style(
            'woo-cor-admin-style',
            WOO_COR_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WOO_COR_VERSION
        );
        
        wp_enqueue_script(
            'woo-cor-admin-script',
            WOO_COR_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'jquery-ui-datepicker', 'chart-js'),
            WOO_COR_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('woo-cor-admin-script', 'woo_cor_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cor_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'woo-customer-orders-report'),
                'error' => __('An error occurred', 'woo-customer-orders-report'),
            )
        ));
        
        // Add inline styles for backward compatibility
        $this->add_inline_styles();
    }
    
    private function add_inline_styles() {
        // Add all the CSS from the original snippet here
        wp_add_inline_style('woo-cor-admin-style', '
            .cor-filters { 
                background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
                padding: 32px; margin: 24px 0; 
                border: 1px solid #e2e8f0; border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
                position: relative; overflow: visible;
            }
            .cor-filters::before {
                content: "";
                position: absolute; top: 0; left: 0; right: 0; height: 3px;
                background: linear-gradient(90deg, #4f46e5 0%, #06b6d4 25%, #10b981 50%, #f59e0b 75%, #ef4444 100%);
            }
            .cor-filter-row { display: flex; gap: 20px; margin-bottom: 24px; align-items: flex-start; flex-wrap: wrap; }
            .cor-filter-group { min-width: 200px; flex: 1; }
            .cor-filter-group.date-range { min-width: 280px; }
            .cor-filter-group.categories { min-width: 220px; }
            .cor-filter-group.products { min-width: 220px; }
            .cor-filter-group.date-range > div > div {
                flex: 1; min-width: 0;
            }
            .cor-filter-group.date-range input.datepicker {
                width: 100%;
                background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
                border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;
                font-size: 14px; color: #374151;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            .cor-filter-group.date-range input.datepicker:hover {
                border-color: #4f46e5;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            }
            .cor-filter-group.date-range input.datepicker:focus {
                outline: none; border-color: #4f46e5;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            }
            .cor-filter-group label { 
                font-weight: 600; margin-bottom: 12px; display: block; 
                color: #0f172a; font-size: 14px;
                position: relative; padding-left: 0;
            }
            .cor-dropdown-container { position: relative; z-index: 1000; }
            .cor-dropdown-trigger { 
                background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
                border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; cursor: pointer; 
                display: flex; justify-content: space-between; align-items: center; min-height: 28px;
                transition: all 0.2s ease; font-size: 14px; color: #374151;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            .cor-dropdown-trigger:hover { 
                border-color: #4f46e5; 
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
                transform: translateY(-1px);
            }
            .cor-dropdown-trigger::after { content: "▼"; font-size: 12px; color: #666; }
            .cor-dropdown-trigger.active::after { content: "▲"; }
            .cor-dropdown-menu { 
                position: absolute; margin-top: 8px; top: 100%; left: 0; right: 0; 
                background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
                border: 1px solid #e2e8f0; border-radius: 12px; max-height: 320px; z-index: 9999; display: none;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
                backdrop-filter: blur(10px);
            }
            .cor-dropdown-menu.active { display: block; }
            .cor-search-box { 
                padding: 8px 12px; border-bottom: 1px solid #eee; 
            }
            .cor-search-box input { 
                width: 100%; border: 1px solid #ddd; border-radius: 3px; padding: 6px 8px; font-size: 13px;
            }
            .cor-dropdown-options { 
                max-height: 200px; overflow-y: auto; 
            }
            .cor-dropdown-option { 
                padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f5f5f5; font-size: 13px;
            }
            .cor-dropdown-option:last-child { border-bottom: none; }
            .cor-dropdown-option:hover { background: #f0f6fc; }
            .cor-dropdown-option.selected { background: #e7f3ff; }
            .cor-selected-tags { 
                display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; min-height: 0;
                transition: all 0.2s ease;
            }
            .cor-selected-tags:empty {
                display: none;
                margin-top: 0;
            }
            .cor-selected-tags:not(:empty) {
                margin-top: 8px;
            }
            .cor-tag { 
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%); 
                color: #fff; padding: 6px 12px; border-radius: 16px; font-size: 12px;
                display: flex; align-items: center; gap: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: all 0.2s ease;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .cor-tag-remove { 
                background: none; border: none; color: #fff; cursor: pointer; font-size: 14px; 
                line-height: 1; padding: 0; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;
                border-radius: 50%; transition: all 0.2s ease;
            }
            .cor-tag-remove:hover { 
                background: rgba(255,255,255,0.2); 
                transform: scale(1.1);
            }
            .cor-tag:hover {
                background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
                transform: translateY(-1px);
            }
            .cor-analytics { 
                padding: 0; margin: 32px 0; 
                position: relative;
            }
            .cor-analytics h2 {
                color: #0f172a; font-size: 24px; font-weight: 600; margin-bottom: 16px;
                text-align: left; letter-spacing: -0.025em;
            }
            .cor-reports-nav {
                display: flex; gap: 0; margin-bottom: 32px;
                border-bottom: 1px solid #e2e8f0;
            }
            .cor-reports-tab {
                padding: 12px 20px; font-size: 14px; font-weight: 500;
                color: #64748b; cursor: pointer; border-bottom: 2px solid transparent;
                transition: all 0.2s ease; position: relative;
            }
            .cor-reports-tab:hover {
                color: #0f172a; background-color: #f8fafc;
            }
            .cor-reports-tab.active {
                color: #ffffff; 
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
                border-bottom: 3px solid transparent;
                border-image: linear-gradient(90deg, #1f2937 0%, #374151 50%, #1f2937 100%) 1;
                position: relative;
            }
            .cor-report-content {
                display: none;
            }
            .cor-report-content.active {
                display: block;
            }
            .cor-stats-grid { 
                display: grid; grid-template-columns: repeat(4, 1fr); 
                gap: 1px; margin-bottom: 32px; 
                background: #e2e8f0; border-radius: 8px; overflow: hidden;
            }
            .cor-stat-card { 
                background: #fff; 
                padding: 24px 20px; text-align: left;
                border: none; transition: all 0.2s ease;
                position: relative;
            }
            .cor-stat-card:first-child { border-radius: 8px 0 0 8px; }
            .cor-stat-card:last-child { border-radius: 0 8px 8px 0; }
            .cor-stat-card:hover { 
                background: #f8fafc;
            }
            .cor-stat-value { 
                display: block; font-size: 28px; font-weight: 700; 
                color: #0f172a; margin-bottom: 4px; line-height: 1.1;
                font-feature-settings: "tnum";
                transition: transform 0.2s ease;
            }
            .cor-stat-label { 
                color: #64748b; font-size: 10px; font-weight: 600;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                padding: 3px 6px; border-radius: 6px;
                border: 1px solid #e2e8f0;
                display: inline-block; margin-top: 6px;
                position: relative; overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
            }
            .cor-stat-description {
                color: #9ca3af; font-size: 9px; font-weight: 400;
                display: block; margin-top: 4px; line-height: 1.3;
                font-style: italic;
            }

            .cor-stat-card:hover .cor-stat-label {
                background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
                border-color: #cbd5e1; color: #475569;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }
            /* Color-coded stat labels to match chart lines */
            .cor-stat-cart {
                background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(79, 70, 229, 0.05) 100%);
                border-color: rgba(79, 70, 229, 0.2);
                color: #4f46e5 !important;
            }
            .cor-stat-cart::before {
                background: linear-gradient(90deg, transparent 0%, rgba(79, 70, 229, 0.4) 50%, transparent 100%);
            }
            .cor-stat-card:hover .cor-stat-cart {
                background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(79, 70, 229, 0.08) 100%);
                border-color: rgba(79, 70, 229, 0.3);
                color: #4f46e5 !important;
            }
            
            .cor-stat-checkout {
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
                border-color: rgba(16, 185, 129, 0.2);
                color: #10b981 !important;
            }
            .cor-stat-checkout::before {
                background: linear-gradient(90deg, transparent 0%, rgba(16, 185, 129, 0.4) 50%, transparent 100%);
            }
            .cor-stat-card:hover .cor-stat-checkout {
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.08) 100%);
                border-color: rgba(16, 185, 129, 0.3);
                color: #10b981 !important;
            }
            
            .cor-stat-future {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
                border-color: rgba(139, 92, 246, 0.2);
                color: #8b5cf6 !important;
            }
            .cor-stat-future::before {
                background: linear-gradient(90deg, transparent 0%, rgba(139, 92, 246, 0.4) 50%, transparent 100%);
            }
            .cor-stat-card:hover .cor-stat-future {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%);
                border-color: rgba(139, 92, 246, 0.3);
                color: #8b5cf6 !important;
            }
            
            .cor-stat-grand {
                background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(14, 165, 233, 0.05) 100%);
                border-color: rgba(14, 165, 233, 0.2);
                color: #0ea5e9 !important;
            }

            .cor-stat-card:hover .cor-stat-grand {
                background: linear-gradient(135deg, rgba(14, 165, 233, 0.15) 0%, rgba(14, 165, 233, 0.08) 100%);
                border-color: rgba(14, 165, 233, 0.3);
                color: #0ea5e9 !important;
            }
            
            .cor-stat-discount {
                background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
                border-color: rgba(239, 68, 68, 0.2);
                color: #ef4444 !important;
            }
            .cor-stat-discount::before {
                background: linear-gradient(90deg, transparent 0%, rgba(239, 68, 68, 0.4) 50%, transparent 100%);
            }
            .cor-stat-card:hover .cor-stat-discount {
                background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.08) 100%);
                border-color: rgba(239, 68, 68, 0.3);
                color: #ef4444 !important;
            }
            
            .cor-stat-tax {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
                border-color: rgba(245, 158, 11, 0.2);
                color: #f59e0b !important;
            }
            .cor-stat-tax::before {
                background: linear-gradient(90deg, transparent 0%, rgba(245, 158, 11, 0.4) 50%, transparent 100%);
            }
            .cor-stat-card:hover .cor-stat-tax {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.08) 100%);
                border-color: rgba(245, 158, 11, 0.3);
                color: #f59e0b !important;
            }
            .cor-charts-grid { 
                display: grid; grid-template-columns: 2fr 1fr; gap: 24px; 
                margin-bottom: 0;
            }
            .cor-chart-container { 
                background: #fff; 
                padding: 24px; border-radius: 8px;
                border: 1px solid #e2e8f0;
                transition: border-color 0.2s ease;
                min-width: 0; /* Allows flex items to shrink below content size */
                overflow: hidden;
            }
            .cor-chart-container:hover {
                border-color: #cbd5e1;
            }
            .cor-chart-title { 
                font-size: 16px; font-weight: 600; margin-bottom: 20px; 
                color: #0f172a; text-align: left;
                letter-spacing: -0.025em;
            }
            .cor-chart-canvas { position: relative; height: 280px; }
            .cor-categories-list { padding: 0; }
            .cor-category-item { 
                display: flex; align-items: center; justify-content: space-between;
                padding: 12px 0; border-bottom: 1px solid #f1f5f9;
            }
            .cor-category-item:last-child { border-bottom: none; }
            .cor-category-info { display: flex; align-items: center; gap: 12px; flex: 1; }
            .cor-category-color { 
                width: 12px; height: 12px; border-radius: 2px; 
                flex-shrink: 0;
            }
            .cor-category-name { 
                font-size: 14px; font-weight: 500; color: #0f172a;
                flex: 0 0 120px; min-width: 0;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .cor-category-count { 
                font-size: 13px; font-weight: 600; color: #64748b;
                margin-right: 16px; font-feature-settings: "tnum";
                flex: 0 0 auto;
            }
            .cor-progress-bar { 
                height: 6px; background: #f1f5f9; border-radius: 3px;
                overflow: hidden; flex: 0 0 140px;
            }
            .cor-progress-fill { 
                height: 100%; border-radius: 3px;
                transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .cor-financial-metrics {
                display: flex; flex-direction: column; gap: 16px;
            }
            .cor-metric-item {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 0; border-bottom: 1px solid #f1f5f9;
            }
            .cor-metric-item:last-child { border-bottom: none; }
            .cor-metric-label {
                font-size: 14px; font-weight: 500; color: #64748b;
            }
            .cor-metric-value {
                font-size: 16px; font-weight: 600; color: #0f172a;
                font-feature-settings: "tnum";
            }
            .cor-revenue-tabs {
                display: flex; gap: 4px;
            }
            .cor-revenue-tab {
                background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;
                padding: 6px 12px; font-size: 12px; font-weight: 500;
                border-radius: 4px; cursor: pointer; transition: all 0.2s ease;
            }
            .cor-revenue-tab:hover {
                background: #f1f5f9; border-color: #cbd5e1; color: #475569;
                transform: translateY(-1px);
            }
            .cor-revenue-tab[data-revenue-type="all"]:hover:not(.active) {
                background: #f3f4f6; border-color: #1f2937; color: #1f2937;
            }
            .cor-revenue-tab[data-revenue-type="cart"]:hover:not(.active) {
                background: #eef2ff; border-color: #4f46e5; color: #4f46e5;
            }
            .cor-revenue-tab[data-revenue-type="discount"]:hover:not(.active) {
                background: #fef2f2; border-color: #ef4444; color: #ef4444;
            }
            .cor-revenue-tab[data-revenue-type="tax"]:hover:not(.active) {
                background: #fffbeb; border-color: #f59e0b; color: #f59e0b;
            }
            .cor-revenue-tab[data-revenue-type="checkout"]:hover:not(.active) {
                background: #f0fdf4; border-color: #10b981; color: #10b981;
            }
            .cor-revenue-tab[data-revenue-type="future"]:hover:not(.active) {
                background: #f3f1ff; border-color: #8b5cf6; color: #8b5cf6;
            }
            .cor-revenue-tab[data-revenue-type="grand"]:hover:not(.active) {
                background: #f0f9ff; border-color: #0ea5e9; color: #0ea5e9;
            }
            .cor-financial-stats-container {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
                padding: 0; margin-bottom: 32px; overflow: hidden;
            }
            .cor-financial-stats-container .cor-stats-grid {
                background: transparent; border: none; border-radius: 0;
                margin-bottom: 0; gap: 0;
            }
            .cor-financial-stats-container .cor-stat-card {
                border: none; border-right: 1px solid #e2e8f0;
                border-radius: 0; background: #fff;
            }
            .cor-financial-stats-container .cor-stat-card:last-child {
                border-right: none;
            }
            .cor-stats-divider {
                height: 1px; background: #e2e8f0; margin: 0;
            }
            /* Custom legend styling for rounded squares */
            .cor-chart-canvas canvas {
                position: relative;
            }
            .cor-chart-container .chartjs-legend li span {
                border-radius: 3px !important;
            }
            /* Custom legend styling for hidden datasets - no strike-through, just opacity */
            .cor-chart-canvas .chartjs-legend ul li {
                transition: opacity 0.2s ease;
            }
            .cor-chart-canvas .chartjs-legend ul li[style*="text-decoration"] {
                text-decoration: none !important;
                opacity: 0.4;
                color: #9ca3af !important;
            }
            .cor-revenue-tab.active {
                color: #fff; font-weight: 600;
            }
            .cor-revenue-tab[data-revenue-type="all"].active {
                background: #1f2937; border-color: #1f2937;
            }
            .cor-revenue-tab[data-revenue-type="cart"].active {
                background: #4f46e5; border-color: #4f46e5;
            }
            .cor-revenue-tab[data-revenue-type="discount"].active {
                background: #ef4444; border-color: #ef4444;
            }
            .cor-revenue-tab[data-revenue-type="tax"].active {
                background: #f59e0b; border-color: #f59e0b;
            }
            .cor-revenue-tab[data-revenue-type="checkout"].active {
                background: #10b981; border-color: #10b981;
            }
            .cor-revenue-tab[data-revenue-type="future"].active {
                background: #8b5cf6; border-color: #8b5cf6;
            }
            .cor-revenue-tab[data-revenue-type="grand"].active {
                background: #0ea5e9; border-color: #0ea5e9;
            }
            /* Active tab hover effects - keep white text and add subtle effects */
            .cor-revenue-tab.active:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                color: #fff !important;
            }
            .cor-revenue-tab[data-revenue-type="all"].active:hover {
                background: #374151; border-color: #374151;
            }
            .cor-revenue-tab[data-revenue-type="cart"].active:hover {
                background: #6366f1; border-color: #6366f1;
            }
            .cor-revenue-tab[data-revenue-type="discount"].active:hover {
                background: #f87171; border-color: #f87171;
            }
            .cor-revenue-tab[data-revenue-type="tax"].active:hover {
                background: #fbbf24; border-color: #fbbf24;
            }
            .cor-revenue-tab[data-revenue-type="checkout"].active:hover {
                background: #34d399; border-color: #34d399;
            }
            .cor-revenue-tab[data-revenue-type="future"].active:hover {
                background: #a78bfa; border-color: #a78bfa;
            }
            .cor-revenue-tab[data-revenue-type="grand"].active:hover {
                background: #38bdf8; border-color: #38bdf8;
            }

            @media (max-width: 1024px) {
                .cor-charts-grid { grid-template-columns: 1fr; gap: 20px; }
                .cor-chart-canvas { height: 260px; }
            }
            @media (max-width: 768px) { 
                .cor-charts-grid { grid-template-columns: 1fr; gap: 16px; }
                .cor-analytics { margin: 24px 0; }
                .cor-stats-grid { 
                    grid-template-columns: repeat(2, 1fr); 
                    border-radius: 8px;
                }
                .cor-stat-card { padding: 20px 16px; }
                .cor-stat-card:first-child { border-radius: 8px 0 0 0; }
                .cor-stat-card:nth-child(2) { border-radius: 0 8px 0 0; }
                .cor-stat-card:nth-child(3) { border-radius: 0 0 0 8px; }
                .cor-stat-card:last-child { border-radius: 0 0 8px 8px; }
                .cor-stat-value { font-size: 24px; }
                .cor-chart-canvas { height: 240px; }
                .cor-chart-container { padding: 20px; }
                .cor-category-name { flex: 0 0 100px; }
                .cor-progress-bar { flex: 0 0 120px; }
            }
            @media (max-width: 480px) {
                .cor-stats-grid { grid-template-columns: 1fr; gap: 1px; }
                .cor-stat-card:first-child { border-radius: 8px 8px 0 0; }
                .cor-stat-card:nth-child(2) { border-radius: 0; }
                .cor-stat-card:nth-child(3) { border-radius: 0; }
                .cor-stat-card:last-child { border-radius: 0 0 8px 8px; }
                .cor-chart-canvas { height: 220px; }
                .cor-chart-container { padding: 16px; }
                .cor-category-name { flex: 0 0 80px; font-size: 13px; }
                .cor-progress-bar { flex: 0 0 100px; }
                .cor-category-count { font-size: 12px; margin-right: 12px; }
            }
            .cor-results { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
            .cor-table { width: 100%; border-collapse: collapse; }
            .cor-table th, .cor-table td { padding: 12px 8px; border: 1px solid #ddd; text-align: left; }
            .cor-table th { background: #f9f9f9; font-weight: 600; }
            .cor-table tr:nth-child(even) { background: #f9f9f9; }
            .cor-pagination { margin: 20px 0; text-align: center; }
            .cor-export { margin: 10px 0; }
            .cor-submit-row { 
                margin-top: 32px; padding-top: 24px; 
                border-top: 1px solid #e2e8f0; 
                display: flex; align-items: center; justify-content: space-between;
            }
            .cor-submit-row .button-primary {
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
                border: none; padding: 6px 24px;
                font-size: 14px; font-weight: 600; color: #ffffff;
                box-shadow: 0 4px 6px rgba(31, 41, 55, 0.25);
                transition: all 0.2s ease; cursor: pointer;
                text-transform: none; text-shadow: none;
                position: relative; overflow: hidden;
            }
            .cor-submit-row .button-primary::before {
                content: "";
                position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
                background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
                transition: left 0.5s ease;
            }
            .cor-submit-row .button-primary:hover {
                background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
                box-shadow: 0 8px 15px rgba(31, 41, 55, 0.35);
                transform: translateY(-2px);
            }
            .cor-submit-row .button-primary:hover::before {
                left: 100%;
            }
            .cor-submit-row .button-primary:active {
                background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
                box-shadow: 0 2px 4px rgba(31, 41, 55, 0.4);
                transform: translateY(0px);
                transition: all 0.1s ease;
            }
            .cor-submit-row .button-primary:focus {
                outline: none;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3), 0 4px 6px rgba(31, 41, 55, 0.25);
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            }
            .cor-submit-row .button-primary:focus:not(:active) {
                transform: translateY(-1px);
            }
            /* Clean Minimal Datepicker Styling */
            .ui-datepicker {
                background: #ffffff;
                border: 1px solid #e5e7eb !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
                padding: 16px !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                width: 252px !important;
            }
            .ui-datepicker .ui-datepicker-header {
                background: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 0 12px 0 !important;
                margin: 0 !important;
                position: relative;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            .ui-datepicker .ui-datepicker-title {
                color: #111827 !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                text-align: center;
                line-height: 1.5;
                margin: 0 !important;
                flex: 1 !important;
            }
            .ui-datepicker .ui-datepicker-prev,
            .ui-datepicker .ui-datepicker-next {
                background: none !important;
                border: none !important;
                border-radius: 4px !important;
                width: 24px !important;
                height: 24px !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: background-color 0.15s ease !important;
                top: 0 !important;
                position: relative !important;
            }
            .ui-datepicker .ui-datepicker-prev:hover,
            .ui-datepicker .ui-datepicker-next:hover {
                background: #f3f4f6 !important;
            }
            .ui-datepicker .ui-datepicker-prev span,
            .ui-datepicker .ui-datepicker-next span {
                background: none !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 14px !important;
                font-weight: 500 !important;
                color: #6b7280 !important;
                width: 24px !important;
                height: 24px !important;
                margin: 0 !important;
                text-indent: 0 !important;
                overflow: visible !important;
            }
            .ui-datepicker .ui-datepicker-prev:hover span,
            .ui-datepicker .ui-datepicker-next:hover span {
                color: #374151 !important;
            }
            .ui-datepicker .ui-datepicker-prev span::before {
                content: "←" !important;
                font-size: 14px !important;
            }
            .ui-datepicker .ui-datepicker-next span::before {
                content: "→" !important;
                font-size: 14px !important;
            }
            /* Hide the default prev/next text */
            .ui-datepicker .ui-datepicker-prev span,
            .ui-datepicker .ui-datepicker-next span {
                text-indent: -9999px !important;
                font-size: 0 !important;
                position: relative !important;
            }
            .ui-datepicker .ui-datepicker-prev span::before,
            .ui-datepicker .ui-datepicker-next span::before {
                text-indent: 0 !important;
                font-size: 14px !important;
                position: absolute !important;
                left: 50% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
            .ui-datepicker table {
                width: 100% !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                margin: 0 !important;
            }
            .ui-datepicker thead th {
                background: none !important;
                border: none !important;
                color: #6b7280 !important;
                font-size: 10px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                padding: 0 0 8px 0 !important;
                text-align: center !important;
                width: 36px !important;
                height: 20px !important;
            }
            .ui-datepicker tbody td {
                border: none !important;
                padding: 0 !important;
                text-align: center !important;
            }
            .ui-datepicker tbody td a {
                background: none !important;
                border: none !important;
                border-radius: 4px !important;
                color: #374151 !important;
                display: block !important;
                font-size: 13px !important;
                font-weight: 400 !important;
                padding: 0 !important;
                text-align: center !important;
                text-decoration: none !important;
                transition: all 0.15s ease !important;
                width: 32px !important;
                height: 32px !important;
                line-height: 32px !important;
                margin: 2px auto !important;
            }
            .ui-datepicker tbody td a:hover {
                background: #f3f4f6 !important;
                color: #111827 !important;
            }
            .ui-datepicker tbody td .ui-state-active {
                background: #4f46e5 !important;
                color: #ffffff !important;
                font-weight: 500 !important;
            }
            .ui-datepicker tbody td .ui-state-active:hover {
                background: #6366f1 !important;
            }
            .ui-datepicker .ui-datepicker-other-month a {
                color: #d1d5db !important;
                background: none !important;
            }
            .ui-datepicker .ui-datepicker-other-month a:hover {
                background: #f9fafb !important;
                color: #9ca3af !important;
            }
            .ui-datepicker .ui-datepicker-today a {
                background: #f3f4f6 !important;
                color: #374151 !important;
                font-weight: 500 !important;
            }
            .ui-datepicker .ui-datepicker-today a:hover {
                background: #e5e7eb !important;
            }
            /* Simple Date Range Selection - Start and End Only */
            .ui-datepicker td.ui-datepicker-range-start a {
                background: #4f46e5 !important;
                color: #ffffff !important;
                font-weight: 600 !important;
                border-radius: 4px !important;
            }
            .ui-datepicker td.ui-datepicker-range-end a {
                background: #4f46e5 !important;
                color: #ffffff !important;
                font-weight: 600 !important;
                border-radius: 4px !important;
            }
            .ui-datepicker td.ui-datepicker-range-start a:hover,
            .ui-datepicker td.ui-datepicker-range-end a:hover {
                background: #6366f1 !important;
            }
            /* Enhanced visual feedback */
            .ui-datepicker td.ui-datepicker-range-start a,
            .ui-datepicker td.ui-datepicker-range-middle a,
            .ui-datepicker td.ui-datepicker-range-end a {
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
                transition: all 0.2s ease !important;
            }
            .ui-datepicker td.ui-datepicker-range-start a:hover,
            .ui-datepicker td.ui-datepicker-range-middle a:hover,
            .ui-datepicker td.ui-datepicker-range-end a:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
                transform: translateY(-1px) !important;
            }
        ');
        
        // Add the JavaScript functionality
        wp_add_inline_script('woo-cor-admin-script', $this->get_inline_javascript());
    }
    
    public function ajax_get_products_by_category() {
        check_ajax_referer('cor_nonce', 'nonce');
        
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        );
        
        if (!empty($categories)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $categories,
                    'operator' => 'IN'
                )
            );
        }
        
        $products = get_posts($args);
        
        $output = '';
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $output .= '<div class="cor-dropdown-option" data-value="' . $product_id . '">' . esc_html($product->get_name()) . '</div>';
            }
        }
        
        wp_send_json_success($output);
    }
    
    /**
     * Show version notice (for testing auto-updater)
     */
    public function show_version_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'woocommerce_page_customer-orders-report') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>WooCommerce Customer Orders Report v1.0.2</strong> - Auto-updater test successful! Check for Updates button added.</p>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX handler to check for updates manually
     */
    public function ajax_check_updates() {
        check_ajax_referer('cor_check_updates', 'nonce');
        
        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have sufficient permissions to update plugins.'));
        }
        
        // Clear the update transient to force a fresh check
        delete_site_transient('update_plugins');
        
        // Trigger WordPress to check for plugin updates
        wp_update_plugins();
        
        // Get the current update transient
        $update_plugins = get_site_transient('update_plugins');
        $plugin_file = 'woo-customer-orders-report/woo-customer-orders-report.php';
        
        if (isset($update_plugins->response[$plugin_file])) {
            $update_info = $update_plugins->response[$plugin_file];
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Update available! Version %s is ready to install.', 'woo-customer-orders-report'),
                    $update_info->new_version
                ),
                'new_version' => $update_info->new_version,
                'has_update' => true
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('No updates available. You have the latest version!', 'woo-customer-orders-report'),
                'has_update' => false
            ));
        }
    }
    
    public function admin_page() {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $filters = $this->get_filters();
        
        echo '<div class="wrap">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
        echo '<h1 style="margin: 0;">' . __('Customer Orders Report', 'woo-customer-orders-report') . '</h1>';
        echo '<div>';
        echo '<button type="button" id="cor-check-updates" class="button button-secondary" style="margin-right: 10px;">';
        echo '<span class="dashicons dashicons-update" style="margin-right: 5px;"></span>';
        echo __('Check for Updates', 'woo-customer-orders-report');
        echo '</button>';
        echo '<span id="cor-update-status" style="margin-left: 10px; font-weight: bold;"></span>';
        echo '</div>';
        echo '</div>';
        
        // Filters Form
        $this->render_filters_form($filters);
        
        // Analytics and Results
        if (!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['categories']) || !empty($filters['products'])) {
            $this->render_analytics($filters);
            $this->render_results($filters, $current_page);
        }
        
        echo '</div>';
    }
    
    // Include all the other methods from the original class...
    // For brevity, I'm including the key methods. You can copy the rest from the original file.
    
    private function get_filters() {
        // Handle plus-separated values for categories and products
        $categories = array();
        if (!empty($_GET['categories'])) {
            $categories = array_map('intval', explode('+', sanitize_text_field($_GET['categories'])));
        }
        
        $products = array();
        if (!empty($_GET['products'])) {
            $products = array_map('intval', explode('+', sanitize_text_field($_GET['products'])));
        }
        
        return array(
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'categories' => $categories,
            'products' => $products
        );
    }
    
    /**
     * Render the filters form
     */
    private function render_filters_form($filters) {
        echo '<form method="get" action="" class="cor-filters">';
        echo '<input type="hidden" name="page" value="customer-orders-report">';
        
        // All filters in one row
        echo '<div class="cor-filter-row">';
        
        // Date Range
        echo '<div class="cor-filter-group date-range">';
        echo '<label>' . __('Date Range:', 'woo-customer-orders-report') . '</label>';
        echo '<div style="display: flex; gap: 10px; align-items: center;">';
        echo '<div>';
        echo '<input type="text" name="date_from" class="datepicker" value="' . esc_attr($filters['date_from']) . '" placeholder="' . __('From: YYYY-MM-DD', 'woo-customer-orders-report') . '">';
        echo '</div>';
        echo '<span style="color: #666;">' . __('to', 'woo-customer-orders-report') . '</span>';
        echo '<div>';
        echo '<input type="text" name="date_to" class="datepicker" value="' . esc_attr($filters['date_to']) . '" placeholder="' . __('To: YYYY-MM-DD', 'woo-customer-orders-report') . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Categories
        echo '<div class="cor-filter-group categories">';
        echo '<label>' . __('Product Categories:', 'woo-customer-orders-report') . '</label>';
        echo '<div class="cor-dropdown-container category-dropdown">';
        echo '<div class="cor-dropdown-trigger">';
        echo '<span>' . __('Select categories...', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        echo '<div class="cor-dropdown-menu">';
        echo '<div class="cor-search-box">';
        echo '<input type="text" placeholder="' . __('Search categories...', 'woo-customer-orders-report') . '">';
        echo '</div>';
        echo '<div class="cor-dropdown-options">';
        $this->render_category_dropdown_options($filters['categories']);
        echo '</div>';
        echo '</div>';
        echo '<div class="cor-selected-tags">';
        $this->render_selected_category_tags($filters['categories']);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Products
        echo '<div class="cor-filter-group products">';
        echo '<label>' . __('Products:', 'woo-customer-orders-report') . '</label>';
        echo '<div class="cor-dropdown-container product-dropdown">';
        echo '<div class="cor-dropdown-trigger">';
        echo '<span>' . __('Select products...', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        echo '<div class="cor-dropdown-menu">';
        echo '<div class="cor-search-box">';
        echo '<input type="text" placeholder="' . __('Search products...', 'woo-customer-orders-report') . '">';
        echo '</div>';
        echo '<div class="cor-dropdown-options">';
        // Products will be loaded via AJAX
        echo '</div>';
        echo '</div>';
        echo '<div class="cor-selected-tags">';
        $this->render_selected_product_tags($filters['products']);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // Close the single filter row
        
        echo '<div class="cor-submit-row">';
        echo '<input type="submit" class="button-primary" value="' . __('Filter Results', 'woo-customer-orders-report') . '">';
        echo '</div>';
        
        echo '</form>';
    }
    
    /**
     * Render category dropdown options
     */
    private function render_category_dropdown_options($selected_categories) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        foreach ($categories as $category) {
            $selected_class = in_array($category->term_id, $selected_categories) ? 'selected' : '';
            echo '<div class="cor-dropdown-option ' . $selected_class . '" data-value="' . $category->term_id . '">' . esc_html($category->name) . '</div>';
        }
    }
    
    /**
     * Render selected category tags
     */
    private function render_selected_category_tags($selected_categories) {
        if (empty($selected_categories)) return;
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'include' => $selected_categories,
            'hide_empty' => false
        ));
        
        foreach ($categories as $category) {
            echo '<div class="cor-tag" data-value="' . $category->term_id . '">';
            echo '<span>' . esc_html($category->name) . '</span>';
            echo '<button type="button" class="cor-tag-remove">×</button>';
            echo '</div>';
        }
        
        // Add single hidden field with plus-separated values
        if (!empty($selected_categories)) {
            echo '<input type="hidden" name="categories" value="' . implode('+', $selected_categories) . '">';
        }
    }
    
    /**
     * Render selected product tags
     */
    private function render_selected_product_tags($selected_products) {
        if (empty($selected_products)) return;
        
        foreach ($selected_products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                echo '<div class="cor-tag" data-value="' . $product_id . '">';
                echo '<span>' . esc_html($product->get_name()) . '</span>';
                echo '<button type="button" class="cor-tag-remove">×</button>';
                echo '</div>';
            }
        }
        
        // Add single hidden field with plus-separated values
        if (!empty($selected_products)) {
            echo '<input type="hidden" name="products" value="' . implode('+', $selected_products) . '">';
        }
    }
    
    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        if (!isset($_GET['export_csv']) || !is_admin()) {
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Access denied', 'woo-customer-orders-report'));
        }
        
        $filters = $this->get_filters();
        
        // Get all matching orders (no pagination for export)
        $orders_data = $this->get_all_orders_for_export($filters);
        
        if (empty($orders_data)) {
            wp_die(__('No data to export', 'woo-customer-orders-report'));
        }
        
        // Set headers for CSV download
        $filename = 'customer-orders-report-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array(
            __('Customer Name', 'woo-customer-orders-report'),
            __('Customer Email', 'woo-customer-orders-report'),
            __('Order Number', 'woo-customer-orders-report'),
            __('Purchase Date', 'woo-customer-orders-report'),
            __('Product Categories', 'woo-customer-orders-report'),
            __('Products', 'woo-customer-orders-report')
        ));
        
        // Add data rows
        foreach ($orders_data as $order_data) {
            fputcsv($output, array(
                $order_data['customer_name'],
                $order_data['customer_email'],
                '#' . $order_data['order_number'],
                $order_data['purchase_date'],
                $order_data['categories'],
                $order_data['products']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get all orders for export
     */
    private function get_all_orders_for_export($filters) {
        global $wpdb;
        
        // Build the WHERE clause
        $where_conditions = array("p.post_type = 'shop_order'", "p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')");
        $join_conditions = array();
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = $wpdb->prepare("p.post_date >= %s", $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $where_conditions[] = $wpdb->prepare("p.post_date <= %s", $filters['date_to'] . ' 23:59:59');
        }
        
        // Product/Category filters at database level
        if (!empty($filters['products']) || !empty($filters['categories'])) {
            $join_conditions[] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id";
            $join_conditions[] = "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id";
            
            $product_conditions = array();
            
            if (!empty($filters['products'])) {
                $product_ids = implode(',', array_map('intval', $filters['products']));
                $product_conditions[] = "(oim.meta_key = '_product_id' AND oim.meta_value IN ({$product_ids}))";
            }
            
            if (!empty($filters['categories'])) {
                // Get all products in selected categories
                $category_ids = implode(',', array_map('intval', $filters['categories']));
                $products_in_categories = $wpdb->get_col("
                    SELECT DISTINCT p.ID 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product' 
                    AND p.post_status = 'publish'
                    AND tt.taxonomy = 'product_cat'
                    AND tt.term_id IN ({$category_ids})
                ");
                
                if (!empty($products_in_categories)) {
                    $category_product_ids = implode(',', array_map('intval', $products_in_categories));
                    $product_conditions[] = "(oim.meta_key = '_product_id' AND oim.meta_value IN ({$category_product_ids}))";
                }
            }
            
            if (!empty($product_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $product_conditions) . ')';
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $join_clause = implode(' ', $join_conditions);
        
        // Get orders in smaller batches to prevent memory issues
        $batch_size = 100;
        $offset = 0;
        $all_orders = array();
        
        do {
            $orders_sql = "
                SELECT DISTINCT p.ID as order_id
                FROM {$wpdb->posts} p
                {$join_clause}
                {$where_clause}
                ORDER BY p.post_date DESC
                LIMIT %d OFFSET %d
            ";
            
            $orders = $wpdb->get_results($wpdb->prepare($orders_sql, $batch_size, $offset));
            
            foreach ($orders as $order_row) {
                $order = wc_get_order($order_row->order_id);
                if (!$order) continue;
                
                $all_orders[] = $this->format_order_data($order);
                
                // Memory management: flush if we have too many orders in memory
                if (count($all_orders) >= 1000) {
                    // In a real-world scenario, you might want to implement streaming CSV export
                    break;
                }
            }
            
            $offset += $batch_size;
            
        } while (count($orders) == $batch_size && count($all_orders) < 5000); // Limit to prevent memory issues
        
        return $all_orders;
    }
    
    /**
     * Format order data for display
     */
    private function format_order_data($order) {
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        
        $products = array();
        $categories = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $products[] = $product->get_name();
                
                $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
                $categories = array_merge($categories, $product_categories);
            }
        }
        
        return array(
            'order_id' => $order->get_id(),
            'customer_name' => trim($customer_name),
            'customer_email' => $customer_email,
            'order_number' => $order->get_order_number(),
            'purchase_date' => $order->get_date_created()->format('M j, Y \a\t g:i A'),
            'categories' => implode(', ', array_unique($categories)),
            'products' => implode(', ', $products)
        );
    }
    
    /**
     * Render analytics section with tabs
     */
    private function render_analytics($filters) {
        $analytics_data = $this->get_analytics_data($filters);
        
        echo '<div class="cor-analytics">';
        echo '<h2>' . __('Reports Overview', 'woo-customer-orders-report') . '</h2>';
        
        // Navigation Tabs
        echo '<div class="cor-reports-nav">';
        echo '<div class="cor-reports-tab active" data-tab="overview">' . __('Overview', 'woo-customer-orders-report') . '</div>';
        echo '<div class="cor-reports-tab" data-tab="financial">' . __('Revenue Report', 'woo-customer-orders-report') . '</div>';
        echo '<div class="cor-reports-tab" data-tab="orders">' . __('Orders Report', 'woo-customer-orders-report') . '</div>';
        echo '<div class="cor-reports-tab" data-tab="payment-plans">' . __('Payment Plans', 'woo-customer-orders-report') . '</div>';
        echo '</div>';
        
        // Overview Report Content
        echo '<div class="cor-report-content active" id="overview-content">';
        
        // Summary Stats
        echo '<div class="cor-stats-grid">';
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">' . number_format($analytics_data['total_orders']) . '</span>';
        echo '<span class="cor-stat-label">' . __('Total Orders', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Completed and processing orders', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['total_revenue'], 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Total Revenue', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Revenue from completed orders', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['avg_order_value'], 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Avg Order Value', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Mean revenue per order', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">' . number_format($analytics_data['unique_customers']) . '</span>';
        echo '<span class="cor-stat-label">' . __('Unique Customers', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Total number of unique customers', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Charts
        echo '<div class="cor-charts-grid">';
        
        // Orders by Date Chart
        echo '<div class="cor-chart-container">';
        echo '<div class="cor-chart-title">' . __('Orders Over Time', 'woo-customer-orders-report') . '</div>';
        echo '<div class="cor-chart-canvas">';
        echo '<canvas id="ordersChart"></canvas>';
        echo '</div>';
        echo '</div>';
        
        // Top Categories Chart
        echo '<div class="cor-chart-container">';
        echo '<div class="cor-chart-title">' . __('Top Product Categories', 'woo-customer-orders-report') . '</div>';
        echo '<div class="cor-categories-list" id="categoriesList">';
        // Categories will be populated via JavaScript
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Initialize Charts JavaScript
        $this->output_chart_scripts($analytics_data);
        
        echo '</div>'; // End overview-content
        
        // Orders Report Content
        echo '<div class="cor-report-content" id="orders-content">';
        $this->render_orders_report($analytics_data);
        echo '</div>';
        
        // Financial Report Content
        echo '<div class="cor-report-content" id="financial-content">';
        $this->render_financial_report($analytics_data);
        echo '</div>';
        
        // Payment Plans Report Content
        echo '<div class="cor-report-content" id="payment-plans-content">';
        $this->render_payment_plans_report($analytics_data);
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Get analytics data for reports
     */
    private function get_analytics_data($filters) {
        global $wpdb;
        
        // Build the same WHERE clause as in get_orders_data
        $where_conditions = array("p.post_type = 'shop_order'", "p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')");
        $join_conditions = array();
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = $wpdb->prepare("p.post_date >= %s", $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $where_conditions[] = $wpdb->prepare("p.post_date <= %s", $filters['date_to'] . ' 23:59:59');
        }
        
        // Product/Category filters
        if (!empty($filters['products']) || !empty($filters['categories'])) {
            $join_conditions[] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id";
            $join_conditions[] = "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id";
            
            $product_conditions = array();
            
            if (!empty($filters['products'])) {
                $product_ids = implode(',', array_map('intval', $filters['products']));
                $product_conditions[] = "(oim.meta_key = '_product_id' AND oim.meta_value IN ({$product_ids}))";
            }
            
            if (!empty($filters['categories'])) {
                $category_ids = implode(',', array_map('intval', $filters['categories']));
                $products_in_categories = $wpdb->get_col("
                    SELECT DISTINCT p.ID 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product' 
                    AND p.post_status = 'publish'
                    AND tt.taxonomy = 'product_cat'
                    AND tt.term_id IN ({$category_ids})
                ");
                
                if (!empty($products_in_categories)) {
                    $category_product_ids = implode(',', array_map('intval', $products_in_categories));
                    $product_conditions[] = "(oim.meta_key = '_product_id' AND oim.meta_value IN ({$category_product_ids}))";
                }
            }
            
            if (!empty($product_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $product_conditions) . ')';
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $join_clause = implode(' ', $join_conditions);
        
        // Get order IDs
        $orders_sql = "
            SELECT DISTINCT p.ID as order_id, p.post_date
            FROM {$wpdb->posts} p
            {$join_clause}
            {$where_clause}
            ORDER BY p.post_date ASC
        ";
        
        $orders = $wpdb->get_results($orders_sql);
        
        // Initialize analytics variables
        $total_orders = count($orders);
        $total_revenue = 0;
        $unique_customers = array();
        $orders_by_date = array();
        $category_counts = array();
        $products_sold = 0;
        $returning_customers = 0;
        $new_customers = 0;
        $completed_orders = 0;
        $customer_order_counts = array();
        $order_times_combined = array();
        $payment_plan_orders = 0;
        $pay_in_full_orders = 0;
        $payment_plans_created = 0;
        $payment_plans_total_value = 0;
        $two_month_plans = 0;
        $six_month_plans = 0;
        $monthly_recurring_revenue = 0;
        $payment_plans_by_date = array();
        $cart_total = 0;
        $discount_total = 0;
        $tax_total = 0;
        $checkout_total = 0;
        $future_total = 0;
        $grand_total = 0;
        $revenue_by_date = array();
        
        foreach ($orders as $order_row) {
            $order = wc_get_order($order_row->order_id);
            if (!$order) continue;
            
            // Skip orders not created via checkout
            if (get_post_meta($order_row->order_id, '_created_via', true) != 'checkout') {
                continue;
            }
            
            // Count completed orders
            $order_status = $order->get_status();
            if (in_array($order_status, array('completed', 'processing'))) {
                $completed_orders++;
            }
            
            // Revenue calculations
            $order_cart_total = $order->get_subtotal();
            $order_discount_total = $order->get_total_discount();
            $order_tax_total = $order->get_total_tax();
            $order_checkout_total = $order->get_total();
            $order_future_total = 0;
            
            // Add to totals
            $cart_total += $order_cart_total;
            $discount_total += $order_discount_total;
            $tax_total += $order_tax_total;
            $checkout_total += $order_checkout_total;
            $future_total += $order_future_total;
            $grand_total += ($order_checkout_total + $order_future_total);
            $total_revenue += $order_checkout_total;
            
            // Unique customers
            $customer_email = $order->get_billing_email();
            if (!empty($customer_email)) {
                $unique_customers[$customer_email] = true;
                if (!isset($customer_order_counts[$customer_email])) {
                    $customer_order_counts[$customer_email] = 0;
                }
                $customer_order_counts[$customer_email]++;
            }
            
            // Orders by date
            $date = date('M j', strtotime($order_row->post_date));
            if (!isset($orders_by_date[$date])) {
                $orders_by_date[$date] = 0;
            }
            $orders_by_date[$date]++;
            
            // Revenue by date
            if (!isset($revenue_by_date[$date])) {
                $revenue_by_date[$date] = array(
                    'cart' => 0, 'discount' => 0, 'tax' => 0,
                    'checkout' => 0, 'future' => 0, 'grand' => 0
                );
            }
            $revenue_by_date[$date]['cart'] += $order_cart_total;
            $revenue_by_date[$date]['discount'] += $order_discount_total;
            $revenue_by_date[$date]['tax'] += $order_tax_total;
            $revenue_by_date[$date]['checkout'] += $order_checkout_total;
            $revenue_by_date[$date]['future'] += $order_future_total;
            $revenue_by_date[$date]['grand'] += ($order_checkout_total + $order_future_total);
            
            // Category counts and products sold
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $products_sold += $item->get_quantity();
                    
                    $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'all'));
                    foreach ($categories as $category) {
                        $category_name = $category->name;
                        if (!isset($category_counts[$category_name])) {
                            $category_counts[$category_name] = 0;
                        }
                        $category_counts[$category_name]++;
                    }
                }
            }
        }
        
        // Calculate metrics
        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        
        foreach ($customer_order_counts as $email => $order_count) {
            if ($order_count == 1) {
                $new_customers++;
            } else {
                $returning_customers++;
            }
        }
        
        // Prepare chart data
        $orders_by_date_labels = array_keys($orders_by_date);
        $orders_by_date_data = array_values($orders_by_date);
        
        // Top categories
        arsort($category_counts);
        $top_categories = array_slice($category_counts, 0, 6, true);
        $top_categories_labels = array_keys($top_categories);
        $top_categories_data = array_values($top_categories);
        
        return array(
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'avg_order_value' => $avg_order_value,
            'unique_customers' => count($unique_customers),
            'orders_by_date' => array(
                'labels' => $orders_by_date_labels,
                'data' => $orders_by_date_data
            ),
            'revenue_by_date' => array(
                'labels' => array_keys($revenue_by_date),
                'cart' => array_column($revenue_by_date, 'cart'),
                'discount' => array_column($revenue_by_date, 'discount'),
                'tax' => array_column($revenue_by_date, 'tax'),
                'checkout' => array_column($revenue_by_date, 'checkout'),
                'future' => array_column($revenue_by_date, 'future'),
                'grand' => array_column($revenue_by_date, 'grand')
            ),
            'top_categories' => array(
                'labels' => $top_categories_labels,
                'data' => $top_categories_data
            ),
            'cart_total' => $cart_total,
            'discount_total' => $discount_total,
            'tax_total' => $tax_total,
            'checkout_total' => $checkout_total,
            'future_total' => $future_total,
            'grand_total' => $grand_total,
            'products_sold' => $products_sold,
            'returning_customers' => $returning_customers,
            'new_customers' => $new_customers,
            'completed_orders' => $completed_orders,
            'order_times' => array('labels' => array(), 'data' => array()),
            'payment_plan_orders' => $payment_plan_orders,
            'pay_in_full_orders' => $pay_in_full_orders,
            'payment_plans_created' => $payment_plans_created,
            'payment_plans_total_value' => $payment_plans_total_value,
            'two_month_plans' => $two_month_plans,
            'six_month_plans' => $six_month_plans,
            'monthly_recurring_revenue' => $monthly_recurring_revenue,
            'payment_plans_by_date' => array('labels' => array(), 'data' => array())
        );
    }
    
    /**
     * Render results table and pagination
     */
    private function render_results($filters, $current_page) {
        $orders_data = $this->get_orders_data($filters, $current_page);
        
        echo '<div class="cor-results">';
        
        // Export button
        if (!empty($orders_data['orders'])) {
            echo '<div class="cor-export">';
            $export_url = add_query_arg(array_merge($_GET, array('export_csv' => '1')));
            echo '<a href="' . esc_url($export_url) . '" class="button">' . __('Export to CSV', 'woo-customer-orders-report') . '</a>';
            echo '</div>';
        }
        
        // Results count
        echo '<p><strong>' . sprintf(__('Total Results: %d', 'woo-customer-orders-report'), $orders_data['total']) . '</strong></p>';
        
        if (!empty($orders_data['orders'])) {
            // Table
            echo '<table class="cor-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Customer Name', 'woo-customer-orders-report') . '</th>';
            echo '<th>' . __('Customer Email', 'woo-customer-orders-report') . '</th>';
            echo '<th>' . __('Order Number', 'woo-customer-orders-report') . '</th>';
            echo '<th>' . __('Purchase Date', 'woo-customer-orders-report') . '</th>';
            echo '<th>' . __('Product Categories', 'woo-customer-orders-report') . '</th>';
            echo '<th>' . __('Products', 'woo-customer-orders-report') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($orders_data['orders'] as $order_data) {
                $order_edit_url = admin_url('post.php?post=' . $order_data['order_id'] . '&action=edit');
                echo '<tr>';
                echo '<td>' . esc_html($order_data['customer_name']) . '</td>';
                echo '<td>' . esc_html($order_data['customer_email']) . '</td>';
                echo '<td><a href="' . esc_url($order_edit_url) . '" target="_blank">#' . esc_html($order_data['order_number']) . '</a></td>';
                echo '<td>' . esc_html($order_data['purchase_date']) . '</td>';
                echo '<td>' . esc_html($order_data['categories']) . '</td>';
                echo '<td>' . esc_html($order_data['products']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Pagination
            $this->render_pagination($orders_data['total'], $current_page, $_GET);
        } else {
            echo '<p>' . __('No orders found matching your criteria.', 'woo-customer-orders-report') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($total, $current_page, $query_args) {
        $total_pages = ceil($total / $this->per_page);
        
        if ($total_pages <= 1) return;
        
        echo '<div class="cor-pagination">';
        
        // Previous
        if ($current_page > 1) {
            $prev_args = array_merge($query_args, array('paged' => $current_page - 1));
            $prev_url = add_query_arg($prev_args);
            echo '<a href="' . esc_url($prev_url) . '" class="button">« ' . __('Previous', 'woo-customer-orders-report') . '</a> ';
        }
        
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                echo '<span class="button button-primary">' . $i . '</span> ';
            } else {
                $page_args = array_merge($query_args, array('paged' => $i));
                $page_url = add_query_arg($page_args);
                echo '<a href="' . esc_url($page_url) . '" class="button">' . $i . '</a> ';
            }
        }
        
        // Next
        if ($current_page < $total_pages) {
            $next_args = array_merge($query_args, array('paged' => $current_page + 1));
            $next_url = add_query_arg($next_args);
            echo '<a href="' . esc_url($next_url) . '" class="button">' . __('Next', 'woo-customer-orders-report') . ' »</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render orders report
     */
    private function render_orders_report($analytics_data) {
        // Orders Summary Stats
        echo '<div class="cor-financial-stats-container">';
        
        // First Row Stats
        echo '<div class="cor-stats-grid">';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">' . number_format($analytics_data['total_orders']) . '</span>';
        echo '<span class="cor-stat-label">' . __('Total Orders', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Checkout orders only, excludes subscription renewals', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">' . number_format($analytics_data['products_sold']) . '</span>';
        echo '<span class="cor-stat-label">' . __('Products Sold', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Total quantity of items purchased', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['avg_order_value'], 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Average Order Value', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Mean revenue per completed order', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        $completion_rate = $analytics_data['total_orders'] > 0 ? ($analytics_data['completed_orders'] / $analytics_data['total_orders']) * 100 : 0;
        echo '<span class="cor-stat-value">' . number_format($completion_rate, 1) . '%</span>';
        echo '<span class="cor-stat-label">' . __('Completion Rate', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Orders completed or processing', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render financial report
     */
    private function render_financial_report($analytics_data) {
        // Combined Revenue Summary Stats
        echo '<div class="cor-financial-stats-container">';
        
        // Primary Metrics
        echo '<div class="cor-stats-grid">';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['cart_total'], 2) . '</span>';
        echo '<span class="cor-stat-label cor-stat-cart">' . __('Cart Total', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Subtotal before discounts and taxes', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['checkout_total'], 2) . '</span>';
        echo '<span class="cor-stat-label cor-stat-checkout">' . __('Checkout Total', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Final amount paid at checkout', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['future_total'], 2) . '</span>';
        echo '<span class="cor-stat-label cor-stat-future">' . __('Future Total', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Projected subscription revenue', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['grand_total'], 2) . '</span>';
        echo '<span class="cor-stat-label cor-stat-grand">' . __('Grand Total', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Checkout plus future revenue', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render payment plans report
     */
    private function render_payment_plans_report($analytics_data) {
        // Payment Plans Summary Stats
        echo '<div class="cor-financial-stats-container">';
        
        // First Row Stats
        echo '<div class="cor-stats-grid">';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">' . number_format($analytics_data['payment_plans_created']) . '</span>';
        echo '<span class="cor-stat-label">' . __('Payment Plans Created', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('New payment plans during date range', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        echo '<span class="cor-stat-value">$' . number_format($analytics_data['payment_plans_total_value'], 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Total Plan Value', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Combined value of all payment plans', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        $monthly_recurring = $analytics_data['payment_plans_created'] > 0 ? $analytics_data['monthly_recurring_revenue'] : 0;
        echo '<span class="cor-stat-value">$' . number_format($monthly_recurring, 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Monthly Recurring', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Expected monthly revenue from plans', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '<div class="cor-stat-card">';
        $avg_plan_value = $analytics_data['payment_plans_created'] > 0 ? $analytics_data['payment_plans_total_value'] / $analytics_data['payment_plans_created'] : 0;
        echo '<span class="cor-stat-value">$' . number_format($avg_plan_value, 2) . '</span>';
        echo '<span class="cor-stat-label">' . __('Avg Plan Value', 'woo-customer-orders-report') . '</span>';
        echo '<span class="cor-stat-description">' . __('Average value per payment plan', 'woo-customer-orders-report') . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Calculate days in period
     */
    private function calculate_days_in_period() {
        $filters = $this->get_filters();
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $start = new DateTime($filters['date_from']);
            $end = new DateTime($filters['date_to']);
            return $start->diff($end)->days + 1;
        }
        return 1; // Default to 1 to avoid division by zero
    }
    
    /**
     * Output chart scripts
     */
    private function output_chart_scripts($analytics_data) {
        echo '<script>';
        echo 'jQuery(document).ready(function($) {';
        
        // Orders by Date Chart
        echo 'var ordersCtx = document.getElementById("ordersChart");';
        echo 'if (ordersCtx) {';
        echo 'new Chart(ordersCtx.getContext("2d"), {';
        echo 'type: "line",';
        echo 'data: {';
        echo 'labels: ' . json_encode($analytics_data['orders_by_date']['labels']) . ',';
        echo 'datasets: [{';
        echo 'label: "Orders",';
        echo 'data: ' . json_encode($analytics_data['orders_by_date']['data']) . ',';
        echo 'borderColor: "#4f46e5",';
        echo 'backgroundColor: "rgba(79, 70, 229, 0.1)",';
        echo 'borderWidth: 3,';
        echo 'tension: 0.4,';
        echo 'fill: true,';
        echo 'pointBackgroundColor: "#4f46e5",';
        echo 'pointBorderColor: "#fff",';
        echo 'pointBorderWidth: 2,';
        echo 'pointRadius: 5';
        echo '}]';
        echo '},';
        echo 'options: {';
        echo 'responsive: true,';
        echo 'maintainAspectRatio: false,';
        echo 'plugins: {';
        echo 'legend: { display: false },';
        echo 'tooltip: {';
        echo 'backgroundColor: "rgba(0, 0, 0, 0.8)",';
        echo 'titleColor: "#fff",';
        echo 'bodyColor: "#fff",';
        echo 'cornerRadius: 8,';
        echo 'displayColors: false';
        echo '}';
        echo '},';
        echo 'scales: {';
        echo 'x: { grid: { display: false }, ticks: { color: "#64748b" } },';
        echo 'y: { beginAtZero: true, grid: { color: "rgba(100, 116, 139, 0.1)" }, ticks: { color: "#64748b" } }';
        echo '}';
        echo '}';
        echo '});';
        echo '}';
        
        // Top Categories Progress Bars
        echo 'var categoriesData = {';
        echo 'labels: ' . json_encode($analytics_data['top_categories']['labels']) . ',';
        echo 'data: ' . json_encode($analytics_data['top_categories']['data']);
        echo '};';
        echo 'var colors = ["#4f46e5", "#06b6d4", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6"];';
        echo 'var categoriesList = document.getElementById("categoriesList");';
        echo 'if (categoriesList && categoriesData.labels.length > 0) {';
        echo 'var maxValue = Math.max(...categoriesData.data);';
        echo 'categoriesData.labels.forEach(function(label, index) {';
        echo 'var count = categoriesData.data[index];';
        echo 'var percentage = maxValue > 0 ? (count / maxValue) * 100 : 0;';
        echo 'var color = colors[index % colors.length];';
        echo 'var item = document.createElement("div");';
        echo 'item.className = "cor-category-item";';
        echo 'item.innerHTML = ';
        echo '"<div class=\"cor-category-info\">" +';
        echo '"<div class=\"cor-category-color\" style=\"background-color: " + color + "\"></div>" +';
        echo '"<div class=\"cor-category-name\">" + label + "</div>" +';
        echo '"</div>" +';
        echo '"<div class=\"cor-category-count\">" + count + "</div>" +';
        echo '"<div class=\"cor-progress-bar\">" +';
        echo '"<div class=\"cor-progress-fill\" style=\"background-color: " + color + "; width: " + percentage + "%\"></div>" +';
        echo '"</div>";';
        echo 'categoriesList.appendChild(item);';
        echo '});';
        echo '}';
        
        echo '});';
        echo '</script>';
    }
    
    /**
     * Get inline JavaScript for enhanced functionality
     */
    private function get_inline_javascript() {
        return '
            // Enhanced date range picker functionality and tab switching from original file
            jQuery(document).ready(function($) {
                // Date range picker initialization
                var dateRangeState = {
                    fromField: null,
                    toField: null,
                    startDate: null,
                    endDate: null,
                    isSelectingRange: false
                };
                
                function initDateRangePicker() {
                    $(".datepicker").each(function() {
                        var $field = $(this);
                        var fieldName = $field.attr("name");
                        
                        $field.datepicker({
                            dateFormat: "yy-mm-dd",
                            showOtherMonths: true,
                            selectOtherMonths: true,
                            changeMonth: true,
                            changeYear: true,
                            beforeShowDay: function(date) {
                                return highlightDateRange(date, fieldName);
                            },
                            onSelect: function(dateText, inst) {
                                handleDateSelection(dateText, fieldName, $field);
                            }
                        });
                        
                        if (fieldName === "date_from") {
                            dateRangeState.fromField = $field;
                        } else if (fieldName === "date_to") {
                            dateRangeState.toField = $field;
                        }
                    });
                    
                    updateDateRangeFromFields();
                }
                
                function handleDateSelection(dateText, fieldName, $field) {
                    var selectedDate = $.datepicker.parseDate("yy-mm-dd", dateText);
                    
                    if (fieldName === "date_from") {
                        dateRangeState.startDate = selectedDate;
                        if (dateRangeState.endDate && selectedDate > dateRangeState.endDate) {
                            dateRangeState.endDate = null;
                            dateRangeState.toField.val("");
                        }
                    } else if (fieldName === "date_to") {
                        dateRangeState.endDate = selectedDate;
                        if (dateRangeState.startDate && selectedDate < dateRangeState.startDate) {
                            dateRangeState.startDate = selectedDate;
                            dateRangeState.fromField.val(dateText);
                            dateRangeState.endDate = null;
                            $field.val("");
                            return;
                        }
                    }
                    
                    setTimeout(function() {
                        $(".datepicker").datepicker("refresh");
                    }, 10);
                }
                
                function updateDateRangeFromFields() {
                    var fromValue = dateRangeState.fromField ? dateRangeState.fromField.val() : "";
                    var toValue = dateRangeState.toField ? dateRangeState.toField.val() : "";
                    
                    if (fromValue) {
                        dateRangeState.startDate = $.datepicker.parseDate("yy-mm-dd", fromValue);
                    }
                    if (toValue) {
                        dateRangeState.endDate = $.datepicker.parseDate("yy-mm-dd", toValue);
                    }
                }
                
                function highlightDateRange(date, fieldName) {
                    var cssClasses = "";
                    var selectable = true;
                    
                    if (dateRangeState.startDate && dateRangeState.endDate) {
                        var dateTime = date.getTime();
                        var startTime = dateRangeState.startDate.getTime();
                        var endTime = dateRangeState.endDate.getTime();
                        
                        if (dateTime === startTime) {
                            cssClasses = "ui-datepicker-range-start";
                        } else if (dateTime === endTime) {
                            cssClasses = "ui-datepicker-range-end";
                        }
                    } else if (dateRangeState.startDate && !dateRangeState.endDate) {
                        if (date.getTime() === dateRangeState.startDate.getTime()) {
                            cssClasses = "ui-datepicker-range-start";
                        }
                    }
                    
                    return [selectable, cssClasses];
                }
                
                // Initialize the enhanced date range picker
                initDateRangePicker();
                
                // Tab switching functionality
                var revenueChartInstance = null;
                var globalRevenueChartInstance = null;
                
                // Initialize tab switching
                function initTabSwitching() {
                    var tabs = document.querySelectorAll(".cor-reports-tab");
                    if (tabs.length === 0) {
                        setTimeout(initTabSwitching, 100);
                        return;
                    }
                    
                    tabs.forEach(function(tab) {
                        tab.addEventListener("click", function(e) {
                            e.preventDefault();
                            var targetTab = this.getAttribute("data-tab");
                            
                            // Remove active class from all tabs and content
                            document.querySelectorAll(".cor-reports-tab").forEach(function(t) {
                                t.classList.remove("active");
                            });
                            document.querySelectorAll(".cor-report-content").forEach(function(content) {
                                content.classList.remove("active");
                            });
                            
                            // Add active class to clicked tab and corresponding content
                            this.classList.add("active");
                            var targetContent = document.getElementById(targetTab + "-content");
                            if (targetContent) {
                                targetContent.classList.add("active");
                            }
                            
                            // Initialize revenue chart when financial tab is clicked
                            if (targetTab === "financial" && !revenueChartInstance) {
                                setTimeout(function() {
                                    initRevenueChart();
                                }, 150);
                            }
                        });
                    });
                }
                
                function initRevenueChart() {
                    if (typeof Chart === "undefined" || !window.revenueChartData) {
                        setTimeout(function() { initRevenueChart(); }, 100);
                        return;
                    }
                    
                    var canvas = document.getElementById("revenueChart");
                    if (canvas && !revenueChartInstance) {
                        var ctx = canvas.getContext("2d");
                        
                        // Define colors for each line
                        var colors = {
                            cart: "#4f46e5",
                            discount: "#ef4444", 
                            tax: "#f59e0b",
                            checkout: "#10b981",
                            future: "#8b5cf6",
                            grand: "#0ea5e9"
                        };
                        
                        // Create datasets for multi-line chart
                        var datasets = [
                            {
                                label: "Cart Total",
                                data: window.revenueChartData.cart,
                                borderColor: colors.cart,
                                backgroundColor: colors.cart + "20",
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.cart,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                revenueType: "cart"
                            },
                            {
                                label: "Discount Total",
                                data: window.revenueChartData.discount,
                                borderColor: colors.discount,
                                backgroundColor: colors.discount + "20",
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.discount,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                revenueType: "discount"
                            },
                            {
                                label: "Tax Total",
                                data: window.revenueChartData.tax,
                                borderColor: colors.tax,
                                backgroundColor: colors.tax + "20",
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.tax,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                revenueType: "tax"
                            },
                            {
                                label: "Checkout Total",
                                data: window.revenueChartData.checkout,
                                borderColor: colors.checkout,
                                backgroundColor: colors.checkout + "20",
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.checkout,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                revenueType: "checkout"
                            },
                            {
                                label: "Future Total",
                                data: window.revenueChartData.future,
                                borderColor: colors.future,
                                backgroundColor: colors.future + "20",
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.future,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                revenueType: "future"
                            },
                            {
                                label: "Grand Total",
                                data: window.revenueChartData.grand,
                                borderColor: colors.grand,
                                backgroundColor: colors.grand + "20",
                                borderWidth: 3,
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: colors.grand,
                                pointBorderColor: "#fff",
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                revenueType: "grand"
                            }
                        ];
                        
                        globalRevenueChartInstance = revenueChartInstance = new Chart(ctx, {
                            type: "line",
                            data: {
                                labels: window.revenueChartData.labels,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { 
                                        display: true,
                                        position: "bottom",
                                        labels: {
                                            usePointStyle: true,
                                            pointStyle: "rect",
                                            pointStyleWidth: 12,
                                            pointStyleHeight: 12,
                                            padding: 20,
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: "rgba(0, 0, 0, 0.8)",
                                        titleColor: "#fff",
                                        bodyColor: "#fff",
                                        cornerRadius: 8,
                                        displayColors: true,
                                        callbacks: {
                                            label: function(context) {
                                                return context.dataset.label + ": $" + context.parsed.y.toLocaleString();
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: { 
                                        grid: { display: false }, 
                                        ticks: { color: "#64748b" } 
                                    },
                                    y: { 
                                        beginAtZero: true, 
                                        grid: { color: "rgba(100, 116, 139, 0.1)" }, 
                                        ticks: { 
                                            color: "#64748b",
                                            callback: function(value) { 
                                                return "$" + value.toLocaleString(); 
                                            }
                                        }
                                    }
                                },
                                interaction: {
                                    intersect: false,
                                    mode: "index"
                                }
                            }
                        });
                        
                        // Initialize revenue tab functionality after chart is created
                        initRevenueTabs();
                    }
                }
                
                function initRevenueTabs() {
                    var revenueTabs = document.querySelectorAll(".cor-revenue-tab");
                    if (revenueTabs.length === 0 || !globalRevenueChartInstance) {
                        setTimeout(initRevenueTabs, 100);
                        return;
                    }
                    
                    revenueTabs.forEach(function(tab) {
                        tab.addEventListener("click", function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var revenueType = this.getAttribute("data-revenue-type");
                            
                            if (revenueType === "all") {
                                // Handle "All" tab - toggle all datasets
                                var isCurrentlyActive = this.classList.contains("active");
                                
                                // Remove active from all tabs first
                                revenueTabs.forEach(function(t) {
                                    t.classList.remove("active");
                                });
                                
                                if (!isCurrentlyActive) {
                                    // Show all datasets
                                    this.classList.add("active");
                                    globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                                        globalRevenueChartInstance.setDatasetVisibility(index, true);
                                    });
                                } else {
                                    // Hide all datasets
                                    globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                                        globalRevenueChartInstance.setDatasetVisibility(index, false);
                                    });
                                }
                                
                                globalRevenueChartInstance.update("none");
                                
                            } else {
                                // Handle individual dataset tabs
                                var allTab = document.querySelector(".cor-revenue-tab[data-revenue-type=\"all\"]");
                                if (allTab && allTab.classList.contains("active")) {
                                    allTab.classList.remove("active");
                                    // When switching from "all" to individual, hide all first
                                    globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                                        globalRevenueChartInstance.setDatasetVisibility(index, false);
                                    });
                                }
                                
                                // Toggle this specific tab
                                var isActive = this.classList.contains("active");
                                this.classList.toggle("active");
                                
                                // Find and toggle the corresponding dataset
                                globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                                    if (dataset.revenueType === revenueType) {
                                        globalRevenueChartInstance.setDatasetVisibility(index, !isActive);
                                    }
                                });
                                
                                // Check if no individual tabs are active, if so activate "All"
                                var individualTabs = document.querySelectorAll(".cor-revenue-tab:not([data-revenue-type=\"all\"])");
                                var hasActiveIndividual = false;
                                individualTabs.forEach(function(tab) {
                                    if (tab.classList.contains("active")) {
                                        hasActiveIndividual = true;
                                    }
                                });
                                
                                if (!hasActiveIndividual && allTab) {
                                    allTab.classList.add("active");
                                    // Show all datasets
                                    globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                                        globalRevenueChartInstance.setDatasetVisibility(index, true);
                                    });
                                }
                                
                                globalRevenueChartInstance.update("none");
                            }
                        });
                    });
                    
                    // Set initial state - show all datasets
                    var allTab = document.querySelector(".cor-revenue-tab[data-revenue-type=\"all\"]");
                    if (allTab && allTab.classList.contains("active")) {
                        globalRevenueChartInstance.data.datasets.forEach(function(dataset, index) {
                            globalRevenueChartInstance.setDatasetVisibility(index, true);
                        });
                        globalRevenueChartInstance.update("none");
                    }
                }
                
                // Initialize tab switching
                initTabSwitching();
                
                // Check for updates functionality
                $("#cor-check-updates").on("click", function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var $status = $("#cor-update-status");
                    
                    // Show loading state
                    $button.prop("disabled", true);
                    $button.find(".dashicons").addClass("dashicons-update-alt").removeClass("dashicons-update");
                    $status.html("<span style=\"color: #0073aa;\">Checking for updates...</span>");
                    
                    // Make AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "cor_check_updates",
                            nonce: "' . wp_create_nonce('cor_check_updates') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.has_update) {
                                    $status.html("<span style=\"color: #d63638;\">✓ " + response.data.message + "</span>");
                                    // Show link to updates page
                                    setTimeout(function() {
                                        $status.append(" <a href=\"" + ajaxurl.replace("admin-ajax.php", "update-core.php") + "\" style=\"text-decoration: none;\">→ Go to Updates</a>");
                                    }, 1000);
                                } else {
                                    $status.html("<span style=\"color: #00a32a;\">✓ " + response.data.message + "</span>");
                                }
                            } else {
                                $status.html("<span style=\"color: #d63638;\">Error checking for updates</span>");
                            }
                        },
                        error: function() {
                            $status.html("<span style=\"color: #d63638;\">Error checking for updates</span>");
                        },
                        complete: function() {
                            // Reset button state
                            $button.prop("disabled", false);
                            $button.find(".dashicons").removeClass("dashicons-update-alt").addClass("dashicons-update");
                            
                            // Clear status after 10 seconds
                            setTimeout(function() {
                                $status.html("");
                            }, 10000);
                        }
                    });
                });
            });
        ';
    }
} 