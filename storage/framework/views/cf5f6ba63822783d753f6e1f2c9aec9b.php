<?php $__env->startSection('page-title', __('Payment Request #') . ($paymentRequest->id ?? '')); ?>
<?php $__env->startSection('page-breadcrumb', __('Machinery,Payment Requests,Details')); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex gap-2">
    <a href="<?php echo e(route('machinery-payment.index')); ?>" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> <?php echo e(__('Back to List')); ?>

    </a>
    <a href="<?php echo e(route('ledger.index', ['machinery_id' => $paymentRequest->machinery_id])); ?>" class="btn btn-sm btn-secondary">
        <i class="ti ti-book"></i> <?php echo e(__('View Ledger')); ?>

    </a>
</div>
<?php $__env->stopSection(); ?>


<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="ti ti-x-circle"></i> <?php echo e(__('Reject Payment Request')); ?>

                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejection_reason"><?php echo e(__('Rejection Reason')); ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejection_reason" rows="4" 
                              placeholder="<?php echo e(__('Please provide a reason for rejection...')); ?>" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo e(__('Cancel')); ?></button>
                <button type="button" class="btn btn-danger" onclick="rejectPaymentRequest()">
                    <i class="ti ti-x"></i> <?php echo e(__('Reject Payment Request')); ?>

                </button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="paymentProofUploadModal" tabindex="-1" aria-labelledby="paymentProofUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentProofUploadModalLabel">
                    <i class="ti ti-upload me-2"></i><?php echo e(__('Upload Payment Proof')); ?>

                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentProofUploadModalForm" enctype="multipart/form-data">
                    <input type="hidden" id="payment_id" name="payment_id">
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo e(__('Payment Proof File')); ?> <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="modal_payment_proof_file" name="payment_proof_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted"><?php echo e(__('PDF, JPG, PNG up to 5MB')); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo e(__('Reference Number')); ?></label>
                        <input type="text" class="form-control" id="modal_reference_number" name="reference_number" placeholder="<?php echo e(__('Enter reference number if any')); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo e(__('Remarks')); ?></label>
                        <textarea class="form-control" id="modal_remarks" name="remarks" rows="2" placeholder="<?php echo e(__('Enter any additional remarks')); ?>"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo e(__('Cancel')); ?></button>
                <button type="button" onclick="uploadPaymentProofForPayment()" class="btn btn-primary">
                    <i class="ti ti-upload me-1"></i> <?php echo e(__('Upload Proof')); ?>

                </button>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('css'); ?>
