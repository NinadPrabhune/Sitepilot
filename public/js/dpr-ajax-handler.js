/**
 * DPR AJAX Handler - Separate JS for AJAX-loaded content
 * This handles all DPR-specific functionality without inline scripts
 */

class DPRModalHandler {
    constructor() {
        this.materials = {};
        this.machinery = {};
        this.currentModal = null;
        this.init();
    }

    init() {
        // Listen for custom events when modal is loaded
        $(document).on('dpr:modalLoaded', (event, data) => {
            this.handleModalLoaded(data);
        });

        // Listen for modal hidden events to cleanup
        $(document).on('hidden.bs.modal', '#commonModal', () => {
            this.cleanup();
        });
    }

    handleModalLoaded(data) {
        this.materials = data.materials || {};
        this.machinery = data.machinery || {};
        this.currentModal = $('#commonModal');
        
        // Initialize all DPR functionality
        this.initializeCalculations();
        this.initializeMaterialSelection();
        this.initializeValidation();
        this.initializeChoices();
    }

    initializeCalculations() {
        const startInput = this.currentModal.find('#machine_start_reading');
        const endInput = this.currentModal.find('#machine_end_reading');
        const idleInput = this.currentModal.find('#machine_idle_reading');

        if (startInput.length && endInput.length) {
            // Bind calculation preview
            [startInput, endInput, idleInput].forEach(input => {
                input.on('input', () => this.updateCalculationPreview());
            });

            // Initial preview
            this.updateCalculationPreview();
        }
    }

    updateCalculationPreview() {
        const startReading = parseFloat(this.currentModal.find('#machine_start_reading').val()) || 0;
        const endReading = parseFloat(this.currentModal.find('#machine_end_reading').val()) || 0;
        const idleHours = parseFloat(this.currentModal.find('#machine_idle_reading').val()) || 0;
        
        const workingHours = Math.max(0, endReading - startReading);
        let billableHours = Math.max(0, workingHours - idleHours);
        
        // Apply minimum billing if rental
        const isRental = this.machinery.owned_by === 'rental';
        const minimumHours = parseFloat(this.machinery.minimum_billing_hours) || 0;
        
        if (isRental && minimumHours > 0) {
            billableHours = Math.max(billableHours, minimumHours);
        }
        
        // Update preview elements
        const previewElement = this.currentModal.find('#calculation-preview');
        if (previewElement.length) {
            const rate = parseFloat(this.machinery.rate) || 0;
            const total = billableHours * rate;
            
            previewElement.html(`
                <div class="calculation-summary">
                    <div>Working Hours: ${workingHours.toFixed(2)}</div>
                    <div>Idle Hours: ${idleHours.toFixed(2)}</div>
                    <div>Billable Hours: ${billableHours.toFixed(2)}</div>
                    <div>Rate: ₹${rate.toFixed(2)}</div>
                    <div><strong>Total: ₹${total.toFixed(2)}</strong></div>
                </div>
            `);
        }
    }

    initializeMaterialSelection() {
        const materialSelects = this.currentModal.find('.item-material');
        
        materialSelects.each((index, select) => {
            const $select = $(select);
            
            $select.on('change', () => {
                const materialId = $select.val();
                const material = this.materials[materialId];
                
                if (material) {
                    // Update stock display
                    const stockInput = $select.closest('tr').find('.item-stock');
                    const stockUnit = $select.closest('tr').find('.item-stock-unit');
                    const unitInput = $select.closest('tr').find('.item-unit');
                    const unitLabel = $select.closest('tr').find('.item-unit-label');
                    
                    stockInput.val(material.total_qty || 0);
                    stockUnit.text(material.unit || 'unit');
                    unitInput.val(material.unit || 'unit');
                    unitLabel.text(material.unit || 'unit');
                }
            });
        });
    }

    initializeValidation() {
        const form = this.currentModal.find('form');
        
        if (form.length) {
            form.on('submit', (e) => {
                if (!this.validateForm()) {
                    e.preventDefault();
                    this.showValidationErrors();
                    return false;
                }
            });
        }
    }

    validateForm() {
        let isValid = true;
        const errors = [];
        
        // Validate machine readings
        const startReading = parseFloat(this.currentModal.find('#machine_start_reading').val()) || 0;
        const endReading = parseFloat(this.currentModal.find('#machine_end_reading').val()) || 0;
        const idleHours = parseFloat(this.currentModal.find('#machine_idle_reading').val()) || 0;
        
        if (endReading < startReading) {
            errors.push('End reading must be greater than or equal to start reading');
            isValid = false;
        }
        
        if (idleHours < 0) {
            errors.push('Idle hours cannot be negative');
            isValid = false;
        }
        
        const workingHours = endReading - startReading;
        if (workingHours > 0 && idleHours > workingHours) {
            errors.push('Idle hours cannot exceed working hours');
            isValid = false;
        }
        
        return isValid;
    }

    showValidationErrors() {
        // Remove existing error alerts
        this.currentModal.find('.alert-danger').remove();
        
        // Add new error alert
        const errorHtml = `
            <div class="alert alert-danger alert-dismissible" role="alert">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>Validation Error:</strong> Please fix the following issues:
                <ul>
                    <li>End reading must be greater than or equal to start reading</li>
                    <li>Idle hours cannot be negative</li>
                    <li>Idle hours cannot exceed working hours</li>
                </ul>
            </div>
        `;
        
        this.currentModal.find('.modal-body').prepend(errorHtml);
    }

    initializeChoices() {
        // Initialize Choices.js for select elements
        const selectElements = this.currentModal.find('select:not(.item-material)');
        
        selectElements.each((index, select) => {
            if (!$(select).data('choices-initialized')) {
                try {
                    new Choices(select, {
                        allowHTML: true,
                        searchEnabled: true,
                        searchPlaceholderValue: 'Type to search...',
                        itemSelectText: 'Press to select',
                        noResultsText: 'No results found'
                    });
                    $(select).data('choices-initialized', true);
                } catch (error) {
                    console.error('Choices.js initialization failed:', error);
                }
            }
        });
    }

    cleanup() {
        // Cleanup when modal is hidden
        this.materials = {};
        this.machinery = {};
        this.currentModal = null;
    }
}

// Initialize the handler
window.dprModalHandler = new DPRModalHandler();
