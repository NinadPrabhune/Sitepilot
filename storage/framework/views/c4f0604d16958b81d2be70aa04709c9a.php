<?php if (app('laratrust')->hasPermission('machinery-payment manage')) : ?>
<div class="action-btn me-2">
    <a href="<?php echo e(route('machinery-payment.show', $request->id)); ?>" 
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip" title="<?php echo e(__('View')); ?>">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->status === 'draft'): ?>
    <div class="action-btn me-2">
        <button onclick="submitRequest(<?php echo e($request->id); ?>)" 
                class="mx-3 btn btn-sm align-items-center bg-success"
                data-bs-toggle="tooltip" title="<?php echo e(__('Submit')); ?>">
            <i class="ti ti-send text-white"></i>
        </button>
    </div>
<?php elseif($request->status === 'submitted'): ?>
    <div class="action-btn me-2">
        <button onclick="verifyRequest(<?php echo e($request->id); ?>)" 
                class="mx-3 btn btn-sm align-items-center bg-info"
                data-bs-toggle="tooltip" title="<?php echo e(__('Verify')); ?>">
            <i class="ti ti-check text-white"></i>
        </button>
    </div>
<?php elseif($request->status === 'verified'): ?>
    <div class="action-btn me-2">
        <button onclick="approveRequest(<?php echo e($request->id); ?>)" 
                class="mx-3 btn btn-sm align-items-center bg-success"
                data-bs-toggle="tooltip" title="<?php echo e(__('Approve')); ?>">
            <i class="ti ti-checks text-white"></i>
        </button>
    </div>
<?php elseif($request->status === 'approved'): ?>
    <div class="action-btn me-2">
        <button onclick="lockRequest(<?php echo e($request->id); ?>)" 
                class="mx-3 btn btn-sm align-items-center bg-warning"
                data-bs-toggle="tooltip" 
                title="<?php echo e(__('Lock payment period and secure ledger entries')); ?>">
            <i class="ti ti-lock text-white"></i>
        </button>
    </div>
<?php elseif($request->status === 'locked'): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('machinery_payment.enable_erp_payment_button', false)): ?>
        <div class="action-btn me-2">
            <button onclick="createMachineryPayment(<?php echo e($request->id); ?>)" 
                    class="mx-3 btn btn-sm align-items-center bg-primary"
                    data-bs-toggle="tooltip" title="<?php echo e(__('Create Machinery Payment')); ?>">
                <i class="ti ti-building-factory-2 text-white"></i>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/machinery-payment/action.blade.php ENDPATH**/ ?>