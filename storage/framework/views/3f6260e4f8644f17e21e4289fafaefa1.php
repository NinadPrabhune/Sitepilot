<?php if (app('laratrust')->hasPermission('manage-payment show')) : ?>
<div class="action-btn me-2">
    <a href="<?php echo e(route('payments-module.show', $paymentsModule->id)); ?>"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View')); ?>"><i class="ti ti-eye text-white"></i></a>
</div>
<?php endif; // app('laratrust')->permission ?>
<?php if (app('laratrust')->hasPermission('manage-payment show')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentsModule->payment_pdf): ?>
    <div class="action-btn me-2">
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_starts_with($paymentsModule->payment_pdf, 'http')): ?>
            
            <a href="<?php echo e($paymentsModule->payment_pdf); ?>" target="_blank"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View PDF')); ?>">
                <i class="ti ti-printer text-white"></i>
            </a>
        <?php elseif(file_exists(public_path($paymentsModule->payment_pdf))): ?>
            
            <a href="<?php echo e(asset($paymentsModule->payment_pdf)); ?>" target="_blank"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View PDF')); ?>">
                <i class="ti ti-printer text-white"></i>
            </a>
        <?php else: ?>
            
            <a href="<?php echo e(route('payments-module.generate-pdf', $paymentsModule->id)); ?>"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Generate PDF')); ?>">
                <i class="ti ti-file-download text-white"></i>
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php else: ?>
    <div class="action-btn me-2">
        <a href="<?php echo e(route('payments-module.generate-pdf', $paymentsModule->id)); ?>"
           class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Generate PDF')); ?>">
            <i class="ti ti-file-download text-white"></i>
        </a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>
<?php if (app('laratrust')->hasPermission('manage-payment edit')) : ?>
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm  align-items-center btn-primary" data-url="<?php echo e(route('payments-module.edit', $paymentsModule->id)); ?>"
       data-ajax-popup="true" data-size="lg " data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Edit')); ?>"
       data-title="<?php echo e(__('Edit Payment')); ?>">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>-->
<?php endif; // app('laratrust')->permission ?>
<?php if (app('laratrust')->hasPermission('manage-payment delete')) : ?>
<!--<div class="action-btn me-2">
    <?php echo Form::open([
    'method' => 'DELETE',
    'route' => ['payments-module.destroy', $paymentsModule->id],
    'id' => 'delete-form-' . $paymentsModule->id,
    ]); ?>

    <a href="#" class="mx-3 btn btn-sm  align-items-center show_confirm btn-danger" data-bs-toggle="tooltip"
       data-bs-original-title="<?php echo e(__('Delete')); ?>"><i class="ti ti-trash text-white"></i></a>
    <?php echo Form::close(); ?>

</div>-->
<?php endif; // app('laratrust')->permission ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/payments-module/action.blade.php ENDPATH**/ ?>