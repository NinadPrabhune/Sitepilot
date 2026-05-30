<!-- 
<?php if (app('laratrust')->hasPermission('grn show')) : ?>
<div class="action-btn me-2">
    <a href="<?php echo e(route('grn.show', $grn->id)); ?>"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View')); ?>"><i class="ti ti-eye text-white"></i></a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('grn edit')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($grn->status === 'Pending'): ?>
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center btn-primary" data-url="<?php echo e(route('grn.edit', $grn->id)); ?>"
       data-ajax-popup="true" data-size="lg" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Edit')); ?>"
       data-title="<?php echo e(__('Edit GRN')); ?>">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<div class="action-btn me-2">
    <a href="<?php echo e(route('grn.print', $grn->id)); ?>" target="_blank"
       class="mx-3 btn btn-sm align-items-center btn-secondary"
       data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Print')); ?>"><i class="ti ti-printer text-white"></i></a>
</div>

<?php if (app('laratrust')->hasPermission('grn delete')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($grn->status === 'Pending'): ?>
<div class="action-btn me-2">
    <?php echo Form::open([
    'method' => 'DELETE',
    'route' => ['grn.destroy', $grn->id],
    'id' => 'delete-form-' . $grn->id,
    ]); ?>

    <a href="#" class="mx-3 btn btn-sm align-items-center btn-danger show_confirm" data-bs-toggle="tooltip"
       data-bs-original-title="<?php echo e(__('Delete')); ?>"><i class="ti ti-trash text-white"></i></a>
    <?php echo Form::close(); ?>

</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?> -->


<?php if (app('laratrust')->hasPermission('grn show')) : ?>
<!--                                    <a href="<?php echo e(route('grn.show', $grn->id)); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View GRN Details')); ?>" title="<?php echo e(__('View GRN Details')); ?>" aria-label="<?php echo e(__('View GRN')); ?>">
    <i class="ti ti-eye"></i>
</a>-->
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('grn edit')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$grn->hasInvoice() && !$grn->is_locked): ?>
<a data-size="xxl" data-url="<?php echo e(route('grn.edit', $grn->id)); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Edit GRN')); ?>" title="<?php echo e(__('Edit GRN')); ?>" data-title="<?php echo e(__('Edit GRN')); ?>" class="btn btn-sm btn-primary" aria-label="<?php echo e(__('Edit GRN Details')); ?>">
    <i class="ti ti-edit"></i>
</a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('grn print')) : ?>
<a href="<?php echo e(route('grn.print', $grn->id)); ?>" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Print GRN')); ?>" title="<?php echo e(__('Print GRN')); ?>" aria-label="<?php echo e(__('Print GRN Document')); ?>">
    <i class="ti ti-printer"></i>
</a>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('purchase-invoice create')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($grn->hasInvoice()): ?>
<a href="<?php echo e(route('purchase-invoice.show', $grn->getInvoice()->id)); ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('View Invoice')); ?>" title="<?php echo e(__('View Invoice')); ?>" aria-label="<?php echo e(__('View Purchase Invoice')); ?>">
    <i class="ti ti-file-invoice"></i>
</a>

<?php else: ?>
<button class="btn btn-sm btn-warning create-invoice" data-id="<?php echo e($grn->id); ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Create Invoice from GRN')); ?>" title="<?php echo e(__('Create Invoice from GRN')); ?>" aria-label="<?php echo e(__('Create Purchase Invoice')); ?>">
    <i class="ti ti-file-invoice"></i>
</button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('grn delete')) : ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$grn->hasInvoice() && !$grn->is_locked): ?>
<form method="POST" action="<?php echo e(route('grn.destroy', $grn->id)); ?>" style="display:inline-block;">
    <?php echo csrf_field(); ?>
    <?php echo method_field('DELETE'); ?>
    <button type="button" class="btn btn-sm btn-danger confirm-delete" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Delete GRN')); ?>" title="<?php echo e(__('Delete GRN')); ?>" aria-label="<?php echo e(__('Delete GRN')); ?>">
        <i class="ti ti-trash"></i>
    </button>
</form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; // app('laratrust')->permission ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/grn/action.blade.php ENDPATH**/ ?>