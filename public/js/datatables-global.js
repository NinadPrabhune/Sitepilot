/**
 * Global DataTables Configuration
 * Applies default settings for all DataTables in the application
 */

$(document).ready(function () {
    
    
    // Apply global defaults (without overriding table-specific selectors)
    $.extend(true, $.fn.dataTable.defaults, {
        // Enable row selection globally (but let tables define their own selector)
        select: {
            style: 'multi'
        },
        // Enable buttons globally
        dom: 'Bfrtip',
        // Language settings can be overridden per table
        language: {
            searchPlaceholder: "Search...",
            search: ""
        }
    });
    
    
    // Check if Select extension is loaded
    if ($.fn.DataTable.select) {
    } else {
    }
    
    // Check if Buttons extension is loaded
    if ($.fn.DataTable.Buttons) {
    } else {
    }

});
