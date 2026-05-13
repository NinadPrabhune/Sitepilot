<?php

namespace App\Helpers;

use App\Models\SupplierTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Grn;
use App\Models\PaymentsModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LedgerHelper
{
    /**
     * Create a supplier ledger entry.
     *
     * @param array $data
     * @return SupplierTransaction
     */
    public static function supplierLedger(array $data)
    {
        $validReferenceTypes = [
            SupplierTransaction::TYPE_PO,
            SupplierTransaction::TYPE_GRN,
            SupplierTransaction::TYPE_INVOICE,
            SupplierTransaction::TYPE_PAYMENT,
            SupplierTransaction::TYPE_ADVANCE,
            SupplierTransaction::TYPE_ADJUSTMENT,
        ];

        if (empty($data['reference_type']) || !in_array($data['reference_type'], $validReferenceTypes)) {
            throw new \InvalidArgumentException("Invalid reference_type: " . ($data['reference_type'] ?? 'null') . ". Must be one of: " . implode(', ', $validReferenceTypes));
        }

        $financialTypes = [
            SupplierTransaction::TYPE_PO,
            SupplierTransaction::TYPE_INVOICE,
            SupplierTransaction::TYPE_PAYMENT,
            SupplierTransaction::TYPE_ADVANCE,
        ];
        if (in_array($data['reference_type'], $financialTypes)) {
            $amount = $data['reference_amount'] ?? 0;
            if ($amount <= 0) {
                throw new \InvalidArgumentException("reference_amount must be > 0 for type: " . $data['reference_type']);
            }
        }

        $supplierId = $data['supplier_id'];
        
        // Get the last balance for this supplier (optionally filtered by site)
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if (!empty($data['site_id'])) {
            $query->where('site_id', $data['site_id']);
        }
        
        $lastTransaction = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $lastBalance = $lastTransaction ? $lastTransaction->balance : 0;

        // Calculate new balance
        // Running balance includes: Invoice (debit), Advance (credit), Payment (credit)
        // Running balance ignores: PO (non_accounting), GRN
        $debit = $data['debit'] ?? 0;
        $credit = $data['credit'] ?? 0;
        
        // Only truly informational types that don't affect running balance
        $informationalTypes = [
            SupplierTransaction::TYPE_PO,
            SupplierTransaction::TYPE_GRN,
        ];
        $isInformational = in_array($data['reference_type'], $informationalTypes);
        
        $meta = $data['meta'] ?? [];
        $isNonAccounting = !empty($meta['non_accounting']);
        
        // Advances are NOT informational - they DO affect running balance
        if ($isInformational || $isNonAccounting) {
            $newBalance = $lastBalance;
        } else {
            $newBalance = $lastBalance + $debit - $credit;
        }

        // Create the ledger entry
        $ledgerEntry = SupplierTransaction::create([
            'supplier_id' => $data['supplier_id'],
            'site_id' => $data['site_id'] ?? null,
            'reference_type' => $data['reference_type'],
            'reference_id' => $data['reference_id'],
            'reference_amount' => $data['reference_amount'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            'transaction_datetime' => $data['transaction_datetime'] ?? now(),
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newBalance,
            'description' => $data['description'] ?? null,
            'meta' => $meta ? json_encode($meta) : null,
            'workspace_id' => $data['workspace_id'],
            'created_by' => $data['created_by'] ?? Auth::id(),
        ]);

        // Log::channel('payment_audit')->info('Ledger entry created', [
        //     'ledger_id' => $ledgerEntry->id,
        //     'supplier_id' => $ledgerEntry->supplier_id,
        //     'site_id' => $ledgerEntry->site_id,
        //     'reference_type' => $ledgerEntry->reference_type,
        //     'reference_id' => $ledgerEntry->reference_id,
        //     'reference_amount' => $ledgerEntry->reference_amount,
        //     'debit' => $ledgerEntry->debit,
        //     'credit' => $ledgerEntry->credit,
        //     'balance' => $ledgerEntry->balance,
        //     'previous_balance' => $lastBalance,
        //     'is_informational' => $isInformational || $isNonAccounting,
        //     'created_by' => $ledgerEntry->created_by,
        //     'created_at' => $ledgerEntry->created_at,
        // ]);

        return $ledgerEntry;
    }

    /**
     * Create a purchase invoice ledger entry.
     * Invoice increases supplier liability (debit = invoice amount).
     *
     * @param PurchaseInvoice $invoice
     * @return SupplierTransaction
     */
    public static function createInvoiceEntry(PurchaseInvoice $invoice)
    {
        $invoiceAmount = number_format($invoice->grand_total, 2);
        
        $ledgerEntry = self::supplierLedger([
            'supplier_id' => $invoice->supplier_id,
            'site_id' => $invoice->site_id,
            'reference_type' => SupplierTransaction::TYPE_INVOICE,
            'reference_id' => $invoice->id,
            'reference_amount' => $invoice->grand_total,
            'transaction_date' => $invoice->invoice_date,
            'debit' => $invoice->grand_total,
            'credit' => 0,
            'description' => "{$invoice->invoice_number} / Invoice Generated / ₹{$invoiceAmount}",
            'workspace_id' => $invoice->workspace_id,
            'created_by' => $invoice->created_by,
        ]);

        // Log::channel('payment_audit')->info('Invoice ledger entry created', [
        //     'ledger_id' => $ledgerEntry->id,
        //     'invoice_id' => $invoice->id,
        //     'invoice_number' => $invoice->invoice_number,
        //     'po_id' => $invoice->po_id,
        //     'supplier_id' => $invoice->supplier_id,
        //     'site_id' => $invoice->site_id,
        //     'invoice_amount' => $invoice->grand_total,
        //     'invoice_date' => $invoice->invoice_date,
        // ]);

        return $ledgerEntry;
    }

    public static function createPaymentEntry(PaymentsModule $payment)
    {
        // Check if ledger entry already exists for this payment
        $existingEntry = SupplierTransaction::where('reference_id', $payment->id)
            ->where(function ($query) {
                $query->where('reference_type', SupplierTransaction::TYPE_PAYMENT)
                      ->orWhere('reference_type', SupplierTransaction::TYPE_ADVANCE);
            })
            ->first();

        if ($existingEntry) {
            // Log::channel('payment_audit')->info('Payment ledger entry already exists, skipping creation', [
            //     'payment_id' => $payment->id,
            //     'payment_number' => $payment->payment_number,
            //     'existing_ledger_id' => $existingEntry->id,
            // ]);
            return $existingEntry;
        }

        $referenceType = $payment->payment_type === 'advance_against_po'
            ? SupplierTransaction::TYPE_ADVANCE
            : SupplierTransaction::TYPE_PAYMENT;

        $mode = $payment->mode ?? 'bank_transfer';

        $typeLabel = match($payment->payment_type) {
            'advance_against_po' => 'Advance Against PO',
            'against_invoice' => 'Against Invoice',
            'against_po' => 'Against PO',
            default => 'Payment'
        };

        $paymentSubtype = match($payment->payment_type) {
            'advance_against_po' => 'advance',
            'against_invoice', 'against_po' => 'invoice_payment',
            default => 'invoice_payment'
        };

        $ledgerEntry = self::supplierLedger([
            'supplier_id' => $payment->supplier_id,
            'site_id' => $payment->site_id,
            'reference_type' => $referenceType,
            'reference_id' => $payment->id,
            'reference_amount' => $payment->amount,
            'transaction_date' => $payment->payment_date,
            'debit' => 0,
            'credit' => $payment->amount,
            'meta' => ['payment_subtype' => $paymentSubtype],
            'description' => "{$payment->payment_number} / {$mode} / {$typeLabel}",
            'workspace_id' => $payment->workspace_id,
            'created_by' => $payment->created_by,
        ]);

        // Log::channel('payment_audit')->info('Payment ledger entry created', [
        //     'ledger_id' => $ledgerEntry->id,
        //     'payment_id' => $payment->id,
        //     'payment_number' => $payment->payment_number,
        //     'payment_type' => $payment->payment_type,
        //     'reference_type' => $referenceType,
        //     'supplier_id' => $payment->supplier_id,
        //     'site_id' => $payment->site_id,
        //     'purchase_invoice_id' => $payment->purchase_invoice_id,
        //     'purchase_order_id' => $payment->purchase_order_id,
        //     'amount' => $payment->amount,
        //     'payment_date' => $payment->payment_date,
        // ]);

        return $ledgerEntry;
    }

    /**
     * Create a Purchase Order ledger entry.
     * PO increases supplier liability (debit = total amount).
     * Uses upsert to prevent duplicates.
     *
     * @param PurchaseOrder $po
     * @return SupplierTransaction
     */
    public static function createPOEntry(PurchaseOrder $po)
    {
        // Calculate total quantity from PO items
        $totalQty = $po->items->sum('quantity');
        
        $ledgerEntry = self::supplierLedger([
            'supplier_id' => $po->supplier_id,
            'site_id' => $po->site_id,
            'reference_type' => SupplierTransaction::TYPE_PO,
            'reference_id' => $po->id,
            'reference_amount' => $po->grand_total,
            'transaction_date' => $po->po_date,
            'debit' => 0,
            'credit' => 0,
            'meta' => ['non_accounting' => true],
            'description' => "{$po->po_number} / PO Created / Total Qty " . number_format($totalQty, 0) . " / ₹" . number_format($po->grand_total, 2),
            'workspace_id' => $po->workspace_id,
            'created_by' => $po->created_by,
        ]);

        // Log::channel('payment_audit')->info('PO ledger entry created', [
        //     'ledger_id' => $ledgerEntry->id,
        //     'po_id' => $po->id,
        //     'po_number' => $po->po_number,
        //     'supplier_id' => $po->supplier_id,
        //     'site_id' => $po->site_id,
        //     'po_amount' => $po->grand_total,
        //     'total_quantity' => $totalQty,
        //     'po_date' => $po->po_date,
        // ]);

        return $ledgerEntry;
    }

    /**
     * Create a GRN ledger entry.
     * GRN is informational only - no financial impact (debit=0, credit=0).
     * Uses upsert to prevent duplicates.
     *
     * @param Grn $grn
     * @return SupplierTransaction
     */
    public static function createGRNEntry(Grn $grn)
    {
        // Calculate total received quantity from GRN items
        $totalQty = $grn->items->sum('received_qty');
        
        return self::supplierLedger([
            'supplier_id' => $grn->supplier_id,
            'site_id' => $grn->site_id,
            'reference_type' => SupplierTransaction::TYPE_GRN,
            'reference_id' => $grn->id,
            'reference_amount' => $grn->total_amount ?? 0,
            'transaction_date' => $grn->grn_date,
            'debit' => 0,
            'credit' => 0,
            'meta' => ['non_accounting' => true],
            'description' => "{$grn->grn_number} / GRN Received / Total Qty " . number_format($totalQty, 0),
            'workspace_id' => $grn->workspace_id,
            'created_by' => $grn->created_by,
        ]);
    }

    /**
     * Create an adjustment ledger entry.
     *
     * @param int $supplierId
     * @param int $referenceId
     * @param string $description
     * @param float $debit
     * @param float $credit
     * @param int $workspaceId
     * @param int|null $createdBy
     * @return SupplierTransaction
     */
    public static function createAdjustmentEntry(
        int $supplierId,
        int $referenceId,
        string $description,
        int $workspaceId,
        float $debit = 0,
        float $credit = 0,
        ?int $createdBy = null
    ) {
        return self::supplierLedger([
            'supplier_id' => $supplierId,
            'site_id' => null,
            'reference_type' => SupplierTransaction::TYPE_ADJUSTMENT,
            'reference_id' => $referenceId,
            'transaction_date' => now()->toDateString(),
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'workspace_id' => $workspaceId,
            'created_by' => $createdBy ?? Auth::id(),
        ]);
    }

    /**
     * Delete ledger entries by reference type and ID.
     *
     * @param string $referenceType
     * @param int $referenceId
     * @return void
     */
    public static function deleteByReference(string $referenceType, int $referenceId)
    {
        SupplierTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->delete();
    }

    /**
     * Delete all ledger entries for a purchase invoice.
     *
     * @param int $invoiceId
     * @return void
     */
    public static function deleteInvoiceEntries(int $invoiceId)
    {
        self::deleteByReference(SupplierTransaction::TYPE_INVOICE, $invoiceId);
    }

    /**
     * Delete all ledger entries for a payment.
     *
     * @param int $paymentId
     * @return void
     */
    public static function deletePaymentEntries(int $paymentId)
    {
        // Delete both payment and advance entries
        SupplierTransaction::where(function ($query) use ($paymentId) {
            $query->where(function ($q) use ($paymentId) {
                $q->where('reference_type', SupplierTransaction::TYPE_PAYMENT)
                  ->where('reference_id', $paymentId);
            })->orWhere(function ($q) use ($paymentId) {
                $q->where('reference_type', SupplierTransaction::TYPE_ADVANCE)
                  ->where('reference_id', $paymentId);
            });
        })->delete();
    }

    /**
     * Delete all ledger entries for a Purchase Order.
     *
     * @param int $poId
     * @return void
     */
    public static function deletePOEntries(int $poId)
    {
        self::deleteByReference(SupplierTransaction::TYPE_PO, $poId);
    }

    /**
     * Delete all ledger entries for a GRN.
     *
     * @param int $grnId
     * @return void
     */
    public static function deleteGRNEntries(int $grnId)
    {
        self::deleteByReference(SupplierTransaction::TYPE_GRN, $grnId);
    }

    /**
     * Handle PO deletion - delete entries and recalculate balance.
     *
     * @param int $poId
     * @return void
     */
    public static function handlePODeletion(int $poId)
    {
        $po = PurchaseOrder::find($poId);
        
        if (!$po) {
            return;
        }

        $supplierId = $po->supplier_id;

        self::deletePOEntries($poId);

        self::recalculateSupplierBalance($supplierId);
    }

    /**
     * Handle GRN deletion - delete entries and recalculate balance.
     *
     * @param int $grnId
     * @return void
     */
    public static function handleGRNDeletion(int $grnId)
    {
        $grn = Grn::find($grnId);
        
        if (!$grn) {
            return;
        }

        $supplierId = $grn->supplier_id;

        self::deleteGRNEntries($grnId);

        self::recalculateSupplierBalance($supplierId);
    }

    /**
     * Recalculate balance for a supplier.
     * This will reorder all transactions by date and recompute balances.
     * 
     * Running balance includes ALL accounting entries except:
     * - PO (marked as non_accounting)
     * - GRN (purely informational)
     * 
     * Advance payments ARE included in running balance.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return void
     */
    public static function recalculateSupplierBalance($supplierId, $siteId = null)
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        // Check for snapshot to optimize performance
        $snapshot = \App\Models\SupplierLedgerSnapshot::getLatest($supplierId, $siteId);
        $startingBalance = 0;
        $startingTransactionId = null;
        
        if ($snapshot) {
            $startingBalance = $snapshot->balance;
            $startingTransactionId = $snapshot->last_transaction_id;
            $query->where('id', '>', $startingTransactionId);
        }
        
        $transactions = $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

        $runningBalance = $startingBalance;
        $balanceUpdates = [];
        $ids = [];

        // Only truly informational types that don't affect running balance
        // PO is marked with non_accounting flag, GRN is purely informational
        $ignoredTypes = [
            SupplierTransaction::TYPE_PO,
            SupplierTransaction::TYPE_GRN,
        ];

        // Start a database transaction to ensure atomicity
        DB::transaction(function () use ($transactions, &$runningBalance, $ignoredTypes, &$balanceUpdates, &$ids) {
            foreach ($transactions as $transaction) {
                $isIgnoredType = in_array($transaction->reference_type, $ignoredTypes);
                $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
                $isNonAccounting = !empty($meta['non_accounting']);

                // Skip only: PO (non_accounting=true) and GRN
                // Include: Invoice, Advance, Payment
                if ($isIgnoredType || $isNonAccounting) {
                    // Don't change running balance for informational/non-accounting entries
                    // Store current running balance for display (will show as "-" in view based on isNonAccounting flag)
                    $transaction->balance = $runningBalance;
                } else {
                    $runningBalance = $runningBalance + $transaction->debit - $transaction->credit;
                    $transaction->balance = $runningBalance;
                }
                
                $balanceUpdates[$transaction->id] = $transaction->balance;
                $ids[] = $transaction->id;
            }
            
            // TRUE batch update using CASE WHEN (single SQL query)
            if (!empty($balanceUpdates)) {
                $caseWhen = 'CASE id ';
                foreach ($balanceUpdates as $id => $balance) {
                    $caseWhen .= "WHEN {$id} THEN {$balance} ";
                }
                $caseWhen .= 'END';
                
                DB::table('supplier_transactions')
                    ->whereIn('id', $ids)
                    ->update(['balance' => DB::raw($caseWhen)]);
            }
        });
        
        // Create new snapshot after recalculation
        \App\Models\SupplierLedgerSnapshot::createSnapshot($supplierId, $siteId);
    }

    /**
     * Handle invoice deletion - delete entries and recalculate balance.
     *
     * @param int $invoiceId
     * @return void
     */
    public static function handleInvoiceDeletion(int $invoiceId)
    {
        // Get the invoice to find supplier_id
        $invoice = PurchaseInvoice::find($invoiceId);
        
        if (!$invoice) {
            return;
        }

        $supplierId = $invoice->supplier_id;

        // Delete invoice ledger entries
        self::deleteInvoiceEntries($invoiceId);

        // Recalculate balance for remaining transactions
        self::recalculateSupplierBalance($supplierId);
    }

    /**
     * Handle payment deletion - delete entries and recalculate balance.
     *
     * @param int $paymentId
     * @return void
     */
    public static function handlePaymentDeletion(int $paymentId)
    {
        // Get the payment to find supplier_id
        $payment = PaymentsModule::find($paymentId);
        
        if (!$payment) {
            return;
        }

        $supplierId = $payment->supplier_id;

        // Delete payment ledger entries
        self::deletePaymentEntries($paymentId);

        // Recalculate balance for remaining transactions
        self::recalculateSupplierBalance($supplierId);
    }

    /**
     * Get summary data for a supplier.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return array
     */
    public static function getSupplierSummary(int $supplierId, ?int $siteId = null)
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $transactions = $query->get();

        $totalPO = $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount');
        $totalPayments = $transactions->where('reference_type', SupplierTransaction::TYPE_PAYMENT)->sum('credit');
        $totalAdvances = $transactions->where('reference_type', SupplierTransaction::TYPE_ADVANCE)->sum('credit');
        
        // Invoice total uses debit column (financial impact)
        $totalInvoiceAmount = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
        
        $currentBalance = self::getCurrentBalanceBySite($supplierId, $siteId);

        return [
            'total_po' => $totalPO,
            'total_payments' => $totalPayments,
            'total_advances' => $totalAdvances,
            'total_invoice' => $totalInvoiceAmount,
            'current_balance' => $currentBalance,
        ];
    }

    /**
     * Get PO vs Payment summary for a supplier.
     * Returns total PO, total paid, and remaining liability.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return array ['total_po' => float, 'total_paid' => float, 'remaining' => float]
     */
    public static function getPOSummary(int $supplierId, ?int $siteId = null): array
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $transactions = $query->get();
        
        // Invoice total uses debit column
        $totalInvoice = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
        
        // Invoice payments: only payments with payment_subtype = 'invoice_payment'
        $invoicePaid = $transactions
            ->filter(function ($t) {
                if ($t->reference_type !== SupplierTransaction::TYPE_PAYMENT) {
                    return false;
                }
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta ?? '{}', true);
                return ($meta['payment_subtype'] ?? null) === 'invoice_payment';
            })
            ->sum('credit');
        
        // Advance payments: only payments with payment_subtype = 'advance'
        $advancePaid = $transactions
            ->filter(function ($t) {
                if ($t->reference_type !== SupplierTransaction::TYPE_PAYMENT) {
                    return false;
                }
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta ?? '{}', true);
                return ($meta['payment_subtype'] ?? null) === 'advance';
            })
            ->sum('credit');
        
        return [
            'total_po' => $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount'),
            'invoice_total' => $totalInvoice,
            'invoice_paid' => $invoicePaid,
            'advance_paid' => $advancePaid,
            'payable' => max(0, $totalInvoice - $invoicePaid),
        ];
    }

    /**
     * Get payable balance for a supplier.
     * 
     * This is the ACTUAL invoice-based payable:
     * payable = total_invoice_debit - total_invoice_payment_credit
     * 
     * Advances are NOT included in payable calculation.
     * They are tracked separately via running balance.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return array ['total_invoice' => float, 'invoice_payments' => float, 'advance_balance' => float, 'payable' => float, 'running_balance' => float]
     */
    public static function getPayableBalance(int $supplierId, ?int $siteId = null): array
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $transactions = $query->get();
        
        // Total Invoice Debits (actual payable liability)
        $totalInvoice = (float) $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
        
        // Invoice Payments: only payments with payment_subtype = 'invoice_payment'
        $invoicePayments = (float) $transactions
            ->filter(function ($t) {
                if ($t->reference_type !== SupplierTransaction::TYPE_PAYMENT) {
                    return false;
                }
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta ?? '{}', true);
                return ($meta['payment_subtype'] ?? null) === 'invoice_payment';
            })
            ->sum('credit');
        
        // Advances: only payments with payment_subtype = 'advance' OR reference_type = 'advance'
        $advanceBalance = (float) $transactions
            ->filter(function ($t) {
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta ?? '{}', true);
                return $t->reference_type === SupplierTransaction::TYPE_ADVANCE 
                    || ($t->reference_type === SupplierTransaction::TYPE_PAYMENT && ($meta['payment_subtype'] ?? null) === 'advance');
            })
            ->sum('credit');
        
        // Payable = Invoice - Invoice Payments (advances handled separately, NOT reducing payable)
        $payable = $totalInvoice - $invoicePayments;
        
        // Get final running balance
        $runningBalance = self::getCurrentBalanceBySite($supplierId, $siteId);
        
        return [
            'total_invoice' => $totalInvoice,
            'invoice_payments' => $invoicePayments,
            'advance_balance' => $advanceBalance,
            'payable' => max(0, $payable),
            'running_balance' => $runningBalance,
        ];
    }

    /**
     * Get total advance balance (unadjusted advances) for a supplier.
     * 
     * Advances that haven't been adjusted to any invoice yet.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return float
     */
    public static function getAdvanceBalance(int $supplierId, ?int $siteId = null): float
    {
        $query = \App\Models\PaymentsModule::where('supplier_id', $supplierId)
            ->where('payment_type', 'advance_against_po');
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $totalAdvances = (float) $query->sum('amount');
        
        // Subtract already adjusted amounts
        $adjustedAmounts = (float) \App\Models\AdvanceAdjustment::whereHas('payment', function($q) use ($supplierId, $siteId) {
            $q->where('supplier_id', $supplierId);
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
        })->sum('utilized_amount');
        
        return max(0, $totalAdvances - $adjustedAmounts);
    }

    /**
     * Calculate remaining PO liability for a supplier.
     * Formula: Total PO (debit) - Total Paid (credit)
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return float
     */
    public static function getRemainingPOLiability(int $supplierId, ?int $siteId = null)
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        // Invoice-based: Total Invoice (debit) - Total Invoice Payments (credit only, excludes advances)
        $totalInvoice = $query->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
        $totalPaid = $query->where('reference_type', SupplierTransaction::TYPE_PAYMENT)->sum('credit');
        
        return $totalInvoice - $totalPaid;
    }

    /**
     * Calculate remaining PO liability with row-level locking.
     * Use this inside a transaction to prevent concurrent overpayments.
     *
     * @param int $supplierId
     * @param int|null $siteId Optional site filter
     * @return float
     */
    public static function getRemainingPOLiabilityWithLock(int $supplierId, ?int $siteId = null): float
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        // Lock the rows for update to prevent concurrent modifications
        $query->lockForUpdate();
        
        // Invoice-based: Total Invoice (debit) - Total Invoice Payments (credit only, excludes advances)
        $totalInvoice = $query->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
        $totalPaid = SupplierTransaction::where('supplier_id', $supplierId)
            ->where(function ($q) use ($siteId) {
                if ($siteId) {
                    $q->where('site_id', $siteId);
                }
            })
            ->where('reference_type', SupplierTransaction::TYPE_PAYMENT)
            ->lockForUpdate()
            ->sum('credit');
        
        return $totalInvoice - $totalPaid;
    }

    /**
     * Validate payment amount against remaining PO liability.
     *
     * @param int $supplierId
     * @param float $paymentAmount
     * @param int|null $siteId Optional site filter
     * @return array ['valid' => bool, 'remaining' => float, 'message' => string]
     */
    public static function validatePaymentAmount(int $supplierId, float $paymentAmount, ?int $siteId = null): array
    {
        $remaining = self::getRemainingPOLiability($supplierId, $siteId);
        
        if ($paymentAmount > $remaining) {
            return [
                'valid' => false,
                'remaining' => $remaining,
                'message' => 'Payment amount exceeds PO liability. Maximum allowable: ' . number_format($remaining, 2)
            ];
        }
        
        return [
            'valid' => true,
            'remaining' => $remaining,
            'message' => 'Valid payment amount'
        ];
    }

    /**
     * Create or update PO ledger entry (prevents duplicates).
     *
     * @param PurchaseOrder $po
     * @return SupplierTransaction
     */
    public static function upsertPOEntry(PurchaseOrder $po)
    {
        $existingEntry = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PO)
            ->where('reference_id', $po->id)
            ->first();

        if ($existingEntry) {
            $existingEntry->update([
                'debit' => 0,
                'credit' => 0,
                'reference_amount' => $po->grand_total,
                'meta' => json_encode(['non_accounting' => true]),
                'description' => "Purchase Order {$po->po_number} / PO Created / ₹" . number_format($po->grand_total, 2),
                'transaction_date' => $po->po_date,
                'site_id' => $po->site_id,
            ]);
            
            self::recalculateSupplierBalance($po->supplier_id);
            
            return $existingEntry;
        }

        return self::supplierLedger([
            'supplier_id' => $po->supplier_id,
            'site_id' => $po->site_id,
            'reference_type' => SupplierTransaction::TYPE_PO,
            'reference_id' => $po->id,
            'reference_amount' => $po->grand_total,
            'transaction_date' => $po->po_date,
            'debit' => 0,
            'credit' => 0,
            'meta' => ['non_accounting' => true],
            'description' => "Purchase Order {$po->po_number} / PO Created / ₹" . number_format($po->grand_total, 2),
            'workspace_id' => $po->workspace_id,
            'created_by' => $po->created_by,
        ]);
    }

    /**
     * Create or update GRN ledger entry (prevents duplicates).
     *
     * @param Grn $grn
     * @return SupplierTransaction
     */
    public static function upsertGRNEntry(Grn $grn)
    {
        $existingEntry = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_GRN)
            ->where('reference_id', $grn->id)
            ->first();

        if ($existingEntry) {
            $existingEntry->update([
                'description' => "GRN {$grn->grn_number}",
                'transaction_date' => $grn->grn_date,
                'site_id' => $grn->site_id,
            ]);
            
            return $existingEntry;
        }

        return self::supplierLedger([
            'supplier_id' => $grn->supplier_id,
            'site_id' => $grn->site_id,
            'reference_type' => SupplierTransaction::TYPE_GRN,
            'reference_id' => $grn->id,
            'reference_amount' => $grn->total_amount ?? 0,
            'transaction_date' => $grn->grn_date,
            'debit' => 0,
            'credit' => 0,
            'description' => "GRN {$grn->grn_number}",
            'workspace_id' => $grn->workspace_id,
            'created_by' => $grn->created_by,
        ]);
    }

    /**
     * Get supplier balance filtered by site.
     *
     * @param int $supplierId
     * @param int|null $siteId
     * @return float
     */
    public static function getCurrentBalanceBySite(int $supplierId, ?int $siteId = null): float
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId)
            ->orderedByDate();

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $lastTransaction = $query->latest()->first();
        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    // ============================================
    // SUPPLIER ADVANCE SYSTEM - 3-LAYER SETTLEMENT MODEL
    // ============================================

    /**
     * Create ledger entry when advance is paid.
     * 3-Layer Settlement Model Step 1: Advance Payment
     * Debit: Supplier Advance (Asset Account)
     * Credit: Bank/Cash
     * 
     * @param \App\Models\SupplierAdvance $advance
     * @param array $paymentData
     * @return SupplierTransaction
     */
    public static function createAdvancePaymentLedgerEntry($advance, array $paymentData)
    {
        return self::supplierLedger([
            'supplier_id' => $advance->supplier_id,
            'site_id' => $advance->site_id,
            'reference_type' => SupplierTransaction::TYPE_ADVANCE,
            'reference_id' => $advance->id,
            'reference_amount' => $advance->amount,
            'transaction_date' => $paymentData['payment_date'] ?? $advance->payment_date,
            'debit' => 0,
            'credit' => $advance->amount,
            'description' => "{$advance->advance_number} / Advance Payment / " . ($paymentData['payment_mode'] ?? 'Bank Transfer'),
            'meta' => [
                'payment_subtype' => 'advance',
                'advance_source' => $advance->source,
                'po_id' => $advance->po_id,
            ],
            'workspace_id' => $advance->workspace_id,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Create ledger entry when advance is used in invoice.
     * 3-Layer Settlement Model Step 2: Advance Allocation
     * Debit: Accounts Payable (Supplier Liability)
     * Credit: Supplier Advance (Asset Account)
     * Also updates invoice payable side: Dr Accounts Payable | Cr Invoice Revenue/Expense
     * 
     * @param \App\Models\SupplierAdvance $advance
     * @param \App\Models\PurchaseInvoice $invoice
     * @param float $amount
     * @return SupplierTransaction
     */
    public static function createAdvanceUtilizationLedgerEntry($advance, $invoice, float $amount)
    {
        // This reduces the liability by applying the advance asset
        return self::supplierLedger([
            'supplier_id' => $advance->supplier_id,
            'site_id' => $advance->site_id,
            'reference_type' => SupplierTransaction::TYPE_ADJUSTMENT,
            'reference_id' => $advance->id,
            'reference_amount' => $amount,
            'transaction_date' => now()->toDateString(),
            'debit' => 0,
            'credit' => $amount,
            'description' => "{$advance->advance_number} / Advance Applied to {$invoice->invoice_number} / ₹" . number_format($amount, 2),
            'meta' => [
                'adjustment_type' => 'advance_utilization',
                'advance_id' => $advance->id,
                'invoice_id' => $invoice->id,
                'po_id' => $advance->po_id,
            ],
            'workspace_id' => $advance->workspace_id,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Create ledger entry when invoice is created.
     * 3-Layer Settlement Model Step 1: Invoice Creation
     * Debit: Expense/Purchase
     * Credit: Accounts Payable (Supplier Liability)
     * 
     * @param \App\Models\PurchaseInvoice $invoice
     * @return SupplierTransaction
     */
    public static function createInvoiceCreationLedgerEntry($invoice)
    {
        // This creates the liability (debit = increase in liability)
        return self::supplierLedger([
            'supplier_id' => $invoice->supplier_id,
            'site_id' => $invoice->site_id,
            'reference_type' => SupplierTransaction::TYPE_INVOICE,
            'reference_id' => $invoice->id,
            'reference_amount' => $invoice->grand_total,
            'transaction_date' => $invoice->invoice_date,
            'debit' => $invoice->grand_total,
            'credit' => 0,
            'description' => "{$invoice->invoice_number} / Invoice Created / ₹" . number_format($invoice->grand_total, 2),
            'meta' => [
                'invoice_type' => $invoice->invoice_type ?? 'standard',
                'po_id' => $invoice->po_id,
            ],
            'workspace_id' => $invoice->workspace_id,
            'created_by' => $invoice->created_by,
        ]);
    }

    /**
     * Create ledger entry when payment is made.
     * 3-Layer Settlement Model Step 3: Payment
     * Debit: Accounts Payable (Supplier Liability)
     * Credit: Bank/Cash
     * 
     * @param \App\Models\PaymentsModule $payment
     * @param \App\Models\PurchaseInvoice|null $invoice
     * @return SupplierTransaction
     */
    public static function createPaymentSettlementLedgerEntry($payment, $invoice = null)
    {
        $description = $payment->payment_number . " / Payment";
        if ($invoice) {
            $description .= " / Against {$invoice->invoice_number}";
        }

        return self::supplierLedger([
            'supplier_id' => $payment->supplier_id,
            'site_id' => $payment->site_id,
            'reference_type' => SupplierTransaction::TYPE_PAYMENT,
            'reference_id' => $payment->id,
            'reference_amount' => $payment->amount,
            'transaction_date' => $payment->payment_date,
            'debit' => 0,
            'credit' => $payment->amount,
            'description' => $description,
            'meta' => [
                'payment_subtype' => 'invoice_payment',
                'invoice_id' => $invoice ? $invoice->id : null,
            ],
            'workspace_id' => $payment->workspace_id,
            'created_by' => $payment->created_by,
        ]);
    }

    /**
     * Validate advance ledger integrity.
     * Verify balance consistency, no negative remaining, supplier match, no duplicate utilization.
     * 
     * @param int $advanceId
     * @return array
     */
    public static function validateAdvanceLedgerIntegrity(int $advanceId): array
    {
        $advance = \App\Models\SupplierAdvance::with('utilizations')->findOrFail($advanceId);

        $issues = [];

        // Check for negative remaining amount
        if ($advance->remaining_amount < 0) {
            $issues[] = 'Negative remaining amount detected';
        }

        // Check balance consistency
        $expectedRemaining = $advance->amount - $advance->utilized_amount - $advance->reserved_amount;
        if (abs($advance->remaining_amount - $expectedRemaining) > 0.01) {
            $issues[] = 'Balance inconsistency detected';
        }

        // Check supplier match in utilizations
        foreach ($advance->utilizations as $utilization) {
            $invoice = $utilization->invoice;
            if ($invoice && $invoice->supplier_id !== $advance->supplier_id) {
                $issues[] = "Supplier mismatch in utilization for invoice {$invoice->invoice_number}";
            }
        }

        // Check for duplicate utilization (same advance + invoice combination)
        $utilizationCounts = $advance->utilizations->groupBy('purchase_invoice_id');
        foreach ($utilizationCounts as $invoiceId => $records) {
            if ($records->count() > 1) {
                $issues[] = "Duplicate utilization detected for invoice ID {$invoiceId}";
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'advance' => [
                'id' => $advance->id,
                'advance_number' => $advance->advance_number,
                'amount' => $advance->amount,
                'remaining_amount' => $advance->remaining_amount,
                'utilized_amount' => $advance->utilized_amount,
                'reserved_amount' => $advance->reserved_amount,
            ],
        ];
    }

    /**
     * Validate ledger balance consistency for a supplier.
     * Verify total ledger matches advance + invoice + payment.
     * 
     * @param int $supplierId
     * @return array
     */
    public static function validateLedgerBalanceConsistency(int $supplierId): array
    {
        $issues = [];

        // Get ledger balance
        $ledgerBalance = self::getCurrentBalanceBySite($supplierId);

        // Get calculated balance from components
        $totalInvoiceDebits = SupplierTransaction::where('supplier_id', $supplierId)
            ->where('reference_type', SupplierTransaction::TYPE_INVOICE)
            ->sum('debit');

        $totalPaymentCredits = SupplierTransaction::where('supplier_id', $supplierId)
            ->where('reference_type', SupplierTransaction::TYPE_PAYMENT)
            ->sum('credit');

        $totalAdvanceCredits = SupplierTransaction::where('supplier_id', $supplierId)
            ->where('reference_type', SupplierTransaction::TYPE_ADVANCE)
            ->sum('credit');

        $totalAdjustmentCredits = SupplierTransaction::where('supplier_id', $supplierId)
            ->where('reference_type', SupplierTransaction::TYPE_ADJUSTMENT)
            ->sum('credit');

        $calculatedBalance = $totalInvoiceDebits - $totalPaymentCredits - $totalAdvanceCredits - $totalAdjustmentCredits;

        if (abs($ledgerBalance - $calculatedBalance) > 0.01) {
            $issues[] = 'Ledger balance does not match calculated balance from components';
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'balances' => [
                'ledger_balance' => $ledgerBalance,
                'calculated_balance' => $calculatedBalance,
                'difference' => abs($ledgerBalance - $calculatedBalance),
                'components' => [
                    'invoice_debits' => $totalInvoiceDebits,
                    'payment_credits' => $totalPaymentCredits,
                    'advance_credits' => $totalAdvanceCredits,
                    'adjustment_credits' => $totalAdjustmentCredits,
                ],
            ],
        ];
    }
}
