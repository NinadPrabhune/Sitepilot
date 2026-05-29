<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Payment Request Details')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('Payment Request')); ?>, <?php echo e(__('Details')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-action'); ?>
<a href="<?php echo e(route('payment-request.index')); ?>" class="btn btn-sm btn-light border">
    <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <div class="col-xl-12">

        <!-- Payment Request Summary -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Payment Request #')); ?><?php echo e($paymentRequest->id); ?></h5>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->status === 'pending'): ?>
                    <span class="badge bg-warning text-dark"><?php echo e(__('Pending')); ?></span>
                <?php elseif($paymentRequest->status === 'approved'): ?>
                    <span class="badge bg-success"><?php echo e(__('Approved')); ?></span>
                <?php elseif($paymentRequest->status === 'partially_approved'): ?>
                    <span class="badge bg-info text-dark"><?php echo e(__('Partial')); ?></span>
                <?php elseif($paymentRequest->status === 'rejected'): ?>
                    <span class="badge bg-danger"><?php echo e(__('Rejected')); ?></span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?php echo e(ucfirst($paymentRequest->status)); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong><?php echo e(__('Request Type')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isPoAdvance()): ?>
                            <span class="badge bg-primary">PO Advance</span>
                        <?php else: ?>
                            <span class="badge bg-info">Invoice Payment</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Invoice/PO')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->purchase_invoice_id): ?>
                            <a href="<?php echo e(route('purchase-invoice.show', $paymentRequest->purchase_invoice_id)); ?>" target="_blank">
                                <?php echo e($paymentRequest->invoice?->invoice_number ?? '-'); ?>

                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('purchase-order.show', $paymentRequest->po_id)); ?>" target="_blank">
                                <?php echo e($paymentRequest->po?->po_number ?? '-'); ?>

                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Supplier')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isPoAdvance()): ?>
                            <?php echo e($paymentRequest->po?->supplier?->name ?? '-'); ?>

                        <?php else: ?>
                            <?php echo e($paymentRequest->invoice?->supplier?->name ?? '-'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Site')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isPoAdvance()): ?>
                            <?php echo e($paymentRequest->po?->site?->name ?? '-'); ?>

                        <?php else: ?>
                            <?php echo e($paymentRequest->invoice?->site?->name ?? '-'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><?php echo e(__('Payment Terms and Conditions (PO)')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isPoAdvance()): ?>
                            <?php echo e($paymentRequest->po?->payment_terms_conditions ?? '-'); ?>

                        <?php else: ?>
                            <?php echo e($paymentRequest->invoice?->purchaseOrder?->payment_terms_conditions ?? '-'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Payment Date')); ?>:</strong><br>
                        <?php echo e($paymentRequest->payment_date ? \Carbon\Carbon::parse($paymentRequest->payment_date)->format('d M Y') : '-'); ?>

                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Status')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->status === 'pending'): ?>
                            <span class="badge bg-warning text-dark"><?php echo e(__('Pending')); ?></span>
                        <?php elseif($paymentRequest->status === 'approved'): ?>
                            <span class="badge bg-success"><?php echo e(__('Approved')); ?></span>
                        <?php elseif($paymentRequest->status === 'partially_approved'): ?>
                            <span class="badge bg-info text-dark"><?php echo e(__('Partial')); ?></span>
                        <?php elseif($paymentRequest->status === 'rejected'): ?>
                            <span class="badge bg-danger"><?php echo e(__('Rejected')); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo e(ucfirst($paymentRequest->status)); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong><?php echo e(__('Requested Amount')); ?>:</strong><br>
                        <span class="h6">₹<?php echo e(format_indian_currency($paymentRequest->requested_amount)); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Approved Amount')); ?>:</strong><br>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isPending()): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            <span class="h6 text-success">₹<?php echo e(format_indian_currency($paymentRequest->approved_amount ?? 0)); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Requested By')); ?>:</strong><br>
                        <?php echo e($paymentRequest->requestedBy?->name ?? '-'); ?>

                    </div>
                    <div class="col-md-3">
                        <strong><?php echo e(__('Created At')); ?>:</strong><br>
                        <?php echo e($paymentRequest->created_at->format('d M Y, h:i A')); ?>

                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->remarks): ?>
                <div class="row">
                    <div class="col-md-12">
                        <strong><?php echo e(__('Remarks')); ?>:</strong><br>
                        <?php echo e($paymentRequest->remarks); ?>

                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Financial Snapshot (Only if approved) -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->hasFinancialSnapshot() && !$paymentRequest->isPending()): ?>
        <div class="card shadow-sm mb-4 border-info">
            <div class="card-header bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-info">
                    <i class="ti ti-camera"></i> <?php echo e(__('Financial Snapshot (Captured at Approval)')); ?>

                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <small class="text-muted d-block"><?php echo e(__('Net Payable (At Approval)')); ?></small>
                        <strong class="h5">₹<?php echo e(format_indian_currency($paymentRequest->net_payable_snapshot)); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block"><?php echo e(__('Advance Used (At Approval)')); ?></small>
                        <strong class="h5">₹<?php echo e(format_indian_currency($paymentRequest->advance_used_snapshot)); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block"><?php echo e(__('Already Paid (At Approval)')); ?></small>
                        <strong class="h5">₹<?php echo e(format_indian_currency($paymentRequest->paid_amount_snapshot)); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Rejection Reason -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->isRejected() && $paymentRequest->rejection_reason): ?>
        <div class="card shadow-sm mb-4 border-danger">
            <div class="card-header bg-danger bg-opacity-10">
                <h5 class="mb-0 text-danger">
                    <i class="ti ti-alert-circle"></i> <?php echo e(__('Rejection Reason')); ?>

                </h5>
            </div>
            <div class="card-body">
                <?php echo e($paymentRequest->rejection_reason); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approvedBy): ?>
                <div class="mt-2 text-muted small">
                    <?php echo e(__('Rejected by')); ?>: <?php echo e($paymentRequest->approvedBy->name); ?>

                    <?php echo e($paymentRequest->approved_at ? \Carbon\Carbon::parse($paymentRequest->approved_at)->format(' d M Y, h:i A') : ''); ?>

                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Approval Audit Trail -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approval_history && is_array($paymentRequest->approval_history) && count($paymentRequest->approval_history) > 0): ?>
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0 text-primary">
                    <i class="ti ti-history"></i> <?php echo e(__('Approval Audit Trail')); ?>

                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $paymentRequest->approval_history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="timeline-item <?php if($index === count($paymentRequest->approval_history) - 1): ?> timeline-item-last <?php endif; ?>">
                        <div class="timeline-marker <?php if($history['action'] === 'approved'): ?> bg-success <?php elseif($history['action'] === 'rejected'): ?> bg-danger <?php else: ?> bg-warning <?php endif; ?>"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="text-<?php echo e($history['action'] === 'approved' ? 'success' : ($history['action'] === 'rejected' ? 'danger' : 'warning')); ?>">
                                        <?php echo e(ucfirst($history['action'])); ?>

                                    </strong>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($history['role'])): ?>
                                    <span class="badge bg-light text-dark ms-2"><?php echo e(ucfirst($history['role'])); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($history['timestamp'])): ?>
                                    <?php echo e(\Carbon\Carbon::parse($history['timestamp'])->format('d M Y, h:i A')); ?>

                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </small>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($history['from_status']) && isset($history['to_status'])): ?>
                            <div class="small text-muted mt-1">
                                <?php echo e(ucfirst($history['from_status'])); ?> → <?php echo e(ucfirst($history['to_status'])); ?>

                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($history['remarks']) && $history['remarks']): ?>
                            <div class="small mt-1">
                                <em>"<?php echo e($history['remarks']); ?>"</em>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($history['reason']) && $history['reason']): ?>
                            <div class="small mt-1">
                                <em><?php echo e($history['reason']); ?></em>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Payments Made -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->payments && $paymentRequest->payments->count() > 0): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?php echo e(__('Payments Made')); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Payment No')); ?></th>
                                <th><?php echo e(__('Date')); ?></th>
                                <th><?php echo e(__('Amount')); ?></th>
                                <th><?php echo e(__('Mode')); ?></th>
                                <th><?php echo e(__('Reference')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $totalPaid = 0; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $paymentRequest->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $totalPaid += $payment->amount; ?>
                            <tr>
                                <td>
                                    <a href="<?php echo e(route('payments-module.edit', $payment->id)); ?>" target="_blank">
                                        <?php echo e($payment->payment_number); ?>

                                    </a>
                                </td>
                                <td><?php echo e(\Carbon\Carbon::parse($payment->payment_date)->format('d M Y')); ?></td>
                                <td><span class="text-success fw-bold">₹<?php echo e(format_indian_currency($payment->amount)); ?></span></td>
                                <td><?php echo e($payment->mode ?? '-'); ?></td>
                                <td><?php echo e($payment->reference_number ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2"><?php echo e(__('Total Paid')); ?></th>
                                <td colspan="3"><span class="fw-bold text-success">₹<?php echo e(format_indian_currency($totalPaid)); ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/payment-request/show.blade.php ENDPATH**/ ?>