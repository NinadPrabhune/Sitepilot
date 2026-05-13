<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Financial Period Service
 * Handles period locking and validation
 */
class FinancialPeriodService
{
    /**
     * Check if period is locked for given date
     */
    public function isPeriodLocked($date): bool
    {
        $period = DB::table('financial_periods')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('is_closed', 1)
            ->first();
            
        return $period !== null;
    }
    
    /**
     * Check if period is closed for given date
     */
    public function isPeriodClosed($date): bool
    {
        $period = DB::table('financial_periods')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('is_closed', 1)
            ->first();
            
        return $period !== null;
    }
    
    /**
     * Validate period lock - throws exception if locked
     */
    public function validatePeriodLock($date): void
    {
        if ($this->isPeriodLocked($date)) {
            $period = DB::table('financial_periods')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('is_closed', 1)
                ->first();
                
            throw new \Exception("Financial period {$period->start_date} to {$period->end_date} is locked");
        }
        
        if ($this->isPeriodClosed($date)) {
            $period = DB::table('financial_periods')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('is_closed', 1)
                ->first();
                
            throw new \Exception("Financial period {$period->start_date} to {$period->end_date} is closed");
        }
    }
    
    /**
     * Get period status for given date
     */
    public function getPeriodStatus($date): ?string
    {
        $period = DB::table('financial_periods')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
            
        return $period ? ($period->is_closed ? 'closed' : 'open') : null;
    }
    
    /**
     * Create financial period
     */
    public function createPeriod($periodYear, $periodMonth, $startDate, $endDate, $createdBy = null): void
    {
        DB::table('financial_periods')->insert([
            'workspace_id' => getActiveWorkSpace(),
            'site_id' => getActiveProject(),
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => 0,
            'created_by' => $createdBy ?? auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        Log::info('Financial period created', [
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }
    
    /**
     * Close financial period
     */
    public function closePeriod($periodId, $closedBy = null): void
    {
        DB::table('financial_periods')
            ->where('id', $periodId)
            ->update([
                'status' => 'closed',
                'closed_by' => $closedBy ?? auth()->id(),
                'closed_at' => now(),
            ]);
            
        Log::info('Financial period closed', [
            'period_id' => $periodId,
            'closed_by' => $closedBy ?? auth()->id(),
        ]);
    }
    
    /**
     * Lock financial period
     */
    public function lockPeriod($periodId, $lockedBy = null): void
    {
        DB::table('financial_periods')
            ->where('id', $periodId)
            ->update([
                'status' => 'locked',
                'closed_by' => $lockedBy ?? auth()->id(),
                'closed_at' => now(),
            ]);
            
        Log::info('Financial period locked', [
            'period_id' => $periodId,
            'locked_by' => $lockedBy ?? auth()->id(),
        ]);
    }
    
    /**
     * Get all periods
     */
    public function getAllPeriods()
    {
        return DB::table('financial_periods')
            ->orderBy('period_start', 'desc')
            ->get();
    }
}
