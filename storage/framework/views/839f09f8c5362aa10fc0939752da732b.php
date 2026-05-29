<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Manage Purchase Orders')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('Purchase Orders')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

       </a>
    <!-- <button id="exportSelectedPo" class="btn btn-sm btn-primary me-2">
        <i class="ti ti-download"></i> <?php echo e(__('Export Selected')); ?>

    </button> -->
    <?php if (app('laratrust')->hasPermission('purchase-order create')) : ?>
    <a data-size="xxl" data-url="<?php echo e(route('purchase-order.create')); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create')); ?>" data-title="<?php echo e(__('Create Purchase Order')); ?>" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i>
    </a>
    <?php endif; // app('laratrust')->permission ?>
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
                    

                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('start_date', __('Start Date'), ['class' => 'form-label'])); ?>

                            <?php echo e(Form::date(
                            'start_date',
                            request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                            ['class' => 'form-control', 'placeholder' => 'Select Date']
                            )); ?>

                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('end_date', __('End Date'), ['class' => 'form-label'])); ?>

                            <?php echo e(Form::date(
                            'end_date',
                            request('end_date') ?? \Carbon\Carbon::now()->toDateString(),
                            ['class' => 'form-control', 'placeholder' => 'Select Date']
                            )); ?>

                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            <?php echo e(Form::label('supplier_filter', __('Supplier'), ['class' => 'form-label'])); ?>

                            <select id="supplier_filter" class="form-select" name="supplier_filter">
                                <option value=""><?php echo e(__('All Suppliers')); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($id); ?>"><?php echo e($name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="<?php echo e(__('Apply')); ?>"
                           id="applyfilter" data-original-title="<?php echo e(__('apply')); ?>">
                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                        </a>
                        <a href="#!" class="btn btn-sm btn-danger " data-bs-toggle="tooltip"
                           title="<?php echo e(__('Reset')); ?>" id="clearfilter" data-original-title="<?php echo e(__('Reset')); ?>">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off "></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <?php echo e($dataTable->table(['width' => '100%'])); ?>

                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('layouts.includes.datatable-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo e($dataTable->scripts()); ?>

<script>
// Filter functionality
$(document).on('click', '#applyfilter', function() {
    $('#purchase-orders-table').DataTable().draw();
});

// Reload table when supplier filter changes
$('#supplier_filter').change(function(){
    $('#purchase-orders-table').DataTable().ajax.reload();
});

// Handle select all checkbox
$(document).on('change','#select-all-rows',function(){
    $('.row-checkbox').prop('checked',$(this).prop('checked'));
});

// Handle Export Selected button
$(document).on('click','#exportSelectedPo',function(){
    let ids = [];

    $('.row-checkbox:checked').each(function(){
        ids.push($(this).val());
    });

    if(ids.length === 0){
        alert('Please select at least one PO');
        return;
    }

    
    // Debug log - Laravel log (via AJAX)
    $.ajax({
        url: "<?php echo e(route('purchase-order.debug-log')); ?>",
        type: "POST",
        data: {
            _token: "<?php echo e(csrf_token()); ?>",
            ids: ids,
            action: 'export_po'
        },
        success: function(response) {
        },
        error: function(xhr) {
        }
    });

    window.location.href = "/export-selected?model=App\\Models\\PurchaseOrder&ids=" + ids.join(',');
});

// Update clear filter to also clear supplier
$(document).on('click', '#clearfilter', function() {
    $('input[name=start_date]').val('');
    $('input[name=end_date]').val('');
    $('#supplier_filter').val('');
    $('#purchase-orders-table').DataTable().draw();
});

// Handle payment request status click
$(document).on('click', '.payment-request-status-btn', function() {
    const requestId = $(this).data('request-id');
    const poId = $(this).data('po-id');
    
    // Reset modal content to loading state
    $('#paymentRequestDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    // Show modal
    $('#paymentRequestStatusModal').modal('show');
    
    // Fetch payment request details
    $.ajax({
        url: "<?php echo e(route('purchase-order.payment-request-details')); ?>",
        type: "GET",
        data: {
            request_id: requestId,
            po_id: poId
        },
        success: function(response) {
            let detailsHtml = '';
            
            if (response.status === 'approved') {
                detailsHtml = `
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Approved Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.approved_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Payment Paid Against Request')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.paid_amount}</div>
                    </div>
                `;
            } else if (response.status === 'partially_approved') {
                detailsHtml = `
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Requested Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.requested_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Approved Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.approved_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Payment Paid Against Request')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.paid_amount}</div>
                    </div>
                `;
            } else if (response.status === 'rejected') {
                detailsHtml = `
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Requested Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.requested_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Rejection Reason')); ?></label>
                        <div class="form-control-plaintext text-danger">${response.rejection_reason || '-'}</div>
                    </div>
                `;
            } else if (response.status === 'pending') {
                detailsHtml = `
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Requested Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.requested_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Status')); ?></label>
                        <div class="form-control-plaintext"><span class="badge bg-warning"><?php echo e(__('Pending Approval')); ?></span></div>
                    </div>
                `;
            } else {
                detailsHtml = `
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Requested Amount')); ?></label>
                        <div class="form-control-plaintext fw-bold">${response.requested_amount}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><?php echo e(__('Status')); ?></label>
                        <div class="form-control-plaintext">${response.status_label}</div>
                    </div>
                `;
            }
            
            $('#paymentRequestDetails').html(detailsHtml);
        },
        error: function(xhr) {
            let errorMessage = '<?php echo e(__('Failed to load payment request details')); ?>';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            $('#paymentRequestDetails').html(`
                <div class="alert alert-danger">
                    ${errorMessage}
                </div>
            `);
            console.error('Error loading payment request details:', xhr);
        }
    });
});

</script>
<?php echo $__env->make('purchase-order.modal.advance-request', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<!-- Payment Request Status Modal -->
<div class="modal fade" id="paymentRequestStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo e(__('Payment Request Details')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="paymentRequestDetails">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo e(__('Close')); ?></button>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/purchase-order/index.blade.php ENDPATH**/ ?>