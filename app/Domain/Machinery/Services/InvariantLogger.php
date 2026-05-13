<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Invariant Logger
 * Logs critical DPR actions for drift detection and audit
 */
class InvariantLogger
{
    /**
     * Log DPR creation invariant
     */
    public function logDprCreation($dpr, $calculation, $userId = null): void
    {
        $invariant = [
            'action' => 'dpr_created',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'dpr_id' => $dpr->id,
            'machinery_id' => $dpr->machinery_id,
            'date' => $dpr->date,
            'start_reading' => $dpr->machine_start_reading,
            'end_reading' => $dpr->machine_end_reading,
            'idle_hours' => $dpr->machine_idle_reading,
            'rate_snapshot' => $dpr->rate_snapshot,
            'billable_hours' => $dpr->billable_hours,
            'calculated_amount' => $dpr->calculated_amount,
            'calculation_hash' => $dpr->calculation_hash,
            'minimum_billing_applied' => $calculation['minimum_billing_applied'] ?? false,
            'calculation_version' => $calculation['calculation_version'] ?? 1,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Log DPR update invariant
     */
    public function logDprUpdate($dpr, $oldValues, $newValues, $userId = null): void
    {
        $invariant = [
            'action' => 'dpr_updated',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'dpr_id' => $dpr->id,
            'machinery_id' => $dpr->machinery_id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'rate_snapshot_unchanged' => ($oldValues['rate_snapshot'] ?? null) === ($newValues['rate_snapshot'] ?? null),
            'calculation_hash_before' => $oldValues['calculation_hash'] ?? null,
            'calculation_hash_after' => $newValues['calculation_hash'] ?? null,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Log DPR locking invariant
     */
    public function logDprLocking($dpr, $userId = null): void
    {
        $invariant = [
            'action' => 'dpr_locked',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'dpr_id' => $dpr->id,
            'machinery_id' => $dpr->machinery_id,
            'locked_at' => $dpr->locked_at,
            'locked_by' => $dpr->locked_by,
            'final_amount' => $dpr->calculated_amount,
            'final_rate_snapshot' => $dpr->rate_snapshot,
            'final_calculation_hash' => $dpr->calculation_hash,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Log ledger creation invariant
     */
    public function logLedgerCreation($ledger, $userId = null): void
    {
        $invariant = [
            'action' => 'ledger_created',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'ledger_id' => $ledger->id,
            'machinery_id' => $ledger->machinery_id,
            'reference_type' => $ledger->reference_type,
            'reference_id' => $ledger->reference_id,
            'dpr_id' => $ledger->dpr_id,
            'payment_request_id' => $ledger->payment_request_id,
            'amount' => $ledger->amount,
            'entry_direction' => $ledger->entry_direction,
            'running_balance' => $ledger->running_balance,
            'is_reversal' => $ledger->is_reversal ?? false,
            'reversal_of_id' => $ledger->reversal_of_id ?? null,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Log rate change invariant
     */
    public function logRateChange($machineryId, $oldRate, $newRate, $effectiveFrom, $userId = null): void
    {
        $invariant = [
            'action' => 'rate_changed',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'machinery_id' => $machineryId,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'effective_from' => $effectiveFrom,
            'rate_difference' => $newRate - $oldRate,
            'rate_change_percentage' => $oldRate > 0 ? (($newRate - $oldRate) / $oldRate) * 100 : 0,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Log payment status change invariant
     */
    public function logPaymentStatusChange($dpr, $oldStatus, $newStatus, $paymentRequestId = null, $userId = null): void
    {
        $invariant = [
            'action' => 'payment_status_changed',
            'timestamp' => now()->toISOString(),
            'user_id' => $userId ?? auth()->id(),
            'dpr_id' => $dpr->id,
            'machinery_id' => $dpr->machinery_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'payment_request_id' => $paymentRequestId,
            'dpr_amount' => $dpr->calculated_amount,
            'rate_snapshot' => $dpr->rate_snapshot,
        ];
        
        $this->writeInvariantLog($invariant);
    }
    
    /**
     * Write invariant log to database
     */
    private function writeInvariantLog(array $invariant): void
    {
        try {
            DB::table('invariant_logs')->insert([
                'log_data' => json_encode($invariant),
                'action_type' => $invariant['action'],
                'reference_type' => $this->getReferenceType($invariant),
                'reference_id' => $invariant['dpr_id'] ?? $invariant['ledger_id'] ?? null,
                'user_id' => $invariant['user_id'],
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            Log::error('Invariant logging failed', [
                'error' => $e->getMessage(),
                'invariant' => $invariant,
            ]);
        }
        
        // Always log to application log
        Log::info('Invariant logged', $invariant);
    }
    
    /**
     * Get reference type from invariant data
     */
    private function getReferenceType(array $invariant): string
    {
        return match($invariant['action']) {
            'dpr_created', 'dpr_updated', 'dpr_locked' => 'DailyProgressReport',
            'ledger_created' => 'MachineryLedger',
            'rate_changed' => 'Machinery',
            'payment_status_changed' => 'DailyProgressReport',
            default => 'Unknown',
        };
    }
    
    /**
     * Detect calculation drift for a DPR
     */
    public function detectCalculationDrift($dprId): array
    {
        $issues = [];
        
        // Get all invariants for this DPR
        $invariants = DB::table('invariant_logs')
            ->where('reference_type', 'DailyProgressReport')
            ->where('reference_id', $dprId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($log) => json_decode($log->log_data, true));
        
        foreach ($invariants as $invariant) {
            // Check for rate snapshot changes
            if (isset($invariant['rate_snapshot']) && isset($invariant['old_values']['rate_snapshot'])) {
                if ($invariant['rate_snapshot'] !== $invariant['old_values']['rate_snapshot']) {
                    $issues[] = [
                        'type' => 'rate_snapshot_changed',
                        'timestamp' => $invariant['timestamp'],
                        'old_rate' => $invariant['old_values']['rate_snapshot'],
                        'new_rate' => $invariant['rate_snapshot'],
                        'severity' => 'high',
                    ];
                }
            }
            
            // Check for calculation hash changes
            if (isset($invariant['calculation_hash_before']) && isset($invariant['calculation_hash_after'])) {
                if ($invariant['calculation_hash_before'] !== $invariant['calculation_hash_after']) {
                    $issues[] = [
                        'type' => 'calculation_hash_changed',
                        'timestamp' => $invariant['timestamp'],
                        'old_hash' => $invariant['calculation_hash_before'],
                        'new_hash' => $invariant['calculation_hash_after'],
                        'severity' => 'high',
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Get invariant history for debugging
     */
    public function getInvariantHistory($referenceType, $referenceId, $limit = 50)
    {
        return DB::table('invariant_logs')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action_type,
                'timestamp' => $log->created_at,
                'data' => json_decode($log->log_data, true),
            ]);
    }
}
