/**
 * Supplier DataTable - Export Handling
 * Selection is handled by Yajra DataTables initComplete callback
 */


$(document).ready(function () {
    
    
    // Export Selected button click handler
    $(document).on('click', '#exportSelected', function (e) {
        
        // Get selected checkbox values
        var selectedIds = [];
        $('#supplier-table tbody .supplier-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        
        if (selectedIds.length === 0) {
            alert("Please select at least one record to export.");
            e.preventDefault();
            return false;
        }
        
        var selectedIdsStr = selectedIds.join(',');
        
        // Build export URL using the global variable set by Blade
        var exportUrl = window.supplierExportUrl + '?ids=' + selectedIdsStr;
        
        // Use fetch to handle the export properly
        fetch(exportUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.blob();
        })
        .then(blob => {
            // Create a download link for the blob
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'suppliers_export_' + new Date().toISOString().slice(0,10) + '.xlsx';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            if (error.error) {
                alert(error.error);
            } else {
                alert("An error occurred during export. Please try again.");
            }
        });
        
        e.preventDefault();
        return false;
    });

});

