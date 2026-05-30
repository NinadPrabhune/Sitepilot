<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Manage Goods Receipt Notes')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('GRN')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

       </a>
    <!-- <button id="exportSelectedGrn" class="btn btn-sm btn-primary me-2">
        <i class="ti ti-download"></i> <?php echo e(__('Export Selected')); ?>

    </button> -->
    <?php if (app('laratrust')->hasPermission('grn create')) : ?>
    <a data-size="xxl" data-url="<?php echo e(route('grn.create')); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Create GRN')); ?>" title="<?php echo e(__('Create GRN')); ?>" data-title="<?php echo e(__('Create Goods Receipt Note')); ?>" class="btn btn-sm btn-primary">
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

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel"><?php echo e(__('Create Purchase Invoice')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="invoiceForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="grn_id" id="invoice_grn_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Invoice Number')); ?></label>
                                <input type="text" name="invoice_number" id="invoice_number" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Invoice Date')); ?> <span class="text-danger">*</span></label>
                                <input type="date" name="invoice_date" id="invoice_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Supplier Invoice Number')); ?></label>
                                <input type="text" name="supplier_invoice_number" id="supplier_invoice_number" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Invoice File')); ?></label>
                                <input type="file" name="invoice_file" id="invoice_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Supplier')); ?></label>
                                <input type="text" id="invoice_supplier" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Site')); ?></label>
                                <input type="text" id="invoice_site" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('PO Number')); ?></label>
                                <input type="text" id="invoice_po" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('GRN Number')); ?></label>
                                <input type="text" id="invoice_grn" class="form-control" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label"><?php echo e(__('Assign To')); ?></label>
                                <select class="multi-select" id="invoice_assign_to" name="assign_to[]" multiple="multiple" data-placeholder="<?php echo e(__('Select Users ...')); ?>">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h5><?php echo e(__('Invoice Items')); ?></h5>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="invoiceItemsTable">
                                    <thead>
                                        <tr>
                                            <th><?php echo e(__('Material')); ?></th>
                                            <th><?php echo e(__('Qty')); ?></th>
                                            <th><?php echo e(__('Rate')); ?></th>
                                            <th><?php echo e(__('Discount')); ?></th>
                                            <th><?php echo e(__('Taxable Value')); ?></th>
                                            <th><?php echo e(__('GST')); ?></th>
                                            <th><?php echo e(__('Tax Amount')); ?></th>
                                            <th><?php echo e(__('Subtotal')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoiceItemsBody">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong><?php echo e(__('Total')); ?></strong></td>
                                            <td><span id="totalTaxableValue">0.00</span></td>
                                            <td></td>
                                            <td><span id="totalTaxAmount">0.00</span></td>
                                            <td><span id="grandTotal">0.00</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo e(__('Close')); ?></button>
                        <button type="submit" class="btn btn-primary" id="saveInvoiceBtn"><?php echo e(__('Create Invoice')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('layouts.includes.datatable-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo e($dataTable->scripts()); ?>

<script>
// Wrapper function for notifications - handles case when toastrS is not defined
function showNotification(title, message, type) {
    if (typeof toastrS === 'function') {
        toastrS(title, message, type);
    } else if (typeof toastr === 'function') {
        toastr[type === 'success' ? 'success' : 'error'](message);
    } else {
        // Fallback to browser alert
        alert((type === 'success' ? 'Success: ' : 'Error: ') + message);
    }
}

// Filter functionality
$(document).on('click', '#applyfilter', function() {
    $('#grn-table').DataTable().draw();
});

// Reload table when supplier filter changes
$('#supplier_filter').change(function(){
    $('#grn-table').DataTable().ajax.reload();
});

// Handle select all checkbox
$(document).on('change','#select-all-rows',function(){
    $('.row-checkbox').prop('checked',$(this).prop('checked'));
});

// Handle Export Selected button
$(document).on('click','#exportSelectedGrn',function(){
    let ids = [];

    $('.row-checkbox:checked').each(function(){
        ids.push($(this).val());
    });

    if(ids.length === 0){
        alert('Please select at least one GRN');
        return;
    }

    
    // Debug log - Laravel log (via AJAX)
    $.ajax({
        url: "<?php echo e(route('grn.debug-log')); ?>",
        type: "POST",
        data: {
            _token: "<?php echo e(csrf_token()); ?>",
            ids: ids,
            action: 'export_grn'
        },
        success: function(response) {
        },
        error: function(xhr) {
        }
    });

    window.location.href = "/export-selected?model=App\\Models\\Grn&ids=" + ids.join(',');
});

// Update clear filter to also clear supplier
$(document).on('click', '#clearfilter', function() {
    $('input[name=start_date]').val('');
    $('input[name=end_date]').val('');
    $('#supplier_filter').val('');
    $('#grn-table').DataTable().draw();
});

$(document).on('click', '.create-invoice', function() {
    let grnId = $(this).data('id');

    $.ajax({
        url: '/grn/' + grnId + '/invoice-data',
        type: 'GET',
        dataType: 'json',
        headers: {
            'Accept': 'application/json'
        },
        success: function(res) {
            if (res.success) {
                $('#invoice_grn_id').val(grnId);
                $('#invoice_number').val(res.next_invoice_number);
                $('#invoice_supplier').val(res.grn.supplier_name);
                $('#invoice_site').val(res.grn.site_name);
                $('#invoice_po').val(res.grn.po_number);
                $('#invoice_grn').val(res.grn.grn_number);
                $('#invoice_date').val(new Date().toISOString().split('T')[0]);

                // Initialize Choices.js for assign_to field
                var assignToSelectElement = document.getElementById('invoice_assign_to');

                if (assignToSelectElement && typeof Choices !== 'undefined') {

                    // Destroy existing Choices.js instance if it exists
                    if (window.invoiceAssignToChoices) {
                        window.invoiceAssignToChoices.destroy();
                        window.invoiceAssignToChoices = null;
                    }

                    // Get users data from the page (from the GRN index page which has users)
                    var usersData = <?php echo json_encode(getActiveProjectEmployees(), 15, 512) ?>;

                    var usersChoicesArray = Object.keys(usersData).map(function(id) {
                        return { value: id.toString(), label: usersData[id] };
                    });

                    window.invoiceAssignToChoices = new Choices(assignToSelectElement, {
                        removeItemButton: true,
                        searchEnabled: true,
                        placeholder: true,
                        placeholderValue: "<?php echo e(__('Select Users ...')); ?>",
                    });


                    window.invoiceAssignToChoices.setChoices(usersChoicesArray, 'value', 'label', true);

                    // Set assign_to from GRN
                    if (res.grn.assign_to) {
                        var assignToArray = res.grn.assign_to.split(',').map(function(id) {
                            return id.trim();
                        });
                        window.invoiceAssignToChoices.setChoiceByValue(assignToArray);
                    } else {
                    }
                }

                // Populate items
                let tbody = $('#invoiceItemsBody');
                tbody.empty();
                
                let taxType = res.grn.tax_type;
                
                res.items.forEach(function(item) {
                    let gstDisplay = '';
                    if (taxType === 'igst') {
                        gstDisplay = item.igst_rate + '% IGST';
                    } else {
                        gstDisplay = item.cgst_rate + '% CGST + ' + item.sgst_rate + '% SGST';
                    }
                    
                    let row = '<tr>' +
                        '<td>' + item.material_name + '</td>' +
                        '<td>' + item.quantity + ' ' + item.material_unit + '</td>' +
                        '<td>' + parseFloat(item.price).toFixed(2) + '</td>' +
                        '<td>' + parseFloat(item.discount_amount).toFixed(2) + '</td>' +
                        '<td>' + parseFloat(item.taxable_value).toFixed(2) + '</td>' +
                        '<td>' + gstDisplay + '</td>' +
                        '<td>' + parseFloat(item.tax_amount).toFixed(2) + '</td>' +
                        '<td>' + parseFloat(item.subtotal).toFixed(2) + '</td>' +
                    '</tr>';
                    tbody.append(row);
                });
                
                // Update totals
                $('#totalTaxableValue').text(parseFloat(res.totals.total_taxable_value).toFixed(2));
                $('#totalTaxAmount').text(parseFloat(res.totals.total_tax).toFixed(2));
                $('#grandTotal').text(parseFloat(res.totals.grand_total).toFixed(2));
                
                $('#invoiceModal').modal('show');
            } else {
                showNotification('Error', res.error || 'Error loading invoice data', 'error');
            }
        },
        error: function(xhr) {
            var errorMsg = 'Error loading invoice data';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            showNotification('Error', errorMsg, 'error');
        }
    });
});

$('#invoiceForm').on('submit', function(e) {
    e.preventDefault();

    var formData = new FormData(this);
    var $btn = $('#saveInvoiceBtn');

    // Extract assign_to values from Choices.js
    if (window.invoiceAssignToChoices) {
        var selectedValues = window.invoiceAssignToChoices.getValue(true);
        formData.delete('assign_to[]');
        selectedValues.forEach(function(value) {
            formData.append('assign_to[]', value);
        });
    }

    $btn.prop('disabled', true).text('<?php echo e(__("Creating...")); ?>');

    $.ajax({
        url: '<?php echo e(route("purchase-invoice.store-from-grn")); ?>',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        headers: {
            'Accept': 'application/json'
        },
        success: function(res) {
            if (res.success) {
                showNotification('Success', res.message || 'Invoice created successfully!', 'success');
                $('#invoiceModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                showNotification('Error', res.error || 'Error creating invoice', 'error');
                $btn.prop('disabled', false).text('<?php echo e(__("Create Invoice")); ?>');
            }
        },
        error: function(xhr) {
            var errorMsg = 'Error creating invoice';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseJSON.errors) {
                    // Handle validation errors
                    var errors = xhr.responseJSON.errors;
                    var firstError = Object.keys(errors)[0];
                    if (firstError) {
                        errorMsg = errors[firstError][0];
                    }
                }
                }
            showNotification('Error', errorMsg, 'error');
            $btn.prop('disabled', false).text('<?php echo e(__("Create Invoice")); ?>');
        }
    });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/grn/index.blade.php ENDPATH**/ ?>