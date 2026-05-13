<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Machinery Ledger</h4>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" action="<?php echo e(route('ledger.index')); ?>" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="machinery_id" class="form-label">Machinery</label>
                    <select class="form-select" id="machinery_id" name="machinery_id">
                        <option value="">All Machinery</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $machineries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $machinery): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($machinery->id); ?>" <?php echo e(request('machinery_id') == $machinery->id ? 'selected' : ''); ?>>
                                <?php echo e($machinery->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo e(request('date_from') ?? date('Y-m-01')); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo e(request('date_to') ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-2">
                    <label for="entry_type" class="form-label">Entry Type</label>
                    <select class="form-select" id="entry_type" name="entry_type">
                        <option value="">All Types</option>
                        <option value="reading_credit" <?php echo e(request('entry_type') == 'reading_credit' ? 'selected' : ''); ?>>Reading Credit</option>
                        <option value="diesel_debit" <?php echo e(request('entry_type') == 'diesel_debit' ? 'selected' : ''); ?>>Diesel Debit</option>
                        <option value="maintenance_debit" <?php echo e(request('entry_type') == 'maintenance_debit' ? 'selected' : ''); ?>>Maintenance Debit</option>
                        <option value="payment_debit" <?php echo e(request('entry_type') == 'payment_debit' ? 'selected' : ''); ?>>Payment Debit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <a href="<?php echo e(route('ledger.index')); ?>" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>

            <!-- Ledger Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Machinery</th>
                            <th>Entry Type</th>
                            <th>Source</th>
                            <th>Reference ID</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Running Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ledgerEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($entry->date->format('d-M-Y')); ?></td>
                            <td><?php echo e($entry->machinery->name ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                    $typeLabels = [
                                        'reading_credit' => 'Reading Credit',
                                        'diesel_debit' => 'Diesel Debit',
                                        'maintenance_debit' => 'Maintenance Debit',
                                        'advance_debit' => 'Advance Debit',
                                        'payment_debit' => 'Payment Debit',
                                        'transfer_debit' => 'Transfer Debit',
                                    ];
                                ?>
                                <?php echo e($typeLabels[$entry->entry_type] ?? $entry->entry_type); ?>

                            </td>
                            <td>
                                <?php
                                    $sourceLabels = [
                                        'DailyProgressReport' => 'DPR',
                                        'DailyConsumptionMaster' => 'Diesel',
                                        'MaintenanceLog' => 'Maintenance',
                                        'MachineryPayment' => 'Payment',
                                        'MachineryPaymentRequest' => 'Payment Request',
                                        'GeneralTransfer' => 'Transfer',
                                    ];
                                ?>
                                <?php echo e($sourceLabels[$entry->reference_type] ?? $entry->reference_type); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->is_reversal): ?>
                                    <span class="badge bg-danger ms-1">Reversal</span>
                                <?php elseif($entry->reversed_entry_id): ?>
                                    <span class="badge bg-warning ms-1">Reversed</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->reference_id): ?>
                                    <?php
                                        $sourceRoute = match($entry->reference_type) {
                                            'DailyProgressReport' => route('daily-progress-reports.show', $entry->reference_id),
                                            'DailyConsumptionMaster' => route('daily-consumption.show', $entry->reference_id),
                                            'MaintenanceLog' => route('maintenance.show', $entry->reference_id),
                                            default => '#',
                                        };
                                    ?>
                                    <a href="<?php echo e($sourceRoute); ?>" class="btn btn-sm btn-outline-primary">
                                        #<?php echo e($entry->reference_id); ?>

                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="text-danger">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->entry_direction == 'debit'): ?>
                                    ₹<?php echo e(number_format($entry->amount, 2)); ?>

                                <?php else: ?>
                                    -
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="text-success">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->entry_direction == 'credit'): ?>
                                    ₹<?php echo e(number_format($entry->amount, 2)); ?>

                                <?php else: ?>
                                    -
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="fw-bold">₹<?php echo e(number_format($entry->running_balance, 2)); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->reference_type == 'MachineryPayment' && $entry->reference_id): ?>
                                    <a href="<?php echo e(route('machinery-payment.show', $entry->reference_id)); ?>" class="btn btn-sm btn-info">
                                        View PR
                                    </a>
                                <?php elseif($entry->reference_type == 'MachineryPaymentRequest' && $entry->reference_id): ?>
                                    <a href="<?php echo e(route('machinery-payment.show', $entry->reference_id)); ?>" class="btn btn-sm btn-info">
                                        View Request
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="9" class="text-center">No ledger entries found.</td>
                        </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo e($ledgerEntries->links()); ?>

        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/ledger/index.blade.php ENDPATH**/ ?>