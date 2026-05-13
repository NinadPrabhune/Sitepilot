/**
 * DPR AJAX Handler V2 - HTML-Only Approach
 * This version works with HTML-only templates and data attributes
 */

class DPRModalHandlerV2 {
    constructor() {
        this.currentModal = null;
        this.data = {};
        this.init();
    }

    init() {
        // Listen for modal content loaded
        $(document).on('dpr:contentLoaded', (event, container) => {
            this.initializeDPR(container);
        });

        // Listen for modal hidden events to cleanup
        $(document).on('hidden.bs.modal', '#commonModal', () => {
            this.cleanup();
        });
    }

    initializeDPR(container) {
        this.currentModal = $(container);
        
        // Load data from data attributes
        this.loadDataFromAttributes();
        
        // Initialize all functionality
        this.initializeCalculations();
        this.initializeMaterialSelection();
        this.initializeValidation();
        this.initializeEventHandlers();
        
        console.log('DPR initialized with data:', this.data);
    }

    loadDataFromAttributes() {
        const dataElement = this.currentModal.find('#dpr-data');
        if (dataElement.length) {
            this.data = dataElement.data();
            
            // Parse JSON strings that jQuery might not auto-parse
            Object.keys(this.data).forEach(key => {
                if (typeof this.data[key] === 'string') {
                    try {
                        this.data[key] = JSON.parse(this.data[key]);
                    } catch (e) {
                        console.warn(`Failed to parse ${key} as JSON:`, e);
                    }
                }
            });
        }
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
        const isRental = this.data.isRental || false;
        const machinery = this.data.machinery || {};
        const minimumHours = parseFloat(machinery.minimum_billing_hours) || 0;
        
        if (isRental && minimumHours > 0) {
            billableHours = Math.max(billableHours, minimumHours);
        }
        
        // Update preview elements
        const previewElement = this.currentModal.find('#calculation-preview');
        if (previewElement.length) {
            const rate = parseFloat(machinery.rate) || 0;
            const total = billableHours * rate;
            
            previewElement.html(`
                <div class="calculation-summary">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Working Hours:</strong> ${workingHours.toFixed(2)}
                        </div>
                        <div class="col-md-3">
                            <strong>Idle Hours:</strong> ${idleHours.toFixed(2)}
                        </div>
                        <div class="col-md-3">
                            <strong>Billable Hours:</strong> ${billableHours.toFixed(2)}
                        </div>
                        <div class="col-md-3">
                            <strong>Total:</strong> ₹${total.toFixed(2)}
                        </div>
                    </div>
                </div>
            `);
        }
    }

    initializeMaterialSelection() {
        const materialSelects = this.currentModal.find('.item-material');
        const materials = this.data.materials || {};
        
        materialSelects.each((index, select) => {
            const $select = $(select);
            
            $select.on('change', () => {
                const materialId = $select.val();
                const material = materials[materialId];
                
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
        
        // Clear previous errors
        this.currentModal.find('.is-invalid').removeClass('is-invalid');
        this.currentModal.find('.text-danger').empty();
        
        // Validate machine readings
        const startReading = parseFloat(this.currentModal.find('#machine_start_reading').val()) || 0;
        const endReading = parseFloat(this.currentModal.find('#machine_end_reading').val()) || 0;
        const idleHours = parseFloat(this.currentModal.find('#machine_idle_reading').val()) || 0;
        
        if (endReading < startReading) {
            errors.push('End reading must be greater than or equal to start reading');
            this.currentModal.find('#machine_end_reading').addClass('is-invalid');
            this.currentModal.find('#endReadingError').text('End reading must be ≥ start reading');
            isValid = false;
        }
        
        if (idleHours < 0) {
            errors.push('Idle hours cannot be negative');
            this.currentModal.find('#machine_idle_reading').addClass('is-invalid');
            this.currentModal.find('#idleHoursError').text('Idle hours cannot be negative');
            isValid = false;
        }
        
        const workingHours = endReading - startReading;
        if (workingHours > 0 && idleHours > workingHours) {
            errors.push('Idle hours cannot exceed working hours');
            this.currentModal.find('#machine_idle_reading').addClass('is-invalid');
            this.currentModal.find('#idleHoursError').text('Idle hours cannot exceed working hours');
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
                <strong>Validation Error:</strong> Please fix the validation errors highlighted below.
            </div>
        `;
        
        this.currentModal.find('.modal-body').prepend(errorHtml);
    }

    initializeEventHandlers() {
        // Add item row handler
        this.currentModal.find('#add-item-row').on('click', () => {
            this.addMaterialRow();
        });

        // Remove item row handler
        this.currentModal.on('click', '.remove-item-row', (e) => {
            $(e.currentTarget).closest('tr').remove();
        });
    }

    addMaterialRow() {
        const table = this.currentModal.find('#consumption-items-table tbody');
        const rowIndex = table.find('tr').length;
        
        const rowHtml = `
            <tr>
                <td>
                    <select name="items[${rowIndex}][material_id]" class="form-control item-material" required>
                        <option value="">Select Material</option>
                        ${Object.entries(this.data.materials || {}).map(([id, material]) => 
                            `<option value="${id}">${material.name}</option>`
                        ).join('')}
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <input type="text" class="form-control item-stock" readonly value="0"/>
                        <span class="input-group-text item-stock-unit">unit</span>
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-quantity" min="1" value="1" required>
                        <input type="hidden" name="items[${rowIndex}][unit]" class="item-unit" value="unit">
                        <span class="input-group-text item-unit-label">unit</span>
                    </div>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][remarks]" class="form-control" value="">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-item-row">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        table.append(rowHtml);
    }

    cleanup() {
        this.currentModal = null;
        this.data = {};
    }
}

// Initialize handler
window.dprModalHandlerV2 = new DPRModalHandlerV2();
