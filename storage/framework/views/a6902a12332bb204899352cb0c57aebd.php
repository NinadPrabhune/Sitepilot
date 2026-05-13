<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Manage Indents')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('Indents')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

       </a>
    <!-- <button id="exportSelectedIndent" class="btn btn-sm btn-primary me-2">
        <i class="ti ti-download"></i> <?php echo e(__('Export Selected')); ?>

    </button> -->
    <?php if (app('laratrust')->hasPermission('indent create')) : ?>
    <a data-size="xxl" data-url="<?php echo e(route('indent.create')); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create')); ?>" data-title="<?php echo e(__('Create Indent')); ?>" class="btn btn-sm btn-primary">
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
    $('#indents-table').DataTable().draw();
});

// Reload table when supplier filter changes
$('#supplier_filter').change(function(){
    $('#indents-table').DataTable().ajax.reload();
});

// Handle select all checkbox
$(document).on('change','#select-all-rows',function(){
    $('.row-checkbox').prop('checked',$(this).prop('checked'));
});

// Handle Export Selected button
$(document).on('click','#exportSelectedIndent',function(){
    let ids = [];

    $('.row-checkbox:checked').each(function(){
        ids.push($(this).val());
    });

    if(ids.length === 0){
        alert('Please select at least one Indent');
        return;
    }

    
    // Debug log - Laravel log (via AJAX)
    $.ajax({
        url: "<?php echo e(route('indent.debug-log')); ?>",
        type: "POST",
        data: {
            _token: "<?php echo e(csrf_token()); ?>",
            ids: ids,
            action: 'export_indent'
        },
        success: function(response) {
        },
        error: function(xhr) {
        }
    });

    window.location.href = "/export-selected?model=App\\Models\\Indent&ids=" + ids.join(',');
});

// Update clear filter to also clear supplier
$(document).on('click', '#clearfilter', function() {
    $('input[name=start_date]').val('');
    $('input[name=end_date]').val('');
    $('#supplier_filter').val('');
    $('#indents-table').DataTable().draw();
});

</script>

<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/indent/index.blade.php ENDPATH**/ ?>