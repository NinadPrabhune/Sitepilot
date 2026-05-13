<?php

namespace App\Domain\Machinery\Services;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Domain\Machinery\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyProgressReportService
{
    /**
     * Create DPR and corresponding ledger entry
     */
    public static function createDPRWithLedger(array $data): DailyProgressReport
    {
        return DB::transaction(function () use ($data) {
            // Enhanced duplicate DPR check
            if (isset($data['machinery_id']) && isset($data['date'])) {
                $existingDPR = DailyProgressReport::where('machinery_id', $data['machinery_id'])
                    ->where('date', $data['date'])
                    ->where(function($query) {
                        $query->where('status', '!=', 'deleted')
                              ->orWhereNull('status');
                    })
                    ->lockForUpdate()
                    ->first();

                if ($existingDPR) {
                    throw new \RuntimeException("DPR already exists for machinery ID {$data['machinery_id']} on date {$data['date']}. Existing DPR ID: {$existingDPR->id}");
                }
            }
            
            // Create DPR first
            $dpr = DailyProgressReport::create($data);
            
            // Calculate DPR values
            $dpr = self::calculateDPRValues($dpr);
            
            // Create ledger entry
            $ledgerEntry = self::createLedgerEntry($dpr);
            
            // Link DPR to ledger entry
            $dpr->update(['ledger_entry_id' => $ledgerEntry->id]);
            
            Log::info('DPR created with ledger entry', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'ledger_entry_id' => $ledgerEntry->id,
                'amount' => $dpr->calculated_amount,
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Calculate DPR values (billable hours and amount)
     */
    private static function calculateDPRValues(DailyProgressReport $dpr): DailyProgressReport
    {
        $machinery = $dpr->machinery;
        $workingHours = $dpr->machine_end_reading - $dpr->machine_start_reading;
        $idleHours = $dpr->machine_idle_reading ?? 0;
        $billableHours = max(0, $workingHours - $idleHours);
        
        // Apply minimum billing for rental machinery
        if ($machinery->owned_by === 'rental' && $machinery->minimum_billing_hours) {
            $billableHours = max($billableHours, $machinery->minimum_billing_hours);
        }
        
        $calculatedAmount = $billableHours * $machinery->rate;
        
        $dpr->update([
            'billable_hours' => $billableHours,
            'calculated_amount' => $calculatedAmount,
        ]);
        
        return $dpr->fresh();
    }
    
    /**
     * Create ledger entry for DPR
     */
    private static function createLedgerEntry(DailyProgressReport $dpr): MachineryLedger
    {
        $machinery = $dpr->machinery;
        
        // Determine ledger type based on ownership
        $ledgerType = $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable';
        
        // Generate idempotency key
        $idempotencyKey = 'dpr_' . $dpr->machinery_id . '_' . $dpr->date;
        
        // Create credit entry for DPR work
        $ledgerEntry = MachineryLedger::create([
            'machinery_id' => $dpr->machinery_id,
            'workspace_id' => $dpr->workspace_id,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'ledger_type' => $ledgerType,
            'cost_category' => 'machine',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'amount' => $dpr->calculated_amount,
            'running_balance' => self::calculateRunningBalance($dpr->machinery_id, $dpr->calculated_amount),
            'date' => $dpr->date,
            'description' => "DPR: {$machinery->name} - " . ($dpr->work_details ?? 'Work performed'),
            'idempotency_key' => $idempotencyKey,
            'is_reversal' => false,
        ]);
        
        return $ledgerEntry;
    }
    
    /**
     * Calculate running balance for machinery
     */
    private static function calculateRunningBalance(int $machineryId, float $newAmount): float
    {
        $lastBalance = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->value('running_balance') ?? 0;
            
        return $lastBalance + $newAmount;
    }
    
    /**
     * Update DPR and corresponding ledger entry
     */
    public static function updateDPRWithLedger(DailyProgressReport $dpr, array $data): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $data) {
            // Check if DPR can be edited (no ledger entry or not locked)
            if ($dpr->ledger_entry_id && $dpr->status === 'approved') {
                throw new \RuntimeException('Cannot edit approved DPR. Use reversal instead.');
            }
            
            // Update DPR
            $dpr->update($data);
            
            // Recalculate values
            $dpr = self::calculateDPRValues($dpr);
            
            // Update existing ledger entry or create new one
            if ($dpr->ledger_entry_id) {
                self::updateLedgerEntry($dpr);
            } else {
                $ledgerEntry = self::createLedgerEntry($dpr);
                $dpr->update(['ledger_entry_id' => $ledgerEntry->id]);
            }
            
            Log::info('DPR updated with ledger entry', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'new_amount' => $dpr->calculated_amount,
            ]);
            
            return $dpr->fresh();
        });
    }
    
    /**
     * Update existing ledger entry
     */
    private static function updateLedgerEntry(DailyProgressReport $dpr): void
    {
        $machinery = $dpr->machinery;
        $ledgerType = $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable';
        
        $dpr->ledgerEntry->update([
            'amount' => $dpr->calculated_amount,
            'ledger_type' => $ledgerType,
            'description' => "DPR: {$machinery->name} - " . ($dpr->work_details ?? 'Work performed') . " (Updated)",
        ]);
        
        // Recalculate running balance for all subsequent entries
        self::recalculateRunningBalances($dpr->machinery_id);
    }
    
    /**
     * Recalculate running balances for machinery after ledger update
     */
    private static function recalculateRunningBalances(int $machineryId): void
    {
        $ledgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
            
        $runningBalance = 0;
        
        foreach ($ledgerEntries as $entry) {
            $runningBalance += $entry->amount;
            $entry->update(['running_balance' => $runningBalance]);
        }
    }
    
    /**
     * Approve DPR and lock for payment processing
     */
    public static function approveDPR(DailyProgressReport $dpr, int $approvedBy): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $approvedBy) {
            // Check if DPR can be approved
            if ($dpr->status === 'approved') {
                throw new \RuntimeException('DPR is already approved.');
            }
            
            if (!$dpr->ledger_entry_id) {
                throw new \RuntimeException('DPR must have ledger entry to be approved.');
            }
            
            // Update DPR status
            $dpr->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);
            
            // Lock ledger entry
            $dpr->ledgerEntry->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => $approvedBy,
            ]);
            
            Log::info('DPR approved and locked', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'approved_by' => $approvedBy,
            ]);
            
            return $dpr->fresh();
        });
    }
    
    /**
     * Reject DPR with reason
     */
    public static function rejectDPR(DailyProgressReport $dpr, string $reason, int $rejectedBy): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $reason, $rejectedBy) {
            // Update DPR status
            $dpr->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
            ]);
            
            // Reverse ledger entry if exists
            if ($dpr->ledger_entry_id) {
                MachineryLedgerService::reverseEntry(
                    $dpr->ledger_entry_id,
                    "DPR rejected: {$reason}"
                );
            }
            
            Log::info('DPR rejected', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'reason' => $reason,
                'rejected_by' => $rejectedBy,
            ]);
            
            return $dpr->fresh();
        });
    }
}
