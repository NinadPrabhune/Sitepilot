<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Machinery Rate Service
 * Handles historical rate lookup and management
 */
class MachineryRateService
{
    /**
     * Get rate for a specific date (deterministic)
     */
    public function getRateForDate($machineryId, $date): float
    {
        // First try to get from rate history
        $rate = DB::table('machinery_rate_history')
            ->where('machinery_id', $machineryId)
            ->where('effective_from', '<=', $date)
            ->where(function($query) use ($date) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->value('rate');
            
        // If no history exists, fall back to machinery master rate
        if ($rate === null) {
            $rate = DB::table('machineries')
                ->where('id', $machineryId)
                ->value('rate');
                
            if ($rate === null) {
                throw new \Exception("No rate found for machinery {$machineryId} on date {$date}");
            }
            
            // Auto-create rate history entry for future consistency
            $this->createRateHistory($machineryId, $rate, $date);
        }
        
        return (float) $rate;
    }
    
    /**
     * Create rate history entry
     */
    public function createRateHistory($machineryId, $rate, $effectiveFrom, $createdBy = null): void
    {
        DB::transaction(function () use ($machineryId, $rate, $effectiveFrom, $createdBy) {
            // Convert effectiveFrom to Carbon if it's a string
            $effectiveDate = is_string($effectiveFrom) 
                ? \Carbon\Carbon::createFromFormat('Y-m-d', $effectiveFrom)
                : $effectiveFrom;
            
            // Close previous rate
            DB::table('machinery_rate_history')
                ->where('machinery_id', $machineryId)
                ->whereNull('effective_to')
                ->update(['effective_to' => $effectiveDate->subDay()->toDateString()]);
            
            // Create new rate
            DB::table('machinery_rate_history')->insert([
                'machinery_id' => $machineryId,
                'rate' => $rate,
                'effective_from' => $effectiveDate->toDateString(),
                'created_by' => $createdBy ?? (auth()->check() ? auth()->id() : 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info('Rate history created', [
                'machinery_id' => $machineryId,
                'rate' => $rate,
                'effective_from' => $effectiveDate->toDateString(),
                'created_by' => $createdBy ?? (auth()->check() ? auth()->id() : 1),
            ]);
        });
    }
    
    /**
     * Get current rate for machinery
     */
    public function getCurrentRate($machineryId): float
    {
        return $this->getRateForDate($machineryId, now()->toDateString());
    }
    
    /**
     * Check if rate exists for date
     */
    public function hasRateForDate($machineryId, $date): bool
    {
        return DB::table('machinery_rate_history')
            ->where('machinery_id', $machineryId)
            ->where('effective_from', '<=', $date)
            ->where(function($query) use ($date) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $date);
            })
            ->exists();
    }
    
    /**
     * Get rate history for machinery
     */
    public function getRateHistory($machineryId, $limit = 10)
    {
        return DB::table('machinery_rate_history')
            ->where('machinery_id', $machineryId)
            ->orderBy('effective_from', 'desc')
            ->limit($limit)
            ->get();
    }
}
