/**
 * DPR Validation using jQuery - Global validation for all DPR forms
 * This file provides centralized validation functions for Daily Progress Report forms
 */

(function($) {
    'use strict';

    // Global DPR validation functions
    window.DPRValidationJQuery = {
        
        /**
         * Validate meter readings including idle hours
         */
        validateReadings: function() {
            const startReading = parseFloat($('#machine_start_reading').val()) || 0;
            const endReading = parseFloat($('#machine_end_reading').val()) || 0;
            const idleHours = parseFloat($('#machine_idle_reading').val()) || 0;
            
            // Clear previous errors
            this.clearValidationErrors();
            
            const errors = [];
            const warnings = [];
            
            // Validation 1: End reading cannot be less than start reading
            if (endReading < startReading) {
                errors.push('End reading cannot be less than start reading');
                this.showFieldError('machine_end_reading', 'End reading must be ≥ start reading');
            }
            
            // Validation 2: Idle hours validation
            const totalProgress = endReading - startReading;
            const workingHours = totalProgress - idleHours;
            
            // 2a: Idle hours cannot be negative
            if (idleHours < 0) {
                errors.push('Idle hours cannot be negative');
                this.showFieldError('machine_idle_reading', 'Idle hours cannot be negative');
            }
            
            // 2b: Idle hours should not exceed working hours
            if (totalProgress > 0 && idleHours > totalProgress) {
                errors.push('Idle hours cannot exceed working hours');
                this.showFieldError('machine_idle_reading', 'Idle hours cannot exceed working hours');
            }
            
            // 2c: Idle hours should not exceed 24 hours
            if (idleHours > 24) {
                errors.push('Idle hours cannot exceed 24 hours');
                this.showFieldError('machine_idle_reading', 'Idle hours cannot exceed 24 hours');
            }
            
            // 2d: Working hours cannot exceed 24
            if (workingHours > 24) {
                errors.push('Working hours cannot exceed 24 hours per day');
                this.showFieldError('machine_idle_reading', 'Too many working hours - check readings');
            }
            
            // Show warnings if any
            if (warnings.length > 0) {
                this.showWarnings(warnings);
            }
            
            // Trigger custom event for other validations
            $(document).trigger('dpr:validated', {
                isValid: errors.length === 0,
                errors: errors,
                warnings: warnings,
                readings: {
                    start: startReading,
                    end: endReading,
                    idle: idleHours,
                    working: workingHours,
                    total: totalProgress
                }
            });
            
            return errors.length === 0;
        },
        
        /**
         * Clear all validation errors from meter reading fields
         */
        clearValidationErrors: function() {
            $('#machine_start_reading, #machine_end_reading, #machine_idle_reading')
                .removeClass('is-invalid')
                .addClass('is-valid');
                
            $('#startReadingError, #endReadingError, #idleHoursError').text('');
        },
        
        /**
         * Show error for specific field
         */
        showFieldError: function(fieldId, message) {
            const field = $('#' + fieldId);
            field.removeClass('is-valid').addClass('is-invalid');
            
            // Find or create error container
            let errorContainer = field.siblings('.invalid-feedback');
            if (errorContainer.length === 0) {
                errorContainer = $('<div class="invalid-feedback"></div>');
                field.after(errorContainer);
            }
            
            errorContainer.text(message);
        },
        
        /**
         * Show warnings in validation warnings section
         */
        showWarnings: function(warnings) {
            const warningsDiv = $('#validation-warnings');
            const messagesDiv = $('#validation-messages');
            
            if (warnings.length > 0 && warningsDiv.length > 0) {
                messagesDiv.html(warnings.map(w => `<small>• ${w}</small>`).join('<br>'));
                warningsDiv.show();
            } else if (warningsDiv.length > 0) {
                warningsDiv.hide();
            }
        },
        
        /**
         * Initialize validation for DPR forms
         */
        init: function() {
            // Bind validation to meter reading inputs
            $(document).on('input change', '#machine_start_reading, #machine_end_reading, #machine_idle_reading', function() {
                DPRValidationJQuery.validateReadings();
            });
            
            // Bind to form submission
            $(document).on('submit', 'form', function(e) {
                const isValid = DPRValidationJQuery.validateReadings();
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show general error message
                    DPRValidationJQuery.showFormError('Please fix validation errors before submitting the form.');
                    
                    // Scroll to first error
                    const firstError = $('.is-invalid').first();
                    if (firstError.length > 0) {
                        firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });
        },
        
        /**
         * Show form-level error message
         */
        showFormError: function(message) {
            // Remove existing alerts
            $('.alert-danger').remove();
            
            // Find appropriate container
            const container = $('.modal-body').length > 0 ? $('.modal-body').first() : $('form').first();
            
            if (container.length > 0) {
                const alertDiv = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>` +
                    '</div>');
                
                container.prepend(alertDiv);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        /**
         * Manual validation trigger
         */
        validateForm: function(form) {
            if (form && $(form).find('#machine_start_reading').length > 0) {
                return DPRValidationJQuery.validateReadings();
            }
            return true;
        }
    };
    
    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        DPRValidationJQuery.init();
    });
    
})(jQuery);
