<?php

namespace App\Services;

use App\Models\Machinery;

/**
 * Pre-submit Validation Service
 * Provides calculation preview and guidance before save
 */
class PreSubmitValidationService
{
    /**
     * Generate comprehensive pre-submit preview
     */
    public static function generatePreview(array $data): array
    {
        $preview = [
            'calculations' => [],
            'validations' => [],
            'recommendations' => [],
            'warnings' => [],
            'status' => 'ready',
        ];

        // Basic calculations
        $calculations = self::calculateBasicMetrics($data);
        $preview['calculations'] = $calculations;

        // Machinery-specific calculations
        if (isset($data['machinery_id'])) {
            $machinery = Machinery::find($data['machinery_id']);
            if ($machinery) {
                $machineryCalculations = self::calculateMachineryMetrics($data, $machinery);
                $preview['calculations'] = array_merge($preview['calculations'], $machineryCalculations);
            }
        }

        // Pre-submit validations
        $validations = self::validateBeforeSubmit($data);
        $preview['validations'] = $validations;

        // Smart recommendations
        $recommendations = self::generateSmartRecommendations($data, $calculations);
        $preview['recommendations'] = $recommendations;

        // Warnings for potential issues
        $warnings = self::generateWarnings($data, $calculations);
        $preview['warnings'] = $warnings;

        // Overall status
        $preview['status'] = self::determineOverallStatus($validations, $warnings);

        return $preview;
    }

    /**
     * Calculate basic metrics
     */
    private static function calculateBasicMetrics(array $data): array
    {
        $metrics = [];

        // Working hours
        if (isset($data['machine_start_reading']) && isset($data['machine_end_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $metrics['working_hours'] = [
                'value' => $workingHours,
                'label' => 'Working Hours',
                'unit' => 'hrs',
                'status' => $workingHours >= 0 ? 'valid' : 'invalid',
            ];
        }

        // Idle hours
        if (isset($data['machine_idle_reading'])) {
            $idleHours = $data['machine_idle_reading'];
            $metrics['idle_hours'] = [
                'value' => $idleHours,
                'label' => 'Idle Hours',
                'unit' => 'hrs',
                'status' => $idleHours >= 0 ? 'valid' : 'invalid',
            ];
        }

        // Billable hours
        if (isset($workingHours) && isset($idleHours)) {
            $billableHours = max(0, $workingHours - $idleHours);
            $metrics['billable_hours'] = [
                'value' => $billableHours,
                'label' => 'Billable Hours',
                'unit' => 'hrs',
                'status' => $billableHours >= 0 ? 'valid' : 'invalid',
            ];
        }

        // Utilization percentage
        if (isset($workingHours) && $workingHours > 0 && isset($idleHours)) {
            $utilization = (($workingHours - $idleHours) / $workingHours) * 100;
            $metrics['utilization'] = [
                'value' => round($utilization, 1),
                'label' => 'Machine Utilization',
                'unit' => '%',
                'status' => $utilization >= 50 ? 'good' : ($utilization >= 25 ? 'fair' : 'poor'),
            ];
        }

        return $metrics;
    }

    /**
     * Calculate machinery-specific metrics
     */
    private static function calculateMachineryMetrics(array $data, Machinery $machinery): array
    {
        $metrics = [];

        // Rate
        $rate = $data['override_rate'] ?? $machinery->rate ?? 0;
        $metrics['rate'] = [
            'value' => $rate,
            'label' => 'Rate',
            'unit' => '₹/hr',
            'status' => $rate > 0 ? 'valid' : 'invalid',
            'source' => isset($data['override_rate']) ? 'override' : 'standard',
        ];

        // Calculated amount
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $idleHours = $data['machine_idle_reading'] ?? 0;
            $billableHours = max(0, $workingHours - $idleHours);
            
            // Apply minimum billing for rental machinery
            if ($machinery->owned_by === 'rental' && $machinery->minimum_billing_hours > 0) {
                $minimumHours = $machinery->minimum_billing_hours;
                if ($billableHours < $minimumHours) {
                    $billableHours = $minimumHours;
                    $metrics['minimum_billing_applied'] = [
                        'value' => true,
                        'label' => 'Minimum Billing Applied',
                        'details' => "Increased from " . ($workingHours - $idleHours) . " to {$minimumHours} hours",
                    ];
                }
            }
            
            $calculatedAmount = $billableHours * $rate;
            $metrics['calculated_amount'] = [
                'value' => $calculatedAmount,
                'label' => 'Calculated Amount',
                'unit' => '₹',
                'status' => $calculatedAmount >= 0 ? 'valid' : 'invalid',
                'formula' => "{$billableHours} hrs × ₹{$rate}/hr",
            ];
        }

        // Operator efficiency
        if (isset($data['number_of_operators']) && $data['number_of_operators'] > 0 && isset($billableHours)) {
            $hoursPerOperator = $billableHours / $data['number_of_operators'];
            $metrics['operator_efficiency'] = [
                'value' => round($hoursPerOperator, 1),
                'label' => 'Hours per Operator',
                'unit' => 'hrs',
                'status' => $hoursPerOperator >= 6 ? 'good' : ($hoursPerOperator >= 4 ? 'fair' : 'poor'),
            ];
        }

        return $metrics;
    }

