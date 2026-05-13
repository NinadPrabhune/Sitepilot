<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

/**
 * Smart Validation Service
 * Converts hard blocks to smart warnings for better UX
 */
class SmartValidationService
{
    /**
     * Validate DPR input with smart classification
     */
    public static function validateDprInput(array $data): array
    {
        $validation = [
            'status' => 'valid',
            'issues' => [],
            'warnings' => [],
            'flags' => [],
            'recommendations' => [],
        ];

        // Critical validations (must block)
        $criticalIssues = self::validateCriticalRules($data);
        if (!empty($criticalIssues)) {
            $validation['status'] = 'blocked';
            $validation['issues'] = $criticalIssues;
            return $validation;
        }

        // Logical warnings (can proceed with confirmation)
        $logicalWarnings = self::validateLogicalRules($data);
        $validation['warnings'] = $logicalWarnings;

        // Behavioral flags (track but don't block)
        $behavioralFlags = self::validateBehavioralPatterns($data);
        $validation['flags'] = $behavioralFlags;

        // Smart recommendations
        $validation['recommendations'] = self::generateRecommendations($data, $logicalWarnings, $behavioralFlags);

        return $validation;
    }

    /**
     * Critical validations that must block
     */
    private static function validateCriticalRules(array $data): array
    {
        $issues = [];

        // End reading must be >= start reading
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            if ($data['machine_end_reading'] < $data['machine_start_reading']) {
                $issues[] = [
                    'type' => 'critical',
                    'field' => 'machine_end_reading',
                    'message' => 'End reading cannot be less than start reading',
                    'action' => 'block',
                ];
            }
        }

