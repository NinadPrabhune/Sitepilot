<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\DailyProgressReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Machinery Ownership Lock Service
 * Prevents machinery ownership changes after DPR creation to maintain financial consistency
 */
class MachineryOwnershipLockService
{
    /**
     * Lock machinery ownership after DPR creation
     */
    public static function lockOwnership(int $machineryId, int $lockedBy): void
    {
        DB::transaction(function () use ($machineryId, $lockedBy) {
            $machinery = Machinery::findOrFail($machineryId);
            
            if ($machinery->ownership_locked) {
                Log::warning('Attempted to lock already locked machinery ownership', [
                    'machinery_id' => $machineryId,
                    'locked_by' => $lockedBy,
                    'already_locked_by' => $machinery->ownership_locked_by,
                    'locked_at' => $machinery->ownership_locked_at,
                ]);
                return;
            }
            
            $machinery->update([
                'ownership_locked' => true,
                'ownership_locked_at' => now(),
                'ownership_locked_by' => $lockedBy,
            ]);
            
            Log::info('Machinery ownership locked', [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'owned_by' => $machinery->owned_by,
                'locked_by' => $lockedBy,
                'locked_at' => now(),
            ]);
        });
    }
    
    /**
     * Check if machinery ownership can be changed
     */
    public static function canChangeOwnership(int $machineryId): bool
    {
        $machinery = Machinery::findOrFail($machineryId);
        
        if ($machinery->ownership_locked) {
            Log::warning('Attempted ownership change on locked machinery', [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'current_owned_by' => $machinery->owned_by,
                'locked_at' => $machinery->ownership_locked_at,
                'locked_by' => $machinery->ownership_locked_by,
            ]);
            
            return false;
        }
        
        // Check if DPRs exist for this machinery
        $dprCount = DailyProgressReport::where('machinery_id', $machineryId)->count();
        if ($dprCount > 0) {
            Log::warning('Attempted ownership change on machinery with existing DPRs', [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'current_owned_by' => $machinery->owned_by,
                'dpr_count' => $dprCount,
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Attempt to change machinery ownership (with validation)
     */
    public static function changeOwnership(int $machineryId, string $newOwnership, int $changedBy): bool
    {
        if (!self::canChangeOwnership($machineryId)) {
            throw new \Exception('Cannot change ownership: machinery is locked or has existing DPRs');
        }
        
        return DB::transaction(function () use ($machineryId, $newOwnership, $changedBy) {
            $machinery = Machinery::findOrFail($machineryId);
            $oldOwnership = $machinery->owned_by;
            
            $machinery->update([
                'owned_by' => $newOwnership,
            ]);
            
            Log::info('Machinery ownership changed', [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'old_ownership' => $oldOwnership,
                'new_ownership' => $newOwnership,
                'changed_by' => $changedBy,
            ]);
            
            return true;
        });
    }
    
    /**
     * Force ownership change with reprocessing (emergency only)
     */
    public static function forceOwnershipChangeWithReprocessing(int $machineryId, string $newOwnership, int $changedBy): array
    {
        return DB::transaction(function () use ($machineryId, $newOwnership, $changedBy) {
            $machinery = Machinery::findOrFail($machineryId);
            $oldOwnership = $machinery->owned_by;
            
            // Get all existing DPRs for this machinery
            $dprs = DailyProgressReport::where('machinery_id', $machineryId)->get();
            
            $reprocessingResults = [];
            
            foreach ($dprs as $dpr) {
                try {
                    // Reverse existing ledger entries
                    $reversalResult = self::reverseDprLedgerEntries($dpr, $changedBy);
                    
                    // Change ownership
                    $machinery->update(['owned_by' => $newOwnership]);
                    
                    // Reprocess DPR with new ownership
                    $reprocessingResult = MachineryFinancialFlowService::processDprFinancials($dpr);
                    
                    $reprocessingResults[] = [
                        'dpr_id' => $dpr->id,
                        'dpr_date' => $dpr->date,
                        'reversal_success' => $reversalResult['success'],
                        'reprocessing_success' => $reprocessingResult['success'],
                        'old_ledger_type' => $reversalResult['old_ledger_type'],
                        'new_ledger_type' => $reprocessingResult['ledger_type'],
                    ];
                    
                } catch (\Exception $e) {
                    Log::error('Failed to reprocess DPR during ownership change', [
                        'machinery_id' => $machineryId,
                        'dpr_id' => $dpr->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    $reprocessingResults[] = [
                        'dpr_id' => $dpr->id,
                        'dpr_date' => $dpr->date,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            // Lock ownership after reprocessing
            $machinery->update([
                'ownership_locked' => true,
                'ownership_locked_at' => now(),
                'ownership_locked_by' => $changedBy,
            ]);
            
            Log::info('Machinery ownership force-changed with reprocessing', [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'old_ownership' => $oldOwnership,
                'new_ownership' => $newOwnership,
                'changed_by' => $changedBy,
                'dprs_processed' => count($dprs),
                'successful_reprocessing' => count(array_filter($reprocessingResults, fn($r) => $r['success'] ?? false)),
            ]);
            
            return [
                'success' => true,
                'machinery_id' => $machineryId,
                'old_ownership' => $oldOwnership,
                'new_ownership' => $newOwnership,
                'dprs_processed' => count($dprs),
                'reprocessing_results' => $reprocessingResults,
            ];
        });
    }
    
    /**
     * Reverse all ledger entries for a DPR
     */
    private static function reverseDprLedgerEntries(DailyProgressReport $dpr, int $reversedBy): array
    {
        $ledgers = MachineryLedger::where('dpr_id', $dpr->id)
                                ->where('is_reversal', false)
                                ->get();
        
        $reversalResults = [];
        
        foreach ($ledgers as $ledger) {
            try {
                $reversal = MachineryLedger::create([
                    'machinery_id' => $ledger->machinery_id,
                    'workspace_id' => $ledger->workspace_id,
                    'entry_direction' => $ledger->entry_direction === 'credit' ? 'debit' : 'credit',
                    'entry_type' => $ledger->entry_type,
                    'ledger_type' => $ledger->ledger_type,
                    'cost_category' => $ledger->cost_category,
                    'reference_type' => 'DailyProgressReport',
                    'reference_id' => $dpr->id,
                    'dpr_id' => $dpr->id,
                    'amount' => $ledger->amount,
                    'running_balance' => 0, // Will be recalculated
                    'date' => now()->toDateString(),
                    'description' => "Reversal of: {$ledger->description}",
                    'is_reversal' => true,
                    'reversal_of_id' => $ledger->id,
                ]);
                
                $reversalResults[] = [
                    'success' => true,
                    'original_ledger_id' => $ledger->id,
                    'reversal_ledger_id' => $reversal->id,
                    'old_ledger_type' => $ledger->ledger_type,
                ];
                
            } catch (\Exception $e) {
                $reversalResults[] = [
                    'success' => false,
                    'original_ledger_id' => $ledger->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'success' => count(array_filter($reversalResults, fn($r) => $r['success'])) === count($reversalResults),
            'reversal_results' => $reversalResults,
        ];
    }
    
    /**
     * Get ownership lock status
     */
    public static function getLockStatus(int $machineryId): array
    {
        $machinery = Machinery::findOrFail($machineryId);
        $dprCount = DailyProgressReport::where('machinery_id', $machineryId)->count();
        
        return [
            'machinery_id' => $machineryId,
            'machinery_name' => $machinery->name,
            'current_ownership' => $machinery->owned_by,
            'ownership_locked' => $machinery->ownership_locked,
            'ownership_locked_at' => $machinery->ownership_locked_at,
            'ownership_locked_by' => $machinery->ownership_locked_by,
            'dpr_count' => $dprCount,
            'can_change_ownership' => !$machinery->ownership_locked && $dprCount === 0,
        ];
    }
}
