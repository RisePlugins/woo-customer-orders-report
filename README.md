# WooCommerce Customer Orders Report

A comprehensive customer orders reporting tool for WooCommerce with advanced filtering, analytics, and export capabilities.

## Description

This plugin provides detailed analytics and reporting for WooCommerce orders with advanced filtering options. It includes multiple report types, interactive charts, and CSV export functionality.

## Features

- **Advanced Filtering**: Filter orders by date range, product categories, and specific products
- **Multiple Report Types**: 
  - Overview Report with key metrics and charts
  - Revenue Report with financial breakdowns
  - Orders Report with customer analytics
  - Payment Plans Report for subscription-based orders
- **Interactive Charts**: Visual representations of data using Chart.js
- **CSV Export**: Export filtered results to CSV format
- **Responsive Design**: Works on desktop and mobile devices
- **Modern UI**: Clean, professional interface with gradient styling

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Upload Plugin Files

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce > Customer Orders Report

### Method 2: WordPress Admin Upload

1. Go to Plugins > Add New > Upload Plugin
2. Choose the plugin zip file and upload
3. Activate the plugin
4. Navigate to WooCommerce > Customer Orders Report

## Usage

1. **Access the Report**: Go to WooCommerce > Customer Orders Report in your WordPress admin
2. **Set Filters**: Use the filter section to narrow down your results:
   - Date Range: Select start and end dates
   - Product Categories: Choose specific categories
   - Products: Select individual products
3. **View Reports**: Switch between different report types using the tabs
4. **Export Data**: Click "Export to CSV" to download your filtered results

## Report Types

### Overview Report
- Total orders, revenue, and customer metrics
- Orders over time chart
- Top product categories breakdown

### Revenue Report
- Detailed financial metrics including cart totals, discounts, taxes
- Interactive revenue chart with toggleable data sets
- Revenue per customer calculations

### Orders Report
- Order completion rates and customer retention
- Peak ordering times analysis
- Payment plan vs. pay-in-full distribution

### Payment Plans Report
- Payment plan creation and adoption rates
- Plan duration analysis (2-month vs 6-month plans)
- Monthly recurring revenue tracking

## File Structure

```
woo-customer-orders-report-plugin.php    # Main plugin file
includes/
  └── class-woo-customer-orders-report.php    # Main report class
assets/
  ├── css/
  │   └── admin-style.css                      # Admin styles (optional)
  └── js/
      └── admin-script.js                      # Admin scripts (optional)
README.md                                      # This file
```

## Hooks and Filters

### Action Hooks
- `woo_cor_before_filters` - Fired before filter form rendering
- `woo_cor_after_analytics` - Fired after analytics section

### Filters
- `woo_cor_per_page` - Modify pagination limit (default: 50)
- `woo_cor_date_format` - Modify date display format
- `woo_cor_export_filename` - Customize CSV export filename

## Development

### Adding Custom Filters

```php
// Modify pagination limit
add_filter('woo_cor_per_page', function($per_page) {
    return 100; // Show 100 orders per page
});

// Customize export filename
add_filter('woo_cor_export_filename', function($filename) {
    return 'my-custom-orders-report-' . date('Y-m-d') . '.csv';
});
```

### Styling Customization

The plugin uses CSS custom properties for easy theming:

```css
:root {
    --cor-primary-color: #4f46e5;
    --cor-secondary-color: #10b981;
    --cor-background-color: #ffffff;
    --cor-border-color: #e2e8f0;
}
```

## Troubleshooting

### Common Issues

1. **Plugin doesn't activate**: Ensure WooCommerce is installed and activated first
2. **No data showing**: Check if you have orders in the selected date range
3. **Charts not loading**: Ensure your site can load external JavaScript from CDN
4. **Export not working**: Check server memory limits and file permissions

### Debug Mode

Add this to your wp-config.php to enable debug logging:

```php
define('WOO_COR_DEBUG', true);
```

## Changelog

### 1.0.0
- Initial release
- Basic reporting functionality
- Multi-tab interface
- CSV export capability
- Advanced filtering options

## Support

For support, please create an issue on the GitHub repository or contact the plugin author.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Chart.js for data visualization
- jQuery UI for date picker functionality
- WooCommerce for e-commerce integration 