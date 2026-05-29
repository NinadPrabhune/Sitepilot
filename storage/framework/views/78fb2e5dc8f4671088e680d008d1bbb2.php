<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Supplier Ledger Report')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
    <?php echo e(__('Report')); ?>,
    <?php echo e(__('Supplier Ledger Report')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('assets/js/plugins/apexcharts.min.js')); ?>"></script>
    <script src="<?php echo e(asset('js/html2pdf.bundle.min.js')); ?>"></script>
    <script>
        var filename = $('#filename').val();

        function saveAsPDF() {
            var element = document.getElementById('printableArea');
            var opt = {
                margin: 0.3,
                filename: filename,
                image: {type: 'jpeg', quality: 1},
                html2canvas: {
                    scale: 4, 
                    dpi: 72, 
                    letterRendering: true,
                    onclone: function(clonedDoc) {
                        var printButtons = clonedDoc.querySelectorAll('#printableArea .print-btn, #printableArea .filter-section');
                        printButtons.forEach(function(el) {
                            el.style.display = 'none !important';
                        });
                    }
                },
                jsPDF: {unit: 'in', format: 'A2'}
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <!-- [ Main Content ] start -->
    <div class="row">
        <!-- [ sample-page ] start -->
        <div class="col-sm-12">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header card-body table-border-style">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5><?php echo e(__('Supplier Ledger Report')); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section (for screen - not included in PDF) -->
            <div class="row filter-section print-btn">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="<?php echo e(route('reports.supplier-ledger')); ?>" method="GET" class="mb-0">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="supplier_id" class="form-label"><?php echo e(__('Supplier')); ?></label>
                                            <select name="supplier_id" id="supplier_id" class="form-control">
                                               
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($key); ?>" <?php echo e(isset($filters['supplier_id']) && $filters['supplier_id'] == $key ? 'selected' : ''); ?>>
                                                        <?php echo e($supplier); ?>

                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="site_id" class="form-label"><?php echo e(__('Site')); ?></label>
                                            <select name="site_id" id="site_id" class="form-control">
                                               
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($key); ?>" <?php echo e(isset($filters['site_id']) && $filters['site_id'] == $key ? 'selected' : ''); ?>>
                                                        <?php echo e($site); ?>

                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="from_date" class="form-label"><?php echo e(__('From Date')); ?></label>
                                            <input type="date" name="from_date" id="from_date" class="form-control" value="<?php echo e($filters['from_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="to_date" class="form-label"><?php echo e(__('To Date')); ?></label>
                                            <input type="date" name="to_date" id="to_date" class="form-control" value="<?php echo e($filters['to_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                         <div class="form-group">
                                        <button type="submit" class="btn btn-primary"><?php echo e(__('Filter')); ?></button>
                                        <a href="<?php echo e(route('reports.supplier-ledger')); ?>" class="btn btn-secondary"><?php echo e(__('Reset')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Printable Area (included in PDF) -->
            <div id="printableArea">
                <!-- Report Title -->
                <div class="row mb-3">
                    <div class="col-12 text-center">
                        <h4><?php echo e(__('Supplier Ledger Report')); ?></h4>
                    </div>
                </div>

                <!-- Filter Info Row (for PDF) -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="border p-2">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong><?php echo e(__('Supplier')); ?>:</strong>
                                    <?php echo e(isset($filters['supplier_id']) && isset($suppliers[$filters['supplier_id']]) ? $suppliers[$filters['supplier_id']] : __('All')); ?>

                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo e(__('Site')); ?>:</strong>
                                    <?php echo e(isset($filters['site_id']) && isset($sites[$filters['site_id']]) ? $sites[$filters['site_id']] : __('All')); ?>

                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo e(__('From Date')); ?>:</strong>
                                    <?php echo e($filters['from_date'] ?? __('All')); ?>

                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo e(__('To Date')); ?>:</strong>
                                    <?php echo e($filters['to_date'] ?? __('All')); ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-primary">
                                    <i class="ti ti-shopping-cart"></i>
                                </div>
                                <p class="text-muted text-sm mb-2"><?php echo e(__('Total PO Amount')); ?></p>
                                <h4 class="mb-0"><?php echo e(currency_format_with_sym_indian($summary['total_po'] ?? 0)); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-info">
                                    <i class="ti ti-file-invoice"></i>
                                </div>
                                <p class="text-muted text-sm mb-2"><?php echo e(__('Total Invoice')); ?></p>
                                <h4 class="mb-0"><?php echo e(currency_format_with_sym_indian($summary['total_invoice'] ?? 0)); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-success">
                                    <i class="ti ti-credit-card"></i>
                                </div>
                                <p class="text-muted text-sm mb-2"><?php echo e(__('Total Payments')); ?></p>
                                <h4 class="mb-0"><?php echo e(currency_format_with_sym_indian($summary['total_payments'])); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-danger">
                                    <i class="ti ti-file-dollar"></i>
                                </div>
                                <p class="text-muted text-sm mb-2"><?php echo e(__('Current Balance')); ?></p>
                                <h4 class="mb-0"><?php echo e(currency_format_with_sym_indian($summary['current_balance'])); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header card-body table-border-style">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php echo e(__('Ledger Transactions')); ?></h5>
                                    <div class="d-flex gap-2 print-btn">
                                        <button onclick="saveAsPDF()" class="btn btn-sm btn-primary">
                                            <i class="ti ti-file"></i> Print
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table" id="pc-dt-simple">
                                        <thead>
                                            <tr>
                                                <th><?php echo e(__('Date & Time')); ?></th>
                                                <th><?php echo e(__('Type')); ?></th>
                                                <th><?php echo e(__('Reference')); ?></th>
                                                <th><?php echo e(__('Supplier')); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(__('Ref. Amount')); ?></th>
                                                <th><?php echo e(__('Site')); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(__('Debit')); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(__('Credit')); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(__('Balance')); ?></th>
                                                <th><?php echo e(__('Description')); ?></th>
                                            </tr>
                                            <tr class="table-primary">
                                                <th colspan="6" class="text-end"><?php echo e(__('Total')); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(currency_format_with_sym_indian($transactions->sum('debit'))); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(currency_format_with_sym_indian($transactions->sum('credit'))); ?></th>
                                                <th class="text-right" style="text-align: right;"><?php echo e(currency_format_with_sym_indian($summary['current_balance'] ?? 0)); ?></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <?php
                                                    // Get meta as array
                                                    $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
                                                    
                                                    // Check if non-accounting (PO, GRN should be ignored in balance)
                                                    $isNonAccounting = !empty($meta['non_accounting']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                            // Priority: created_at (has time) > transaction_datetime > transaction_date
                                                            $sortDate = $transaction->created_at;
                                                            if (!empty($transaction->transaction_datetime)) {
                                                                $sortDate = $transaction->transaction_datetime;
                                                            }
                                                        ?>
                                                        <?php echo e(\Carbon\Carbon::parse($sortDate)->format('d M Y')); ?>

                                                        <br><small class="text-muted"><?php echo e(\Carbon\Carbon::parse($sortDate)->format('h:i A')); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->reference_type == 'po'): ?>
                                                            <span class="badge bg-primary"><?php echo e(__('PO')); ?></span>
                                                        <?php elseif($transaction->reference_type == 'grn'): ?>
                                                            <span class="badge bg-secondary"><?php echo e(__('GRN')); ?></span>
                                                        <?php elseif($transaction->reference_type == 'invoice'): ?>
                                                            <span class="badge bg-info"><?php echo e(__('Invoice')); ?></span>
                                                        <?php elseif($transaction->reference_type == 'payment'): ?>
                                                            <span class="badge bg-success"><?php echo e(__('Payment')); ?></span>
                                                        <?php elseif($transaction->reference_type == 'advance'): ?>
                                                            <span class="badge bg-warning"><?php echo e(__('Advance')); ?></span>
                                                        <?php elseif($transaction->reference_type == 'adjustment'): ?>
                                                            <span class="badge bg-dark"><?php echo e(__('Adjustment')); ?></span>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->reference_type == 'po'): ?>
                                                            <a href="<?php echo e(route('purchase-order.show', $transaction->reference_id)); ?>" target="_blank"><?php echo e($transaction->reference_number); ?></a>
                                                        <?php elseif($transaction->reference_type == 'grn'): ?>
                                                            <a href="<?php echo e(route('grn.show', $transaction->reference_id)); ?>" target="_blank"><?php echo e($transaction->reference_number); ?></a>
                                                        <?php elseif($transaction->reference_type == 'invoice'): ?>
                                                            <a href="<?php echo e(route('purchase-invoice.show', $transaction->reference_id)); ?>" target="_blank"><?php echo e($transaction->reference_number); ?></a>
                                                        <?php elseif($transaction->reference_type == 'payment' || $transaction->reference_type == 'advance'): ?>
                                                            <a href="<?php echo e(route('payments-module.edit', $transaction->reference_id)); ?>" target="_blank"><?php echo e($transaction->reference_number); ?></a>
                                                        <?php else: ?>
                                                            <?php echo e($transaction->reference_number ?? '-'); ?>

                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                    <td><?php echo e($transaction->supplier->name ?? '-'); ?></td>
                                                    <td class="text-right" style="text-align: right;"><?php echo e(currency_format_with_sym_indian($transaction->reference_amount ?? 0)); ?></td>
                                                    <td><?php echo e($transaction->site->name ?? '-'); ?></td>
                                                    <td class="text-right" style="text-align: right;">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNonAccounting): ?>
                                                            -
                                                        <?php else: ?>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float)($transaction->debit ?? 0) > 0): ?>
                                                                <?php echo e(currency_format_with_sym_indian($transaction->debit)); ?>

                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                    <td class="text-right" style="text-align: right;">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNonAccounting): ?>
                                                            -
                                                        <?php else: ?>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float)($transaction->credit ?? 0) > 0): ?>
                                                                <?php echo e(currency_format_with_sym_indian($transaction->credit)); ?>

                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                    <td class="text-right <?php echo e($transaction->balance < 0 ? 'text-danger' : ''); ?>" style="text-align: right;">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNonAccounting): ?>
                                                            -
                                                        <?php else: ?>
                                                            <?php echo e(currency_format_with_sym_indian($transaction->balance)); ?>

                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                    <td><?php echo e($transaction->description ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr>
                                                    <td colspan="10" class="text-center"><?php echo e(__('No transactions found')); ?></td>
                                                </tr>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ sample-page ] end -->
    </div>
    <!-- [ Main Content ] end -->
    
    <input type="hidden" id="filename" value="Supplier-Ledger-Report-<?php echo e(date('Y-m-d')); ?>">
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/reports/supplier-ledger/index.blade.php ENDPATH**/ ?>