    /**
     * Validate before submit
     */
    private static function validateBeforeSubmit(array $data): array
    {
        $validations = [];

        // Required fields
        $requiredFields = ['date', 'machinery_id', 'machine_start_reading', 'machine_end_reading'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $validations[] = [
                    'type' => 'error',
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                ];
            }
        }

        // Logical validations
        if (isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            if ($data['machine_end_reading'] < $data['machine_start_reading']) {
                $validations[] = [
                    'type' => 'error',
                    'field' => 'machine_end_reading',
                    'message' => 'End reading must be greater than or equal to start reading',
                ];
            }
        }

        if (isset($data['machine_idle_reading']) && $data['machine_idle_reading'] < 0) {
            $validations[] = [
                'type' => 'error',
                'field' => 'machine_idle_reading',
                'message' => 'Idle hours cannot be negative',
            ];
        }

        // Operator validations
        if (isset($data['number_of_operators']) && $data['number_of_operators'] > 0) {
            if (!isset($data['operator_names']) || trim($data['operator_names']) === '') {
                $validations[] = [
                    'type' => 'warning',
                    'field' => 'operator_names',
                    'message' => 'Operator names recommended when operator count is specified',
                ];
            } else {
                $nameCount = count(array_filter(explode(',', $data['operator_names']), 'trim'));
                if ($nameCount !== $data['number_of_operators']) {
                    $validations[] = [
                        'type' => 'warning',
                        'field' => 'operator_names',
                        'message' => "Operator names count ({$nameCount}) doesn't match operator count ({$data['number_of_operators']})",
                    ];
                }
            }
        }

