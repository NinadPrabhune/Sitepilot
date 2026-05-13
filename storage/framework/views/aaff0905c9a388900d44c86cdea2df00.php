

<?php $__env->startSection('page-title', __('Consumption #') . ($daily_consumption->consumption_number ?? '')); ?>
<?php $__env->startSection('page-breadcrumb', __('Consumption Log,Details')); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex gap-2">
    <a href="<?php echo e(route('daily-consumption.index')); ?>" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> <?php echo e(__('Back to List')); ?>

    </a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daily_consumption->machinery_id): ?>
    <a href="<?php echo e(route('ledger.index', ['machinery_id' => $daily_consumption->machinery_id])); ?>" class="btn btn-sm btn-secondary">
        <i class="ti ti-book"></i> <?php echo e(__('View Ledger')); ?>

    </a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('css'); ?>
<?php echo $__env->make('layouts.includes.datatable-css', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<!-- <style>
    .info-card {
        border-left: 4px solid #0d6efd;
    }
    .info-card.warning {
        border-left-color: #ffc107;
    }
    .info-card.success {
        border-left-color: #198754;
    }
    .info-card.danger {
        border-left-color: #dc3545;
    }
</style> -->
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    
    <div class="col-sm-12 col-lg-8">
        <div class="card info-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="ti ti-info-circle me-2"></i><?php echo e(__('General Information')); ?></h5>
                <span class="badge bg-primary"><?php echo e(ucfirst($daily_consumption->consumption_type)); ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Consumption Number')); ?></label>
                        <div class="fw-bold"><?php echo e($daily_consumption->consumption_number); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Date')); ?></label>
                        <div class="fw-bold"><?php echo e(\Carbon\Carbon::parse($daily_consumption->consumption_date)->format('d M Y')); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Site')); ?></label>
                        <div class="fw-bold"><?php echo e(optional($daily_consumption->site)->name ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Workspace')); ?></label>
                        <div class="fw-bold"><?php echo e(optional($daily_consumption->workspace)->name ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Machinery Type')); ?></label>
                        <div class="fw-bold"><?php echo e($daily_consumption->machinery_type ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Machinery')); ?></label>
                        <div class="fw-bold"><?php echo e(optional($daily_consumption->machinery)->name ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1"><?php echo e(__('Created By')); ?></label>
                        <div class="fw-bold"><?php echo e(optional($daily_consumption->creator)->name ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daily_consumption->consumption_file): ?>
                        <label class="form-label text-muted mb-1"><?php echo e(__('Attached File')); ?></label>
                        <div>
                            <a href="<?php echo e(Storage::url($daily_consumption->consumption_file)); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="ti ti-file-download me-1"></i> <?php echo e(__('View File')); ?>

                            </a>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-list-details me-2"></i><?php echo e(__('Consumption Items')); ?></h5>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Material')); ?></th>
                                <th class="text-end"><?php echo e(__('Quantity')); ?></th>
                                <th><?php echo e(__('Unit')); ?></th>
                                <th><?php echo e(__('Remarks')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $daily_consumption->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><?php echo e(optional($detail->material)->name ?? 'N/A'); ?></td>
                                    <td class="text-end fw-bold"><?php echo e($detail->quantity); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo e($detail->unit); ?></span></td>
                                    <td><?php echo e($detail->remarks ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="ti ti-inbox fs-3 mb-2 d-block"></i>
                                        <?php echo e(__('No items recorded.')); ?>

                                    </td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-sm-12 col-lg-4">
        <div class="card info-card <?php echo e($daily_consumption->ledger_entry_id ? 'success' : 'warning'); ?> mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-receipt me-2"></i><?php echo e(__('Ledger Traceability')); ?></h5>
            </div>
            <div class="card-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daily_consumption->ledger_entry_id): ?>
                    <?php
                        $ledgerEntry = \App\Domain\Machinery\Models\MachineryLedger::find($daily_consumption->ledger_entry_id);
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ledgerEntry): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ledgerEntry->reversed_entry_id): ?>
                            <div class="alert alert-danger">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong><?php echo e(__('Reversed')); ?></strong>
                                <p class="mb-0 small mt-1"><?php echo e(__('This ledger entry has been reversed. The financial impact has been neutralized.')); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ledgerEntry->reversed_entry_id): ?>
                                    <a href="<?php echo e(route('ledger.index')); ?>#entry-<?php echo e($ledgerEntry->reversed_entry_id); ?>" class="alert-link small"><?php echo e(__('View Reversal Entry')); ?> #<?php echo e($ledgerEntry->reversed_entry_id); ?></a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="ti ti-check-circle me-2"></i>
                                <strong><?php echo e(__('System Trust: Linked')); ?></strong>
                                <p class="mb-0 small mt-1"><?php echo e(__('Ledger entry is active and posted.')); ?></p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Ledger Entry ID')); ?></label>
                            <div><code>#LED-<?php echo e($ledgerEntry->id); ?></code></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Entry Type')); ?></label>
                            <div class="fw-bold"><?php echo e(__('Debit')); ?> (<?php echo e($daily_consumption->consumption_type); ?>)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Debit Amount')); ?></label>
                            <div class="text-danger fw-bold fs-5">₹<?php echo e(number_format($ledgerEntry->amount, 2)); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Running Balance')); ?></label>
                            <div class="fw-bold">₹<?php echo e(number_format($ledgerEntry->running_balance, 2)); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Status')); ?></label>
                            <div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ledgerEntry->reversed_entry_id): ?>
                                    <span class="badge bg-danger"><?php echo e(__('Reversed')); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?php echo e(__('Posted')); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1"><?php echo e(__('Posted At')); ?></label>
                            <div class="small"><?php echo e($ledgerEntry->created_at->format('d M Y, h:i A')); ?></div>
                        </div>

                        <a href="<?php echo e(route('ledger.index', ['machinery_id' => $daily_consumption->machinery_id])); ?>" class="btn btn-primary w-100">
                            <i class="ti ti-eye me-1"></i> <?php echo e(__('View in Ledger')); ?>

                        </a>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-2"></i>
                            <strong><?php echo e(__('Ledger Entry Not Found')); ?></strong>
                            <p class="mb-0 small mt-1"><?php echo e(__('Linked ledger entry ID')); ?> <?php echo e($daily_consumption->ledger_entry_id); ?> <?php echo e(__('does not exist.')); ?></p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="ti ti-unlink me-2"></i>
                        <strong><?php echo e(__('No Ledger Entry Linked')); ?></strong>
                        <p class="mb-0 small mt-1"><?php echo e(__('This consumption has no associated ledger entry. The financial impact is not visible in the ledger.')); ?></p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('layouts.includes.datatable-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/daily-consumption/show.blade.php ENDPATH**/ ?>