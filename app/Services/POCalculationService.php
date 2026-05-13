<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\PaymentModuleAllocation;
use App\Models\AdvanceAdjustment;
use App\Models\Grn;
use App\Helpers\LedgerHelper;
use Illuminate\Support\Facades\Log;

class POCalculationService
{
    public function calculate(int $poId): array
    {
        $po = PurchaseOrder::findOrFail($poId);

        $invoicedAmount = $this->getInvoicedAmount($poId);
        $totalPaid = $this->getTotalPaid($poId);

        $poBalance = $po->grand_total - $invoicedAmount;
        $payable = $invoicedAmount - $totalPaid;

        $advancePaid = $this->getAdvancePaid($poId);
        $advanceUtilized = $this->getAdvanceUtilized($poId);
        $advanceBalance = $advancePaid - $advanceUtilized;

        return [
            'po_total' => (float) $po->grand_total,
            'invoiced_amount' => $invoicedAmount,
            'po_balance' => max(0, $poBalance),
            'total_paid' => $totalPaid,
            'payable' => max(0, $payable),
            'advance_paid' => $advancePaid,
            'advance_utilized' => $advanceUtilized,
            'advance_balance' => max(0, $advanceBalance),
        ];
    }

    public function getInvoicedAmount(int $poId): float
    {
        return PurchaseInvoice::where('po_id', $poId)->sum('grand_total');
    }

    public function getTotalPaid(int $poId): float
    {
        $fromAllocations = PaymentModuleAllocation::where('purchase_order_id', $poId)
            ->sum('allocated_amount');

        $fromDirectPayments = PaymentsModule::where('purchase_order_id', $poId)
            ->where('payment_type', 'against_po')
            ->sum('amount');

        return $fromAllocations + $fromDirectPayments;
    }

    public function getAdvancePaid(int $poId): float
    {
        return PaymentsModule::where('purchase_order_id', $poId)
            ->where('payment_type', 'advance_against_po')
            ->sum('amount');
    }

    public function getAdvanceUtilized(int $poId): float
    {
        $invoiceIds = PurchaseInvoice::where('po_id', $poId)->pluck('id');
        return AdvanceAdjustment::withoutTrashed()
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->sum('utilized_amount');
    }

    public function getPOBalance(int $poId): float
    {
        $po = PurchaseOrder::findOrFail($poId);
        $invoiced = $this->getInvoicedAmount($poId);
        return max(0, $po->grand_total - $invoiced);
    }

    public function getInvoicePayable(int $poId): float
    {
        $invoiced = $this->getInvoicedAmount($poId);
        $paid = $this->getTotalPaid($poId);
        return max(0, $invoiced - $paid);
    }

    public function canClosePO(int $poId): bool
    {
        $po = PurchaseOrder::findOrFail($poId);
        $poBalance = $this->getPOBalance($poId);
        $payable = $this->getInvoicePayable($poId);

        return $poBalance == 0 && $payable == 0;
    }

    public function updatePOInvoiceAmount(int $poId): void
    {
        $po = PurchaseOrder::findOrFail($poId);
        $invoicedAmount = $this->getInvoicedAmount($poId);
        $po->invoiced_amount = $invoicedAmount;
        $po->save();

        $this->updatePOStatus($po);
        
        // Update payment flag when invoice amounts change
        $po->updatePaymentFlag();
    }

    /**
     * Update payment flag after a payment is made.
     * 
     * @param int $poId
     * @return void
     */
    public function updatePaymentFlag(int $poId): void
    {
        $po = PurchaseOrder::findOrFail($poId);
        $po->updatePaymentFlag();
    }

    /**
     * Update PO status based on invoiced vs PO amount.
     * NOTE: This is for invoice-based closure, NOT payment-based.
     * Payment-based closure should only consider settlement payments (against_po, against_invoice).
     */
    protected function updatePOStatus(PurchaseOrder $po): void
    {
        $poBalance = $po->grand_total - ($po->invoiced_amount ?? 0);

        if ($poBalance <= 0 && $po->status !== 'Closed') {
            $po->status = 'Closed';
            $po->closed_date = now();
            $po->save();
            Log::info('PO auto-closed due to full invoicing', ['po_id' => $po->id]);
        } elseif ($poBalance > 0 && $po->invoiced_amount > 0 && $po->status !== 'Partial') {
            $po->status = 'Partial';
            $po->save();
        }
    }

