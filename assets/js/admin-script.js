/**
 * WooCommerce Customer Orders Report - Admin JavaScript
 * 
 * @package WooCustomerOrdersReport
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize date pickers with enhanced functionality
        initDateRangePicker();
        
        // Initialize dropdown functionality
        initDropdowns();
        
        // Initialize chart interactions and tab switching
        initChartInteractions();
        
        // Initialize export functionality
        initExportFunctionality();
        
        // Initialize product options
        updateProductOptions();
        
        // Restore selected tags from form values after page load
        restoreSelectedTags();
        
    });
    
    // Enhanced date range picker functionality
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
                },
                onChangeMonthYear: function(year, month, inst) {
                    // Refresh highlighting when month/year changes
                    setTimeout(function() {
                        $field.datepicker("refresh");
                    }, 50);
                }
            });
            
            // Store field references
            if (fieldName === "date_from") {
                dateRangeState.fromField = $field;
            } else if (fieldName === "date_to") {
                dateRangeState.toField = $field;
            }
        });
        
        // Initialize with existing values
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
        
        // Refresh both datepickers to update highlighting
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

    /**
     * Initialize dropdown functionality
     */
    function initDropdowns() {
        // Dropdown toggle
        $(document).on('click', '.cor-dropdown-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $menu = $(this).siblings('.cor-dropdown-menu');
            var $trigger = $(this);
            
            // Close other dropdowns
            $('.cor-dropdown-menu').not($menu).removeClass('active');
            $('.cor-dropdown-trigger').not($trigger).removeClass('active');
            
            // Toggle current dropdown
            $menu.toggleClass('active');
            $trigger.toggleClass('active');
            
            // Focus search input if opened
            if ($menu.hasClass('active')) {
                $menu.find('.cor-search-box input').focus();
            }
        });
        
        // Close dropdowns when clicking outside
        $(document).on('click', function() {
            $('.cor-dropdown-menu').removeClass('active');
            $('.cor-dropdown-trigger').removeClass('active');
        });
        
        // Prevent dropdown from closing when clicking inside
        $(document).on('click', '.cor-dropdown-menu', function(e) {
            e.stopPropagation();
        });
        
        // Search functionality
        $(document).on('input', '.cor-search-box input', function() {
            var query = $(this).val().toLowerCase();
            var $options = $(this).closest('.cor-dropdown-menu').find('.cor-dropdown-option');
            
            $options.each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) > -1);
            });
        });
        
        // Option selection
        $(document).on('click', '.cor-dropdown-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $option = $(this);
            var value = $option.data('value');
            var text = $option.text();
            var $container = $option.closest('.cor-dropdown-container');
            var $tags = $container.find('.cor-selected-tags');
            
            if (!$option.hasClass('selected')) {
                // Add selection
                $option.addClass('selected');
                addTag($tags, value, text, $container.hasClass('category-dropdown'));
                
                // Update products if category was selected
                if ($container.hasClass('category-dropdown')) {
                    updateProductOptions();
                }
            }
        });
        
        // Remove tag
        $(document).on('click', '.cor-tag-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $tag = $(this).closest('.cor-tag');
            var value = $tag.data('value');
            var $container = $tag.closest('.cor-dropdown-container');
            var isCategory = $container.hasClass('category-dropdown');
            
            // Remove tag
            $tag.remove();
            
            // Unselect option
            $container.find('.cor-dropdown-option[data-value="' + value + '"]').removeClass('selected');
            
            // Update hidden field
            updateHiddenField($container.find('.cor-selected-tags'), isCategory ? 'categories' : 'products');
            
            // Update products if category was removed
            if (isCategory) {
                updateProductOptions();
            }
        });
    }
    
    /**
     * Add a tag to the selected tags container
     */
    function addTag($container, value, text, isCategory) {
        var fieldName = isCategory ? 'categories' : 'products';
        var $tag = $('<div class="cor-tag" data-value="' + value + '">' +
            '<span>' + text + '</span>' +
            '<button type="button" class="cor-tag-remove">×</button>' +
            '</div>');
        
        $container.append($tag);
        updateHiddenField($container, fieldName);
    }
    
    /**
     * Update hidden field with selected values
     */
    function updateHiddenField($container, fieldName) {
        var $dropdown = $container.closest('.cor-dropdown-container');
        
        // Remove existing hidden field
        $dropdown.find('input[name="' + fieldName + '"]').remove();
        
        // Collect all selected values
        var values = [];
        $container.find('.cor-tag').each(function() {
            values.push($(this).data('value'));
        });
        
        // Create new hidden field with plus-separated values
        if (values.length > 0) {
            var $hiddenField = $('<input type="hidden" name="' + fieldName + '" value="' + values.join('+') + '">');
            $dropdown.append($hiddenField);
        }
    }
    
    /**
     * Update product options based on selected categories
     */
    function updateProductOptions() {
        var selectedCategories = [];
        $('.category-dropdown .cor-tag').each(function() {
            selectedCategories.push($(this).data('value'));
        });
        
        if (typeof woo_cor_ajax === 'undefined') {
            console.error('AJAX configuration not found');
            return;
        }
        
        $.ajax({
            url: woo_cor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_products_by_category',
                categories: selectedCategories,
                nonce: woo_cor_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Store currently selected product values
                    var selectedProducts = [];
                    var productValues = $('.product-dropdown input[name="products"]').val();
                    if (productValues) {
                        selectedProducts = productValues.split('+');
                    }
                    
                    $('.product-dropdown .cor-dropdown-options').html(response.data);
                    
                    // Restore selected state for products that are still available
                    selectedProducts.forEach(function(productId) {
                        var $option = $('.product-dropdown .cor-dropdown-option[data-value="' + productId + '"]');
                        if ($option.length > 0) {
                            $option.addClass('selected');
                        } else {
                            // Remove tag if product is no longer available in selected categories
                            $('.product-dropdown .cor-selected-tags .cor-tag[data-value="' + productId + '"]').remove();
                        }
                    });
                    
                    // Update hidden field with only available products
                    updateHiddenField($('.product-dropdown .cor-selected-tags'), 'products');
                }
            },
            error: function() {
                console.error('Failed to update product options');
            }
        });
    }
    
    function restoreSelectedTags() {
        // Restore category tags
        var categoryValues = $(".category-dropdown input[name=categories]").val();
        if (categoryValues) {
            categoryValues.split("+").forEach(function(value) {
                var $option = $(".category-dropdown .cor-dropdown-option[data-value=\"" + value + "\"]");
                if ($option.length > 0) {
                    $option.addClass("selected");
                }
            });
        }
        
        // Restore product tags
        var productValues = $(".product-dropdown input[name=products]").val();
        if (productValues) {
            productValues.split("+").forEach(function(value) {
                var $option = $(".product-dropdown .cor-dropdown-option[data-value=\"" + value + "\"]");
                if ($option.length > 0) {
                    $option.addClass("selected");
                }
            });
        }
    }

    /**
     * Initialize chart interactions and tab switching
     */
    function initChartInteractions() {
        var revenueChartInstance = null;
        var globalRevenueChartInstance = null;
        
        // Initialize tab switching
        function initTabSwitching() {
            var tabs = document.querySelectorAll(".cor-reports-tab");
            if (tabs.length === 0) {
                // Retry after a short delay if tabs not found
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
    }
    
    /**
     * Initialize export functionality
     */
    function initExportFunctionality() {
        // Add export progress indicator or other export-related functionality
        $(document).on('click', '.cor-export .button', function() {
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Exporting...').prop('disabled', true);
            
            // Re-enable button after a delay (in case the download doesn't trigger properly)
            setTimeout(function() {
                $button.text(originalText).prop('disabled', false);
            }, 3000);
        });
    }
    
})(jQuery); 