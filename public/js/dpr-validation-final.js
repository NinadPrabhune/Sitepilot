/**
 * DPR Final Validation - Ultra-minimal, conflict-free solution
 * Simple validation without any external dependencies
 */

window.validateDPR = function() {
    var start = document.getElementById('machine_start_reading');
    var end = document.getElementById('machine_end_reading');
    var idle = document.getElementById('machine_idle_reading');
    
    // Check if elements exist before accessing their properties
    if (!start || !end || !idle) {
        console.log('DPR elements not found, skipping validation');
        return true;
    }
    
    var startReading = parseFloat(start.value) || 0;
    var endReading = parseFloat(end.value) || 0;
    var idleHours = parseFloat(idle.value) || 0;
    
    // Clear errors
    if (start) start.classList.remove('is-invalid');
    if (end) end.classList.remove('is-invalid');
    if (idle) idle.classList.remove('is-invalid');
    
    var startError = document.getElementById('startReadingError');
    var endError = document.getElementById('endReadingError');
    var idleError = document.getElementById('idleHoursError');
    
    if (startError) startError.textContent = '';
    if (endError) endError.textContent = '';
    if (idleError) idleError.textContent = '';
    
    var isValid = true;
    
    // Validation rules
    if (endReading < startReading) {
        if (end) end.classList.add('is-invalid');
        if (endError) endError.textContent = 'End reading must be >= start reading';
        isValid = false;
    }
    
    var totalProgress = endReading - startReading;
    var workingHours = totalProgress - idleHours;
    
    if (idleHours < 0) {
        if (idle) idle.classList.add('is-invalid');
        if (idleError) idleError.textContent = 'Idle hours cannot be negative';
        isValid = false;
    }
    
    if (totalProgress > 0 && idleHours > totalProgress) {
        if (idle) idle.classList.add('is-invalid');
        if (idleError) idleError.textContent = 'Idle hours cannot exceed working hours';
        isValid = false;
    }
    
    if (idleHours > 24) {
        if (idle) idle.classList.add('is-invalid');
        if (idleError) idleError.textContent = 'Idle hours cannot exceed 24 hours';
        isValid = false;
    }
    
    if (workingHours > 24) {
        if (idle) idle.classList.add('is-invalid');
        if (idleError) idleError.textContent = 'Too many working hours';
        isValid = false;
    }
    
    return isValid;
};

document.addEventListener('DOMContentLoaded', function() {
    var inputs = ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading'];
    
    inputs.forEach(function(id) {
        var element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', window.validateDPR);
            element.addEventListener('change', window.validateDPR);
        }
    });
    
    var forms = document.getElementsByTagName('form');
    
    for (var i = 0; i < forms.length; i++) {
        forms[i].addEventListener('submit', function(e) {
            var isValid = window.validateDPR();
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                alert('Please fix validation errors before submitting the form.');
            }
        });
    }
});

console.log('DPR Final Validation Loaded');
