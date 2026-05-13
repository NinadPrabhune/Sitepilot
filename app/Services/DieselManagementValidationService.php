<?php

namespace App\Services;

use App\Models\DailyConsumptionMaster;
use App\Models\DailyProgressReport;
use App\Models\Machinery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Diesel Management Validation Service
 * Prevents diesel duplication and ensures proper linkage
 */
class DieselManagementValidationService
{
    /**
     * Validate diesel entry input
     */
    public static function validateDieselEntry(array $data): array
    {
        $rules = [
            'machinery_id' => 'required|exists:machineries,id',
            'date' => 'required|date',
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'site_id' => 'required|exists:sites,id',
        ];
        
        $validator = Validator::make($data, $rules, [
            'quantity.min' => 'Quantity must be greater than 0',
            'material_id.exists' => 'Selected material does not exist',
            'machinery_id.exists' => 'Selected machinery does not exist',
        ]);
        
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }
        
        // Business logic validation
        $issues = [];
        
        // Check if material is diesel type
        $material = DB::table('materials')->where('id', $data['material_id'])->first();
        if (!$material || strtolower($material->name) !== 'diesel') {
            $issues[] = [
                'type' => 'invalid_diesel_material',
                'severity' => 'high',
                'message' => 'Selected material is not diesel',
                'material_id' => $data['material_id'],
                'material_name' => $material->name ?? 'Unknown',
            ];
        }
        
        // Check for duplicate diesel entry on same date and machinery
        $existingEntry = DailyConsumptionMaster::where('date', $data['date'])
                                              ->where('machinery_id', $data['machinery_id'])
                                              ->where('material_id', $data['material_id'])
                                              ->first();
        
        if ($existingEntry) {
            $issues[] = [
                'type' => 'duplicate_diesel_entry',
                'severity' => 'critical',
                'message' => 'Diesel entry already exists for this machinery on this date',
                'existing_entry_id' => $existingEntry->id,
                'date' => $data['date'],
                'machinery_id' => $data['machinery_id'],
            ];
        }
        
        // Check if diesel entry requires DPR linkage
        $dprExists = DailyProgressReport::where('date', $data['date'])
                                       ->where('machinery_id', $data['machinery_id'])
                                       ->exists();
        
        if (!$dprExists) {
            $issues[] = [
                'type' => 'diesel_without_dpr',
                'severity' => 'medium',
                'message' => 'No DPR found for this machinery on this date',
                'date' => $data['date'],
                'machinery_id' => $data['machinery_id'],
                'recommendation' => 'Create DPR first, then add diesel entry',
            ];
        }
        
