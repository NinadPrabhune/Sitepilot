<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\DprEditHistory;
use App\Models\DprAnomalies;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DPR Lifecycle Service
 * Manages DPR states and behavioral tracking
 */
class DprLifecycleService
{
    /**
     * Create DPR with lifecycle tracking
     */
    public static function createDpr(array $data, int $createdBy): DailyProgressReport
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $dpr = DailyProgressReport::create(array_merge($data, [
                'lifecycle_state' => 'draft',
                'created_by' => $createdBy,
            ]));
            
            // Track creation
            DprEditHistory::create([
                'dpr_id' => $dpr->id,
                'user_id' => $createdBy,
                'action' => 'created',
                'new_values' => $dpr->toArray(),
                'created_at' => now(),
            ]);
            
            Log::info('DPR created', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'date' => $dpr->date,
                'created_by' => $createdBy,
                'lifecycle_state' => 'draft',
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Update DPR with edit tracking
     */
    public static function updateDpr(DailyProgressReport $dpr, array $newData, int $updatedBy, string $reason = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $newData, $updatedBy, $reason) {
            // Check if DPR can be edited
            if (!self::canEditDpr($dpr)) {
                throw new \Exception('DPR cannot be edited in current state: ' . $dpr->lifecycle_state);
            }
            
            $oldValues = $dpr->toArray();
            $dpr->update($newData);
            
            // Track update
            DprEditHistory::create([
                'dpr_id' => $dpr->id,
                'user_id' => $updatedBy,
                'action' => 'updated',
                'old_values' => $oldValues,
                'new_values' => $newData,
                'reason' => $reason,
                'created_at' => now(),
            ]);
            
            // Check for anomalies
            self::checkForAnomalies($dpr);
            
            Log::info('DPR updated', [
                'dpr_id' => $dpr->id,
                'updated_by' => $updatedBy,
                'reason' => $reason,
                'lifecycle_state' => $dpr->lifecycle_state,
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Verify DPR (move to verified state)
     */
    public static function verifyDpr(DailyProgressReport $dpr, int $verifiedBy, string $reason = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $verifiedBy, $reason) {
            if ($dpr->lifecycle_state !== 'draft') {
                throw new \Exception('Only draft DPRs can be verified');
            }
            
            $dpr->update([
                'lifecycle_state' => 'verified',
                'verified_at' => now(),
                'verified_by' => $verifiedBy,
            ]);
            
            // Track verification
            DprEditHistory::create([
                'dpr_id' => $dpr->id,
                'user_id' => $verifiedBy,
                'action' => 'verified',
                'new_values' => ['lifecycle_state' => 'verified'],
                'reason' => $reason,
                'created_at' => now(),
            ]);
            
            Log::info('DPR verified', [
                'dpr_id' => $dpr->id,
                'verified_by' => $verifiedBy,
                'reason' => $reason,
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Lock DPR (move to locked state)
     */
    public static function lockDpr(DailyProgressReport $dpr, int $lockedBy, string $reason = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $lockedBy, $reason) {
            if (!in_array($dpr->lifecycle_state, ['draft', 'verified'])) {
                throw new \Exception('Only draft or verified DPRs can be locked');
            }
            
            $dpr->update([
                'lifecycle_state' => 'locked',
                'locked_at' => now(),
                'locked_by' => $lockedBy,
            ]);
            
            // Track locking
            DprEditHistory::create([
                'dpr_id' => $dpr->id,
                'user_id' => $lockedBy,
                'action' => 'locked',
                'new_values' => ['lifecycle_state' => 'locked'],
                'reason' => $reason,
                'created_at' => now(),
            ]);
            
            Log::info('DPR locked', [
                'dpr_id' => $dpr->id,
                'locked_by' => $lockedBy,
                'reason' => $reason,
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Mark DPR as paid
     */
    public static function markDprAsPaid(DailyProgressReport $dpr, int $paidBy, string $reason = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dpr, $paidBy, $reason) {
            if ($dpr->lifecycle_state !== 'locked') {
                throw new \Exception('Only locked DPRs can be marked as paid');
            }
            
            $dpr->update([
                'lifecycle_state' => 'paid',
                'paid_at' => now(),
                'paid_by' => $paidBy,
            ]);
            
            // Track payment
            DprEditHistory::create([
                'dpr_id' => $dpr->id,
                'user_id' => $paidBy,
                'action' => 'paid',
                'new_values' => ['lifecycle_state' => 'paid'],
                'reason' => $reason,
                'created_at' => now(),
            ]);
            
            Log::info('DPR marked as paid', [
                'dpr_id' => $dpr->id,
                'paid_by' => $paidBy,
                'reason' => $reason,
            ]);
            
            return $dpr;
        });
    }
    
    /**
     * Check if DPR can be edited
     */
    public static function canEditDpr(DailyProgressReport $dpr): bool
    {
        return in_array($dpr->lifecycle_state, ['draft', 'verified']);
    }
    
    /**
     * Get DPR edit history
     */
    public static function getEditHistory(DailyProgressReport $dpr): array
    {
        return DprEditHistory::where('dpr_id', $dpr->id)
                            ->with('user')
                            ->orderBy('created_at', 'asc')
                            ->get()
                            ->toArray();
    }
    
    /**
     * Check for behavioral anomalies
     */
    private static function checkForAnomalies(DailyProgressReport $dpr): void
    {
        $editCount = DprEditHistory::where('dpr_id', $dpr->id)
                                  ->where('action', 'updated')
                                  ->count();
        
        // Check for excessive edits
        if ($editCount > 5) {
            DprAnomalies::create([
                'dpr_id' => $dpr->id,
                'anomaly_type' => 'excessive_edits',
                'description' => "DPR has been edited {$editCount} times",
                'anomaly_data' => ['edit_count' => $editCount],
                'severity' => $editCount > 10 ? 'high' : 'medium',
                'detected_at' => now(),
            ]);
        }
        
        // Check for suspicious patterns (rapid edits)
        $recentEdits = DprEditHistory::where('dpr_id', $dpr->id)
                                   ->where('action', 'updated')
                                   ->where('created_at', '>', now()->subHours(1))
                                   ->count();
        
        if ($recentEdits > 3) {
            DprAnomalies::create([
                'dpr_id' => $dpr->id,
                'anomaly_type' => 'suspicious_pattern',
                'description' => "DPR has {$recentEdits} edits in the last hour",
                'anomaly_data' => ['recent_edit_count' => $recentEdits, 'timeframe' => '1 hour'],
                'severity' => 'medium',
                'detected_at' => now(),
            ]);
        }
        
        // Check for consumption spike (if diesel data available)
        self::checkConsumptionAnomaly($dpr);
    }
    
    /**
     * Check for consumption anomalies
     */
    private static function checkConsumptionAnomaly(DailyProgressReport $dpr): void
    {
        $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
        
        if ($workingHours <= 0) {
            return;
        }
        
        // Get diesel entries for this DPR
        $dieselEntries = DB::table('daily_consumption_masters')
                          ->where('machinery_id', $dpr->machinery_id)
                          ->where('date', $dpr->date)
                          ->where('material_id', function($query) {
                              $query->select('id')
                                   ->from('materials')
                                   ->where('name', 'like', '%diesel%');
                          })
                          ->sum('quantity');
        
        if ($dieselEntries > 0) {
            $consumptionRate = $dieselEntries / $workingHours;
            
            // Check for excessive consumption (> 50L/hour)
            if ($consumptionRate > 50) {
                DprAnomalies::create([
                    'dpr_id' => $dpr->id,
                    'anomaly_type' => 'consumption_spike',
                    'description' => "High diesel consumption: {$consumptionRate}L/hour",
                    'anomaly_data' => [
                        'consumption_rate' => $consumptionRate,
                        'working_hours' => $workingHours,
                        'diesel_quantity' => $dieselEntries,
                    ],
                    'severity' => $consumptionRate > 100 ? 'high' : 'medium',
                    'detected_at' => now(),
                ]);
            }
        }
    }
    
    /**
     * Get DPRs by lifecycle state
     */
    public static function getDprsByState(string $state, array $filters = []): array
    {
        $query = DailyProgressReport::where('lifecycle_state', $state);
        
        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['machinery_id'])) {
            $query->where('machinery_id', $filters['machinery_id']);
        }
        
        return $query->with(['machinery', 'editHistory.user'])
                    ->orderBy('date', 'desc')
                    ->get()
                    ->toArray();
    }
    
    /**
     * Get behavioral statistics
     */
    public static function getBehavioralStats(array $filters = []): array
    {
        $query = DailyProgressReport::query();
        
        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }
        
        $totalDprs = $query->count();
        
        $stateBreakdown = DailyProgressReport::selectRaw('lifecycle_state, COUNT(*) as count')
                                         ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                                             return $q->where('date', '>=', $filters['date_from']);
                                         })
                                         ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                                             return $q->where('date', '<=', $filters['date_to']);
                                         })
                                         ->groupBy('lifecycle_state')
                                         ->pluck('count', 'lifecycle_state')
                                         ->toArray();
        
        // Edit frequency
        $editFrequency = DprEditHistory::selectRaw('dpr_id, COUNT(*) as edit_count')
                                     ->where('action', 'updated')
                                     ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                                         return $q->where('created_at', '>=', $filters['date_from']);
                                     })
                                     ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                                         return $q->where('created_at', '<=', $filters['date_to']);
                                     })
                                     ->groupBy('dpr_id')
                                     ->get();
        
        $avgEdits = $editFrequency->avg('edit_count');
        $maxEdits = $editFrequency->max('edit_count');
        
        // Anomaly statistics
        $anomalyStats = DprAnomalies::selectRaw('anomaly_type, severity, COUNT(*) as count')
                                   ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                                       return $q->where('detected_at', '>=', $filters['date_from']);
                                   })
                                   ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                                       return $q->where('detected_at', '<=', $filters['date_to']);
                                   })
                                   ->groupBy('anomaly_type', 'severity')
                                   ->get()
                                   ->groupBy('anomaly_type')
                                   ->toArray();
        
        return [
            'total_dprs' => $totalDprs,
            'state_breakdown' => $stateBreakdown,
            'edit_frequency' => [
                'average_edits' => round($avgEdits, 2),
                'max_edits' => $maxEdits,
                'total_edits' => $editFrequency->sum('edit_count'),
            ],
            'anomaly_stats' => $anomalyStats,
            'behavioral_health' => self::calculateBehavioralHealth($totalDprs, $avgEdits, $anomalyStats),
        ];
    }
    
    /**
     * Calculate behavioral health score
     */
    private static function calculateBehavioralHealth(int $totalDprs, float $avgEdits, array $anomalyStats): array
    {
        $healthScore = 100;
        $issues = [];
        
        // Penalize high edit frequency
        if ($avgEdits > 2) {
            $healthScore -= min(20, ($avgEdits - 2) * 5);
            $issues[] = "High edit frequency: {$avgEdits} edits per DPR";
        }
        
        // Penalize anomalies
        $totalAnomalies = array_sum(array_map(fn($group) => array_sum(array_column($group, 'count')), $anomalyStats));
        if ($totalAnomalies > 0) {
            $anomalyRate = $totalAnomalies / max(1, $totalDprs);
            $healthScore -= min(30, $anomalyRate * 100);
            $issues[] = "Anomaly rate: " . round($anomalyRate * 100, 1) . "%";
        }
        
        $healthGrade = $healthScore >= 90 ? 'A' : ($healthScore >= 80 ? 'B' : ($healthScore >= 70 ? 'C' : 'D'));
        
        return [
            'score' => max(0, $healthScore),
            'grade' => $healthGrade,
            'issues' => $issues,
        ];
    }
}