<?php echo $__env->make('layouts.includes.datatable-css', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<style>
    /* Font Size Consistency Rules */
    .card-header h5 {
        font-size: 1rem !important;
        font-weight: 600 !important;
        margin-bottom: 0 !important;
    }
    
    .amount-card h4 {
        font-size: 1.25rem !important;
        font-weight: 700 !important;
        margin-bottom: 0 !important;
    }
    
    .status-timeline {
        position: relative;
        padding-left: 1.5rem;
    }
    .status-timeline::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 0.5rem;
        bottom: 0.5rem;
        width: 2px;
        background: #e9ecef;
    }
    .status-item {
        position: relative;
        padding-bottom: 1rem;
    }
    .status-item::before {
        content: '';
        position: absolute;
        left: -1.25rem;
        top: 0.25rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #6c757d;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #e9ecef;
    }
    .status-item.active::before {
        background: #0d6efd;
        box-shadow: 0 0 0 2px #0d6efd;
    }
    .status-item.completed::before {
        background: #198754;
        box-shadow: 0 0 0 2px #198754;
    }
    
    /* Consistent font sizes for status timeline */
    .status-item small {
        font-size: 0.75rem !important;
        font-weight: 500 !important;
    }
    .status-item .fw-bold {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    
    /* Amount cards consistency */
    .amount-card {
        border-left: 4px solid #0d6efd;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .amount-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .amount-card.credit {
        border-left-color: #198754;
    }
    .amount-card.debit {
        border-left-color: #dc3545;
    }
    .amount-card.net {
        border-left-color: #0dcaf0;
    }
    .amount-card .text-muted {
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    /* Card consistency */
    .card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: box-shadow 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        font-weight: 600;
        padding: 1rem 1.25rem !important;
    }
    
    /* Form labels consistency */
    .form-label {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        color: #495057 !important;
        margin-bottom: 0.5rem !important;
    }
    
    /* Button consistency */
    .btn {
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.875rem !important;
        transition: all 0.2s ease;
    }
    .btn:hover {
        transform: translateY(-1px);
    }
    .btn-sm {
        font-size: 0.75rem !important;
        font-weight: 500 !important;
    }
    
    /* Table consistency */
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.875rem !important;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        padding: 0.75rem !important;
    }
    .table td {
        font-size: 0.875rem !important;
        padding: 0.75rem !important;
        vertical-align: middle !important;
    }
    
    /* Badge consistency */
    .badge {
        font-size: 0.75rem !important;
        font-weight: 500 !important;
    }
    
    /* Payment summary consistency */
    .payment-summary .text-muted {
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    .payment-summary .fw-bold {
        font-size: 0.875rem !important;
        font-weight: 700 !important;
    }
    
    /* Modal consistency */
    .modal-header h5 {
        font-size: 1.125rem !important;
        font-weight: 600 !important;
    }
    .modal-body .form-label {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    
    /* Small text consistency */
    small.text-muted {
        font-size: 0.75rem !important;
        font-weight: 400 !important;
    }
    
    /* Payment info section */
    .payment-info .fw-bold {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    
    <div class="col-sm-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><?php echo e(__('Payment Request Information')); ?></h5>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($paymentRequest->status):
                    case ('draft'): ?> <span class="badge bg-secondary"><?php echo e(__('Draft')); ?></span> <?php break; ?>
                    <?php case ('submitted'): ?> <span class="badge bg-info"><?php echo e(__('Submitted')); ?></span> <?php break; ?>
                    <?php case ('verified'): ?> <span class="badge bg-primary"><?php echo e(__('Verified')); ?></span> <?php break; ?>
                    <?php case ('approved'): ?> <span class="badge bg-success"><?php echo e(__('Approved')); ?></span> <?php break; ?>
                    <?php case ('locked'): ?> <span class="badge bg-warning"><?php echo e(__('Locked')); ?></span> <?php break; ?>
                    <?php case ('paid'): ?> <span class="badge bg-success"><?php echo e(__('Paid')); ?></span> <?php break; ?>
                    <?php case ('rejected'): ?> <span class="badge bg-danger"><?php echo e(__('Rejected')); ?></span> <?php break; ?>
                    <?php case ('hold'): ?> <span class="badge bg-warning"><?php echo e(__('Hold')); ?></span> <?php break; ?>
                <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="card-body">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($paymentRequest->status, ['approved', 'paid', 'locked'])): ?>
                <div class="alert alert-warning mb-3">
                    <h6><i class="ti ti-lock"></i> 🔒 <?php echo e(__('Financial Snapshot Locked')); ?></h6>
                    <p class="mb-0"><?php echo e(__('This payment request has been approved and the financial snapshot is now immutable. No further recalculation is allowed.')); ?></p>
                    <small class="text-muted"><?php echo e(__('Locked on')); ?>: <?php echo e($paymentRequest->approved_at ? $paymentRequest->approved_at->format('d M Y H:i') : '-'); ?></small>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                
                <div class="row g-3 payment-info">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted mb-1"><?php echo e(__('Machinery')); ?></label>
                            <div class="fw-bold"><?php echo e($paymentRequest->machinery->name ?? 'N/A'); ?></div>
                            <small class="text-muted"><?php echo e($paymentRequest->machinery->code ?? ''); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted mb-1"><?php echo e(__('Supplier')); ?></label>
                            <div class="fw-bold"><?php echo e($paymentRequest->supplier->name ?? 'N/A'); ?></div>
                            <small class="text-muted"><?php echo e($paymentRequest->supplier->code ?? ''); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted mb-1"><?php echo e(__('Period')); ?></label>
                            <div class="fw-bold">
                                <?php echo e(\Carbon\Carbon::parse($paymentRequest->period_start)->format('d M Y')); ?>

                                <i class="ti ti-arrow-right mx-1 text-muted"></i>
                                <?php echo e(\Carbon\Carbon::parse($paymentRequest->period_end)->format('d M Y')); ?>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted mb-1"><?php echo e(__('Entries')); ?></label>
                            <div class="fw-bold"><?php echo e($paymentRequest->audit_snapshot['entry_count'] ?? 0); ?> <?php echo e(__('ledger entries')); ?></div>
                        </div>
                    </div>
                </div>
                
                
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->status === 'draft'): ?>
                            <button type="button" class="btn btn-primary" onclick="submitPaymentRequest(<?php echo e($paymentRequest->id); ?>)">
                                <i class="ti ti-send"></i> <?php echo e(__('Submit')); ?>

                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->status === 'submitted'): ?>
                            <button type="button" class="btn btn-success" onclick="approvePaymentRequest(<?php echo e($paymentRequest->id); ?>)">
                                <i class="ti ti-check"></i> <?php echo e(__('Approve')); ?>

                            </button>
                            <button type="button" class="btn btn-danger" onclick="showRejectionModal(<?php echo e($paymentRequest->id); ?>)">
                                <i class="ti ti-x"></i> <?php echo e(__('Reject')); ?>

                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->status === 'approved'): ?>
                            <button type="button" class="btn btn-info" onclick="markAsPaid(<?php echo e($paymentRequest->id); ?>)">
                                <i class="ti ti-cash"></i> <?php echo e(__('Mark as Paid')); ?>

                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo e(route('machinery-payment.index')); ?>" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-sm-12 col-lg-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="card amount-card credit">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase"><?php echo e(__('Credits')); ?></small>
                                <h4 class="mb-0 text-success"><?php echo e(number_format($paymentRequest->credits ?? 0, 2)); ?></h4>
                            </div>
                            <div class="avatar avatar-sm bg-success-subtle rounded">
                                <i class="ti ti-trending-up text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card amount-card debit">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase"><?php echo e(__('Debits')); ?></small>
                                <h4 class="mb-0 text-danger"><?php echo e(number_format($paymentRequest->debits ?? 0, 2)); ?></h4>
                            </div>
                            <div class="avatar avatar-sm bg-danger-subtle rounded">
                                <i class="ti ti-trending-down text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card amount-card net">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase"><?php echo e(__('Net Payable')); ?></small>
                                <h4 class="mb-0 text-info"><?php echo e(number_format($paymentRequest->net_payable ?? 0, 2)); ?></h4>
                            </div>
                            <div class="avatar avatar-sm bg-info-subtle rounded">
                                <i class="ti ti-calculator text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-sm-12 mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-calculator me-2"></i><?php echo e(__('Billing Breakdown')); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Description')); ?></th>
                                <th class="text-right"><?php echo e(__('Amount (₹)')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php echo e(__('Machine Work Charges')); ?></strong></td>
                                <td class="text-right font-weight-bold text-success">
                                    +<?php echo e(number_format($paymentRequest->gross_amount ?? $paymentRequest->net_payable, 2)); ?>

                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->diesel_deduction > 0): ?>
                            <tr>
                                <td><strong><?php echo e(__('Diesel Recovery')); ?></strong></td>
                                <td class="text-right font-weight-bold text-danger">
                                    -<?php echo e(number_format($paymentRequest->diesel_deduction, 2)); ?>

                                </td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($paymentRequest->adjustments) && $paymentRequest->adjustments != 0): ?>
                            <tr>
                                <td><strong><?php echo e(__('Adjustments')); ?></strong></td>
                                <td class="text-right font-weight-bold text-info">
                                    <?php echo e($paymentRequest->adjustments > 0 ? '+' : ''); ?><?php echo e(number_format($paymentRequest->adjustments, 2)); ?>

                                </td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <tr class="table-active">
                                <td><strong><?php echo e(__('Net Payable')); ?></strong></td>
                                <td class="text-right font-weight-bold">
                                    ₹<?php echo e(number_format($paymentRequest->net_payable, 2)); ?>

                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->diesel_breakdown && $paymentRequest->diesel_deduction > 0): ?>
                <div class="alert alert-warning mt-3">
                    <h6><i class="ti ti-gas-pump"></i> <?php echo e(__('Diesel Recovery Details')); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong><?php echo e(__('Total Liters')); ?>:</strong> 
                            <?php echo e($paymentRequest->diesel_breakdown['total_liters'] ?? 0); ?> L
                        </div>
                        <div class="col-md-6">
                            <strong><?php echo e(__('Average Rate')); ?>:</strong> 
                            ₹<?php echo e(number_format($paymentRequest->diesel_breakdown['average_rate'] ?? 90, 2)); ?>/L
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo e(__('This amount will be recovered from supplier billing.')); ?>

                        </small>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="col-sm-12 col-lg-4 mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-clock me-2"></i><?php echo e(__('Status Timeline')); ?></h5>
            </div>
            <div class="card-body">
                <div class="status-timeline">
                    <div class="status-item <?php echo e($paymentRequest->created_at ? 'completed' : ''); ?>">
                        <small class="text-muted"><?php echo e(__('Created')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->requester->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->created_at ? $paymentRequest->created_at->format('d M Y, h:i A') : 'N/A'); ?></small>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->submitted_at): ?>
                    <div class="status-item completed">
                        <small class="text-muted"><?php echo e(__('Submitted')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->submitter->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->submitted_at->format('d M Y, h:i A')); ?></small>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->verified_at): ?>
                    <div class="status-item completed">
                        <small class="text-muted"><?php echo e(__('Verified')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->verifier->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->verified_at->format('d M Y, h:i A')); ?></small>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approved_at): ?>
                    <div class="status-item completed">
                        <small class="text-muted"><?php echo e(__('Approved')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->approver->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->approved_at->format('d M Y, h:i A')); ?></small>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->locked_at): ?>
                    <div class="status-item completed">
                        <small class="text-muted"><?php echo e(__('Locked')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->locker->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->locked_at->format('d M Y, h:i A')); ?></small>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->paid_at): ?>
                    <div class="status-item completed">
                        <small class="text-muted"><?php echo e(__('Paid')); ?></small>
                        <div class="fw-bold"><?php echo e($paymentRequest->payer->name ?? 'N/A'); ?></div>
                        <small><?php echo e($paymentRequest->paid_at->format('d M Y, h:i A')); ?></small>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        
    </div>

    
    <div class="col-sm-12 col-lg-8 mt-4">
        
        

        
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="ti ti-list-details me-2"></i><?php echo e(__('Ledger Entry Breakdown')); ?></h5>
                <div>
                    <?php
                        $entryIds = $paymentRequest->audit_snapshot['ledger_entry_ids'] ?? [];
                        $entryCount = count($entryIds);
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entryCount > 0): ?>
                        <span class="badge bg-success"><?php echo e($entryCount); ?> <?php echo e(__('entries linked')); ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning"><?php echo e(__('No entries')); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="ledgerEntriesTable">
                        <thead>
                            <tr>
                                <th><?php echo e(__('ID')); ?></th>
                                <th><?php echo e(__('Date')); ?></th>
                                <th><?php echo e(__('Direction')); ?></th>
                                <th><?php echo e(__('Type')); ?></th>
                                <th class="text-end"><?php echo e(__('Amount')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                    <span class="ms-2"><?php echo e(__('Loading entries...')); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        

        
        
    </div>
</div>

    
    <div class="col-sm-12 mt-4">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="ti ti-history me-2"></i><?php echo e(__('Payment History')); ?></h5>
                <div>
                    <?php
                        $paymentCount = $paymentRequest->payments()->count();
                        $postedCount = $paymentRequest->payments()->posted()->count();
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($postedCount > 0): ?>
                        <span class="badge bg-success"><?php echo e($postedCount); ?> <?php echo e(__('Posted Payments')); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentCount > $postedCount): ?>
                        <span class="badge bg-warning"><?php echo e($paymentCount - $postedCount); ?> <?php echo e(__('Draft/Other')); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->payments->count() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo e(__('Payment Number')); ?></th>
                                    <th><?php echo e(__('Payment Date')); ?></th>
                                    <th><?php echo e(__('Machinery')); ?></th>
                                    <th><?php echo e(__('Supplier')); ?></th>
                                    <th><?php echo e(__('Period')); ?></th>
                                    <th><?php echo e(__('Net Payable')); ?></th>
                                    <th><?php echo e(__('Paid Amount')); ?></th>
                                    <th><?php echo e(__('Payment Mode')); ?></th>
                                    <th><?php echo e(__('Payment Proof')); ?></th>
                                    <th><?php echo e(__('Status')); ?></th>
                                    <th><?php echo e(__('Created By')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $paymentRequest->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_number): ?>
                                            <span class="badge bg-primary"><?php echo e($payment->payment_number); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td><?php echo e($payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : 'N/A'); ?></td>
                                    <td><?php echo e($paymentRequest->machinery->name ?? 'N/A'); ?></td>
                                    <td><?php echo e($paymentRequest->supplier->name ?? 'N/A'); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->period_start)->format('d M Y')); ?> - <?php echo e(\Carbon\Carbon::parse($paymentRequest->period_end)->format('d M Y')); ?>

                                        </small>
                                    </td>
                                    <td class="text-end fw-bold text-primary"><?php echo e(number_format($paymentRequest->net_payable, 2)); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo e(number_format($payment->amount, 2)); ?></td>
                                    <td><?php echo e(ucfirst($payment->mode ?? 'N/A')); ?></td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_proff_file): ?>
                                            <a href="<?php echo e(asset('uploads/' . $payment->payment_proff_file)); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-file-text me-1"></i> <?php echo e(__('View')); ?>

                                            </a>
                                        <?php else: ?>
                                            <button type="button" onclick="showPaymentProofUploadModal(<?php echo e($payment->id); ?>)" class="btn btn-sm btn-outline-success">
                                                <i class="ti ti-upload me-1"></i> <?php echo e(__('Upload')); ?>

                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statusClass = match($payment->status) {
                                                'posted' => 'success',
                                                'draft' => 'secondary',
                                                'cancelled' => 'danger',
                                                'reversed' => 'warning',
                                                'failed' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?php echo e($statusClass); ?>">
                                            <?php echo e(ucfirst($payment->status ?? 'unknown')); ?>

                                        </span>
                                    </td>
                                    <td><?php echo e($payment->creator->name ?? 'System'); ?></td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    
                    <div class="row mt-4 payment-summary">
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Net Payable')); ?></small>
                                <div class="fw-bold text-primary"><?php echo e(number_format($paymentRequest->net_payable, 2)); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Total Posted')); ?></small>
                                <div class="fw-bold text-success"><?php echo e(number_format($paymentRequest->payments()->posted()->sum('amount'), 2)); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Balance')); ?></small>
                                <div class="fw-bold text-info"><?php echo e(number_format(max(0, $paymentRequest->net_payable - $paymentRequest->payments()->posted()->sum('amount')), 2)); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Settlement Status')); ?></small>
                                <div>
                                    <?php
                                        $settlementStatus = $paymentRequest->settlement_status;
                                        $statusBadgeClass = match($settlementStatus) {
                                            'unpaid' => 'secondary',
                                            'partial' => 'warning',
                                            'paid' => 'success',
                                            'overpaid' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>
                                    <span class="badge bg-<?php echo e($statusBadgeClass); ?>">
                                        <?php echo e(ucfirst($settlementStatus)); ?>

                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Payment Count')); ?></small>
                                <div class="fw-bold"><?php echo e($paymentCount); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block"><?php echo e(__('Posted Count')); ?></small>
                                <div class="fw-bold"><?php echo e($postedCount); ?></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-cash-off fs-1 mb-3 text-secondary"></i>
                        <div class="h6 text-secondary"><?php echo e(__('No ERP payments recorded yet')); ?></div>
                        <small class="text-muted"><?php echo e(__('Payment history will appear here once ERP payments are created against this request')); ?></small>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('layouts.includes.datatable-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script>
const paymentRequestId = <?php echo e($paymentRequest->id ?? 0); ?>;

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">Draft</span>',
        'submitted': '<span class="badge bg-info">Submitted</span>',
        'verified': '<span class="badge bg-primary">Verified</span>',
        'approved': '<span class="badge bg-success">Approved</span>',
        'locked': '<span class="badge bg-warning">Locked</span>',
        'paid': '<span class="badge bg-success">Paid</span>',
        'rejected': '<span class="badge bg-danger">Rejected</span>',
        'hold': '<span class="badge bg-warning">Hold</span>'
    };
    return badges[status] || status;
}

function loadActions() {
    fetch(`/machinery/payment-requests/${paymentRequestId}/data`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const request = data.data;
            const status = request.status;
            let actions = '';

            if (status === 'draft') {
                actions += `<button onclick="submitRequest()" class="btn btn-primary"><i class="ti ti-send me-1"></i> Submit</button>`;
            } else if (status === 'submitted') {
                actions += `<button onclick="verifyRequest()" class="btn btn-primary"><i class="ti ti-checks me-1"></i> Verify</button>`;
            } else if (status === 'verified') {
                actions += `<button onclick="approveRequest()" class="btn btn-success"><i class="ti ti-check me-1"></i> Approve</button>`;
            } else if (status === 'approved') {
                actions += `<button onclick="lockRequest()" class="btn btn-warning" title="Freezes the payment period and secures ledger entries from further modifications"><i class="ti ti-lock me-1"></i> Lock</button>`;
            } else if (status === 'locked') {
                // Feature flag for ERP payment creation
                <?php if(config('machinery_payment.enable_erp_payment_button', false)): ?>
                actions += `<button onclick="createErpPayment()" class="btn btn-primary"><i class="ti ti-building-factory-2 me-1"></i> Create Machinery Payment</button> `;
                <?php endif; ?>
//                actions += `<button onclick="markAsPaid()" class="btn btn-success"><i class="ti ti-cash me-1"></i> Mark Paid (Legacy)</button>`;
            }

            if (['draft', 'submitted', 'verified'].includes(status)) {
                actions += ` <button onclick="rejectRequest()" class="btn btn-danger"><i class="ti ti-ban me-1"></i> Reject</button>`;
            }

            if (actions === '') {
                actions = '<span class="text-muted"><i class="ti ti-info-circle me-1"></i> No actions available for this status</span>';
            }

            document.getElementById('actionButtons').innerHTML = actions;
        }
    })
    .catch(error => {
        console.error('Error loading actions:', error);
        document.getElementById('actionButtons').innerHTML = '<span class="text-danger"><i class="ti ti-alert-triangle me-1"></i> Error loading actions</span>';
    });
}

