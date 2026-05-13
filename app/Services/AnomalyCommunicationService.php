<?php

namespace App\Services;

/**
 * Anomaly Communication Service
 * Converts scary anomaly messages to helpful guidance
 */
class AnomalyCommunicationService
{
    /**
     * Format anomaly message for user consumption
     */
    public static function formatAnomalyMessage(array $anomaly): array
    {
        $formatted = [
            'type' => 'guidance',
            'severity' => $anomaly['severity'] ?? 'medium',
            'title' => '',
            'message' => '',
            'impact' => '',
            'recommendations' => [],
            'actions' => [],
            'tone' => 'helpful',
        ];

        switch ($anomaly['anomaly_type']) {
            case 'excessive_edits':
                $formatted = self::formatExcessiveEditsMessage($anomaly, $formatted);
                break;
            
            case 'consumption_spike':
                $formatted = self::formatConsumptionSpikeMessage($anomaly, $formatted);
                break;
            
            case 'suspicious_pattern':
                $formatted = self::formatSuspiciousPatternMessage($anomaly, $formatted);
                break;
            
            case 'timing_mismatch':
                $formatted = self::formatTimingMismatchMessage($anomaly, $formatted);
                break;
            
            default:
                $formatted = self::formatGenericAnomalyMessage($anomaly, $formatted);
                break;
        }

        return $formatted;
    }

    /**
     * Format excessive edits message
     */
    private static function formatExcessiveEditsMessage(array $anomaly, array $formatted): array
    {
        $editCount = $anomaly['anomaly_data']['edit_count'] ?? 0;
        
        $formatted['title'] = 'Multiple Changes Detected';
        $formatted['message'] = "This DPR has been edited {$editCount} times";
        $formatted['impact'] = 'Frequent changes may indicate uncertainty or data entry issues';
        $formatted['recommendations'] = [
            'Review all changes before finalizing',
            'Consider if the information is accurate',
            'Ensure all calculations are correct',
        ];
        $formatted['actions'] = [
            [
                'label' => 'Review Edit History',
                'action' => 'show_history',
                'type' => 'primary',
            ],
            [
                'label' => 'Continue Anyway',
                'action' => 'proceed',
                'type' => 'secondary',
            ],
        ];

        if ($editCount > 10) {
            $formatted['severity'] = 'high';
            $formatted['recommendations'][] = 'Consider creating a new DPR if significant changes are needed';
        }

        return $formatted;
    }

    /**
     * Format consumption spike message
     */
    private static function formatConsumptionSpikeMessage(array $anomaly, array $formatted): array
    {
        $consumptionRate = $anomaly['anomaly_data']['consumption_rate'] ?? 0;
        $workingHours = $anomaly['anomaly_data']['working_hours'] ?? 0;
        $dieselQuantity = $anomaly['anomaly_data']['diesel_quantity'] ?? 0;
        
        $formatted['title'] = 'High Fuel Consumption';
        $formatted['message'] = "Fuel consumption rate is " . round($consumptionRate, 1) . " liters per hour";
        $formatted['impact'] = 'This is higher than typical consumption patterns';
        $formatted['recommendations'] = [
            'Verify the fuel quantity entered is correct',
            'Check if machine was working under heavy load',
            'Consider if there were any fuel leaks or issues',
        ];
        $formatted['actions'] = [
            [
                'label' => 'Verify Fuel Entry',
                'action' => 'edit_fuel',
                'type' => 'primary',
            ],
            [
                'label' => 'Mark as Normal',
                'action' => 'acknowledge',
                'type' => 'secondary',
            ],
        ];

        if ($consumptionRate > 100) {
            $formatted['severity'] = 'high';
            $formatted['recommendations'][] = 'This consumption level is unusual - please double-check all entries';
        }

        return $formatted;
    }

    /**
     * Format suspicious pattern message
     */
    private static function formatSuspiciousPatternMessage(array $anomaly, array $formatted): array
    {
        $timeframe = $anomaly['anomaly_data']['timeframe'] ?? 'recent period';
        $editCount = $anomaly['anomaly_data']['recent_edit_count'] ?? 0;
        
        $formatted['title'] = 'Frequent Changes Detected';
        $formatted['message'] = "{$editCount} changes made in the last {$timeframe}";
        $formatted['impact'] = 'Rapid changes may indicate data entry uncertainty';
        $formatted['recommendations'] = [
            'Take a moment to review all information',
            'Ensure you have all necessary details before making changes',
            'Consider if a break might help ensure accuracy',
        ];
        $formatted['actions'] = [
            [
                'label' => 'Review All Data',
                'action' => 'review',
                'type' => 'primary',
            ],
            [
                'label' => 'Continue Editing',
                'action' => 'proceed',
                'type' => 'secondary',
            ],
        ];

        return $formatted;
    }

    /**
     * Format timing mismatch message
     */
    private static function formatTimingMismatchMessage(array $anomaly, array $formatted): array
    {
        $formatted['title'] = 'Timing Inconsistency';
        $formatted['message'] = 'There may be a timing inconsistency in the data';
        $formatted['impact'] = 'This could affect cost calculations or reporting';
        $formatted['recommendations'] = [
            'Verify all timestamps and readings',
            'Check if work was performed during recorded hours',
            'Ensure diesel entries match machine usage',
        ];
        $formatted['actions'] = [
            [
                'label' => 'Review Timeline',
                'action' => 'review_timeline',
                'type' => 'primary',
            ],
            [
                'label' => 'Mark as Correct',
                'action' => 'acknowledge',
                'type' => 'secondary',
            ],
        ];

        return $formatted;
    }

