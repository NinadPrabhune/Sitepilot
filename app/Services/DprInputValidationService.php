<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * DPR Input Validation Service
 * Prevents operational gaps in DPR creation and editing
 */
class DprInputValidationService
{
    /**
     * Validate DPR input data with comprehensive checks
     */
    public static function validateDprInput(array $data, ?DailyProgressReport $dpr = null): array
    {
        $machinery = Machinery::findOrFail($data['machinery_id']);
        
        // Basic validation rules
        $rules = [
            'date' => 'required|date',
            'machinery_id' => 'required|exists:machineries,id',
            'machine_start_reading' => 'required|numeric|min:0',
            'machine_end_reading' => 'required|numeric|min:0',
            'machine_idle_reading' => 'nullable|numeric|min:0',
            'number_of_operators' => 'nullable|integer|min:0',
            'operator_names' => 'nullable|string|max:1000',
            'work_details' => 'nullable|string|max:2000',
        ];
        
        $validator = Validator::make($data, $rules, [
            'machine_start_reading.min' => 'Start reading cannot be negative',
            'machine_end_reading.min' => 'End reading cannot be negative',
            'machine_idle_reading.min' => 'Idle reading cannot be negative',
            'number_of_operators.min' => 'Number of operators cannot be negative',
        ]);
        
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }
        
        // 🔴 CRITICAL: Business logic validation
        $issues = [];
        
        // Check end reading >= start reading
        if ($data['machine_end_reading'] < $data['machine_start_reading']) {
            $issues[] = [
                'type' => 'invalid_reading_order',
                'severity' => 'critical',
                'message' => 'End reading must be greater than or equal to start reading',
                'field' => 'machine_end_reading',
            ];
        }
        
        // Check idle hours don't exceed working hours
        $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
        $idleHours = $data['machine_idle_reading'] ?? 0;
        
        if ($idleHours > $workingHours) {
            $issues[] = [
                'type' => 'idle_exceeds_working',
                'severity' => 'high',
                'message' => 'Idle hours cannot exceed working hours',
                'field' => 'machine_idle_reading',
                'working_hours' => $workingHours,
                'idle_hours' => $idleHours,
            ];
        }
        
        // Check operator count vs names
        $operatorCount = $data['number_of_operators'] ?? 0;
        $operatorNames = $data['operator_names'] ?? '';
        
        if ($operatorCount > 0 && empty($operatorNames)) {
            $issues[] = [
                'type' => 'missing_operator_names',
                'severity' => 'medium',
                'message' => 'Operator names required when number of operators is specified',
                'field' => 'operator_names',
            ];
        }
        
        if ($operatorCount > 0 && !empty($operatorNames)) {
            $nameCount = count(array_filter(explode(',', $operatorNames), 'trim'));
            if ($nameCount !== $operatorCount) {
                $issues[] = [
                    'type' => 'operator_name_count_mismatch',
                    'severity' => 'medium',
                    'message' => "Number of operator names ({$nameCount}) does not match operator count ({$operatorCount})",
                    'field' => 'operator_names',
                    'expected_count' => $operatorCount,
                    'actual_count' => $nameCount,
                ];
            }
        }
        
        // Check for duplicate DPR on same date and machinery
        $existingDpr = DailyProgressReport::where('date', $data['date'])
                                        ->where('machinery_id', $data['machinery_id'])
                                        ->when($dpr, function ($query) use ($dpr) {
                                            return $query->where('id', '!=', $dpr->id);
                                        })
                                        ->first();
        
        if ($existingDpr) {
            $issues[] = [
                'type' => 'duplicate_dpr',
                'severity' => 'critical',
                'message' => 'DPR already exists for this machinery on this date',
                'field' => 'date',
                'existing_dpr_id' => $existingDpr->id,
            ];
        }
        
        // Validate rate override if present
        if (isset($data['override_rate']) && $data['override_rate'] > 0) {
            if (empty($data['override_reason'])) {
                $issues[] = [
                    'type' => 'missing_override_reason',
                    'severity' => 'high',
                    'message' => 'Override reason is required when override rate is specified',
                    'field' => 'override_reason',
                ];
            }
            
            if ($data['override_rate'] <= 0) {
                $issues[] = [
                    'type' => 'invalid_override_rate',
                    'severity' => 'high',
                    'message' => 'Override rate must be greater than 0',
                    'field' => 'override_rate',
                ];
            }
        }
        
