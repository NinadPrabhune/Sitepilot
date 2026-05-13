<?php $__env->startSection('page-title', __('Create Machinery Payment Request')); ?>
<?php $__env->startSection('page-breadcrumb', __('Machinery,Payment Requests,Create')); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

    </a>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo e(__('Create Machinery Payment Request')); ?></h5>
            </div>
            <div class="card-body">
                <form id="paymentRequestForm">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo e(Form::label('machinery_id', __('Machinery'), ['class' => 'form-label'])); ?>

                                <span class="text-danger">*</span>
                                <select class="form-select" id="machinery_id" name="machinery_id" required>
                                    <option value=""><?php echo e(__('Select Machinery')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $machineries ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $machinery): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($machinery->id); ?>" data-supplier="<?php echo e($machinery->supplier_id); ?>"><?php echo e($machinery->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo e(Form::label('supplier_id', __('Supplier'), ['class' => 'form-label'])); ?>

                                <span class="text-danger">*</span>
                                <select class="form-select" id="supplier_id" name="supplier_id" required readonly style="background-color: #f8f9fa;">
                                    <option value=""><?php echo e(__('Select Machinery First')); ?></option>
                                </select>
                                <small class="text-muted"><?php echo e(__('Supplier will be auto-selected based on machinery')); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Period Selection -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><?php echo e(__('Monthly Period Selection')); ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <?php echo e(Form::label('period_year', __('Year'), ['class' => 'form-label'])); ?>

                                                <span class="text-danger">*</span>
                                                <select class="form-select" id="period_year" required>
                                                    <?php
                                                        $currentYear = date('Y');
                                                        for($year = $currentYear; $year >= $currentYear - 2; $year--) {
                                                            echo '<option value="'.$year.'"'.($year == $currentYear ? ' selected' : '').'>'.$year.'</option>';
                                                        }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <?php echo e(Form::label('period_month', __('Month'), ['class' => 'form-label'])); ?>

                                                <span class="text-danger">*</span>
                                                <select class="form-select" id="period_month" required>
                                                    <?php
                                                        $months = [
                                                            '01' => 'January', '02' => 'February', '03' => 'March',
                                                            '04' => 'April', '05' => 'May', '06' => 'June',
                                                            '07' => 'July', '08' => 'August', '09' => 'September',
                                                            '10' => 'October', '11' => 'November', '12' => 'December'
                                                        ];
                                                        $currentMonth = date('m');
                                                        foreach($months as $num => $name) {
                                                            echo '<option value="'.$num.'"'.($num == $currentMonth ? ' selected' : '').'>'.$name.'</option>';
                                                        }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                   
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <?php echo e(Form::label('period_start', __('Period Start'), ['class' => 'form-label'])); ?>

                                                <input type="text" class="form-control" id="period_start" name="period_start" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <?php echo e(Form::label('period_end', __('Period End'), ['class' => 'form-label'])); ?>

                                                <input type="text" class="form-control" id="period_end" name="period_end" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-center">
                            <button type="button" id="calculateBtn" class="btn btn-primary">
                                <i class="ti ti-calculator me-2"></i> <?php echo e(__('Calculate from Ledger')); ?>

                            </button>
                            <div class="mt-2">
                                <small class="text-muted"><?php echo e(__('This will calculate the net payable amount based on ledger entries for the selected period')); ?></small>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Calculation Results -->
                <div id="calculationResults" class="mt-4" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><?php echo e(__('Calculation Results')); ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="creditsValue" class="mb-1">Rs. 0.00</h5>
                                        <small class="text-muted"><?php echo e(__('Credits')); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="debitsValue" class="mb-1">Rs. 0.00</h5>
                                        <small class="text-muted"><?php echo e(__('Debits')); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded bg-light">
                                        <h5 id="netPayableValue" class="mb-1 text-primary">Rs. 0.00</h5>
                                        <small class="text-muted"><?php echo e(__('Net Payable')); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="entryCountValue" class="mb-1">0</h5>
                                        <small class="text-muted"><?php echo e(__('Entry Count')); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 text-center">
                                    <button type="button" id="submitBtn" class="btn btn-success">
                                        <i class="ti ti-check me-2"></i> <?php echo e(__('Submit Payment Request')); ?>

                                    </button>
                                    <div class="mt-2">
                                        <small class="text-muted"><?php echo e(__('Payment request will be created and submitted for approval')); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Machinery change handler - Auto-select supplier
const machinerySelect = document.getElementById('machinery_id');
const supplierSelect = document.getElementById('supplier_id');
const periodYear = document.getElementById('period_year');
const periodMonth = document.getElementById('period_month');
const periodStart = document.getElementById('period_start');
const periodEnd = document.getElementById('period_end');
const calculateBtn = document.getElementById('calculateBtn');
const submitBtn = document.getElementById('submitBtn');

// Supplier data from server
const suppliers = <?php echo json_encode($suppliers ?? [], 15, 512) ?>;

// Form state persistence
const FORM_STATE_KEY = 'machinery_payment_form_state';

