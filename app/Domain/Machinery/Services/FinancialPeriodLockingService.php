<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialPeriodLockingService
{
    /**
     * Check if a DPR can be edited based on financial period locking
     */
    public static function canEditDPR(int $dprId, \DateTime $dprDate): bool
    {
        // Check if the DPR date falls within a locked financial period
        $lockedPeriod = self::getLockedPeriodForDate($dprDate);
        
        if ($lockedPeriod) {
            Log::warning('DPR edit blocked - financial period locked', [
                'dpr_id' => $dprId,
                'dpr_date' => $dprDate->format('Y-m-d'),
                'locked_period' => $lockedPeriod->period_name,
                'locked_by' => $lockedPeriod->locked_by,
                'locked_at' => $lockedPeriod->locked_at
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Lock a financial period to prevent DPR edits
     */
    public static function lockFinancialPeriod(
        \DateTime $startDate, 
        \DateTime $endDate, 
        string $periodName, 
        int $lockedBy, 
        string $reason = 'Financial period closed'
    ): array {
        try {
            DB::beginTransaction();
            
            // Create or update financial period lock record
            $lockId = DB::table('financial_period_locks')->insertGetId([
                'period_name' => $periodName,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'is_locked' => true,
                'locked_by' => $lockedBy,
                'locked_at' => now(),
                'lock_reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            Log::info('Financial period locked', [
                'lock_id' => $lockId,
                'period_name' => $periodName,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'locked_by' => $lockedBy,
                'reason' => $reason
            ]);
            
            return [
                'success' => true,
                'lock_id' => $lockId,
                'message' => 'Financial period locked successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to lock financial period', [
                'period_name' => $periodName,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to lock financial period: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get locked financial period for a given date
     */
    public static function getLockedPeriodForDate(\DateTime $date): ?object
    {
        return DB::table('financial_period_locks')
            ->where('is_locked', true)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->first();
    }
    
    /**
     * Check if any DPRs exist within a date range that would be affected by locking
     */
    public static function getDPRsInDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return DB::table('daily_progress_reports')
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get(['id', 'date', 'machinery_id'])
            ->toArray();
    }
    
    /**
     * Get all locked financial periods
     */
    public static function getLockedPeriods(): array
    {
        return DB::table('financial_period_locks')
            ->where('is_locked', true)
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Unlock a financial period (admin operation only)
     */
    public static function unlockFinancialPeriod(int $lockId, int $unlockedBy, string $reason): array
    {
        try {
            $affected = DB::table('financial_period_locks')
                ->where('id', $lockId)
                ->update([
                    'is_locked' => false,
                    'unlocked_by' => $unlockedBy,
                    'unlocked_at' => now(),
                    'unlock_reason' => $reason,
                    'updated_at' => now()
                ]);
            
            if ($affected) {
                Log::warning('Financial period unlocked', [
                    'lock_id' => $lockId,
                    'unlocked_by' => $unlockedBy,
                    'reason' => $reason
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Financial period unlocked successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Financial period not found or already unlocked'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to unlock financial period', [
                'lock_id' => $lockId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to unlock financial period: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create journal adjustment entry for locked period corrections
     */
    public static function createJournalAdjustment(
        int $dprId,
        array $adjustmentData,
        int $createdBy,
        string $reason
    ): array {
        try {
            $adjustmentId = DB::table('journal_adjustments')->insertGetId([
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dprId,
                'adjustment_data' => json_encode($adjustmentData),
                'adjustment_reason' => $reason,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info('Journal adjustment created for locked period', [
                'adjustment_id' => $adjustmentId,
                'dpr_id' => $dprId,
                'created_by' => $createdBy,
                'reason' => $reason
            ]);
            
            return [
                'success' => true,
                'adjustment_id' => $adjustmentId,
                'message' => 'Journal adjustment created successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create journal adjustment', [
                'dpr_id' => $dprId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create journal adjustment: ' . $e->getMessage()
            ];
        }
    }
}