        return [
            'valid' => empty($issues),
            'errors' => empty($issues) ? [] : ['business' => $issues],
            'warnings' => self::generateWarnings($data, $machinery),
        ];
    }
    
    /**
     * Generate warnings for DPR input
     */
    private static function generateWarnings(array $data, Machinery $machinery): array
    {
        $warnings = [];
        
        // Warning for high working hours (possible data entry error)
        $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
        if ($workingHours > 24) {
            $warnings[] = [
                'type' => 'high_working_hours',
                'message' => 'Working hours exceed 24 hours - please verify readings',
                'working_hours' => $workingHours,
            ];
        }
        
        // Warning for zero working hours
        if ($workingHours == 0) {
            $warnings[] = [
                'type' => 'zero_working_hours',
                'message' => 'No working hours recorded - machine was not used',
            ];
        }
        
        // Warning for rental machinery with no minimum billing
        if ($machinery->owned_by === 'rental' && (!$machinery->minimum_billing_hours || $machinery->minimum_billing_hours == 0)) {
            $warnings[] = [
                'type' => 'no_minimum_billing',
                'message' => 'Rental machinery has no minimum billing hours set',
                'machinery_id' => $machinery->id,
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Validate DPR calculation consistency
     */
    public static function validateCalculationConsistency(array $dprData, array $calculationResult): array
    {
        $issues = [];
        
        // Check if calculated amount matches expected
        $expectedAmount = $calculationResult['billable_hours'] * $calculationResult['rate_snapshot'];
        $actualAmount = $dprData['calculated_amount'] ?? 0;
        
        if (abs($expectedAmount - $actualAmount) > 0.01) {
            $issues[] = [
                'type' => 'calculation_mismatch',
                'severity' => 'high',
                'message' => 'Calculated amount does not match expected calculation',
                'expected' => $expectedAmount,
                'actual' => $actualAmount,
                'difference' => abs($expectedAmount - $actualAmount),
            ];
        }
        
        // Check rounding consistency
        $roundedBillable = round($calculationResult['billable_hours'], 2);
        $roundedRate = round($calculationResult['rate_snapshot'], 2);
        $roundedExpected = round($roundedBillable * $roundedRate, 2);
        
        if (abs($roundedExpected - $actualAmount) > 0.01) {
            $issues[] = [
                'type' => 'rounding_inconsistency',
                'severity' => 'medium',
                'message' => 'Rounding inconsistency detected',
                'rounded_expected' => $roundedExpected,
                'actual' => $actualAmount,
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Validate DPR edit permissions and constraints
     */
    public static function validateDprEdit(DailyProgressReport $dpr, array $newData): array
    {
        $issues = [];
        
        // Check if DPR is locked due to period lock
        if (self::isPeriodLocked($dpr->date)) {
            $issues[] = [
                'type' => 'period_locked',
                'severity' => 'critical',
                'message' => 'DPR cannot be edited - period is locked',
                'date' => $dpr->date,
            ];
        }
        
        // Check if DPR has payment requests
        if ($dpr->paymentRequests()->count() > 0) {
            $issues[] = [
                'type' => 'has_payment_requests',
                'severity' => 'high',
                'message' => 'DPR has payment requests - editing may affect payments',
                'payment_request_count' => $dpr->paymentRequests()->count(),
            ];
        }
        
        // Check if machinery ownership is locked
        $machinery = $dpr->machinery;
        if ($machinery->ownership_locked) {
            $issues[] = [
                'type' => 'machinery_ownership_locked',
                'severity' => 'medium',
                'message' => 'Machinery ownership is locked - cannot change machinery',
                'machinery_id' => $machinery->id,
            ];
        }
        
        // Validate new data if provided
        if (!empty($newData)) {
            $validation = self::validateDprInput($newData, $dpr);
            if (!$validation['valid']) {
                $issues = array_merge($issues, $validation['errors']['business'] ?? []);
            }
        }
        
        return [
            'can_edit' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Check if period is locked
     */
    private static function isPeriodLocked(string $date): bool
    {
        // This would integrate with your FinancialPeriodService
        // For now, return false as placeholder
        return false;
    }
    
    /**
     * Validate DPR deletion
     */
    public static function validateDprDeletion(DailyProgressReport $dpr): array
    {
        $issues = [];
        
        // Check if DPR has payment requests
        if ($dpr->paymentRequests()->count() > 0) {
            $issues[] = [
                'type' => 'has_payment_requests',
                'severity' => 'critical',
                'message' => 'Cannot delete DPR with payment requests',
                'payment_request_count' => $dpr->paymentRequests()->count(),
            ];
        }
        
        // Check if DPR has ledger entries
        if ($dpr->machineryLedgers()->count() > 0) {
            $issues[] = [
                'type' => 'has_ledger_entries',
                'severity' => 'critical',
                'message' => 'Cannot delete DPR with ledger entries - create reversal instead',
                'ledger_count' => $dpr->machineryLedgers()->count(),
            ];
        }
        
        // Check if period is locked
        if (self::isPeriodLocked($dpr->date)) {
            $issues[] = [
                'type' => 'period_locked',
                'severity' => 'critical',
                'message' => 'Cannot delete DPR - period is locked',
                'date' => $dpr->date,
            ];
        }
        
        return [
            'can_delete' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Validate concurrent DPR creation
     */
    public static function validateConcurrentCreation(array $data): array
    {
        // Use database lock to prevent race conditions
        $lockKey = "dpr_creation_{$data['machinery_id']}_{$data['date']}";
        
        // Check for existing DPR with lock
        $existingDpr = DailyProgressReport::where('date', $data['date'])
                                        ->where('machinery_id', $data['machinery_id'])
                                        ->lockForUpdate()
                                        ->first();
        
        if ($existingDpr) {
            return [
                'can_create' => false,
                'reason' => 'duplicate_dpr',
                'message' => 'DPR already exists for this machinery on this date',
                'existing_dpr_id' => $existingDpr->id,
            ];
        }
        
        return [
            'can_create' => true,
            'lock_key' => $lockKey,
        ];
    }
}
