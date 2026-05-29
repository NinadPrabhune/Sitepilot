<?php $__env->startSection('page-title', __('Purchase Order Details')); ?>
<?php $__env->startSection('page-breadcrumb', __('Purchase Orders')); ?>

<?php $__env->startSection('page-action'); ?>
<a href="<?php echo e(route('purchase-order.index')); ?>" class="btn btn-sm btn-primary">
    <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

</a>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('grn create')): ?>
<a data-size="xxl" data-url="<?php echo e(route('grn.create', ['po_id' => $purchaseOrder->id])); ?>" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create GRN')); ?>" data-title="<?php echo e(__('Create Goods Receipt Note')); ?>" class="btn btn-sm btn-info ms-2">
    <i class="ti ti-package"></i> <?php echo e(__('Create GRN')); ?>

</a>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Purchase Order')); ?>: <?php echo e($purchaseOrder->po_number); ?></h5>
                <span class="badge px-3 py-2 fs-6 
                    bg-<?php echo e($purchaseOrder->display_status == 'Draft' ? 'secondary' : 
                        ($purchaseOrder->display_status == 'Approved' ? 'primary' : 
                        ($purchaseOrder->display_status == 'Partial Received' ? 'warning' : 
                        ($purchaseOrder->display_status == 'Completed' ? 'success' : 
                        ($purchaseOrder->display_status == 'Flagged - Corrected' ? 'info' : 
                        ($purchaseOrder->display_status == 'Short Closed' ? 'dark' : 'danger')))))); ?>">
                    <?php echo e(__($purchaseOrder->display_status)); ?>

                </span>
            </div>
            <div class="card-body">
                <div class="row gy-4">

                    
                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('PO Number')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->po_number); ?></div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('PO Date')); ?></small>
                        <div class="fw-bold">
                            <?php echo e($purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d M Y') : '-'); ?>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Supplier Invoice Number')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->supplier_invoice_number ?? '-'); ?></div>
                    </div>

                    
                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Supplier')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->supplier->name ?? '-'); ?></div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Site')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->site->name ?? '-'); ?></div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Indent')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->indent->indent_number ?? '-'); ?></div>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($assignedUsers->isNotEmpty()): ?>
                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Assigned To')); ?></small>
                        <div class="mt-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $assignedUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="badge bg-primary"><?php echo e($user->name); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Tax Type')); ?></small>
                        <div class="fw-bold">
                            <?php echo e($purchaseOrder->tax_type == 'igst' ? __('IGST') : __('CGST + SGST')); ?>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Delivery Date')); ?></small>
                        <div class="fw-bold">
                            <?php echo e($purchaseOrder->delivery_date ? \Carbon\Carbon::parse($purchaseOrder->delivery_date)->format('d M Y') : '-'); ?>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted"><?php echo e(__('Reference File')); ?></small>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->reference_file): ?>
                            <div>
                                <a href="<?php echo e(asset($purchaseOrder->reference_file)); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="ti ti-file"></i> <?php echo e(__('View File')); ?>

                                </a>
                            </div>
                        <?php else: ?>
                            <div class="fw-bold">-</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->description): ?>
                    <div class="col-md-6">
                        <small class="text-muted"><?php echo e(__('Description')); ?></small>
                        <div class="p-3 border rounded bg-light mt-1">
                            <?php echo e($purchaseOrder->description); ?>

                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->delivery_terms_conditions): ?>
                    <div class="col-md-6">
                        <small class="text-muted"><?php echo e(__('Delivery Terms & Conditions')); ?></small>
                        <div class="p-3 border rounded bg-light mt-1">
                            <?php echo e($purchaseOrder->delivery_terms_conditions); ?>

                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->remark): ?>
                    <div class="col-md-6">
                        <small class="text-muted"><?php echo e(__('Remark')); ?></small>
                        <div class="p-3 border rounded bg-light mt-1">
                            <?php echo e($purchaseOrder->remark); ?>

                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->rejection_reason): ?>
                    <div class="col-md-6">
                        <small class="text-muted"><?php echo e(__('Rejection Reason')); ?></small>
                        <div class="p-3 border rounded bg-danger bg-opacity-10 mt-1 text-danger">
                            <?php echo e($purchaseOrder->rejection_reason); ?>

                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?php echo e(__('Purchase Order Items')); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Material')); ?></th>
                                <th><?php echo e(__('Indent Qty')); ?></th>
                                <th><?php echo e(__('PO Qty')); ?></th>
                                <th><?php echo e(__('Unit')); ?></th>
                                <th><?php echo e(__('Price')); ?></th>
                                <th><?php echo e(__('GST (%)')); ?></th>
                                <th><?php echo e(__('Tax Amount')); ?></th>
                                <th><?php echo e(__('Discount')); ?></th>
                                <th><?php echo e(__('Subtotal')); ?></th>
                                <th><?php echo e(__('Remarks')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $totalTaxable = 0;
                                $totalTax = 0;
                                $totalDiscount = 0;
                            ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $purchaseOrder->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $indentItem = optional($purchaseOrder->indent)
                                        ->items
                                        ->where('material_id', $item->material_id)
                                        ->first();

                                    $indentQty = $indentItem->quantity ?? 0;
                                    $rowTaxable = ($item->quantity * $item->price);
                                    $totalTaxable += $rowTaxable;
                                    $totalTax += $item->tax_amount ?? 0;
                                    $totalDiscount += $item->discount_amount ?? 0;
                                ?>

                                <tr>
                                    <td class="fw-semibold"><?php echo e($item->material->name ?? '-'); ?></td>
                                    <td><?php echo e(number_format($indentQty, 2)); ?></td>
                                    <td><?php echo e(number_format($item->quantity, 2)); ?></td>
                                    <td><?php echo e($item->unit ?? '-'); ?></td>
                                    <td><?php echo e(currency_format_with_sym_indian($item->price)); ?></td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->gstMaster): ?>
                                            <?php echo e($item->gstMaster->name); ?>

                                            <small class="text-muted">(<?php echo e($purchaseOrder->tax_type == 'igst' ? $item->gstMaster->igst : ($item->gstMaster->cgst + $item->gstMaster->sgst)); ?>%)</small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td><?php echo e(currency_format_with_sym_indian($item->tax_amount ?? 0)); ?></td>
                                    <td><?php echo e(currency_format_with_sym_indian($item->discount_amount ?? 0)); ?></td>
                                    <td class="fw-semibold text-primary">
                                        <?php echo e(currency_format_with_sym_indian($item->subtotal ?? 0)); ?>

                                    </td>
                                    <td><?php echo e($item->remarks ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted"><?php echo e(__('No items found')); ?></td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?php echo e(__('Financial Summary')); ?></h5>
            </div>
            <div class="card-body">
                <div class="row gy-4">
                    
                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Total Taxable Value')); ?></small>
                        <div class="fw-bold text-primary fs-5">
                            <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_taxable_value ?? 0)); ?>

                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->tax_type == 'igst'): ?>
                        <div class="col-md-3">
                            <small class="text-muted"><?php echo e(__('Total IGST')); ?></small>
                            <div class="fw-bold fs-5">
                                <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_igst ?? 0)); ?>

                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-3">
                            <small class="text-muted"><?php echo e(__('Total CGST')); ?></small>
                            <div class="fw-bold fs-5">
                                <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_cgst ?? 0)); ?>

                            </div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted"><?php echo e(__('Total SGST')); ?></small>
                            <div class="fw-bold fs-5">
                                <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_sgst ?? 0)); ?>

                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Total Tax')); ?></small>
                        <div class="fw-bold fs-5">
                            <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_tax ?? 0)); ?>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Total Discount')); ?></small>
                        <div class="fw-bold text-danger fs-5">
                            <?php echo e(currency_format_with_sym_indian($purchaseOrder->total_discount ?? 0)); ?>

                        </div>
                    </div>

                    
                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Additional Charge')); ?></small>
                        <div class="fw-bold">
                            + <?php echo e(currency_format_with_sym_indian($purchaseOrder->additional_charge ?? 0)); ?>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Additional Deduction')); ?></small>
                        <div class="fw-bold text-danger">
                            - <?php echo e(currency_format_with_sym_indian($purchaseOrder->additional_deduction ?? 0)); ?>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Additional Discount')); ?></small>
                        <div class="fw-bold text-danger">
                            - <?php echo e(currency_format_with_sym_indian($purchaseOrder->additional_discount ?? 0)); ?>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Grand Total')); ?></small>
                        <div class="fw-bold text-success fs-4">
                            <?php echo e(currency_format_with_sym_indian($purchaseOrder->grand_total ?? 0)); ?>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Payment Requests - PO Advance')); ?></h5>
                <small class="text-muted"><?php echo e($paymentRequests->where('type', 'po_advance')->count()); ?> <?php echo e(__('Requests')); ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('PO')); ?></th>
                                <th><?php echo e(__('PO Date')); ?></th>
                                <th><?php echo e(__('PO Amount')); ?></th>
                                <th><?php echo e(__('Requested Amount')); ?></th>
                                <th><?php echo e(__('Requested Date')); ?></th>
                                <th><?php echo e(__('Approved Amount')); ?></th>
                                <th><?php echo e(__('Status')); ?></th>
                                <th><?php echo e(__('Requested By')); ?></th>
                                <th><?php echo e(__('Approved By')); ?></th>
                                <th><?php echo e(__('Approved At')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $paymentRequests->where('type', 'po_advance'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($paymentRequest->po?->po_number ?? '-'); ?></td>
                                    <td><?php echo e($paymentRequest->po?->po_date ? \Carbon\Carbon::parse($paymentRequest->po->po_date)->format('d M Y') : '-'); ?></td>
                                    <td class="fw-semibold"><?php echo e($paymentRequest->po ? currency_format_with_sym_indian($paymentRequest->po->grand_total) : '-'); ?></td>
                                    <td class="fw-semibold"><?php echo e(currency_format_with_sym_indian($paymentRequest->requested_amount)); ?></td>
                                    <td><?php echo e($paymentRequest->created_at ? \Carbon\Carbon::parse($paymentRequest->created_at)->format('d M Y, h:i A') : '-'); ?></td>
                                    <td class="fw-semibold <?php echo e($paymentRequest->approved_amount ? 'text-success' : ''); ?>">
                                        <?php echo e($paymentRequest->approved_amount ? currency_format_with_sym_indian($paymentRequest->approved_amount) : '-'); ?>

                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php if($paymentRequest->status == 'pending'): ?> bg-warning
                                            <?php elseif($paymentRequest->status == 'approved'): ?> bg-success
                                            <?php elseif($paymentRequest->status == 'partially_approved'): ?> bg-info
                                            <?php elseif($paymentRequest->status == 'rejected'): ?> bg-danger
                                            <?php elseif($paymentRequest->status == 'partially_paid'): ?> bg-primary
                                            <?php elseif($paymentRequest->status == 'paid'): ?> bg-success
                                            <?php else: ?> bg-secondary
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $paymentRequest->status))); ?>

                                        </span>
                                    </td>
                                    <td><?php echo e($paymentRequest->requestedBy->name ?? '-'); ?></td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approvedBy): ?>
                                            <?php echo e($paymentRequest->approvedBy->name); ?>

                                        <?php elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->creator): ?>
                                            <?php echo e($paymentRequest->payments->first()->creator->name); ?>

                                        <?php else: ?>
                                            -
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approved_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->approved_at)->format('d M Y, h:i A')); ?>

                                        <?php elseif($paymentRequest->paid_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->paid_at)->format('d M Y, h:i A')); ?>

                                        <?php elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->created_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->payments->first()->created_at)->format('d M Y, h:i A')); ?>

                                        <?php else: ?>
                                            -
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted"><?php echo e(__('No PO Advance payment requests found')); ?></td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
             <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Payment Requests - Invoice Payment')); ?></h5>
                <small class="text-muted"><?php echo e($paymentRequests->where('type', 'invoice_payment')->count()); ?> <?php echo e(__('Requests')); ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Invoice')); ?></th>
                                <th><?php echo e(__('Invoice Date')); ?></th>
                                <th><?php echo e(__('Invoice Amount')); ?></th>
                                <th><?php echo e(__('Requested Amount')); ?></th>
                                <th><?php echo e(__('Requested Date')); ?></th>
                                <th><?php echo e(__('Approved Amount')); ?></th>
                                <th><?php echo e(__('Status')); ?></th>
                                <th><?php echo e(__('Requested By')); ?></th>
                                <th><?php echo e(__('Approved By')); ?></th>
                                <th><?php echo e(__('Approved At')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $paymentRequests->where('type', 'invoice_payment'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><?php echo e($paymentRequest->invoice?->invoice_number ?? '-'); ?></td>
                                    <td><?php echo e($paymentRequest->invoice?->invoice_date ? \Carbon\Carbon::parse($paymentRequest->invoice->invoice_date)->format('d M Y') : '-'); ?></td>
                                    <td class="fw-semibold"><?php echo e($paymentRequest->invoice ? currency_format_with_sym_indian($paymentRequest->invoice->grand_total) : '-'); ?></td>
                                    <td class="fw-semibold"><?php echo e(currency_format_with_sym_indian($paymentRequest->requested_amount)); ?></td>
                                    <td><?php echo e($paymentRequest->created_at ? \Carbon\Carbon::parse($paymentRequest->created_at)->format('d M Y, h:i A') : '-'); ?></td>
                                    <td class="fw-semibold <?php echo e($paymentRequest->approved_amount ? 'text-success' : ''); ?>">
                                        <?php echo e($paymentRequest->approved_amount ? currency_format_with_sym_indian($paymentRequest->approved_amount) : '-'); ?>

                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php if($paymentRequest->status == 'pending'): ?> bg-warning
                                            <?php elseif($paymentRequest->status == 'approved'): ?> bg-success
                                            <?php elseif($paymentRequest->status == 'partially_approved'): ?> bg-info
                                            <?php elseif($paymentRequest->status == 'rejected'): ?> bg-danger
                                            <?php elseif($paymentRequest->status == 'partially_paid'): ?> bg-primary
                                            <?php elseif($paymentRequest->status == 'paid'): ?> bg-success
                                            <?php else: ?> bg-secondary
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $paymentRequest->status))); ?>

                                        </span>
                                    </td>
                                    <td><?php echo e($paymentRequest->requestedBy->name ?? '-'); ?></td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approvedBy): ?>
                                            <?php echo e($paymentRequest->approvedBy->name); ?>

                                        <?php elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->creator): ?>
                                            <?php echo e($paymentRequest->payments->first()->creator->name); ?>

                                        <?php else: ?>
                                            -
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentRequest->approved_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->approved_at)->format('d M Y, h:i A')); ?>

                                        <?php elseif($paymentRequest->paid_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->paid_at)->format('d M Y, h:i A')); ?>

                                        <?php elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->created_at): ?>
                                            <?php echo e(\Carbon\Carbon::parse($paymentRequest->payments->first()->created_at)->format('d M Y, h:i A')); ?>

                                        <?php else: ?>
                                            -
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted"><?php echo e(__('No Invoice Payment requests found')); ?></td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Supplier Ledger')); ?> - <?php echo e($purchaseOrder->supplier->name ?? '-'); ?></h5>
                <small class="text-muted"><?php echo e($supplierTransactions->count()); ?> <?php echo e(__('Transactions')); ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo e(__('Date')); ?></th>
                                <th><?php echo e(__('Type')); ?></th>
                                <th><?php echo e(__('Reference')); ?></th>
                                <th><?php echo e(__('Description')); ?></th>
                                <th class="text-end"><?php echo e(__('Debit')); ?></th>
                                <th class="text-end"><?php echo e(__('Credit')); ?></th>
                                <th class="text-end"><?php echo e(__('Balance')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $supplierTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td>
                                        <?php echo e($transaction->created_at ? \Carbon\Carbon::parse($transaction->created_at)->format('d M Y, h:i A') : '-'); ?>

                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php if($transaction->reference_type == 'invoice'): ?> bg-danger
                                            <?php elseif($transaction->reference_type == 'payment'): ?> bg-success
                                            <?php elseif($transaction->reference_type == 'advance'): ?> bg-info
                                            <?php elseif($transaction->reference_type == 'po'): ?> bg-primary
                                            <?php elseif($transaction->reference_type == 'grn'): ?> bg-warning
                                            <?php else: ?> bg-secondary
                                            <?php endif; ?>">
                                            <?php echo e($transaction->reference_type_label); ?>

                                        </span>
                                    </td>
                                    <td class="fw-semibold"><?php echo e($transaction->reference_number); ?></td>
                                    <td><?php echo e($transaction->description ?? '-'); ?></td>
                                    <td class="text-end <?php echo e($transaction->debit > 0 ? 'text-danger' : ''); ?>">
                                        <?php echo e($transaction->debit > 0 ? currency_format_with_sym_indian($transaction->debit) : '-'); ?>

                                    </td>
                                    <td class="text-end <?php echo e($transaction->credit > 0 ? 'text-success' : ''); ?>">
                                        <?php echo e($transaction->credit > 0 ? currency_format_with_sym_indian($transaction->credit) : '-'); ?>

                                    </td>
                                    <td class="text-end fw-bold <?php echo e($transaction->balance > 0 ? 'text-danger' : ($transaction->balance < 0 ? 'text-success' : '')); ?>">
                                        <?php echo e(currency_format_with_sym_indian($transaction->balance)); ?>

                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted"><?php echo e(__('No ledger transactions found')); ?></td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?php echo e(__('Audit Information')); ?></h5>
            </div>
            <div class="card-body">
                <div class="row gy-4">
                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Created By')); ?></small>
                        <div class="fw-bold"><?php echo e($purchaseOrder->creator->name ?? '-'); ?></div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Created At')); ?></small>
                        <div><?php echo e($purchaseOrder->created_at ? $purchaseOrder->created_at->format('d M Y, h:i A') : '-'); ?></div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Updated At')); ?></small>
                        <div><?php echo e($purchaseOrder->updated_at ? $purchaseOrder->updated_at->format('d M Y, h:i A') : '-'); ?></div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseOrder->deleted_at): ?>
                    <div class="col-md-3">
                        <small class="text-muted"><?php echo e(__('Deleted At')); ?></small>
                        <div class="text-danger"><?php echo e($purchaseOrder->deleted_at->format('d M Y, h:i A')); ?></div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/purchase-order/show.blade.php ENDPATH**/ ?>