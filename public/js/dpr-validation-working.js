/**
 * DPR Working Validation - Simple, conflict-free solution
 * This file provides basic validation without external dependencies
 */

// Simple global validation function
function validateReadings() {
    try {
        const startReading = parseFloat(document.getElementById('machine_start_reading')?.value) || 0;
        const endReading = parseFloat(document.getElementById('machine_end_reading')?.value) || 0;
        const idleHours = parseFloat(document.getElementById('machine_idle_reading')?.value) || 0;
        
        // Clear previous errors
        const fields = ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading'];
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        });
        
        // Clear error messages
        ['startReadingError', 'endReadingError', 'idleHoursError'].forEach(errorId => {
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.textContent = '';
            }
        });
        
        let isValid = true;
        
        // Validation 1: End reading cannot be less than start reading
        if (endReading < startReading) {
            const endField = document.getElementById('machine_end_reading');
            const errorElement = document.getElementById('endReadingError');
            if (endField && errorElement) {
                endField.classList.remove('is-valid');
                endField.classList.add('is-invalid');
                errorElement.textContent = 'End reading must be ≥ start reading';
            }
            isValid = false;
        }
        
        // Validation 2: Idle hours
        const totalProgress = endReading - startReading;
        const workingHours = totalProgress - idleHours;
        
        // 2a: Idle hours cannot be negative
        if (idleHours < 0) {
            const idleField = document.getElementById('machine_idle_reading');
            const errorElement = document.getElementById('idleHoursError');
            if (idleField && errorElement) {
                idleField.classList.remove('is-valid');
                idleField.classList.add('is-invalid');
                errorElement.textContent = 'Idle hours cannot be negative';
            }
            isValid = false;
        }
        
        // 2b: Idle hours should not exceed working hours
        if (totalProgress > 0 && idleHours > totalProgress) {
            const idleField = document.getElementById('machine_idle_reading');
            const errorElement = document.getElementById('idleHoursError');
            if (idleField && errorElement) {
                idleField.classList.remove('is-valid');
                idleField.classList.add('is-invalid');
                errorElement.textContent = 'Idle hours cannot exceed working hours';
            }
            isValid = false;
        }
        
        // 2c: Idle hours should not exceed 24 hours
        if (idleHours > 24) {
            const idleField = document.getElementById('machine_idle_reading');
            const errorElement = document.getElementById('idleHoursError');
            if (idleField && errorElement) {
                idleField.classList.remove('is-valid');
                idleField.classList.add('is-invalid');
                errorElement.textContent = 'Idle hours cannot exceed 24 hours';
            }
            isValid = false;
        }
        
        // 2d: Working hours cannot exceed 24
        if (workingHours > 24) {
            const idleField = document.getElementById('machine_idle_reading');
            const errorElement = document.getElementById('idleHoursError');
            if (idleField && errorElement) {
                idleField.classList.remove('is-valid');
                idleField.classList.add('is-invalid');
                errorElement.textContent = 'Too many working hours - check readings';
            }
            isValid = false;
        }
        
        return isValid;
    } catch (error) {
        console.error('DPR Validation Error:', error);
        return false;
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Bind validation to meter reading inputs using event delegation
    ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            ['input', 'change'].forEach(eventType => {
                field.addEventListener(eventType, validateReadings);
            });
        }
    });
    
    // Bind to form submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const isValid = validateReadings();
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                
                // Show general error message
                const errorMessage = 'Please fix validation errors before submitting the form.';
                
                // Remove existing alerts
                const existingAlerts = document.querySelectorAll('.alert-danger');
                existingAlerts.forEach(alert => alert.remove());
                
                // Find appropriate container
                const container = document.querySelector('.modal-body') || form;
                
                if (container) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.setAttribute('role', 'alert');
                    alertDiv.innerHTML = errorMessage + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    
                    container.insertBefore(alertDiv, container.firstChild);
                    
                    // Auto-remove after 5 seconds
                    setTimeout(function() {
                        alertDiv.style.opacity = '0';
                        setTimeout(function() {
                            if (alertDiv.parentNode) {
                                alertDiv.parentNode.removeChild(alertDiv);
                            }
                        }, 500);
                    }, 5000);
                    
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            }
        });
    });
});

console.log('DPR Working Validation Loaded');