// Save form state to localStorage
function saveFormState() {
    const formState = {
        machinery_id: machinerySelect.value,
        supplier_id: supplierSelect.value,
        period_year: periodYear.value,
        period_month: periodMonth.value
    };
    localStorage.setItem(FORM_STATE_KEY, JSON.stringify(formState));
}

// Load form state from localStorage
function loadFormState() {
    const savedState = localStorage.getItem(FORM_STATE_KEY);
    if (savedState) {
        try {
            const formState = JSON.parse(savedState);
            
            // Restore machinery selection
            if (formState.machinery_id) {
                machinerySelect.value = formState.machinery_id;
                // Trigger machinery change to update supplier
                machinerySelect.dispatchEvent(new Event('change'));
            }
            
            // Restore period selection
            if (formState.period_year) {
                periodYear.value = formState.period_year;
            }
            if (formState.period_month) {
                periodMonth.value = formState.period_month;
            }
            
            // Update period dates after restoring values
            updatePeriodDates();
            
        } catch (e) {
            console.error('Error loading form state:', e);
        }
    }
}

// Clear form state
function clearFormState() {
    localStorage.removeItem(FORM_STATE_KEY);
}

// Update period dates when month/year changes
function updatePeriodDates() {
    const year = periodYear.value;
    const month = periodMonth.value;
    if (year && month) {
        const startDate = `${year}-${month}-01`;
        const lastDay = new Date(year, parseInt(month), 0).getDate();
        const endDate = `${year}-${month}-${lastDay}`;
        periodStart.value = startDate;
        periodEnd.value = endDate;
    }
}

// Update supplier when machinery changes
machinerySelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const supplierId = selectedOption.getAttribute('data-supplier');
    
    // Clear and populate supplier dropdown
    supplierSelect.innerHTML = '';
    
    if (supplierId) {
        const supplier = suppliers.find(s => s.id == supplierId);
        if (supplier) {
            const option = document.createElement('option');
            option.value = supplier.id;
            option.textContent = supplier.name;
            option.selected = true;
            supplierSelect.appendChild(option);
        }
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '<?php echo e(__('Select Machinery First')); ?>';
        supplierSelect.appendChild(option);
    }
    
    // Save form state after machinery change
    saveFormState();
});

// Listen for period changes
periodYear.addEventListener('change', function() {
    updatePeriodDates();
    saveFormState();
});

periodMonth.addEventListener('change', function() {
    updatePeriodDates();
    saveFormState();
});

// Initialize period dates on page load
updatePeriodDates();

// Load form state on page load
document.addEventListener('DOMContentLoaded', function() {
    loadFormState();
});

// Clear form state when form is successfully submitted
submitBtn.addEventListener('click', function() {
    clearFormState();
});

// Calculate button handler
calculateBtn.addEventListener('click', function() {
    const machineryId = machinerySelect.value;
    const supplierId = supplierSelect.value;
    const startDate = periodStart.value;
    const endDate = periodEnd.value;
    
    if (!machineryId || !supplierId || !startDate || !endDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: '<?php echo e(__('Please fill all required fields')); ?>'
        });
        return;
    }
    
    // Disable button during calculation
    this.disabled = true;
    this.innerHTML = '<i class="ti ti-loader-2 me-2"></i> <?php echo e(__('Calculating...')); ?>';
    
    fetch('<?php echo e(route('machinery-payment.store-ajax')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
        },
        body: JSON.stringify({
            machinery_id: machineryId,
            supplier_id: supplierId,
            period_start: startDate,
            period_end: endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reset button
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = '<i class="ti ti-calculator me-2"></i> <?php echo e(__('Calculate from Ledger')); ?>';
        
        if (data.success) {
            // Update results display
            const resultsContainer = document.getElementById('calculationResults');
            
            // Format currency values (assuming currency symbol is Rs.)
            const credits = parseFloat(data.data.credits) || 0;
            const debits = parseFloat(data.data.debits) || 0;
            const netPayable = parseFloat(data.data.net_payable) || 0;
            const entryCount = data.data.audit_snapshot?.entry_count || 0;
            
            document.getElementById('creditsValue').textContent = 'Rs. ' + credits.toFixed(2);
            document.getElementById('debitsValue').textContent = 'Rs. ' + debits.toFixed(2);
            document.getElementById('netPayableValue').textContent = 'Rs. ' + netPayable.toFixed(2);
            document.getElementById('entryCountValue').textContent = entryCount;
            
            resultsContainer.style.display = 'block';
            
            // Scroll to results
            resultsContainer.scrollIntoView({ behavior: 'smooth' });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || '<?php echo e(__('Error creating payment request')); ?>'
            });
        }
    })
    .catch(error => {
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = '<i class="ti ti-calculator me-2"></i> <?php echo e(__('Calculate from Ledger')); ?>';
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo e(__('Error')); ?>: ' + error.message
        });
    });
});

// Submit button handler
submitBtn.addEventListener('click', function() {
    window.location.href = '/machinery/payment-requests';
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/machinery-payment/create.blade.php ENDPATH**/ ?>