        return [
            'valid' => empty($issues),
            'errors' => empty($issues) ? [] : ['business' => $issues],
        ];
    }
    
    /**
     * Validate diesel-dpr linkage consistency
     */
    public static function validateDieselDprLinkage(int $machineryId, string $date): array
    {
        $issues = [];
        
        $dpr = DailyProgressReport::where('machinery_id', $machineryId)
                                  ->where('date', $date)
                                  ->first();
        
        $dieselEntries = DailyConsumptionMaster::where('machinery_id', $machineryId)
                                               ->where('date', $date)
                                               ->get();
        
        // Check for diesel without DPR
        if (!$dpr && $dieselEntries->count() > 0) {
            $issues[] = [
                'type' => 'diesel_without_dpr',
                'severity' => 'medium',
                'message' => 'Diesel entries exist without corresponding DPR',
                'diesel_count' => $dieselEntries->count(),
                'date' => $date,
                'machinery_id' => $machineryId,
            ];
        }
        
        // Check for DPR without diesel (warning only)
        if ($dpr && $dieselEntries->count() == 0) {
            $issues[] = [
                'type' => 'dpr_without_diesel',
                'severity' => 'low',
                'message' => 'DPR exists but no diesel entries found',
                'dpr_id' => $dpr->id,
                'date' => $date,
                'machinery_id' => $machineryId,
            ];
        }
        
        // Check for multiple diesel entries (potential duplication)
        if ($dieselEntries->count() > 1) {
            $issues[] = [
                'type' => 'multiple_diesel_entries',
                'severity' => 'high',
                'message' => 'Multiple diesel entries found - potential duplication',
                'diesel_count' => $dieselEntries->count(),
                'diesel_ids' => $dieselEntries->pluck('id')->toArray(),
                'date' => $date,
                'machinery_id' => $machineryId,
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'dpr_exists' => $dpr !== null,
            'diesel_count' => $dieselEntries->count(),
        ];
    }
    
    /**
     * Prevent diesel duplication across date ranges
     */
    public static function preventDieselDuplication(array $data, ?DailyConsumptionMaster $existingEntry = null): array
    {
        $machineryId = $data['machinery_id'];
        $date = $data['date'];
        $materialId = $data['material_id'];
        
        // Check for entries in close date range (within 1 day)
        $nearbyEntries = DailyConsumptionMaster::where('machinery_id', $machineryId)
                                              ->where('material_id', $materialId)
                                              ->whereBetween('date', [
                                                  date('Y-m-d', strtotime($date . ' -1 day')),
                                                  date('Y-m-d', strtotime($date . ' +1 day'))
                                              ])
                                              ->when($existingEntry, function ($query) use ($existingEntry) {
                                                  return $query->where('id', '!=', $existingEntry->id);
                                              })
                                              ->get();
        
        $issues = [];
        
        foreach ($nearbyEntries as $entry) {
            $daysDiff = abs(strtotime($entry->date) - strtotime($date)) / (60 * 60 * 24);
            
            if ($daysDiff == 0) {
                // Same day - critical issue
                $issues[] = [
                    'type' => 'same_day_duplicate',
                    'severity' => 'critical',
                    'message' => 'Diesel entry already exists for same date',
                    'existing_entry_id' => $entry->id,
                    'existing_date' => $entry->date,
                ];
            } elseif ($daysDiff <= 1) {
                // Nearby day - potential duplicate
                $issues[] = [
                    'type' => 'nearby_duplicate',
                    'severity' => 'medium',
                    'message' => 'Diesel entry found on nearby date - possible duplicate',
                    'existing_entry_id' => $entry->id,
                    'existing_date' => $entry->date,
                    'days_difference' => $daysDiff,
                ];
            }
        }
        
        return [
            'can_create' => !collect($issues)->contains('severity', 'critical'),
            'issues' => $issues,
            'nearby_entries_count' => $nearbyEntries->count(),
        ];
    }
    
    /**
     * Validate diesel quantity reasonableness
     */
    public static function validateDieselQuantity(array $data): array
    {
        $issues = [];
        $quantity = $data['quantity'];
        $machineryId = $data['machinery_id'];
        $date = $data['date'];
        
        // Check for unreasonable quantities
        if ($quantity > 1000) {
            $issues[] = [
                'type' => 'excessive_quantity',
                'severity' => 'medium',
                'message' => 'Diesel quantity seems excessive',
                'quantity' => $quantity,
                'threshold' => 1000,
            ];
        }
        
        // Check against historical averages
        $historicalAverage = DailyConsumptionMaster::where('machinery_id', $machineryId)
                                                   ->where('material_id', $data['material_id'])
                                                   ->where('date', '<', $date)
                                                   ->where('date', '>=', date('Y-m-d', strtotime($date . ' -30 days')))
                                                   ->avg('quantity');
        
        if ($historicalAverage && $quantity > ($historicalAverage * 3)) {
            $issues[] = [
                'type' => 'quantity_spike',
                'severity' => 'medium',
                'message' => 'Diesel quantity is significantly higher than historical average',
                'current_quantity' => $quantity,
                'historical_average' => $historicalAverage,
                'ratio' => $quantity / $historicalAverage,
            ];
        }
        
        // Check against DPR working hours
        $dpr = DailyProgressReport::where('machinery_id', $machineryId)
                                  ->where('date', $date)
                                  ->first();
        
        if ($dpr) {
            $workingHours = $dpr->machine_end_reading - $dpr->machine_start_reading;
            $consumptionRate = $workingHours > 0 ? $quantity / $workingHours : 0;
            
            if ($consumptionRate > 50) {
                $issues[] = [
                    'type' => 'high_consumption_rate',
                    'severity' => 'medium',
                    'message' => 'Diesel consumption rate seems very high',
                    'consumption_rate' => $consumptionRate,
                    'working_hours' => $workingHours,
                    'threshold' => 50,
                ];
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $issues,
        ];
    }
    
    /**
     * Comprehensive diesel validation
     */
    public static function comprehensiveValidation(array $data, ?DailyConsumptionMaster $existingEntry = null): array
    {
        $basicValidation = self::validateDieselEntry($data);
        $duplicationCheck = self::preventDieselDuplication($data, $existingEntry);
        $quantityCheck = self::validateDieselQuantity($data);
        
        $allIssues = array_merge(
            $basicValidation['errors']['business'] ?? [],
            $duplicationCheck['issues'],
            $quantityCheck['issues'] ?? []
        );
        
        $criticalIssues = array_filter($allIssues, fn($issue) => ($issue['severity'] ?? 'medium') === 'critical');
        $highIssues = array_filter($allIssues, fn($issue) => ($issue['severity'] ?? 'medium') === 'high');
        
        return [
            'valid' => empty($criticalIssues) && empty($highIssues),
            'can_create' => empty($criticalIssues),
            'critical_issues_count' => count($criticalIssues),
            'high_issues_count' => count($highIssues),
            'total_issues_count' => count($allIssues),
            'issues' => $allIssues,
            'warnings' => $quantityCheck['warnings'] ?? [],
            'recommendations' => self::generateRecommendations($allIssues),
        ];
    }
    
    /**
     * Generate recommendations based on validation issues
     */
    private static function generateRecommendations(array $issues): array
    {
        $recommendations = [];
        
        $duplicateIssues = array_filter($issues, fn($issue) => in_array($issue['type'] ?? '', ['duplicate_diesel_entry', 'same_day_duplicate']));
        if (!empty($duplicateIssues)) {
            $recommendations[] = [
                'action' => 'Check existing diesel entries before creating new ones',
                'reason' => 'Prevent duplicate diesel entries',
            ];
        }
        
        $dprIssues = array_filter($issues, fn($issue) => ($issue['type'] ?? '') === 'diesel_without_dpr');
        if (!empty($dprIssues)) {
            $recommendations[] = [
                'action' => 'Create DPR before adding diesel entries',
                'reason' => 'Ensure proper diesel-dpr linkage',
            ];
        }
        
        $quantityIssues = array_filter($issues, fn($issue) => in_array($issue['type'] ?? '', ['excessive_quantity', 'quantity_spike', 'high_consumption_rate']));
        if (!empty($quantityIssues)) {
            $recommendations[] = [
                'action' => 'Verify diesel quantity and units',
                'reason' => 'Prevent data entry errors',
            ];
        }
        
        return $recommendations;
    }
}
