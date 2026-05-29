<?php if (app('laratrust')->hasPermission('purchase-invoice show')) : ?>
<div class="action-btn me-2">
    <a href="<?php echo e(route('payment-request.show', $pr->id)); ?>"
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip" title="<?php echo e(__('View')); ?>">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('manage-payment create')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pr->isPending()): ?>
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-primary"
       data-url="<?php echo e(route('payment-request.approval', $pr->id)); ?>"
       data-ajax-popup="true" data-size="lg"
       data-bs-toggle="tooltip" title="<?php echo e(__('Approve')); ?>"
       data-title="<?php echo e(__('Payment Request Approval')); ?>">
        <i class="ti ti-check text-white"></i>
    </a>
</div>
<?php elseif($pr->canMakePayment()): ?>
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-info"
       data-url="<?php echo e(route('payments-module.create-from-payment-request', $pr->id)); ?>"
       data-ajax-popup="true" data-size="lg"
       data-bs-toggle="tooltip" title="<?php echo e(__('Make Payment')); ?>"
       data-title="<?php echo e(__('Make Payment')); ?>">
        <i class="ti ti-cash text-white"></i>
    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/payment-request/list-action.blade.php ENDPATH**/ ?>