    /**
     * Get ledger entries for a PO (A/c Statement format)
     * 
     * Accounting rules:
     * - PO (non_accounting): shown but doesn't affect balance
     * - GRN (non_accounting): shown but doesn't affect balance  
     * - Invoice: DEBIT - increases balance
     * - Advance: CREDIT - decreases balance
     * - Payment: CREDIT - decreases balance
     * 
     * @param int $poId
     * @return array
     */
    public function getLedgerEntries(int $poId): array
    {
        $po = PurchaseOrder::findOrFail($poId);
        $entries = [];
        $runningBalance = 0; // Start at 0, not PO grand_total
        $totalPoQty = $po->items->sum('quantity');

        // 1. PO Creation Entry (non-accounting - shown but doesn't affect balance)
        $entries[] = [
            'date' => $po->po_date,
            'datetime' => $po->created_at ? $po->created_at->format('Y-m-d H:i:s') : $po->po_date . ' 00:00:00',
            'details' => $po->po_number . ' / ' . $po->supplier->name . ' / PO Created / Total Qty' . $totalPoQty,
            'debit' => (float) $po->grand_total,
            'credit' => 0,
            'running_balance' => $runningBalance, // PO doesn't affect balance
            'type' => 'po_created',
            'meta' => ['non_accounting' => true],
        ];

        // 2. GRN Entries (non-accounting - shown but doesn't affect balance)
        $grns = Grn::where('po_id', $poId)
            ->orderBy('grn_date')
            ->orderBy('created_at')
            ->get();

        foreach ($grns as $grn) {
            $grn->load('items');
            $totalQty = $grn->items->sum('received_qty');
            $entries[] = [
                'date' => $grn->grn_date,
                'datetime' => $grn->created_at ? $grn->created_at->format('Y-m-d H:i:s') : $grn->grn_date . ' 00:00:00',
                'details' => $grn->grn_number . ' / GRN Received / Total Qty ' . $totalQty,
                'debit' => 0,
                'credit' => 0,
                'running_balance' => $runningBalance, // GRN doesn't affect balance
                'type' => 'grn_info',
                'meta' => ['non_accounting' => true],
            ];
        }

        // 3. Advance Payments (Credit - reduces balance)
        $advances = PaymentsModule::where('purchase_order_id', $poId)
            ->where('payment_type', 'advance_against_po')
            ->orderBy('payment_date')
            ->orderBy('created_at')
            ->get();

        foreach ($advances as $advance) {
            $runningBalance -= (float) $advance->amount;
            $entries[] = [
                'date' => $advance->payment_date,
                'datetime' => $advance->created_at ? $advance->created_at->format('Y-m-d H:i:s') : $advance->payment_date . ' 00:00:00',
                'details' => $advance->payment_number . ' / ' . $advance->mode . ' / Advance Against PO',
                'debit' => 0,
                'credit' => (float) $advance->amount,
                'running_balance' => $runningBalance,
                'type' => 'advance',
                'meta' => ['payment_subtype' => 'advance'],
            ];
        }

        // 4. Invoices (Debit - INCREASES balance)
        $invoices = PurchaseInvoice::where('po_id', $poId)
            ->orderBy('invoice_date')
            ->orderBy('created_at')
            ->get();

        foreach ($invoices as $invoice) {
            $runningBalance += (float) $invoice->grand_total; // Invoice ADDS to balance
            $entries[] = [
                'date' => $invoice->invoice_date,
                'datetime' => $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : $invoice->invoice_date . ' 00:00:00',
                'details' => $invoice->invoice_number . ' / Invoice Generated / ₹' . number_format($invoice->grand_total, 2),
                'invoice_number' => $invoice->invoice_number,
                'amount' => (float) $invoice->grand_total,
                'debit' => (float) $invoice->grand_total, // Invoice is a DEBIT
                'credit' => 0,
                'running_balance' => $runningBalance, // Invoice INCREASES balance
                'type' => 'invoice_info',
                'meta' => ['payment_subtype' => 'invoice'],
            ];
        }

        // 5. Payments Against Invoices (Credit - reduces balance)
        $payments = PaymentsModule::where('purchase_order_id', $poId)
            ->whereIn('payment_type', ['against_po', 'against_invoice'])
            ->orderBy('payment_date')
            ->orderBy('created_at')
            ->get();

        foreach ($payments as $payment) {
            $runningBalance -= (float) $payment->amount;
            $typeLabel = $payment->payment_type === 'against_invoice' ? 'Against Invoice' : 'Payment Against Invoice';
            $entries[] = [
                'date' => $payment->payment_date,
                'datetime' => $payment->created_at ? $payment->created_at->format('Y-m-d H:i:s') : $payment->payment_date . ' 00:00:00',
                'details' => $payment->payment_number . ' / ' . $payment->mode . ' / ' . $typeLabel,
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'running_balance' => $runningBalance,
                'type' => 'payment',
                'meta' => ['payment_subtype' => 'invoice_payment'],
            ];
        }

        // Sort all entries by datetime
        usort($entries, function($a, $b) {
            return strcmp($a['datetime'], $b['datetime']);
        });

        // Recalculate running balance after sorting (to ensure correct chronological order)
        // Now respects non_accounting flag
        $runningBalance = 0;
        foreach ($entries as &$entry) {
            $meta = $entry['meta'] ?? [];
            $isNonAccounting = !empty($meta['non_accounting']);
            
            if (!$isNonAccounting) {
                $runningBalance = $runningBalance + ($entry['debit'] ?? 0) - ($entry['credit'] ?? 0);
            }
            $entry['running_balance'] = $runningBalance;
        }

        return $entries;
    }

