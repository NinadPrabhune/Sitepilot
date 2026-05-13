<?php

namespace App\Services;

/**
 * User Coaching Service
 * Provides intelligent coaching hints to improve user behavior
 */
class UserCoachingService
{
    /**
     * Get coaching hints for user based on their behavior patterns
     */
    public static function getUserCoachingHints(int $userId, string $context = 'general'): array
    {
        $coaching = [
            'hints' => [],
            'tips' => [],
            'recommendations' => [],
            'progress' => [],
        ];

        // Get user behavior metrics
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        $reasonPatterns = ReasonIntelligenceService::analyzeUserReasonPatterns($userId, '30_days');
        $escalationStatus = SoftLockEscalationService::getUserEscalationStatus($userId);

        // Generate coaching based on metrics
        $coaching['hints'] = self::generateBehavioralHints($warningDensity, $reasonPatterns, $escalationStatus);
        $coaching['tips'] = self::generateActionableTips($warningDensity, $reasonPatterns);
        $coaching['recommendations'] = self::generateRecommendations($warningDensity, $reasonPatterns, $escalationStatus);
        $coaching['progress'] = self::generateProgressTracking($userId);

        return $coaching;
    }

    /**
     * Generate behavioral hints
     */
    private static function generateBehavioralHints(array $warningDensity, array $reasonPatterns, array $escalationStatus): array
    {
        $hints = [];

        // High override rate coaching
        if ($warningDensity['override_rate'] > 60) {
            $hints[] = [
                'type' => 'override_frequency',
                'priority' => 'high',
                'title' => 'Reduce Override Frequency',
                'message' => "Your override rate is {$warningDensity['override_rate']}%, which is higher than average",
                'coaching' => [
                    'Before saving, double-check your entries',
                    'Use the pre-submit validation preview',
                    'Review typical values for this machinery',
                ],
                'impact' => 'Reducing overrides will improve data quality and reduce escalation',
            ];
        }

        // Low reason quality coaching
        if ($reasonPatterns['legitimate_rate'] < 70) {
            $hints[] = [
                'type' => 'reason_quality',
                'priority' => 'high',
                'title' => 'Improve Reason Quality',
                'message' => 'Your reasons could be more specific and helpful',
                'coaching' => [
                    'Include specific details (what, why, when)',
                    'Avoid generic terms like "ok" or "adjusted"',
                    'Mention actual operational conditions',
                ],
                'examples' => [
                    'Instead of: "Machine issue"',
                    'Use: "Hydraulic pump failure - maintenance team called"',
                ],
            ];
        }

        // Specific warning type coaching
        foreach ($warningDensity['warning_types'] as $type => $count) {
            if ($count > 2) {
                $hints[] = self::getSpecificWarningTypeCoaching($type, $count);
            }
        }

        // Escalation risk coaching
        if ($escalationStatus['current_level'] !== 'none') {
            $hints[] = [
                'type' => 'escalation_risk',
                'priority' => 'high',
                'title' => 'Avoid Escalation',
                'message' => 'Your current behavior may trigger escalation',
                'coaching' => [
                    'Focus on accuracy over speed',
                    'Take time to verify data before entry',
                    'Ask for help if unsure about correct values',
                ],
                'consequences' => 'Escalation requires supervisor approval and slows down workflow',
            ];
        }

        return $hints;
    }

    /**
     * Get specific warning type coaching
     */
    private static function getSpecificWarningTypeCoaching(string $warningType, int $count): array
    {
        return match($warningType) {
            'excessive_idle_hours' => [
                'type' => 'idle_hours',
                'priority' => 'medium',
                'title' => 'Optimize Idle Time',
                'message' => "You've had {$count} excessive idle warnings",
                'coaching' => [
                    'Plan work to minimize machine idle time',
                    'Coordinate material delivery in advance',
                    'Schedule maintenance during non-productive hours',
                ],
                'tip' => 'Check historical patterns for typical idle hours',
            ],
            'operator_mismatch' => [
                'type' => 'operator_data',
                'priority' => 'medium',
                'title' => 'Accurate Operator Information',
                'message' => "You've had {$count} operator mismatch warnings",
                'coaching' => [
                    'Get operator names before starting work',
                    'Update operator information promptly when changes occur',
                    'Verify operator count matches names provided',
                ],
                'tip' => 'Keep a list of regular operators handy',
            ],
            'duplicate_diesel' => [
                'type' => 'diesel_entries',
                'priority' => 'medium',
                'title' => 'Avoid Duplicate Diesel Entries',
                'message' => "You've had {$count} duplicate diesel warnings",
                'coaching' => [
                    'Check existing diesel entries before adding new ones',
                    'Update existing entries instead of creating duplicates',
                    'Review diesel entries at end of shift',
                ],
                'tip' => 'Use the search function to check for existing entries',
            ],
            default => [
                'type' => 'general',
                'priority' => 'low',
                'title' => 'General Data Quality',
                'message' => "You've had {$count} warnings of this type",
                'coaching' => [
                    'Review entry patterns for this warning type',
                    'Ask for clarification if unsure about requirements',
                    'Take time to verify data accuracy',
                ],
            ],
        };
    }

