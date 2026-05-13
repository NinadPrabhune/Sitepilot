<?php

namespace App\Services;

use App\Models\AdvanceUtilization;
use App\Models\SupplierAdvance;
use App\Models\SupplierTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerDoubleEntryService
{
    /**
     * Create double-entry ledger records for advance allocation
     * CRITICAL: Wrapped in transaction for atomic debit/credit entries
     */
    public static function createAdvanceAllocationLedgerEntry(AdvanceUtilization $utilization): void
    {
        DB::transaction(function () use ($utilization) {
            $advance = SupplierAdvance::find($utilization->supplier_advance_id);

            // Debit entry (advance is being used)
            SupplierTransaction::create([
                'supplier_id' => $advance->supplier_id,
                'transaction_type' => 'debit',
                'amount' => $utilization->utilized_amount,
                'reference_type' => 'advance_utilization',
                'reference_id' => $utilization->id,
                'description' => "Advance allocated to invoice #{$utilization->purchase_invoice_id}",
                'transaction_date' => now()->toDateString(),
                'workspace_id' => $advance->workspace_id,
                'site_id' => $advance->site_id,
                'transaction_flow_id' => $advance->transaction_flow_id,
            ]);

            // Credit entry (invoice is being reduced by advance)
            SupplierTransaction::create([
                'supplier_id' => $advance->supplier_id,
                'transaction_type' => 'credit',
                'amount' => $utilization->utilized_amount,
                'reference_type' => 'advance_utilization',
                'reference_id' => $utilization->id,
                'description' => "Invoice #{$utilization->purchase_invoice_id} reduced by advance",
                'transaction_date' => now()->toDateString(),
                'workspace_id' => $advance->workspace_id,
                'site_id' => $advance->site_id,
                'transaction_flow_id' => $advance->transaction_flow_id,
            ]);

            Log::channel('finance')->info('Ledger entries created for advance allocation', [
                'utilization_id' => $utilization->id,
                'amount' => $utilization->utilized_amount,
                'flow_id' => $advance->transaction_flow_id,
            ]);
        });
    }

    /**
     * Create reversal ledger entry for advance allocation
     */
    public static function createReversalEntry(AdvanceUtilization $utilization, string $reason): void
    {
        DB::transaction(function () use ($utilization, $reason) {
            $advance = SupplierAdvance::find($utilization->supplier_advance_id);

            // Reversal credit (return advance balance)
            SupplierTransaction::create([
                'supplier_id' => $advance->supplier_id,
                'transaction_type' => 'credit',
                'amount' => $utilization->utilized_amount,
                'reference_type' => 'advance_utilization_reversal',
                'reference_id' => $utilization->id,
                'description' => "Advance allocation reversed: {$reason}",
                'transaction_date' => now()->toDateString(),
                'workspace_id' => $advance->workspace_id,
                'site_id' => $advance->site_id,
                'transaction_flow_id' => $advance->transaction_flow_id,
            ]);

            // Reversal debit (restore invoice balance)
            SupplierTransaction::create([
                'supplier_id' => $advance->supplier_id,
                'transaction_type' => 'debit',
                'amount' => $utilization->utilized_amount,
                'reference_type' => 'advance_utilization_reversal',
                'reference_id' => $utilization->id,
                'description' => "Invoice #{$utilization->purchase_invoice_id} restored from advance reversal",
                'transaction_date' => now()->toDateString(),
                'workspace_id' => $advance->workspace_id,
                'site_id' => $advance->site_id,
                'transaction_flow_id' => $advance->transaction_flow_id,
            ]);

            Log::channel('finance')->info('Ledger reversal entry created', [
                'utilization_id' => $utilization->id,
                'amount' => $utilization->utilized_amount,
                'reason' => $reason,
                'flow_id' => $advance->transaction_flow_id,
            ]);
        });
    }

    /**
     * Create ledger entry for advance payment
     */
    public static function createAdvancePaymentLedgerEntry(SupplierAdvance $advance): void
    {
        DB::transaction(function () use ($advance) {
            // Credit entry (supplier receives advance)
            SupplierTransaction::create([
                'supplier_id' => $advance->supplier_id,
                'transaction_type' => 'credit',
                'amount' => $advance->amount,
                'reference_type' => 'supplier_advance',
                'reference_id' => $advance->id,
                'description' => "Advance payment received: {$advance->advance_number}",
                'transaction_date' => $advance->payment_date ?? now()->toDateString(),
                'workspace_id' => $advance->workspace_id,
                'site_id' => $advance->site_id,
                'transaction_flow_id' => $advance->transaction_flow_id,
            ]);

            Log::channel('finance')->info('Ledger entry created for advance payment', [
                'advance_id' => $advance->id,
                'amount' => $advance->amount,
                'flow_id' => $advance->transaction_flow_id,
            ]);
        });
    }
}
