<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use App\Models\DailyProgressReport;
use App\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Report Snapshot Service
 * Creates consistent report snapshots to prevent drift
 */
class ReportSnapshotService
{
    /**
     * Create machinery cost report snapshot
     */
    public static function createMachineryCostSnapshot(string $date, int $createdBy, array $filters = []): ReportSnapshot
    {
        $reportData = self::generateMachineryCostData($date, $filters);
        
        $snapshot = ReportSnapshot::create([
            'report_type' => 'machinery_cost',
            'report_key' => 'daily_' . ($filters['machinery_id'] ?? 'all') . '_' . $date,
            'report_date' => $date,
            'report_data' => $reportData,
            'total_amount' => $reportData['summary']['total_cost'],
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
        
        Log::info('Machinery cost snapshot created', [
            'snapshot_id' => $snapshot->id,
            'date' => $date,
            'total_cost' => $reportData['summary']['total_cost'],
            'created_by' => $createdBy,
        ]);
        
        return $snapshot;
    }
    
    /**
     * Generate machinery cost data for snapshot
     */
    private static function generateMachineryCostData(string $date, array $filters): array
    {
        // Get finalized DPRs only (exclude drafts)
        $dprQuery = DailyProgressReport::where('date', $date)
                                      ->whereIn('lifecycle_state', ['verified', 'locked', 'paid']);
        
        if (!empty($filters['machinery_id'])) {
            $dprQuery->where('machinery_id', $filters['machinery_id']);
        }
        
        $dprs = $dprQuery->with(['machinery', 'machineryLedgers'])->get();
        
        $reportData = [
            'date' => $date,
            'machinery_costs' => [],
            'summary' => [
                'total_cost' => 0,
                'internal_cost' => 0,
                'payable_cost' => 0,
                'expense_cost' => 0,
                'dpr_count' => 0,
                'owned_machines' => 0,
                'rental_machines' => 0,
            ],
            'cost_breakdown' => [
                'machine' => 0,
                'diesel' => 0,
                'maintenance' => 0,
                'operator' => 0,
                'other' => 0,
            ],
        ];
        
        foreach ($dprs as $dpr) {
            $machineryCost = 0;
            $ledgerType = 'internal_cost';
            
            // Get machine cost from ledger
            $machineLedger = $dpr->machineryLedgers
                                ->where('cost_category', 'machine')
                                ->where('is_reversal', false)
                                ->first();
            
            if ($machineLedger) {
                $machineryCost = $machineLedger->amount;
                $ledgerType = $machineLedger->ledger_type;
            }
            
            // Get associated costs (diesel, maintenance, etc.)
            $associatedCosts = MachineryLedger::where('machinery_id', $dpr->machinery_id)
                                            ->where('date', $date)
                                            ->where('is_reversal', false)
                                            ->where('cost_category', '!=', 'machine')
                                            ->sum('amount');
            
            $totalMachineryCost = $machineryCost + $associatedCosts;
            
            $reportData['machinery_costs'][] = [
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
                'machinery_name' => $dpr->machinery->name,
                'owned_by' => $dpr->machinery->owned_by,
                'machine_cost' => $machineryCost,
                'associated_costs' => $associatedCosts,
                'total_cost' => $totalMachineryCost,
                'ledger_type' => $ledgerType,
                'lifecycle_state' => $dpr->lifecycle_state,
            ];
            
            // Update summary
            $reportData['summary']['total_cost'] += $totalMachineryCost;
            $reportData['summary']['dpr_count']++;
            
            if ($dpr->machinery->owned_by === 'owned') {
                $reportData['summary']['internal_cost'] += $totalMachineryCost;
                $reportData['summary']['owned_machines']++;
            } else {
                $reportData['summary']['payable_cost'] += $totalMachineryCost;
                $reportData['summary']['rental_machines']++;
            }
            
            // Update cost breakdown
            $reportData['cost_breakdown']['machine'] += $machineryCost;
            $reportData['cost_breakdown']['other'] += $associatedCosts;
        }
        
        // Add expense costs (diesel, maintenance, etc.)
        $expenseQuery = MachineryLedger::where('date', $date)
                                       ->where('ledger_type', 'expense')
                                       ->where('is_reversal', false);
        
        if (!empty($filters['machinery_id'])) {
            $expenseQuery->where('machinery_id', $filters['machinery_id']);
        }
        
        $expenses = $expenseQuery->get();
        
        foreach ($expenses as $expense) {
            $reportData['cost_breakdown'][$expense->cost_category] += $expense->amount;
            $reportData['summary']['expense_cost'] += $expense->amount;
        }
        
        // Add expense cost to total (excluding payables to avoid double counting)
        $reportData['summary']['total_cost'] += $reportData['summary']['expense_cost'];
        
        return $reportData;
    }
    
    /**
     * Get snapshot comparison (detect drift)
     */
    public static function getSnapshotComparison(string $reportType, string $reportKey, string $date): array
    {
        $snapshot = ReportSnapshot::where('report_type', $reportType)
                                ->where('report_key', $reportKey)
                                ->where('report_date', $date)
                                ->first();
        
        if (!$snapshot) {
            return ['exists' => false];
        }
        
        // Generate current live data for comparison
        $currentData = match($reportType) {
            'machinery_cost' => self::generateMachineryCostData($date, []),
            default => null
        };
        
        if (!$currentData) {
            return ['exists' => true, 'comparison_available' => false];
        }
        
        $drift = self::calculateDrift($snapshot->report_data, $currentData);
        
        return [
            'exists' => true,
            'comparison_available' => true,
            'snapshot' => [
                'id' => $snapshot->id,
                'created_at' => $snapshot->created_at,
                'total_amount' => $snapshot->total_amount,
            ],
            'current' => [
                'total_amount' => $currentData['summary']['total_cost'],
                'generated_at' => now(),
            ],
            'drift' => $drift,
        ];
    }
    
    /**
     * Calculate drift between snapshot and current data
     */
    private static function calculateDrift(array $snapshotData, array $currentData): array
    {
        $drift = [
            'total_amount_drift' => $currentData['summary']['total_cost'] - $snapshotData['summary']['total_cost'],
            'percentage_drift' => 0,
            'drift_details' => [],
            'significant_changes' => [],
        ];
        
        if ($snapshotData['summary']['total_cost'] > 0) {
            $drift['percentage_drift'] = ($drift['total_amount_drift'] / $snapshotData['summary']['total_cost']) * 100;
        }
        
        // Check component-level drift
        foreach (['internal_cost', 'payable_cost', 'expense_cost'] as $component) {
            $snapshotAmount = $snapshotData['summary'][$component] ?? 0;
            $currentAmount = $currentData['summary'][$component] ?? 0;
            $componentDrift = $currentAmount - $snapshotAmount;
            
            if (abs($componentDrift) > 0.01) {
                $drift['drift_details'][] = [
                    'component' => $component,
                    'snapshot_amount' => $snapshotAmount,
                    'current_amount' => $currentAmount,
                    'drift' => $componentDrift,
                    'percentage_drift' => $snapshotAmount > 0 ? ($componentDrift / $snapshotAmount) * 100 : 0,
                ];
            }
        }
        
        // Identify significant changes (> 5% drift)
        if (abs($drift['percentage_drift']) > 5) {
            $drift['significant_changes'][] = [
                'type' => 'total_amount',
                'description' => "Total amount changed by " . round($drift['percentage_drift'], 2) . "%",
                'severity' => abs($drift['percentage_drift']) > 20 ? 'high' : 'medium',
            ];
        }
        
        // Check for DPR count changes
        $snapshotDprCount = $snapshotData['summary']['dpr_count'] ?? 0;
        $currentDprCount = $currentData['summary']['dpr_count'] ?? 0;
        
        if ($snapshotDprCount !== $currentDprCount) {
            $drift['significant_changes'][] = [
                'type' => 'dpr_count',
                'description' => "DPR count changed from {$snapshotDprCount} to {$currentDprCount}",
                'severity' => 'high',
            ];
        }
        
        return $drift;
    }
    
    /**
     * Create activity completion snapshot
     */
    public static function createActivityCompletionSnapshot(string $date, int $createdBy): ReportSnapshot
    {
        $reportData = self::generateActivityCompletionData($date);
        
        $snapshot = ReportSnapshot::create([
            'report_type' => 'activity_completion',
            'report_key' => 'daily_' . $date,
            'report_date' => $date,
            'report_data' => $reportData,
            'total_amount' => $reportData['summary']['total_completed_quantity'],
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
        
        Log::info('Activity completion snapshot created', [
            'snapshot_id' => $snapshot->id,
            'date' => $date,
            'total_completed' => $reportData['summary']['total_completed_quantity'],
            'created_by' => $createdBy,
        ]);
        
        return $snapshot;
    }
    
    /**
     * Generate activity completion data
     */
    private static function generateActivityCompletionData(string $date): array
    {
        // Get activities and their completions for the date
        $activities = DB::table('activities')
                       ->leftJoin('activity_completed', function ($join) use ($date) {
                           $join->on('activities.id', '=', 'activity_completed.activity_id')
                                ->where('activity_completed.date', $date);
                       })
                       ->selectRaw('
                           activities.id,
                           activities.name,
                           activities.quantity as total_quantity,
                           COALESCE(SUM(activity_completed.quantity), 0) as completed_quantity,
                           COALESCE(COUNT(activity_completed.id), 0) as completion_count
                       ')
                       ->groupBy('activities.id', 'activities.name', 'activities.quantity')
                       ->get();
        
        $reportData = [
            'date' => $date,
            'activities' => [],
            'summary' => [
                'total_activities' => 0,
                'total_quantity' => 0,
                'total_completed_quantity' => 0,
                'completion_percentage' => 0,
                'activities_with_completion' => 0,
                'activities_without_completion' => 0,
            ],
        ];
        
        foreach ($activities as $activity) {
            $completionPercentage = $activity->total_quantity > 0 
                ? ($activity->completed_quantity / $activity->total_quantity) * 100 
                : 0;
            
            $hasDprCoverage = DB::table('daily_progress_reports')
                              ->where('date', $date)
                              ->where('activity_completed_id', '>', 0)
                              ->exists();
            
            $reportData['activities'][] = [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'total_quantity' => $activity->total_quantity,
                'completed_quantity' => $activity->completed_quantity,
                'completion_percentage' => round($completionPercentage, 2),
                'completion_count' => $activity->completion_count,
                'has_dpr_coverage' => $hasDprCoverage,
            ];
            
            // Update summary
            $reportData['summary']['total_activities']++;
            $reportData['summary']['total_quantity'] += $activity->total_quantity;
            $reportData['summary']['total_completed_quantity'] += $activity->completed_quantity;
            
            if ($activity->completion_count > 0) {
                $reportData['summary']['activities_with_completion']++;
            } else {
                $reportData['summary']['activities_without_completion']++;
            }
        }
        
        // Calculate overall completion percentage
        if ($reportData['summary']['total_quantity'] > 0) {
            $reportData['summary']['completion_percentage'] = 
                ($reportData['summary']['total_completed_quantity'] / $reportData['summary']['total_quantity']) * 100;
        }
        
        return $reportData;
    }
    
    /**
     * Get available snapshots
     */
    public static function getAvailableSnapshots(string $reportType, array $filters = []): array
    {
        $query = ReportSnapshot::where('report_type', $reportType);
        
        if (!empty($filters['date_from'])) {
            $query->where('report_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('report_date', '<=', $filters['date_to']);
        }
        
        return $query->with('creator')
                    ->orderBy('report_date', 'desc')
                    ->get()
                    ->toArray();
    }
    
    /**
     * Delete old snapshots (cleanup)
     */
    public static function cleanupOldSnapshots(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = ReportSnapshot::where('created_at', '<', $cutoffDate)
                                      ->delete();
        
        Log::info('Old snapshots cleaned up', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate,
        ]);
        
        return $deletedCount;
    }
}
