<?php $__env->startSection('page-title'); ?>
   <?php echo e(__('Manage Activities')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
    <?php echo e(__('Activities')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>
    <div class="d-flex">
        <?php echo $__env->yieldPushContent('addButtonHook'); ?>
        <a href="<?php echo e(url()->previous()); ?>" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

       </a>
        
             <a data-size="xl" data-url="<?php echo e(route('activities.create')); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create')); ?>" data-title="<?php echo e(__('Create Activity')); ?>" class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
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

<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/activities/index.blade.php ENDPATH**/ ?>