    /**
     * Format currency as Indian format: ₹1,23,456.00
     * 
     * @param float $amount
     * @return string
     */
    public function formatCurrency(float $amount): string
    {
        return '₹' . number_format($amount, 2, '.', ',');
    }

    /**
     * @deprecated Auto-allocate payment to invoices using FIFO method
     * This method is deprecated as of Phase 3 - payments are now directly linked to invoices
     * via purchase_invoice_id instead of using payment_module_allocations table
     *
     * @param PaymentsModule $payment
     * @return void
     */
    public function autoAllocateToInvoices(PaymentsModule $payment): void
    {
        Log::channel('payment_audit')->warning('Deprecated method autoAllocateToInvoices called', [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
        ]);

        $poId = $payment->purchase_order_id;
        if (!$poId) {
            return;
        }

        $remainingAmount = (float) $payment->amount;

        $invoices = PurchaseInvoice::where('po_id', $poId)
            ->where('payment_status', '!=', 'paid')
            ->orderBy('invoice_date', 'asc')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceBalance = (float) $invoice->grand_total - (float) ($invoice->paid_amount ?? 0);

            if ($invoiceBalance <= 0) {
                continue;
            }

            $allocatedAmount = min($remainingAmount, $invoiceBalance);

            // Deprecated: Do not create allocations anymore
            // Payments should be directly linked to invoices via purchase_invoice_id
            // This is kept for backward compatibility only
            Log::channel('payment_audit')->warning('Skipping payment allocation creation (deprecated)', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $allocatedAmount,
            ]);