    /**
     * Generate actionable tips
     */
    private static function generateActionableTips(array $warningDensity, array $reasonPatterns): array
    {
        $tips = [];

        // Pre-save validation tip
        if ($warningDensity['override_rate'] > 30) {
            $tips[] = [
                'type' => 'pre_save_validation',
                'title' => 'Use Pre-Save Preview',
                'description' => 'Always check the calculation preview before saving',
                'steps' => [
                    'Enter all data fields',
                    'Review the calculation preview',
                    'Check for warnings and recommendations',
                    'Make corrections before final save',
                ],
                'benefit' => 'Reduces need for corrections and overrides',
            ];
        }

        // Historical data tip
        $tips[] = [
            'type' => 'historical_reference',
            'title' => 'Use Historical Data as Guide',
            'description' => 'Check typical values before entering data',
            'steps' => [
                'Look at yesterday\'s entries for the same machine',
                'Review weekly averages for patterns',
                'Use suggested values from system',
            ],
            'benefit' => 'Improves accuracy and reduces warnings',
        ];

        // Reason documentation tip
        if ($reasonPatterns['legitimate_rate'] < 80) {
            $tips[] = [
                'type' => 'reason_documentation',
                'title' => 'Document Specific Reasons',
                'description' => 'Provide detailed, specific reasons for overrides',
                'examples' => [
                    'Good: "Waiting for concrete delivery - truck delayed by traffic"',
                    'Poor: "delay"',
                ],
                'benefit' => 'Better audit trail and process understanding',
            ];
        }

        return $tips;
    }

    /**
     * Generate recommendations
     */
    private static function generateRecommendations(array $warningDensity, array $reasonPatterns, array $escalationStatus): array
    {
        $recommendations = [];

        // Training recommendations
        if ($warningDensity['override_rate'] > 50) {
            $recommendations[] = [
                'type' => 'training',
                'priority' => 'high',
                'title' => 'Data Entry Training Recommended',
                'description' => 'Your override rate suggests additional training would be beneficial',
                'suggested_training' => [
                    'DPR data entry best practices',
                    'Understanding warning system',
                    'Historical data usage',
                ],
                'expected_outcome' => 'Reduce override rate by 30-40%',
            ];
        }

        // Process improvement recommendations
        if (isset($reasonPatterns['category_distribution']['VALID_OPERATIONAL']) && 
            $reasonPatterns['category_distribution']['VALID_OPERATIONAL'] > 5) {
            $recommendations[] = [
                'type' => 'process',
                'priority' => 'medium',
                'title' => 'Process Improvement Opportunity',
                'description' => 'Frequent operational delays suggest process optimization',
                'suggested_actions' => [
                    'Review material delivery schedules',
                    'Optimize equipment maintenance planning',
                    'Improve coordination between teams',
                ],
                'expected_outcome' => 'Reduce operational delays and warnings',
            ];
        }

        // Quality improvement recommendations
        if ($reasonPatterns['legitimate_rate'] < 60) {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'high',
                'title' => 'Quality Improvement Plan',
                'description' => 'Focus on improving data quality and reason documentation',
                'action_steps' => [
                    'Review reason quality guidelines',
                    'Practice writing specific reasons',
                    'Get feedback on entry quality',
                ],
                'expected_outcome' => 'Improve legitimate reason rate to >80%',
            ];
        }