        return $validations;
    }

    /**
     * Generate smart recommendations
     */
    private static function generateSmartRecommendations(array $data, array $calculations): array
    {
        $recommendations = [];

        // Utilization recommendations
        if (isset($calculations['utilization'])) {
            $utilization = $calculations['utilization']['value'];
            if ($utilization < 50) {
                $recommendations[] = [
                    'type' => 'efficiency',
                    'priority' => 'medium',
                    'title' => 'Low Machine Utilization',
                    'message' => "Machine utilization is only {$utilization}%",
                    'suggestions' => [
                        'Check if machine was actually idle during recorded time',
                        'Consider if work could be optimized to reduce idle time',
                        'Verify if start/end readings are accurate',
                    ],
                ];
            }
        }

        // Operator efficiency recommendations
        if (isset($calculations['operator_efficiency'])) {
            $hoursPerOperator = $calculations['operator_efficiency']['value'];
            if ($hoursPerOperator < 4) {
                $recommendations[] = [
                    'type' => 'efficiency',
                    'priority' => 'medium',
                    'title' => 'Low Operator Efficiency',
                    'message' => "Each operator has only {$hoursPerOperator} billable hours",
                    'suggestions' => [
                        'Consider if operator count is optimal for the work',
                        'Check if work was distributed efficiently',
                        'Review if additional operators were necessary',
                    ],
                ];
            } elseif ($hoursPerOperator > 12) {
                $recommendations[] = [
                    'type' => 'workload',
                    'priority' => 'high',
                    'title' => 'High Operator Workload',
                    'message' => "Each operator has {$hoursPerOperator} billable hours",
                    'suggestions' => [
                        'Consider if additional operators are needed',
                        'Check if workload distribution is reasonable',
                        'Monitor for operator fatigue',
                    ],
                ];
            }
        }

        // Rate override recommendations
        if (isset($data['override_rate']) && isset($calculations['rate'])) {
            $standardRate = \App\Models\Machinery::find($data['machinery_id'])->rate ?? 0;
            if ($standardRate > 0) {
                $deviation = (($data['override_rate'] - $standardRate) / $standardRate) * 100;
                
                if (abs($deviation) > 20) {
                    $recommendations[] = [
                        'type' => 'rate',
                        'priority' => 'high',
                        'title' => 'Significant Rate Deviation',
                        'message' => "Override rate deviates by " . round($deviation, 1) . "% from standard rate",
                        'suggestions' => [
                            'Verify if rate deviation is justified',
                            'Ensure proper approval for rate overrides',
                            'Document reason for rate change',
                        ],
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Generate warnings
     */
    private static function generateWarnings(array $data, array $calculations): array
    {
        $warnings = [];

        // High working hours warning
        if (isset($calculations['working_hours'])) {
            $workingHours = $calculations['working_hours']['value'];
            if ($workingHours > 24) {
                $warnings[] = [
                    'type' => 'data_quality',
                    'priority' => 'high',
                    'message' => "Working hours ({$workingHours}) exceed 24 hours",
                    'suggestion' => 'Please verify the readings are correct',
                ];
            }
        }

        // Zero working hours
        if (isset($calculations['working_hours']) && $calculations['working_hours']['value'] == 0) {
            $warnings[] = [
                'type' => 'usage',
                'priority' => 'medium',
                'message' => 'No working hours recorded',
                'suggestion' => 'Verify if machine was operational on this date',
            ];
        }

        // High idle time
        if (isset($calculations['utilization'])) {
            $utilization = $calculations['utilization']['value'];
            if ($utilization < 25) {
                $warnings[] = [
                    'type' => 'efficiency',
                    'priority' => 'medium',
                    'message' => "Very low machine utilization ({$utilization}%)",
                    'suggestion' => 'Consider reasons for high idle time',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Determine overall status
     */
    private static function determineOverallStatus(array $validations, array $warnings): string
    {
        $hasErrors = collect($validations)->contains('type', 'error');
        $hasWarnings = !empty($warnings) || collect($validations)->contains('type', 'warning');

        if ($hasErrors) {
            return 'blocked';
        } elseif ($hasWarnings) {
            return 'warning';
        } else {
            return 'ready';
        }
    }

    /**
     * Format preview for UI display
     */
    public static function formatForUi(array $preview): array
    {
        $formatted = [
            'status' => $preview['status'],
            'calculations' => [],
            'validations' => [],
            'recommendations' => [],
            'warnings' => [],
        ];

        // Format calculations
        foreach ($preview['calculations'] as $key => $calc) {
            $formatted['calculations'][] = [
                'key' => $key,
                'label' => $calc['label'],
                'value' => $calc['value'],
                'unit' => $calc['unit'] ?? '',
                'status' => $calc['status'] ?? 'valid',
                'formula' => $calc['formula'] ?? null,
                'source' => $calc['source'] ?? null,
            ];
        }

        // Format validations
        foreach ($preview['validations'] as $validation) {
            $formatted['validations'][] = [
                'type' => $validation['type'],
                'field' => $validation['field'],
                'message' => $validation['message'],
                'severity' => $validation['type'] === 'error' ? 'high' : 'medium',
            ];
        }

        // Format recommendations
        foreach ($preview['recommendations'] as $rec) {
            $formatted['recommendations'][] = [
                'type' => $rec['type'],
                'priority' => $rec['priority'],
                'title' => $rec['title'],
                'message' => $rec['message'],
                'suggestions' => $rec['suggestions'] ?? [],
            ];
        }

        // Format warnings
        foreach ($preview['warnings'] as $warning) {
            $formatted['warnings'][] = [
                'type' => $warning['type'],
                'priority' => $warning['priority'],
                'message' => $warning['message'],
                'suggestion' => $warning['suggestion'] ?? null,
            ];
        }

        return $formatted;
    }
}
