 <?php if (app('laratrust')->hasPermission('activity show')) : ?> 
 <div class="action-btn me-2">
    <a href="<?php echo e(route('activities.show', $activity->id)); ?>" class="mx-3 btn btn-sm align-items-center bg-warning" 
        data-bs-toggle="tooltip" title="<?php echo e(__('View')); ?>">
        <i class="ti ti-eye text-white"></i>
    </a>
</div> 
 <?php endif; // app('laratrust')->permission ?> 

 <?php if (app('laratrust')->hasPermission('activity edit')) : ?> 
<div class="action-btn me-2">
    <a href="<?php echo e(route('activities.edit', $activity->id)); ?>" class="mx-3 btn btn-sm align-items-center bg-info" 
       data-bs-toggle="tooltip" title="<?php echo e(__('Edit')); ?>">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
 <?php endif; // app('laratrust')->permission ?> 

 <?php if (app('laratrust')->hasPermission('activity delete')) : ?> 
<div class="action-btn">
    <?php echo Form::open([
        'method' => 'DELETE',
        'route' => ['activities.destroy', $activity->id],
        'id' => 'delete-form-' . $activity->id,
    ]); ?>

    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger" 
       data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>">
        <i class="ti ti-trash text-white"></i>
    </a>
    <?php echo Form::close(); ?>

</div>
 <?php endif; // app('laratrust')->permission ?> 
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/activities/action.blade.php ENDPATH**/ ?>