// DPR Machine Reading Validation
class DPRValidation {
    constructor() {
        this.init();
    }

    init() {
        // Bind events when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            this.bindMachineReadingValidation();
        });
    }

    bindMachineReadingValidation() {
        const startReadingInput = document.getElementById('machine_start_reading');
        const endReadingInput = document.getElementById('machine_end_reading');
        const idleHoursInput = document.getElementById('machine_idle_reading');

        if (startReadingInput && endReadingInput) {
            // Validate on input change
            startReadingInput.addEventListener('input', () => {
                this.validateMachineReadings(startReadingInput, endReadingInput, idleHoursInput);
            });

            endReadingInput.addEventListener('input', () => {
                this.validateMachineReadings(startReadingInput, endReadingInput, idleHoursInput);
            });

            // Add idle hours validation if input exists
            if (idleHoursInput) {
                idleHoursInput.addEventListener('input', () => {
                    this.validateMachineReadings(startReadingInput, endReadingInput, idleHoursInput);
                });
            }

            // Validate on form submission
            const form = startReadingInput.closest('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    // Remove any existing alerts
                    this.removeExistingAlerts();
                    
                    if (!this.validateMachineReadings(startReadingInput, endReadingInput, idleHoursInput)) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Show a clear error message to user
                        this.showFormError('Please fix validation errors before submitting the form.');
                        
                        // Scroll to first error
                        const firstError = document.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    }
                });
            }
        }
    }

    validateMachineReadings(startInput, endInput, idleInput = null) {
        const startValue = parseFloat(startInput.value);
        const endValue = parseFloat(endInput.value);
        const idleValue = idleInput ? parseFloat(idleInput.value) || 0 : 0;
        let isValid = true;

        // Clear previous validation states
        this.clearValidationState(startInput);
        this.clearValidationState(endInput);
        if (idleInput) {
            this.clearValidationState(idleInput);
        }

        // Validate if both values are provided
        if (startInput.value.trim() !== '' && endInput.value.trim() !== '') {
            if (!isNaN(startValue) && !isNaN(endValue)) {
                if (endValue <= startValue) {
                    // Show validation error
                    this.showValidationError(endInput, 'End reading must be greater than start reading');
                    isValid = false;
                } else {
                    // Show success state
                    this.showValidationSuccess(endInput);
                    this.showValidationSuccess(startInput);
                }

                // Validate idle hours if input exists
                if (idleInput && idleInput.value.trim() !== '') {
                    const totalProgress = endValue - startValue;
                    const workingHours = totalProgress - idleValue;

                    // 3a: Idle hours cannot be negative
                    if (idleValue < 0) {
                        this.showValidationError(idleInput, 'Idle hours cannot be negative');
                        isValid = false;
                    }
                    // 3b: Idle hours should not exceed working hours
                    else if (totalProgress > 0 && idleValue > totalProgress) {
                        this.showValidationError(idleInput, 'Idle hours cannot exceed working hours');
                        isValid = false;
                    }
                    // 3c: Idle hours should not exceed 24 hours
                    else if (idleValue > 24) {
                        this.showValidationError(idleInput, 'Idle hours cannot exceed 24 hours');
                        isValid = false;
                    }
                    // 3d: Working hours cannot exceed 24
                    else if (workingHours > 24) {
                        this.showValidationError(idleInput, 'Too many working hours - check readings');
                        isValid = false;
                    } else {
                        this.showValidationSuccess(idleInput);
                    }
                }
            } else {
                // Invalid numbers
                if (isNaN(startValue)) {
                    this.showValidationError(startInput, 'Invalid start reading');
                    isValid = false;
                }
                if (isNaN(endValue)) {
                    this.showValidationError(endInput, 'Invalid end reading');
                    isValid = false;
                }
            }
        } else if (startInput.value.trim() !== '' || endInput.value.trim() !== '') {
            // Partial completion - show warning but don't block
            if (startInput.value.trim() !== '' && !isNaN(startValue)) {
                this.showValidationSuccess(startInput);
            }
            if (endInput.value.trim() !== '' && !isNaN(endValue)) {
                this.showValidationSuccess(endInput);
            }
        }

        return isValid;
    }

    clearValidationState(input) {
        // Remove validation classes
        input.classList.remove('is-invalid', 'is-valid');
        
        // Remove existing feedback
        const existingFeedback = input.parentNode.querySelector('.invalid-feedback, .valid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
    }

    showValidationError(input, message) {
        // Add error class
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');

        // Remove existing success feedback
        const existingSuccess = input.parentNode.querySelector('.valid-feedback');
        if (existingSuccess) {
            existingSuccess.remove();
        }

        // Add error feedback
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    showValidationSuccess(input) {
        // Add success class
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');

        // Remove existing error feedback
        const existingError = input.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }

        // Add success feedback
        let feedback = input.parentNode.querySelector('.valid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'valid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = 'Valid reading';
    }

    showFormError(message) {
        // Find the modal body or form container
        const modalBody = document.querySelector('.modal-body');
        const formContainer = document.querySelector('form');
        const container = modalBody || formContainer;

        if (container) {
            // Create error alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            // Insert at the top of the container
            container.insertBefore(alertDiv, container.firstChild);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }

    removeExistingAlerts() {
        // Remove any existing alerts in the modal
        const existingAlerts = document.querySelectorAll('.modal-body .alert, form .alert');
        existingAlerts.forEach(alert => alert.remove());
    }

    // Utility method for manual validation
    static validateForm(form) {
        const startInput = form.querySelector('#machine_start_reading');
        const endInput = form.querySelector('#machine_end_reading');
        const idleInput = form.querySelector('#machine_idle_reading');
        
        if (startInput && endInput) {
            const validator = new DPRValidation();
            return validator.validateMachineReadings(startInput, endInput, idleInput);
        }
        
        return true;
    }
}

// Auto-initialize
new DPRValidation();

// Make available globally for manual validation
window.DPRValidation = DPRValidation;
