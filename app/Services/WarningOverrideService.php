<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use Illuminate\Support\Facades\DB;

/**
 * Warning Override Service
 * Tracks and manages warning overrides with required reasons
 */
class WarningOverrideService
{
    /**
     * Process warning override with reason requirement
     */
    public static function processOverride(array $data, int $userId): array
    {
        $override = [
            'id' => uniqid('override_'),
            'user_id' => $userId,
            'entity_type' => $data['entity_type'] ?? 'dpr',
            'entity_id' => $data['entity_id'] ?? null,
            'warning_type' => $data['warning_type'],
            'warning_message' => $data['warning_message'] ?? '',
            'reason' => $data['reason'] ?? '',
            'created_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Validate reason requirement
        $validation = self::validateOverrideReason($override);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
                'requires_reason' => true,
            ];
        }

        // Store override
        self::storeOverride($override);

        // Update user warning metrics
        self::updateUserWarningMetrics($userId, $override['warning_type']);

        return [
            'success' => true,
            'override_id' => $override['id'],
            'message' => 'Warning override recorded',
        ];
    }

    /**
     * Validate override reason
     */
    private static function validateOverrideReason(array $override): array
    {
        $validation = ['valid' => true, 'error' => ''];

        // Check if reason is required for this warning type
        $reasonRequired = self::isReasonRequired($override['warning_type']);
        
        if ($reasonRequired && empty(trim($override['reason']))) {
            $validation['valid'] = false;
            $validation['error'] = 'Reason is required to override this warning';
            return $validation;
        }

        // Validate reason quality
        if (!empty($override['reason'])) {
            $reasonQuality = self::validateReasonQuality($override['reason']);
            if (!$reasonQuality['valid']) {
                $validation['valid'] = false;
                $validation['error'] = $reasonQuality['error'];
                return $validation;
            }
        }

        return $validation;
    }

    /**
     * Check if reason is required for warning type
     */
    private static function isReasonRequired(string $warningType): bool
    {
        $reasonRequiredTypes = [
            'excessive_idle_hours',
            'operator_mismatch',
            'duplicate_diesel',
            'diesel_without_dpr',
            'high_consumption_rate',
            'suspicious_pattern',
            'timing_mismatch',
        ];

        return in_array($warningType, $reasonRequiredTypes);
    }

    /**
     * Validate reason quality
     */
    private static function validateReasonQuality(string $reason): array
    {
        $validation = ['valid' => true, 'error' => ''];

        // Minimum length
        if (strlen(trim($reason)) < 10) {
            $validation['valid'] = false;
            $validation['error'] = 'Reason must be at least 10 characters long';
            return $validation;
        }

        // Check for generic/reasonless reasons
        $genericReasons = ['ok', 'fine', 'proceed', 'continue', 'yes', 'confirmed', 'correct'];
        if (in_array(strtolower(trim($reason)), $genericReasons)) {
            $validation['valid'] = false;
            $validation['error'] = 'Please provide a specific reason for the override';
            return $validation;
        }

        return $validation;
    }

    /**
     * Store override record
     */
    private static function storeOverride(array $override): void
    {
        // Store in database (would use actual model in production)
        DB::table('warning_overrides')->insert([
            'id' => $override['id'],
            'user_id' => $override['user_id'],
            'entity_type' => $override['entity_type'],
            'entity_id' => $override['entity_id'],
            'warning_type' => $override['warning_type'],
            'warning_message' => $override['warning_message'],
            'reason' => $override['reason'],
            'created_at' => $override['created_at'],
            'ip_address' => $override['ip_address'],
            'user_agent' => $override['user_agent'],
        ]);
    }

    /**
     * Update user warning metrics
     */
    private static function updateUserWarningMetrics(int $userId, string $warningType): void
    {
        $today = now()->toDateString();
        
        // Get or create daily metrics
        $metrics = DB::table('user_warning_metrics')
                   ->where('user_id', $userId)
                   ->where('date', $today)
                   ->first();

        if ($metrics) {
            // Update existing
            DB::table('user_warning_metrics')
                ->where('user_id', $userId)
                ->where('date', $today)
                ->update([
                    'total_overrides' => $metrics->total_overrides + 1,
                    'warning_types' => json_encode(array_merge(
                        json_decode($metrics->warning_types, true) ?? [],
                        [$warningType]
                    )),
                    'updated_at' => now(),
                ]);
        } else {
            // Create new
            DB::table('user_warning_metrics')->insert([
                'user_id' => $userId,
                'date' => $today,
                'total_overrides' => 1,
                'warning_types' => json_encode([$warningType]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get user warning density
     */
    public static function getUserWarningDensity(int $userId, string $period = '30_days'): array
    {
        $dateRange = match($period) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            default => now()->subDays(30),
        };

        // Get DPRs created by user in period
        $dprsCreated = DailyProgressReport::where('created_by', $userId)
                                         ->where('created_at', '>=', $dateRange)
                                         ->count();

        // Get warnings overridden by user in period
        $warningsOverridden = DB::table('warning_overrides')
                              ->where('user_id', $userId)
                              ->where('created_at', '>=', $dateRange)
                              ->count();

        // Get warning types breakdown
        $warningTypes = DB::table('warning_overrides')
                          ->where('user_id', $userId)
                          ->where('created_at', '>=', $dateRange)
                          ->selectRaw('warning_type, COUNT(*) as count')
                          ->groupBy('warning_type')
                          ->pluck('count', 'warning_type')
                          ->toArray();

        $density = [
            'user_id' => $userId,
            'period' => $period,
            'dprs_created' => $dprsCreated,
            'warnings_overridden' => $warningsOverridden,
            'override_rate' => $dprsCreated > 0 ? round(($warningsOverridden / $dprsCreated) * 100, 2) : 0,
            'warning_types' => $warningTypes,
            'risk_level' => self::calculateRiskLevel($warningsOverridden, $dprsCreated),
        ];

        return $density;
    }

    /**
     * Calculate user risk level
     */
    private static function calculateRiskLevel(int $overrides, int $dprs): string
    {
        if ($dprs === 0) {
            return 'low';
        }

        $overrideRate = ($overrides / $dprs) * 100;

        if ($overrideRate > 60) {
            return 'high';
        } elseif ($overrideRate > 30) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Check if user requires escalation
     */
    public static function requiresEscalation(int $userId): array
    {
        $warningDensity = self::getUserWarningDensity($userId, '30_days');
        $escalation = [
            'required' => false,
            'level' => 'none',
            'reason' => '',
            'conditions' => [],
        ];

        // High override rate
        if ($warningDensity['override_rate'] > 60) {
            $escalation['required'] = true;
            $escalation['level'] = 'supervisor';
            $escalation['reason'] = 'High warning override rate: ' . $warningDensity['override_rate'] . '%';
            $escalation['conditions'][] = 'Override rate > 60%';
        }

        // Frequent specific warning types
        if (isset($warningDensity['warning_types']['duplicate_diesel']) && 
            $warningDensity['warning_types']['duplicate_diesel'] > 3) {
            $escalation['required'] = true;
            $escalation['level'] = 'supervisor';
            $escalation['reason'] = 'Frequent duplicate diesel overrides';
            $escalation['conditions'][] = 'Duplicate diesel overrides > 3';
        }

        // Rapid escalation in recent period
        $recentDensity = self::getUserWarningDensity($userId, '7_days');
        if ($recentDensity['override_rate'] > 80 && $recentDensity['warnings_overridden'] > 5) {
            $escalation['required'] = true;
            $escalation['level'] = 'manager';
            $escalation['reason'] = 'Rapid increase in warning overrides';
            $escalation['conditions'][] = 'Recent override rate > 80%';
        }

        return $escalation;
    }

    /**
     * Get team warning metrics
     */
    public static function getTeamWarningMetrics(array $userIds, string $period = '30_days'): array
    {
        $metrics = [];
        
        foreach ($userIds as $userId) {
            $metrics[$userId] = self::getUserWarningDensity($userId, $period);
        }

        // Calculate team averages
        $teamMetrics = [
            'total_users' => count($userIds),
            'total_dprs' => array_sum(array_column($metrics, 'dprs_created')),
            'total_overrides' => array_sum(array_column($metrics, 'warnings_overridden')),
            'average_override_rate' => 0,
            'risk_distribution' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'top_warning_types' => [],
            'users_requiring_escalation' => [],
        ];

        if (count($metrics) > 0) {
            $teamMetrics['average_override_rate'] = round(array_sum(array_column($metrics, 'override_rate')) / count($metrics), 2);

            // Risk distribution
            foreach ($metrics as $metric) {
                $teamMetrics['risk_distribution'][$metric['risk_level']]++;
            }

            // Top warning types
            $allWarningTypes = [];
            foreach ($metrics as $metric) {
                foreach ($metric['warning_types'] as $type => $count) {
                    $allWarningTypes[$type] = ($allWarningTypes[$type] ?? 0) + $count;
                }
            }
            arsort($allWarningTypes);
            $teamMetrics['top_warning_types'] = array_slice($allWarningTypes, 0, 5, true);

            // Users requiring escalation
            foreach ($userIds as $userId) {
                $escalation = self::requiresEscalation($userId);
                if ($escalation['required']) {
                    $teamMetrics['users_requiring_escalation'][] = [
                        'user_id' => $userId,
                        'level' => $escalation['level'],
                        'reason' => $escalation['reason'],
                    ];
                }
            }
        }

        return $teamMetrics;
    }

    /**
     * Generate override reason suggestions
     */
    public static function getReasonSuggestions(string $warningType): array
    {
        $suggestions = match($warningType) {
            'excessive_idle_hours' => [
                'Machine was actually idle due to weather conditions',
                'Waiting for materials to arrive',
                'Equipment maintenance during operation',
                'Shift changeover period',
            ],
            'operator_mismatch' => [
                'Operator names not available at time of entry',
                'Temporary operator assigned',
                'Names will be updated later',
                'Multiple operators shared duties',
            ],
            'duplicate_diesel' => [
                'Additional fuel required due to extended work',
                'Previous entry was incorrect',
                'Multiple fuel supplies used',
                'Fuel consumption split across shifts',
            ],
            'diesel_without_dpr' => [
                'DPR will be created later',
                'Fuel entry for different machine',
                'Administrative entry for inventory',
                'Fuel used for maintenance activities',
            ],
            'high_consumption_rate' => [
                'Heavy load conditions',
                'Extended operating hours',
                'Difficult terrain or conditions',
                'Equipment performance issues',
            ],
            default => [
                'Business requirement justification',
                'Data entry correction needed',
                'Special circumstances apply',
                'Approved deviation from standard',
            ],
        };

        return $suggestions;
    }

    /**
     * Validate override before processing
     */
    public static function validateOverrideRequest(array $data, int $userId): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check if user is allowed to override
        $escalation = self::requiresEscalation($userId);
        if ($escalation['required']) {
            $validation['valid'] = false;
            $validation['errors'][] = [
                'field' => 'user_permission',
                'message' => 'User requires escalation approval: ' . $escalation['reason'],
                'escalation_level' => $escalation['level'],
            ];
            return $validation;
        }

        // Check warning type validity
        $validTypes = [
            'excessive_idle_hours',
            'operator_mismatch', 
            'duplicate_diesel',
            'diesel_without_dpr',
            'high_consumption_rate',
            'suspicious_pattern',
            'timing_mismatch',
        ];

        if (!in_array($data['warning_type'], $validTypes)) {
            $validation['valid'] = false;
            $validation['errors'][] = [
                'field' => 'warning_type',
                'message' => 'Invalid warning type',
            ];
        }

        return $validation;
    }
}