function loadLedgerEntries() {
    fetch(`/machinery/payment-requests/${paymentRequestId}/data`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.getElementById('ledgerEntriesTable');
            tbody.innerHTML = '';

            // Try linked ledger entries first (after approval), then fall back to audit snapshot
            let ledgerEntries = data.data.ledger_entries || [];

            // If no linked entries, use entries from audit snapshot (before approval)
            if (ledgerEntries.length === 0 && data.data.audit_snapshot?.entry_details) {
                ledgerEntries = data.data.audit_snapshot.entry_details.map(e => ({
                    id: e.id,
                    date: e.date,
                    entry_direction: e.direction,
                    entry_type: e.type,
                    amount: e.amount
                }));
            }

            if (ledgerEntries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No entries</td></tr>';
                return;
            }

            ledgerEntries.forEach(entry => {
                const directionBadge = entry.entry_direction === 'credit'
                    ? '<span class="badge bg-success-subtle text-success">Credit</span>'
                    : '<span class="badge bg-danger-subtle text-danger">Debit</span>';

                tbody.innerHTML += `
                    <tr>
                        <td><code>#LED-${entry.id}</code></td>
                        <td>${entry.date ? new Date(entry.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : 'N/A'}</td>
                        <td>${directionBadge}</td>
                        <td>${entry.entry_type || 'N/A'}</td>
                        <td class="text-end fw-bold">${Number(entry.amount || 0).toFixed(2)}</td>
                    </tr>
                `;
            });
        }
    })
    .catch(error => {
        console.error('Error loading ledger entries:', error);
        const tbody = document.getElementById('ledgerEntriesTable');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading entries</td></tr>';
    });
}