            $remainingAmount -= $allocatedAmount;
        }
    }

    /**
     * Update invoice payment status based on paid amount
     * 
     * @param PurchaseInvoice $invoice
     * @return void
     */
    protected function updateInvoicePaymentStatus(PurchaseInvoice $invoice): void
    {
        $paidAmount = (float) ($invoice->paid_amount ?? 0);
        $totalAmount = (float) $invoice->grand_total;

        if ($paidAmount <= 0) {
            $status = 'unpaid';
        } elseif ($paidAmount >= $totalAmount) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        $invoice->payment_status = $status;
        $invoice->save();
    }

    public function getSupplierLedgerEntries(int $supplierId, ?int $siteId = null): array
    {
        $query = PurchaseOrder::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $pos = $query->orderBy('po_date')->get();
        
        $entries = [];
        
        foreach ($pos as $po) {
            $poEntries = $this->getLedgerEntries($po->id);
            
            foreach ($poEntries as $entry) {
                $entry['po_number'] = $po->po_number;
                $entries[] = $entry;
            }
        }
        
        // Also get direct payments against invoices (no PO)
        $directPayments = PaymentsModule::where('supplier_id', $supplierId)
            ->where('payment_type', 'against_invoice')
            ->whereNull('purchase_order_id')
            ->orderBy('payment_date')
            ->get();
        
        foreach ($directPayments as $payment) {
            $invoice = $payment->purchase_invoice_id ? PurchaseInvoice::find($payment->purchase_invoice_id) : null;
            $invoiceNumber = $invoice ? $invoice->invoice_number : 'N/A';
            $entries[] = [
                'date' => $payment->payment_date,
                'datetime' => $payment->created_at ? $payment->created_at->format('Y-m-d H:i:s') : $payment->payment_date . ' 00:00:00',
                'details' => $payment->payment_number . ' / ' . $payment->mode . ' / Against Invoice / Invoice: ' . $invoiceNumber,
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'running_balance' => 0, // Will be calculated after sorting
                'type' => 'payment',
                'invoice_number' => $invoiceNumber,
                'amount' => (float) $payment->amount,
            ];
        }
        
        usort($entries, function($a, $b) {
            return strcmp($a['datetime'], $b['datetime']);
        });
        
        return $entries;
    }

    /**
     * Get supplier ledger entries by PO ID or Invoice ID.
     * 
     * @param int|null $poId
     * @param int|null $invoiceId
     * @param array $options Optional filters and pagination
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getSupplierLedger(?int $poId = null, ?int $invoiceId = null, array $options = []): array
    {
        if (!$poId && !$invoiceId) {
            throw new \InvalidArgumentException('Either po_id or invoice_id is required');
        }

        if ($poId && $invoiceId) {
            throw new \InvalidArgumentException('Provide only one of po_id or invoice_id, not both');
        }

        $actualPoId = $poId;

        if ($invoiceId) {
            $invoice = PurchaseInvoice::find($invoiceId);
            if (!$invoice) {
                throw new \InvalidArgumentException('Invoice not found');
            }
            $actualPoId = $invoice->po_id;
            if (!$actualPoId) {
                throw new \InvalidArgumentException('No linked PO found for this invoice');
            }
        }

        $entries = $this->getLedgerEntries($actualPoId);

        $entries = $this->applyFilters($entries, $options);

        return $entries;
    }

    /**
     * Apply optional filters to ledger entries.
     * 
     * @param array $entries
     * @param array $options
     * @return array
     */
    protected function applyFilters(array $entries, array $options): array
    {
        if (!empty($options['start_date'])) {
            $startDate = $options['start_date'];
            $entries = array_filter($entries, function($entry) use ($startDate) {
                return $entry['date'] >= $startDate;
            });
        }

        if (!empty($options['end_date'])) {
            $endDate = $options['end_date'];
            $entries = array_filter($entries, function($entry) use ($endDate) {
                return $entry['date'] <= $endDate;
            });
        }

        if (!empty($options['type'])) {
            $type = $options['type'];
            $entries = array_filter($entries, function($entry) use ($type) {
                return $entry['type'] === $type;
            });
        }

        return array_values($entries);
    }

    /**
     * Add running balance to entries.
     * 
     * @param array $entries
     * @return array
     */
    protected function addRunningBalance(array $entries): array
    {
        $balance = 0;
        foreach ($entries as &$entry) {
            $balance = $balance + $entry['debit'] - $entry['credit'];
            $entry['running_balance'] = $balance;
        }
        return $entries;
    }

    /**
     * Get supplier ledger entries with pagination.
     * 
     * @param int|null $poId
     * @param int|null $invoiceId
     * @param array $options
     * @return array
     */
    public function getSupplierLedgerPaginated(?int $poId = null, ?int $invoiceId = null, array $options = []): array
    {
        $entries = $this->getSupplierLedger($poId, $invoiceId, $options);
        
        $perPage = $options['per_page'] ?? 20;
        $page = $options['page'] ?? 1;
        
        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        
        $paginatedEntries = array_slice($entries, $offset, $perPage);
        
        return [
            'data' => $paginatedEntries,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]
        ];
    }

    /**
     * Get PO-specific summary for payment module
     * Returns: po_total, invoiced_amount, paid_amount, payable, advance_paid
     *
     * @param int $poId
     * @return array
     * @throws \Exception
     */
    public function getPaymentModuleSummary(int $poId): array
    {
        if (!$poId) {
            throw new \Exception("PO context is required for payment module calculations.");
        }
        return $this->calculate($poId); // Reuse existing calculate method
    }

    /**
     * Get PO-specific remaining payment based on payment type
     * For advance_against_po: po_total - advance_paid
     * For against_po/against_invoice: payable
     *
     * @param int $poId
     * @param string $paymentType
     * @return float
     * @throws \Exception
     */
    public function getRemainingPaymentByType(int $poId, string $paymentType): float
    {
        if (!$poId) {
            throw new \Exception("PO context is required for payment module calculations.");
        }

        $poData = $this->calculate($poId);

        // Data safety check: protect against manual DB edits or corruption
        if ($poData['po_total'] < $poData['advance_paid']) {
            throw new \Exception("Data inconsistency: advance exceeds PO total.");
        }

        if ($paymentType === 'advance_against_po') {
            return max(0, $poData['po_total'] - $poData['advance_paid']);
        }

        return $poData['payable'];
    }

    /**
     * Get supplier-level summary (legacy mode)
     * Preserves current supplier_transactions aggregation
     *
     * @param int $supplierId
     * @param int|null $siteId
     * @return array
     */
    public function getSupplierSummary(int $supplierId, ?int $siteId = null): array
    {
        // Use LedgerHelper::getPOSummary for supplier-level data
        return LedgerHelper::getPOSummary($supplierId, $siteId);
    }
}