        return $recommendations;
    }

    /**
     * Generate progress tracking
     */
    private static function generateProgressTracking(int $userId): array
    {
        $progress = [
            'current_metrics' => [],
            'trends' => [],
            'goals' => [],
            'achievements' => [],
        ];

        // Get current metrics
        $currentMetrics = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        $progress['current_metrics'] = [
            'override_rate' => $currentMetrics['override_rate'],
            'risk_level' => $currentMetrics['risk_level'],
            'total_overrides' => $currentMetrics['warnings_overridden'],
        ];

        // Calculate trends (compare with previous period)
        $previousMetrics = WarningOverrideService::getUserWarningDensity($userId, '60_days');
        if ($previousMetrics['warnings_overridden'] > 0) {
            $previousRate = $previousMetrics['override_rate'];
            $trend = $currentMetrics['override_rate'] - $previousRate;
            $progress['trends']['override_rate'] = [
                'current' => $currentMetrics['override_rate'],
                'previous' => $previousRate,
                'trend' => $trend > 0 ? 'increasing' : ($trend < 0 ? 'decreasing' : 'stable'),
                'change' => abs($trend),
            ];
        }

        // Set goals
        $progress['goals'] = [
            'target_override_rate' => 30,
            'target_legitimate_rate' => 80,
            'target_risk_level' => 'low',
        ];

        // Check achievements
        if ($currentMetrics['override_rate'] <= 30) {
            $progress['achievements'][] = [
                'type' => 'override_rate',
                'title' => 'Override Rate Target Met',
                'description' => 'Override rate is at or below target',
                'date_achieved' => now()->toDateString(),
            ];
        }

        if ($currentMetrics['risk_level'] === 'low') {
            $progress['achievements'][] = [
                'type' => 'risk_level',
                'title' => 'Low Risk Status',
                'description' => 'User behavior is low risk',
                'date_achieved' => now()->toDateString(),
            ];
        }

        return $progress;
    }

    /**
     * Get contextual coaching hint
     */
    public static function getContextualCoaching(int $userId, string $action, array $context = []): array
    {
        $coaching = [
            'show_hint' => false,
            'hint' => '',
            'type' => 'info',
            'priority' => 'low',
        ];

        // Get user metrics
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '7_days');

        // Context-specific coaching
        switch ($action) {
            case 'dpr_create':
                if ($warningDensity['override_rate'] > 50) {
                    $coaching = [
                        'show_hint' => true,
                        'hint' => 'Take extra time to verify all entries. Use the pre-save preview to check calculations.',
                        'type' => 'warning',
                        'priority' => 'high',
                    ];
                }
                break;

            case 'warning_override':
                $coaching = [
                    'show_hint' => true,
                    'hint' => 'Provide specific details about why this override is necessary. Include operational conditions.',
                    'type' => 'info',
                    'priority' => 'medium',
                ];
                break;

            case 'diesel_entry':
                if (isset($context['machinery_id'])) {
                    $suggestions = HistoricalSuggestionService::getDieselSuggestions($context);
                    if (!empty($suggestions['quantity'])) {
                        $coaching = [
                            'show_hint' => true,
                            'hint' => 'Typical diesel quantity for this machine: ' . $suggestions['quantity'][0]['recommended'] . 'L',
                            'type' => 'info',
                            'priority' => 'low',
                        ];
                    }
                }
                break;
        }

        return $coaching;
    }

    /**
     * Get coaching summary for dashboard
     */
    public static function getCoachingSummary(int $userId): array
    {
        $coachingHints = self::getUserCoachingHints($userId);
        
        $summary = [
            'overall_score' => 0,
            'priority_issues' => 0,
            'improvement_areas' => [],
            'strengths' => [],
            'next_steps' => [],
        ];

        // Calculate overall score
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        $reasonPatterns = ReasonIntelligenceService::analyzeUserReasonPatterns($userId, '30_days');
        
        // Score components
        $overrideScore = max(0, 100 - $warningDensity['override_rate']);
        $reasonScore = $reasonPatterns['legitimate_rate'];
        $riskScore = $warningDensity['risk_level'] === 'low' ? 100 : ($warningDensity['risk_level'] === 'medium' ? 70 : 40);
        
        $summary['overall_score'] = round(($overrideScore + $reasonScore + $riskScore) / 3, 1);

        // Priority issues
        foreach ($coachingHints['hints'] as $hint) {
            if ($hint['priority'] === 'high') {
                $summary['priority_issues']++;
                $summary['improvement_areas'][] = $hint['title'];
            }
        }

        // Strengths
        if ($warningDensity['override_rate'] <= 30) {
            $summary['strengths'][] = 'Low override rate';
        }
        
        if ($reasonPatterns['legitimate_rate'] >= 80) {
            $summary['strengths'][] = 'High quality reason documentation';
        }

        if ($warningDensity['risk_level'] === 'low') {
            $summary['strengths'][] = 'Low risk behavior';
        }

        // Next steps
        if ($summary['priority_issues'] > 0) {
            $summary['next_steps'][] = 'Address high-priority coaching hints';
        }
        
        if ($warningDensity['override_rate'] > 30) {
            $summary['next_steps'][] = 'Focus on reducing override rate';
        }
        
        if ($reasonPatterns['legitimate_rate'] < 80) {
            $summary['next_steps'][] = 'Improve reason quality and specificity';
        }

        return $summary;
    }

    /**
     * Generate adaptive coaching based on user improvement
     */
    public static function generateAdaptiveCoaching(int $userId): array
    {
        $adaptiveCoaching = [
            'level' => 'standard',
            'intensity' => 'normal',
            'focus_areas' => [],
            'rewards' => [],
        ];

        // Get user progress
        $progress = self::generateProgressTracking($userId);
        $currentRate = $progress['current_metrics']['override_rate'];

        // Determine coaching level
        if ($currentRate <= 20) {
            $adaptiveCoaching['level'] = 'advanced';
            $adaptiveCoaching['intensity'] = 'minimal';
            $adaptiveCoaching['rewards'][] = 'Reduced validation friction';
            $adaptiveCoaching['rewards'][] = 'Faster save workflow';
        } elseif ($currentRate <= 40) {
            $adaptiveCoaching['level'] = 'intermediate';
            $adaptiveCoaching['intensity'] = 'normal';
            $adaptiveCoaching['rewards'][] = 'Standard workflow with tips';
        } else {
            $adaptiveCoaching['level'] = 'basic';
            $adaptiveCoaching['intensity'] = 'high';
            $adaptiveCoaching['focus_areas'][] = 'Data entry accuracy';
            $adaptiveCoaching['focus_areas'][] = 'Reason documentation';
        }

        return $adaptiveCoaching;
    }
}