async function submitRequest() {
    const result = await Swal.fire({
        title: 'Submit Payment Request?',
        text: 'Do you want to submit this payment request?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, submit it!'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/submit`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Request submitted successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    });
}

async function verifyRequest() {
    const result = await Swal.fire({
        title: 'Verify Payment Request?',
        text: 'Do you want to verify this payment request?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, verify it!'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/verify`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Request verified successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    });
}

async function approveRequest() {
    const result = await Swal.fire({
        title: 'Approve Payment Request?',
        text: 'This will lock the period and link ledger entries. Do you want to approve this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/approve`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Request approved successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    });
}

async function lockRequest() {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Lock Payment Request?',
        text: 'This will freeze the payment period and lock all linked ledger entries to prevent any modifications. The payment amounts will become final and ready for processing. Do you want to lock this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, lock it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/lock`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Locked!',
                        'Request locked successfully',
                        'success'
                    );
                    location.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function markAsPaid() {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Mark as Paid?',
        text: 'Do you want to mark this payment request as paid?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, mark as paid!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/pay`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Marked as Paid!',
                        'Request marked as paid successfully',
                        'success'
                    );
                    location.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

function rejectRequest() {
    const reason = prompt('Enter rejection reason:');
    if (reason === null) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/reject`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Request rejected successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    });
}

function recalculate() {
    fetch(`/machinery/payment-requests/${paymentRequestId}/recalculate`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const diff = data.data.diff;
            const hasMismatch = data.data.has_mismatch;
            const canApprove = data.data.can_approve;
            
            let message = `Original: ${data.data.original.net_payable.toFixed(2)}\n`;
            message += `Current: ${data.data.current.net_payable.toFixed(2)}\n`;
            message += `Diff: ${diff.net_payable.toFixed(2)}\n\n`;
            
            if (hasMismatch) {
                message += '⚠️ Calculation mismatch detected!\n';
                message += 'Approval would be blocked.';
            } else {
                message += '✅ Calculation matches.\n';
                message += 'Approval allowed.';
            }
            
            Swal.fire({
                icon: 'info',
                title: 'Calculation Results',
                html: message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

function showDebug() {
    fetch(`/machinery/payment-requests/${paymentRequestId}/debug`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Debug Info:', data.data);
            Swal.fire({
                icon: 'info',
                title: 'Debug Info',
                text: 'Debug info logged to console. Check browser console for details.'
            });
        }
    });
}

function showEntryIds() {
    const container = document.getElementById('entryIdsContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

async function forceReject() {
    const reason = prompt('Enter override reason for force reject:');
    if (!reason) return;
    
    const result = await Swal.fire({
        title: '⚠️ Force Reject?',
        text: 'This will force reject the payment request regardless of current status. This action is logged and cannot be undone. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, force reject!',
        cancelButtonText: 'Cancel'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/force-reject`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ override_reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Payment request force rejected successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

async function forceUnlock() {
    const reason = prompt('Enter override reason for force unlock:');
    if (!reason) return;
    
    const result = await Swal.fire({
        title: '⚠️ Force Unlock?',
        text: 'This will unlock the period and unlink all ledger entries. This action is logged and cannot be undone. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, force unlock!',
        cancelButtonText: 'Cancel'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/force-unlock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ override_reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Period force unlocked successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

function addOverrideNote() {
    const note = prompt('Enter override note:');
    if (!note) return;
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/override-note`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ note: note })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Override note added successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

function uploadInvoice() {
    const fileInput = document.getElementById('invoice_file');
    const file = fileInput.files[0];
    
    if (!file) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please select a file to upload.'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('invoice_file', file);
    
    fetch(`/machinery/payment-requests/${paymentRequestId}/upload-invoice`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Invoice uploaded successfully'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + JSON.stringify(data)
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

async function createErpPayment() {
    // Load modal content via AJAX
    try {
        const response = await fetch(`/machinery/payment-requests/${paymentRequestId}/payment-modal`, {
            headers: {
                'Accept': 'text/html',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const modalHtml = await response.text();
        
        // Create and show modal
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = `
            <div class="modal fade" id="machineryPaymentModal" tabindex="-1" aria-labelledby="machineryPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="machineryPaymentModalLabel">
                                <i class="ti ti-building-factory-2 me-2"></i>Create Machinery Payment
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        ${modalHtml}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modalContainer);
        
        // Initialize and show modal
        const modal = new bootstrap.Modal(document.getElementById('machineryPaymentModal'));
        modal.show();
        
        // Clean up modal after hidden
        document.getElementById('machineryPaymentModal').addEventListener('hidden.bs.modal', function () {
            document.body.removeChild(modalContainer);
        });
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load payment form. Please try again.'
        });
    }
}


function showPaymentProofUploadModal(paymentId) {
    // Set the payment ID in the hidden field
    document.getElementById('payment_id').value = paymentId;
    
    // Reset form
    document.getElementById('paymentProofUploadModalForm').reset();
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('paymentProofUploadModal'));
    modal.show();
}

function uploadPaymentProofForPayment() {
    const form = document.getElementById('paymentProofUploadModalForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const fileInput = document.getElementById('modal_payment_proof_file');
    const file = fileInput.files[0];
    
    if (!file) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please select a payment proof file'
        });
        return;
    }
    
    // Validate file size
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        Swal.fire({
            icon: 'warning',
            title: 'File Size Error',
            text: 'File size must be less than 5MB'
        });
        return;
    }
    
    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'warning',
            title: 'File Type Error',
            text: 'Only PDF, JPG, and PNG files are allowed'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Uploading...',
        text: 'Please wait while we upload your payment proof',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/machinery/payments/${formData.get('payment_id')}/upload-proof`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('paymentProofUploadModal'));
            modal.hide();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Payment proof uploaded successfully'
            }).then(() => {
                // Reload page to show updated payment proof
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + (data.message || JSON.stringify(data))
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

// Rejection workflow functions
function showRejectionModal(paymentRequestId) {
    currentPaymentRequestId = paymentRequestId;
    $('#rejectionModal').modal('show');
}

function rejectPaymentRequest() {
    const reason = document.getElementById('rejection_reason').value.trim();
    
    if (!reason) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please provide a rejection reason.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Rejecting...',
        text: 'Please wait while we process the rejection',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/machinery-payment/${currentPaymentRequestId}/reject`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Rejected!',
                text: 'Payment request has been rejected successfully.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + (data.message || JSON.stringify(data))
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

// Submit payment request
function submitPaymentRequest(paymentRequestId) {
    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we submit the payment request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/machinery-payment/${paymentRequestId}/submit`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Submitted!',
                text: 'Payment request has been submitted successfully.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + (data.message || JSON.stringify(data))
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

// Approve payment request
function approvePaymentRequest(paymentRequestId) {
    Swal.fire({
        title: 'Approving...',
        text: 'Please wait while we approve the payment request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/machinery-payment/${paymentRequestId}/approve`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Approved!',
                text: 'Payment request has been approved successfully.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + (data.message || JSON.stringify(data))
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

// Mark as paid
function markAsPaid(paymentRequestId) {
    Swal.fire({
        title: 'Marking as Paid...',
        text: 'Please wait while we update the payment request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/machinery-payment/${paymentRequestId}/paid`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Marked as Paid!',
                text: 'Payment request has been marked as paid successfully.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error: ' + (data.message || JSON.stringify(data))
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error: ' + error.message
        });
    });
}

// Global variable for current payment request ID
let currentPaymentRequestId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadActions();
    loadLedgerEntries();
    
});    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/machinery-payment/show.blade.php ENDPATH**/ ?>