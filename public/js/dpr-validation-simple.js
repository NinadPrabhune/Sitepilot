/**
 * Simple DPR Validation - Robust solution for validateReadings error
 * This file provides a simple, always-available validation function
 */

// Global validation function that's always available
window.validateReadings = function() {
    try {
        const startReading = parseFloat($('#machine_start_reading').val()) || 0;
        const endReading = parseFloat($('#machine_end_reading').val()) || 0;
        const idleHours = parseFloat($('#machine_idle_reading').val()) || 0;
        
        // Clear previous errors
        $('#machine_start_reading, #machine_end_reading, #machine_idle_reading').removeClass('is-invalid');
        $('#startReadingError, #endReadingError, #idleHoursError').text('');
        
        let isValid = true;
        const errors = [];
        
        // Validation 1: End reading cannot be less than start reading
        if (endReading < startReading) {
            errors.push('End reading cannot be less than start reading');
            $('#machine_end_reading').addClass('is-invalid');
            $('#endReadingError').text('End reading must be ≥ start reading');
            isValid = false;
        }
        
        // Validation 2: Idle hours validation
        const totalProgress = endReading - startReading;
        const workingHours = totalProgress - idleHours;
        
        // 2a: Idle hours cannot be negative
        if (idleHours < 0) {
            errors.push('Idle hours cannot be negative');
            $('#machine_idle_reading').addClass('is-invalid');
            $('#idleHoursError').text('Idle hours cannot be negative');
            isValid = false;
        }
        
        // 2b: Idle hours should not exceed working hours
        if (totalProgress > 0 && idleHours > totalProgress) {
            errors.push('Idle hours cannot exceed working hours');
            $('#machine_idle_reading').addClass('is-invalid');
            $('#idleHoursError').text('Idle hours cannot exceed working hours');
            isValid = false;
        }
        
        // 2c: Idle hours should not exceed 24 hours
        if (idleHours > 24) {
            errors.push('Idle hours cannot exceed 24 hours');
            $('#machine_idle_reading').addClass('is-invalid');
            $('#idleHoursError').text('Idle hours cannot exceed 24 hours');
            isValid = false;
        }
        
        // 2d: Working hours cannot exceed 24
        if (workingHours > 24) {
            errors.push('Working hours cannot exceed 24 hours per day');
            $('#machine_idle_reading').addClass('is-invalid');
            $('#idleHoursError').text('Too many working hours - check readings');
            isValid = false;
        }
        
        // Trigger calculation preview update if function exists
        if (typeof updateCalculationPreview === 'function') {
            updateCalculationPreview();
        }
        
        return isValid;
    } catch (error) {
        console.error('DPR Validation Error:', error);
        return false;
    }
}

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Bind validation to meter reading inputs
    $(document).on('input change', '#machine_start_reading, #machine_end_reading, #machine_idle_reading', function() {
        window.validateReadings();
    });
    
    // Bind to form submission
    $(document).on('submit', 'form', function(e) {
        const isValid = window.validateReadings();
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
            
            // Show general error message
            const errorMessage = 'Please fix validation errors before submitting the form.';
            
            // Remove existing alerts
            $('.alert-danger').remove();
            
            // Find appropriate container
            const container = $('.modal-body').length > 0 ? $('.modal-body').first() : $('form').first();
            
            if (container.length > 0) {
                const alertDiv = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    `${errorMessage}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>` +
                    '</div>');
                
                container.prepend(alertDiv);
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    alertDiv.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
                
                // Scroll to first error
                const firstError = $('.is-invalid').first();
                if (firstError.length > 0) {
                    firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        }
    });
});

console.log('DPR Simple Validation Loaded');
