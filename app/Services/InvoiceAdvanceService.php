<?php

namespace App\Services;

use App\Models\AdvanceUtilization;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Log;

class InvoiceAdvanceService
{
    /**
     * Calculate net payable after advance deduction.
     * 
     * @param int $invoiceId
     * @return array
     */
    public function calculateNetPayable(int $invoiceId): array
    {
        $invoice = PurchaseInvoice::with('supplier')->findOrFail($invoiceId);

        $invoiceTotal = (float) $invoice->grand_total;
        $directPayments = $invoice->payments()->sum('amount');
        $totalAdvanceAvailable = $this->getTotalAdvanceAvailableForSupplier($invoice->supplier_id);
        $advanceUtilized = AdvanceUtilization::getTotalUtilizedForInvoice($invoiceId);
        
        $netPayable = max(0, $invoiceTotal - $directPayments - $advanceUtilized);
        $remainingAdvanceBalance = max(0, $totalAdvanceAvailable - $advanceUtilized);

        $advanceBreakdown = $this->getInvoiceAdvanceUtilization($invoiceId);

        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => $invoiceTotal,
            'direct_payments' => $directPayments,
            'total_advance_available' => $totalAdvanceAvailable,
            'advance_utilized' => $advanceUtilized,
            'remaining_advance_balance' => $remainingAdvanceBalance,
            'net_payable' => $netPayable,
            'advance_breakdown' => $advanceBreakdown,
        ];
    }

    /**
     * Get all advances used for an invoice.
     * 
     * @param int $invoiceId
     * @return array
     */
    public function getInvoiceAdvanceUtilization(int $invoiceId): array
    {
        $utilizations = AdvanceUtilization::getUtilizationWithAdvanceDetails($invoiceId);

        return $utilizations->map(function ($utilization) {
            return [
                'advance_number' => $utilization->advance->advance_number,
                'advance_id' => $utilization->advance->id,
                'po_number' => $utilization->advance->po->po_number ?? 'Manual',
                'po_id' => $utilization->advance->po_id,
                'amount' => $utilization->utilized_amount,
                'advance_date' => $utilization->advance->advance_date,
            ];
        })->toArray();
    }

    /**
     * Validate allocation integrity (no over-allocation).
     * 
     * @param int $invoiceId
     * @return array
     */
    public function validateAllocationIntegrity(int $invoiceId): array
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);
        $utilizations = AdvanceUtilization::where('purchase_invoice_id', $invoiceId)->get();

        $totalUtilized = $utilizations->sum('utilized_amount');
        $invoiceTotal = (float) $invoice->grand_total;
        $directPayments = $invoice->payments()->sum('amount');
        $expectedMaxUtilization = max(0, $invoiceTotal - $directPayments);

        $isValid = $totalUtilized <= $expectedMaxUtilization;

        if (!$isValid) {
            Log::warning('Advance allocation integrity violation detected', [
                'invoice_id' => $invoiceId,
                'total_utilized' => $totalUtilized,
                'expected_max' => $expectedMaxUtilization,
                'over_allocation' => $totalUtilized - $expectedMaxUtilization,
            ]);
        }

        return [
            'is_valid' => $isValid,
            'total_utilized' => $totalUtilized,
            'expected_max_utilization' => $expectedMaxUtilization,
            'over_allocation' => $isValid ? 0 : ($totalUtilized - $expectedMaxUtilization),
        ];
    }

    /**
     * Lock invoice financially after allocation.
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function lockInvoiceFinancially(int $invoiceId): bool
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);

        if ($invoice->is_financially_locked) {
            return true; // Already locked
        }

        $result = $invoice->update([
            'is_financially_locked' => true,
            'financially_locked_at' => now(),
            'financially_locked_by' => auth()->id(),
        ]);

        if ($result) {
            Log::info('Invoice financially locked', [
                'invoice_id' => $invoiceId,
                'locked_by' => auth()->id(),
            ]);
        }

        return $result;
    }

    /**
     * Check if invoice is financially locked.
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function isInvoiceFinanciallyLocked(int $invoiceId): bool
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);
        return $invoice->is_financially_locked;
    }

    /**
     * Recalculate allocation if invoice is updated (optional, safer to lock).
     * 
     * @param int $invoiceId
     * @return array
     */
    public function recalculateAllocationOnUpdate(int $invoiceId): array
    {
        // This is a dangerous operation - requires explicit approval
        // For safety, we recommend using financial lock instead
        // This method is provided only if feature flag is enabled

        if (!config('features.advance_soft_reallocation_enabled', false)) {
            return [
                'success' => false,
                'message' => 'Soft reallocation is disabled by feature flag',
            ];
        }

        $invoice = PurchaseInvoice::findOrFail($invoiceId);

        if ($invoice->is_financially_locked) {
            return [
                'success' => false,
                'message' => 'Invoice is financially locked - cannot recalculate',
            ];
        }

        // Release existing allocations
        $allocationService = new AdvanceAllocationService();
        $allocationService->rollbackAllocation($invoiceId);

        // Reallocate with new amounts
        $result = $allocationService->allocateToInvoice($invoiceId);

        Log::info('Advance allocation recalculated', [
            'invoice_id' => $invoiceId,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Get total advance available for a supplier.
     * 
     * @param int $supplierId
     * @return float
     */
    private function getTotalAdvanceAvailableForSupplier(int $supplierId): float
    {
        $advanceService = new SupplierAdvanceService();
        return $advanceService->getSupplierAvailableBalance($supplierId);
    }
}
