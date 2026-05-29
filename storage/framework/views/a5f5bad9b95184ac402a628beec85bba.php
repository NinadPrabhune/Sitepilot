<?php if (app('laratrust')->hasPermission('purchase-order print')) : ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Approved','Partial Received','Completed','Closed','Partial'])): ?>
<div class="action-btn me-2">
<a href="<?php echo e(route('purchase-order.print-invoice', $po->id)); ?>" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" title="<?php echo e(__('Print Invoice')); ?>">
    <i class="ti ti-printer"></i>
</a><!-- comment -->
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('supplier-advance manage')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Approved'])): ?>
<!--<div class="action-btn me-2">
    <a href="<?php echo e(route('supplier-advance.create-from-po', $po->id)); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?php echo e(__('Request Advance')); ?>">
        <i class="ti ti-credit-card text-white"></i>
    </a>
</div>-->
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('purchase-order advance-request')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Approved']) && !$po->isPaymentCompleted() && !$po->hasAdvanceRequest()): ?>
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
       class="btn btn-sm btn-info po-advance-request-btn" 
       data-po-id="<?php echo e($po->id); ?>" 
       data-bs-toggle="tooltip" 
       title="<?php echo e(__('Request PO Advance')); ?>">
        <i class="ti ti-credit-card text-white"></i>
    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<!--                                    <a href="<?php echo e(route('purchase-order.print-invoice-2', $po->id)); ?>" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" title="<?php echo e(__('Print Invoice V2')); ?>">
    <i class="ti ti-printer"></i>
</a>-->


<?php if (app('laratrust')->hasPermission(['purchase-order payment','manage-payment create'])) : ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Approved','Partial Received'])): ?>
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center btn-info" 
       data-url="<?php echo e(route('payments-module.create-from-po', $po->id)); ?>" 
       data-ajax-popup="true" 
       data-size="xl" 
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Make Payment')); ?>" 
       data-title="<?php echo e(__('Make Payment')); ?>"> 
        <i class="ti ti-cash text-white"></i> 
    </a>
</div>-->

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; // app('laratrust')->permission ?>


<?php if (app('laratrust')->hasPermission('purchase-order edit')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Draft', 'Flagged']) || $po->display_status == 'Flagged - Corrected'): ?>
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
        data-size="xxl"
        data-url="<?php echo e(route('purchase-order.edit', $po->id)); ?>" 
        data-ajax-popup="true" 
        data-bs-toggle="tooltip" 
        data-bs-original-title="<?php echo e(__('Edit')); ?>" 
        data-title="<?php echo e(__('Edit Purchase Order')); ?>" 
        class="btn btn-sm btn-primary">
        <i class="ti ti-edit text-white"></i>
    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('purchase-order edit')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($po->status, ['Draft', 'Flagged', 'Partial Received']) || $po->display_status == 'Flagged - Corrected'): ?>
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
        data-url="<?php echo e(route('purchase-order.approve', $po->id)); ?>" 
        data-ajax-popup="true"
        data-bs-toggle="tooltip" 
        data-bs-original-title="<?php echo e(__('Approve')); ?>" 
        data-title="<?php echo e(__('Update Purchase Order Status')); ?>"
        class="btn btn-sm btn-success">
        <i class="ti ti-check text-white"></i>
    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('purchase-order delete')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($po->status == 'Draft' && $po->items->isEmpty()): ?>
<div class="action-btn me-2">
    <?php echo Form::open(['method' => 'DELETE', 'route' => ['purchase-order.destroy', $po->id], 'class' => 'd-inline']); ?>

    <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Delete')); ?>">
        <i class="ti ti-trash text-white"></i>
    </button>
    <?php echo Form::close(); ?>

</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/purchase-order/action.blade.php ENDPATH**/ ?>