        // Negative values not allowed
        $numericFields = ['machine_start_reading', 'machine_end_reading', 'machine_idle_reading', 'number_of_operators'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] < 0) {
                $issues[] = [
                    'type' => 'critical',
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' cannot be negative',
                    'action' => 'block',
                ];
            }
        }

        // Duplicate DPR check
        if (isset($data['date']) && isset($data['machinery_id'])) {
            $existingDpr = \App\Models\DailyProgressReport::where('date', $data['date'])
                                                        ->where('machinery_id', $data['machinery_id'])
                                                        ->first();
            if ($existingDpr) {
                $issues[] = [
                    'type' => 'critical',
                    'field' => 'date',
                    'message' => 'DPR already exists for this machinery on this date',
                    'action' => 'block',
                    'existing_dpr_id' => $existingDpr->id,
                ];
            }
        }

        return $issues;
    }

    /**
     * Logical warnings that can be overridden
     */
    private static function validateLogicalRules(array $data): array
    {
        $warnings = [];

        // Idle hours exceeding working hours
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading']) && isset($data['machine_idle_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $idleHours = $data['machine_idle_reading'];
            
            if ($idleHours > $workingHours) {
                $warnings[] = [
                    'type' => 'logical',
                    'field' => 'machine_idle_reading',
                    'message' => "Idle hours ({$idleHours}) exceed working hours ({$workingHours})",
                    'impact' => 'This may indicate incorrect readings',
                    'suggestion' => 'Consider reducing idle hours or checking readings',
                    'can_override' => true,
                    'override_reason_required' => true,
                ];
            }
        }

        // Operator name count mismatch
        if (isset($data['number_of_operators']) && isset($data['operator_names'])) {
            $operatorCount = $data['number_of_operators'];
            $nameCount = count(array_filter(explode(',', $data['operator_names']), 'trim'));
            
            if ($operatorCount > 0 && $nameCount !== $operatorCount) {
                $warnings[] = [
                    'type' => 'logical',
                    'field' => 'operator_names',
                    'message' => "Number of operator names ({$nameCount}) doesn't match operator count ({$operatorCount})",
                    'impact' => 'Missing operator information',
                    'suggestion' => 'Add missing operator names or adjust operator count',
                    'can_override' => true,
                    'override_reason_required' => false,
                ];
            }
        }

        // High working hours (possible data entry error)
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            
            if ($workingHours > 24) {
                $warnings[] = [
                    'type' => 'logical',
                    'field' => 'machine_end_reading',
                    'message' => "Working hours ({$workingHours}) exceed 24 hours",
                    'impact' => 'Possible data entry error',
                    'suggestion' => 'Please verify the readings are correct',
                    'can_override' => true,
                    'override_reason_required' => true,
                ];
            }
        }

        return $warnings;
    }

    /**
     * Behavioral flags for tracking patterns
     */
    private static function validateBehavioralPatterns(array $data): array
    {
        $flags = [];

        // Zero working hours
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            
            if ($workingHours == 0) {
                $flags[] = [
                    'type' => 'behavioral',
                    'field' => 'readings',
                    'message' => 'No working hours recorded',
                    'impact' => 'Machine was not used',
                    'suggestion' => 'Verify if machine was operational',
                    'track_pattern' => true,
                ];
            }
        }

        // Unusual idle percentage
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading']) && isset($data['machine_idle_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $idleHours = $data['machine_idle_reading'];
            
            if ($workingHours > 0) {
                $idlePercentage = ($idleHours / $workingHours) * 100;
                
                if ($idlePercentage > 50) {
                    $flags[] = [
                        'type' => 'behavioral',
                        'field' => 'machine_idle_reading',
                        'message' => "High idle time: " . round($idlePercentage, 1) . "%",
                        'impact' => 'Low machine utilization',
                        'suggestion' => 'Consider reasons for high idle time',
                        'track_pattern' => true,
                    ];
                }
            }
        }

        return $flags;
    }

    /**
     * Generate smart recommendations
     */
    private static function generateRecommendations(array $data, array $warnings, array $flags): array
    {
        $recommendations = [];

        // Calculate preview values
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $idleHours = $data['machine_idle_reading'] ?? 0;
            $billableHours = max(0, $workingHours - $idleHours);
            
            $recommendations[] = [
                'type' => 'calculation_preview',
                'title' => 'Calculation Preview',
                'values' => [
                    'working_hours' => $workingHours,
                    'idle_hours' => $idleHours,
                    'billable_hours' => $billableHours,
                ],
            ];

            // Minimum billing warning
            if (isset($data['machinery_id']) && $data['machinery_id']) {
                $machinery = \App\Models\Machinery::find($data['machinery_id']);
                if ($machinery && $machinery->owned_by === 'rental' && $machinery->minimum_billing_hours > 0) {
                    $minimumHours = $machinery->minimum_billing_hours;
                    
                    if ($billableHours < $minimumHours) {
                        $recommendations[] = [
                            'type' => 'minimum_billing',
                            'title' => 'Minimum Billing Alert',
                            'message' => "Rental machinery has minimum billing of {$minimumHours} hours",
                            'current_hours' => $billableHours,
                            'minimum_hours' => $minimumHours,
                            'impact' => 'Billable hours will be increased to meet minimum',
                            'final_hours' => $minimumHours,
                        ];
                    }
                }
            }
        }

        // Operator efficiency suggestion
        if (isset($data['number_of_operators']) && $data['number_of_operators'] > 0) {
            if (isset($billableHours) && $billableHours > 0) {
                $hoursPerOperator = $billableHours / $data['number_of_operators'];
                
                if ($hoursPerOperator < 4) {
                    $recommendations[] = [
                        'type' => 'efficiency',
                        'title' => 'Operator Efficiency',
                        'message' => "Low hours per operator: " . round($hoursPerOperator, 1) . " hours",
                        'suggestion' => 'Consider if operator count is optimal',
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Validate diesel entries with smart classification
     */
    public static function validateDieselEntry(array $data): array
    {
        $validation = [
            'status' => 'valid',
            'issues' => [],
            'warnings' => [],
            'flags' => [],
        ];

        // Critical validations
        $criticalIssues = self::validateDieselCriticalRules($data);
        if (!empty($criticalIssues)) {
            $validation['status'] = 'blocked';
            $validation['issues'] = $criticalIssues;
            return $validation;
        }

        // Logical warnings
        $logicalWarnings = self::validateDieselLogicalRules($data);
        $validation['warnings'] = $logicalWarnings;

        // Behavioral flags
        $behavioralFlags = self::validateDieselBehavioralPatterns($data);
        $validation['flags'] = $behavioralFlags;

        return $validation;
    }

    /**
     * Critical diesel validations
     */
    private static function validateDieselCriticalRules(array $data): array
    {
        $issues = [];

        // Negative quantity
        if (isset($data['quantity']) && $data['quantity'] <= 0) {
            $issues[] = [
                'type' => 'critical',
                'field' => 'quantity',
                'message' => 'Quantity must be greater than 0',
                'action' => 'block',
            ];
        }

        // Invalid material
        if (isset($data['material_id'])) {
            $material = \DB::table('materials')->where('id', $data['material_id'])->first();
            if (!$material) {
                $issues[] = [
                    'type' => 'critical',
                    'field' => 'material_id',
                    'message' => 'Invalid material selected',
                    'action' => 'block',
                ];
            }
        }

        return $issues;
    }

    /**
     * Logical diesel warnings
     */
    private static function validateDieselLogicalRules(array $data): array
    {
        $warnings = [];

        // Duplicate diesel entry (now a warning, not block)
        if (isset($data['machinery_id']) && isset($data['date']) && isset($data['material_id'])) {
            $existing = \App\Models\DailyConsumptionMaster::where('machinery_id', $data['machinery_id'])
                                                         ->where('date', $data['date'])
                                                         ->where('material_id', $data['material_id'])
                                                         ->first();
            
            if ($existing) {
                $warnings[] = [
                    'type' => 'logical',
                    'field' => 'duplicate',
                    'message' => 'Diesel entry already exists for this machinery on this date',
                    'existing_quantity' => $existing->quantity,
                    'new_quantity' => $data['quantity'],
                    'can_override' => true,
                    'override_reason_required' => true,
                    'suggestion' => 'Update existing entry instead of creating duplicate',
                ];
            }
        }

        // Diesel without DPR (now allowed with warning)
        if (isset($data['machinery_id']) && isset($data['date'])) {
            $dpr = \App\Models\DailyProgressReport::where('machinery_id', $data['machinery_id'])
                                                  ->where('date', $data['date'])
                                                  ->first();
            
            if (!$dpr) {
                $warnings[] = [
                    'type' => 'logical',
                    'field' => 'dpr_link',
                    'message' => 'No DPR found for this machinery on this date',
                    'impact' => 'Diesel consumption will be unlinked to machine usage',
                    'can_override' => true,
                    'override_reason_required' => false,
                    'suggestion' => 'Create DPR first for better cost tracking',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Behavioral diesel patterns
     */
    private static function validateDieselBehavioralPatterns(array $data): array
    {
        $flags = [];

        // Excessive consumption
        if (isset($data['machinery_id']) && isset($data['date']) && isset($data['quantity'])) {
            // Get working hours from DPR if available
            $dpr = \App\Models\DailyProgressReport::where('machinery_id', $data['machinery_id'])
                                                  ->where('date', $data['date'])
                                                  ->first();
            
            if ($dpr) {
                $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
                
                if ($workingHours > 0) {
                    $consumptionRate = $data['quantity'] / $workingHours;
                    
                    if ($consumptionRate > 50) {
                        $flags[] = [
                            'type' => 'behavioral',
                            'field' => 'quantity',
                            'message' => "High consumption rate: " . round($consumptionRate, 1) . " L/hour",
                            'impact' => 'Unusual fuel consumption pattern',
                            'suggestion' => 'Verify readings and consumption data',
                            'track_pattern' => true,
                        ];
                    }
                }
            }
        }

        return $flags;
    }
}
