<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Manage Machinery')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
  <?php echo e(__('Machinery')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>
    <div class="d-flex">
        <?php echo $__env->yieldPushContent('addButtonHook'); ?>
       <?php if (app('laratrust')->hasPermission('machinery import')) : ?>--}}
<!--            <a href="#"  class="btn btn-sm btn-primary me-2" data-ajax-popup="true" data-title="<?php echo e(__('Machinery Import')); ?>" data-url="<?php echo e(route('machineries.file.import')); ?>"  data-toggle="tooltip" title="<?php echo e(__('Import')); ?>"><i class="ti ti-file-import"></i>
            </a>-->
      <?php endif; // app('laratrust')->permission ?>
      
    
      
       <a href="<?php echo e(route('projects.show', getActiveProject())); ?>" class="btn btn-sm btn-light border me-2">
    <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

</a>


      <?php if (app('laratrust')->hasPermission('machinery create')) : ?>
            <a data-size="xl" data-url="<?php echo e(route('machineries.create')); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create')); ?>" data-title="<?php echo e(__('Create Machinery')); ?>"  class="btn btn-sm btn-primary">
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
        <div class="col-xl-12">
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

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/machineries/index.blade.php ENDPATH**/ ?>