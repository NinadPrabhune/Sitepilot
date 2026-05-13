<?php $__env->startSection('page-title', __('Machinery Payment Requests')); ?>
<?php $__env->startSection('page-breadcrumb', __('Machinery,Payment Requests')); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

    </a>
    <a href="<?php echo e(route('machinery-payment.create')); ?>" class="btn btn-sm bg-primary text-white">
        <i class="ti ti-plus text-white"></i> <?php echo e(__('Create Request')); ?>

    </a>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('css'); ?>
<?php echo $__env->make('layouts.includes.datatable-css', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end">
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('status', __('Status'), ['class' => 'form-label'])); ?>

                            <select class="form-select" name="status_filter" id="statusFilter">
                                <option value=""><?php echo e(__('All Statuses')); ?></option>
                                <option value="draft"><?php echo e(__('Draft')); ?></option>
                                <option value="submitted"><?php echo e(__('Submitted')); ?></option>
                                <option value="verified"><?php echo e(__('Verified')); ?></option>
                                <option value="approved"><?php echo e(__('Approved')); ?></option>
                                <option value="locked"><?php echo e(__('Locked')); ?></option>
                                <option value="paid"><?php echo e(__('Paid')); ?></option>
                                <option value="rejected"><?php echo e(__('Rejected')); ?></option>
                                <option value="hold"><?php echo e(__('Hold')); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('machinery', __('Machinery'), ['class' => 'form-label'])); ?>

                            <select class="form-select" name="machinery_filter" id="machineryFilter">
                                <option value=""><?php echo e(__('All Machinery')); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $machineries ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $machinery): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($machinery->id); ?>"><?php echo e($machinery->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('start_date', __('Start Date'), ['class' => 'form-label'])); ?>

                            <?php echo e(Form::date(
                            'start_date',
                            request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                            ['class' => 'form-control', 'id' => 'dateFrom', 'placeholder' => 'Select Date']
                            )); ?>

                        </div>
                    </div>

                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('end_date', __('End Date'), ['class' => 'form-label'])); ?>

                            <?php echo e(Form::date(
                            'end_date',
                            request('end_date') ?? \Carbon\Carbon::now()->toDateString(),
                            ['class' => 'form-control', 'id' => 'dateTo', 'placeholder' => 'Select Date']
                            )); ?>

                        </div>
                    </div>
                    
                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm bg-primary me-1" data-bs-toggle="tooltip" title="<?php echo e(__('Apply')); ?>"
                           id="applyfilter" data-original-title="<?php echo e(__('apply')); ?>">
                            <span class="btn-inner--icon"><i class="ti ti-search text-white"></i></span>
                        </a>
                        <a href="#!" class="btn btn-sm bg-danger" data-bs-toggle="tooltip"
                           title="<?php echo e(__('Reset')); ?>" id="clearfilter" data-original-title="<?php echo e(__('Reset')); ?>">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <?php echo e($dataTable->table(['width' => '100%'])); ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('layouts.includes.datatable-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo e($dataTable->scripts()); ?>

<script>
// Action functions for DataTable buttons
async function submitRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Submit Payment Request?',
        text: 'Do you want to submit this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/submit`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Submitted!',
                        'Request submitted successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function verifyRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Verify Payment Request?',
        text: 'Do you want to verify this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, verify it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/verify`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Verified!',
                        'Request verified successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function approveRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Approve Payment Request?',
        text: 'This will lock period and link ledger entries. Do you want to approve this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/approve`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Approved!',
                        'Request approved successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function lockRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Lock Payment Request?',
        text: 'This will freeze the payment period and lock all linked ledger entries to prevent any modifications. The payment amounts will become final and ready for processing. Do you want to lock this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, lock it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/lock`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Locked!',
                        'Request locked successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function createMachineryPayment(paymentRequestId) {
    // Load modal content via AJAX (same as createErpPayment)
    try {
        const response = await fetch(`/machinery/payment-requests/${paymentRequestId}/payment-modal`, {
            headers: {
                'Accept': 'text/html',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const modalHtml = await response.text();
        
        // Create and show modal
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = `
            <div class="modal fade" id="machineryPaymentModal" tabindex="-1" aria-labelledby="machineryPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="machineryPaymentModalLabel">
                                <i class="ti ti-building-factory-2 me-2"></i>Create Machinery Payment
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        ${modalHtml}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modalContainer);
        
        // Initialize and show modal
        const modal = new bootstrap.Modal(document.getElementById('machineryPaymentModal'));
        modal.show();
        
        // Clean up modal after hidden
        document.getElementById('machineryPaymentModal').addEventListener('hidden.bs.modal', function () {
            document.body.removeChild(modalContainer);
        });
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load payment form. Please try again.'
        });
    }
}

// Global function for submitting machinery payment
window.submitMachineryPayment = function() {
    const form = document.getElementById('machineryPaymentForm');
    if (!form) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment form not found'
        });
        return;
    }
    
    const formData = new FormData(form);
    
    // Basic validation
    const amount = formData.get('amount');
    const paymentDate = formData.get('payment_date');
    const paymentMode = formData.get('payment_mode');
    const paymentProof = formData.get('payment_proof');
    
    if (!amount || !paymentDate || !paymentMode || !paymentProof || paymentProof.size === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please fill in all required fields'
        });
        return;
    }
    
    // Validate amount
    if (parseFloat(amount) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Amount must be greater than 0'
        });
        return;
    }
    
    // Get remaining balance from hidden input
    const remainingBalanceInput = document.getElementById('remaining-balance');
    const remainingBalance = remainingBalanceInput ? parseFloat(remainingBalanceInput.value) : 0;
    
    // Validate amount against remaining balance
    if (parseFloat(amount) > remainingBalance) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: `Amount cannot be greater than Remaining Balance (${remainingBalance.toFixed(2)})`
        });
        return;
    }
    
    // Additional server-side validation check
    if (parseFloat(amount) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Amount must be greater than 0'
        });
        return;
    }
    
    // Show loading
    const submitBtn = document.querySelector('#machineryPaymentModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
    
    // Get payment request ID from form
    const paymentRequestId = formData.get('payment_request_id');
    
    // Submit via AJAX
    $.ajax({
        url: `/machinery/payment-requests/${paymentRequestId}/create-erp-payment`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Machinery payment created successfully'
                });
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('machineryPaymentModal')).hide();
                // Reload page to show updated payment history
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to create payment'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to create payment';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        },
        complete: function() {
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
};
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/machinery-payment/index.blade.php ENDPATH**/ ?>