    /**
     * Format generic anomaly message
     */
    private static function formatGenericAnomalyMessage(array $anomaly, array $formatted): array
    {
        $formatted['title'] = 'Data Pattern Detected';
        $formatted['message'] = 'The system detected an unusual pattern in the data';
        $formatted['impact'] = 'This may require attention to ensure data accuracy';
        $formatted['recommendations'] = [
            'Review the entered data for accuracy',
            'Consider if any corrections are needed',
            'Ensure all information is complete',
        ];
        $formatted['actions'] = [
            [
                'label' => 'Review Data',
                'action' => 'review',
                'type' => 'primary',
            ],
            [
                'label' => 'Continue',
                'action' => 'proceed',
                'type' => 'secondary',
            ],
        ];

        return $formatted;
    }

    /**
     * Generate behavioral insights
     */
    public static function generateBehavioralInsights(array $anomalies): array
    {
        $insights = [
            'patterns' => [],
            'recommendations' => [],
            'overall_score' => 100,
        ];

        $patternCounts = [];
        foreach ($anomalies as $anomaly) {
            $type = $anomaly['anomaly_type'];
            $patternCounts[$type] = ($patternCounts[$type] ?? 0) + 1;
        }

        // Analyze patterns
        foreach ($patternCounts as $type => $count) {
            $insights['patterns'][] = [
                'type' => $type,
                'count' => $count,
                'frequency' => $count / max(1, count($anomalies)),
                'impact' => self::getPatternImpact($type),
            ];
        }

        // Generate recommendations based on patterns
        if (isset($patternCounts['excessive_edits']) && $patternCounts['excessive_edits'] > 2) {
            $insights['recommendations'][] = [
                'type' => 'process',
                'priority' => 'high',
                'title' => 'Improve Data Entry Process',
                'message' => 'Multiple DPRs require many edits - consider improving the data entry workflow',
                'suggestions' => [
                    'Provide better pre-submit validation',
                    'Offer data entry templates',
                    'Conduct user training on common issues',
                ],
            ];
        }

        if (isset($patternCounts['consumption_spike']) && $patternCounts['consumption_spike'] > 1) {
            $insights['recommendations'][] = [
                'type' => 'data_quality',
                'priority' => 'medium',
                'title' => 'Review Fuel Consumption Patterns',
                'message' => 'Multiple instances of unusual fuel consumption detected',
                'suggestions' => [
                    'Provide consumption rate guidelines',
                    'Add fuel efficiency monitoring',
                    'Review machine maintenance schedules',
                ],
            ];
        }

        // Calculate overall score
        $scoreDeductions = 0;
        foreach ($patternCounts as $type => $count) {
            $scoreDeductions += self::getScoreDeduction($type) * $count;
        }
        $insights['overall_score'] = max(0, 100 - $scoreDeductions);

        return $insights;
    }

    /**
     * Get pattern impact level
     */
    private static function getPatternImpact(string $type): string
    {
        $impacts = [
            'excessive_edits' => 'medium',
            'consumption_spike' => 'medium',
            'suspicious_pattern' => 'high',
            'timing_mismatch' => 'medium',
        ];

        return $impacts[$type] ?? 'low';
    }

    /**
     * Get score deduction for pattern type
     */
    private static function getScoreDeduction(string $type): int
    {
        $deductions = [
            'excessive_edits' => 5,
            'consumption_spike' => 3,
            'suspicious_pattern' => 8,
            'timing_mismatch' => 4,
        ];

        return $deductions[$type] ?? 2;
    }

    /**
     * Create user-friendly anomaly summary
     */
    public static function createAnomalySummary(array $anomalies): array
    {
        $summary = [
            'total_anomalies' => count($anomalies),
            'critical_count' => 0,
            'high_count' => 0,
            'medium_count' => 0,
            'low_count' => 0,
            'categories' => [],
            'actions_needed' => [],
            'overall_health' => 'good',
        ];

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'medium';
            $type = $anomaly['anomaly_type'];
            
            // Count by severity
            switch ($severity) {
                case 'critical':
                    $summary['critical_count']++;
                    break;
                case 'high':
                    $summary['high_count']++;
                    break;
                case 'medium':
                    $summary['medium_count']++;
                    break;
                case 'low':
                    $summary['low_count']++;
                    break;
            }

            // Group by type
            if (!isset($summary['categories'][$type])) {
                $summary['categories'][$type] = [
                    'count' => 0,
                    'severity' => $severity,
                    'description' => self::getAnomalyDescription($type),
                ];
            }
            $summary['categories'][$type]['count']++;
        }

        // Determine actions needed
        if ($summary['critical_count'] > 0) {
            $summary['actions_needed'][] = 'Immediate attention required for critical issues';
        }
        
        if ($summary['high_count'] > 2) {
            $summary['actions_needed'][] = 'Review high-priority anomalies';
        }

        // Overall health
        if ($summary['critical_count'] > 0 || $summary['high_count'] > 3) {
            $summary['overall_health'] = 'poor';
        } elseif ($summary['high_count'] > 0 || $summary['medium_count'] > 5) {
            $summary['overall_health'] = 'fair';
        } else {
            $summary['overall_health'] = 'good';
        }

        return $summary;
    }

    /**
     * Get anomaly description
     */
    private static function getAnomalyDescription(string $type): string
    {
        $descriptions = [
            'excessive_edits' => 'Multiple changes to DPR data',
            'consumption_spike' => 'Unusual fuel consumption patterns',
            'suspicious_pattern' => 'Rapid or unusual data changes',
            'timing_mismatch' => 'Inconsistent timing between related data',
        ];

        return $descriptions[$type] ?? 'Unusual data pattern detected';
    }
}
