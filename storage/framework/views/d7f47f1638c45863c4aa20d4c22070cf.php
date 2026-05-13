<?php if (app('laratrust')->hasPermission('machinery show')) : ?>
    <div class="action-btn me-2">
        <a href="<?php echo e(route('machineries.show', $machinery->id)); ?>"
            class="mx-3 btn btn-sm align-items-center bg-warning"
            data-bs-toggle="tooltip" title="<?php echo e(__('View')); ?>"><i class="ti ti-eye text-white"></i></a>
    </div>
<?php endif; // app('laratrust')->permission ?>
<?php if (app('laratrust')->hasPermission('machinery edit')) : ?>
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm  align-items-center bg-info" data-url="<?php echo e(route('machineries.edit', $machinery->id)); ?>"
            data-ajax-popup="true" data-size="xl " data-bs-toggle="tooltip" title="<?php echo e(__('Edit')); ?>"
            data-title="<?php echo e(__('Edit Machinery')); ?>">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>
<?php endif; // app('laratrust')->permission ?>


<?php if (app('laratrust')->hasPermission('machinery transfer')) : ?>
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-success"
       data-url="<?php echo e(route('general_transfer.create', ['transfer_type' => 'machinery', 'machinery_id' => $machinery->id])); ?>"
       data-ajax-popup="true"
       data-size="xl"
       data-bs-toggle="tooltip"
       title="<?php echo e(__('Create Machinery Transfer')); ?>"
       data-title="<?php echo e(__('New Machinery Transfer')); ?>">
        <i class="ti ti-arrows-left-right text-white"></i>
    </a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('machinery-dpr create')) : ?>
<!--<div class="action-btn me-2">
    <a href="<?php echo e(route('machinery.dpr.create', $machinery)); ?>"
       class="mx-3 btn btn-sm align-items-center bg-primary"
       data-bs-toggle="tooltip"
       title="<?php echo e(__('Create DPR')); ?>">
        <i class="ti ti-file-plus text-white"></i>
    </a>
</div>-->
<?php endif; // app('laratrust')->permission ?> 

<?php if (app('laratrust')->hasPermission('machinery delete')) : ?>
    <div class="action-btn me-2">
        <?php echo Form::open([
            'method' => 'DELETE',
            'route' => ['machineries.destroy', $machinery->id],
            'id' => 'delete-form-' . $machinery->id,
        ]); ?>

        <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para show_confirm bg-danger" data-bs-toggle="tooltip"
            title="<?php echo e(__('Delete')); ?>"><i class="ti ti-trash text-white"></i></a>
        <?php echo Form::close(); ?>

    </div>
<?php endif; // app('laratrust')->permission ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/machineries/action.blade.php ENDPATH**/ ?>