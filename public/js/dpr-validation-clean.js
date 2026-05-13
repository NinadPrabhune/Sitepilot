/**
 * DPR Clean Validation - Minimal, conflict-free solution
 * Simple validation without external dependencies
 */

// Global validation function
window.validateReadings = function() {
    const startReading = parseFloat(document.getElementById('machine_start_reading')?.value) || 0;
    const endReading = parseFloat(document.getElementById('machine_end_reading')?.value) || 0;
    const idleHours = parseFloat(document.getElementById('machine_idle_reading')?.value) || 0;
    
    // Clear errors
    ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading'].forEach(fieldId => {
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
    
    // Validation rules
    if (endReading < startReading) {
        document.getElementById('machine_end_reading').classList.add('is-invalid');
        document.getElementById('endReadingError').textContent = 'End reading must be ≥ start reading';
        isValid = false;
    }
    
    const totalProgress = endReading - startReading;
    const workingHours = totalProgress - idleHours;
    
    if (idleHours < 0) {
        document.getElementById('machine_idle_reading').classList.add('is-invalid');
        document.getElementById('idleHoursError').textContent = 'Idle hours cannot be negative';
        isValid = false;
    }
    
    if (totalProgress > 0 && idleHours > totalProgress) {
        document.getElementById('machine_idle_reading').classList.add('is-invalid');
        document.getElementById('idleHoursError').textContent = 'Idle hours cannot exceed working hours';
        isValid = false;
    }
    
    if (idleHours > 24) {
        document.getElementById('machine_idle_reading').classList.add('is-invalid');
        document.getElementById('idleHoursError').textContent = 'Idle hours cannot exceed 24 hours';
        isValid = false;
    }
    
    if (workingHours > 24) {
        document.getElementById('machine_idle_reading').classList.add('is-invalid');
        document.getElementById('idleHoursError').textContent = 'Too many working hours - check readings';
        isValid = false;
    }
    
    return isValid;
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Bind to meter reading inputs
    ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', window.validateReadings);
            field.addEventListener('change', window.validateReadings);
        }
    });
    
    // Bind to form submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const isValid = window.validateReadings();
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                alert('Please fix validation errors before submitting the form.');
            }
        });
    });
});

console.log('DPR Clean Validation Loaded');
