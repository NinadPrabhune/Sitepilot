<?php

namespace App\Services;

/**
 * Soft Lock Escalation Service
 * Implements progressive restriction based on user behavior
 */
class SoftLockEscalationService
{
    /**
     * Check if user requires escalation for action
     */
    public static function checkEscalationRequired(int $userId, string $action, array $context = []): array
    {
        $escalation = [
            'required' => false,
            'level' => 'none',
            'reason' => '',
            'conditions' => [],
            'approver_id' => null,
        ];

        // Get user warning metrics
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        
        // Get user performance metrics
        $performanceMetrics = self::getUserPerformanceMetrics($userId);

        // Check escalation rules
        $escalationRules = self::getEscalationRules($action);
        
        foreach ($escalationRules as $rule) {
            if (self::evaluateRule($rule, $warningDensity, $performanceMetrics, $context)) {
                $escalation['required'] = true;
                $escalation['level'] = $rule['level'];
                $escalation['reason'] = $rule['reason'];
                $escalation['conditions'][] = $rule['condition'];
                
                // Set approver based on level
                $escalation['approver_id'] = self::getApprover($userId, $rule['level']);
                
                // Use highest level if multiple rules match
                if ($rule['level'] === 'manager') {
                    break;
                }
            }
        }

        return $escalation;
    }

    /**
     * Get escalation rules for action
     */
    private static function getEscalationRules(string $action): array
    {
        $baseRules = [
            // High override rate rules
            [
                'condition' => 'high_override_rate',
                'level' => 'supervisor',
                'reason' => 'High warning override rate detected',
                'threshold' => 60, // percentage
            ],
            [
                'condition' => 'very_high_override_rate',
                'level' => 'manager',
                'reason' => 'Very high warning override rate detected',
                'threshold' => 80, // percentage
            ],
            
            // Frequent specific warning types
            [
                'condition' => 'frequent_duplicate_diesel',
                'level' => 'supervisor',
                'reason' => 'Frequent duplicate diesel overrides',
                'threshold' => 3, // count in 30 days
            ],
            [
                'condition' => 'frequent_consumption_spike',
                'level' => 'manager',
                'reason' => 'Frequent high consumption overrides',
                'threshold' => 2, // count in 30 days
            ],
            
            // Recent escalation rules
            [
                'condition' => 'rapid_recent_overrides',
                'level' => 'supervisor',
                'reason' => 'Rapid increase in warning overrides',
                'threshold' => 5, // overrides in last 7 days
            ],
            
            // Quality score rules
            [
                'condition' => 'low_quality_score',
                'level' => 'supervisor',
                'reason' => 'Low data quality score',
                'threshold' => 70, // percentage
            ],
        ];

        // Action-specific rules
        $actionSpecificRules = match($action) {
            'dpr_create' => [
                [
                    'condition' => 'dpr_creation_quality_issues',
                    'level' => 'supervisor',
                    'reason' => 'Multiple DPR quality issues',
                    'threshold' => 3, // issues in last 10 DPRs
                ],
            ],
            'dpr_edit' => [
                [
                    'condition' => 'excessive_dpr_edits',
                    'level' => 'supervisor',
                    'reason' => 'Excessive DPR editing pattern',
                    'threshold' => 4, // average edits per DPR
                ],
            ],
            'diesel_entry' => [
                [
                    'condition' => 'diesel_quality_issues',
                    'level' => 'manager',
                    'reason' => 'Multiple diesel quality issues',
                    'threshold' => 4, // issues in last 20 entries
                ],
            ],
            default => [],
        };

        return array_merge($baseRules, $actionSpecificRules);
    }

    /**
     * Evaluate escalation rule
     */
    private static function evaluateRule(array $rule, array $warningDensity, array $performanceMetrics, array $context): bool
    {
        return match($rule['condition']) {
            'high_override_rate' => $warningDensity['override_rate'] >= $rule['threshold'],
            'very_high_override_rate' => $warningDensity['override_rate'] >= $rule['threshold'],
            'frequent_duplicate_diesel' => ($warningDensity['warning_types']['duplicate_diesel'] ?? 0) >= $rule['threshold'],
            'frequent_consumption_spike' => ($warningDensity['warning_types']['high_consumption_rate'] ?? 0) >= $rule['threshold'],
            'rapid_recent_overrides' => self::getRecentOverrideCount($warningDensity['user_id']) >= $rule['threshold'],
            'low_quality_score' => ($performanceMetrics['quality_score'] ?? 100) <= $rule['threshold'],
            'dpr_creation_quality_issues' => ($performanceMetrics['dpr_quality_issues'] ?? 0) >= $rule['threshold'],
            'excessive_dpr_edits' => ($performanceMetrics['avg_dpr_edits'] ?? 0) >= $rule['threshold'],
            'diesel_quality_issues' => ($performanceMetrics['diesel_quality_issues'] ?? 0) >= $rule['threshold'],
            default => false,
        };
    }

    /**
     * Get user performance metrics
     */
    private static function getUserPerformanceMetrics(int $userId): array
    {
        $metrics = [
            'quality_score' => 100,
            'dpr_quality_issues' => 0,
            'avg_dpr_edits' => 0,
            'diesel_quality_issues' => 0,
        ];

        // Calculate DPR quality issues
        $recentDprs = \App\Models\DailyProgressReport::where('created_by', $userId)
                                                 ->where('created_at', '>=', now()->subDays(10))
                                                 ->get();

        foreach ($recentDprs as $dpr) {
            if ($dpr->warning_override_count > 0) {
                $metrics['dpr_quality_issues']++;
            }
            
            // Get edit count from history
            $editCount = \App\Models\DprEditHistory::where('dpr_id', $dpr->id)
                                                      ->where('action', 'updated')
                                                      ->count();
            $metrics['avg_dpr_edits'] += $editCount;
        }

        if (count($recentDprs) > 0) {
            $metrics['avg_dpr_edits'] = round($metrics['avg_dpr_edits'] / count($recentDprs), 2);
        }

        // Calculate diesel quality issues
        $recentDiesel = \App\Models\DailyConsumptionMaster::where('created_by', $userId)
                                                           ->where('created_at', '>=', now()->subDays(20))
                                                           ->get();

        foreach ($recentDiesel as $diesel) {
            if ($diesel->warning_override_count > 0) {
                $metrics['diesel_quality_issues']++;
            }
        }

        // Calculate overall quality score
        $totalIssues = $metrics['dpr_quality_issues'] + $metrics['diesel_quality_issues'];
        $totalEntries = count($recentDprs) + count($recentDiesel);
        
        if ($totalEntries > 0) {
            $issueRate = ($totalIssues / $totalEntries) * 100;
            $metrics['quality_score'] = max(0, 100 - ($issueRate * 10));
        }

        return $metrics;
    }

    /**
     * Get recent override count
     */
    private static function getRecentOverrideCount(int $userId): int
    {
        return \Illuminate\Support\Facades\DB::table('warning_overrides')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    /**
     * Get approver for escalation level
     */
    private static function getApprover(int $userId, string $level): ?int
    {
        // Get user's supervisor/manager based on organization structure
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return null;
        }

        // This would integrate with your organization structure
        // For now, return a default approver based on level
        return match($level) {
            'supervisor' => self::getSupervisorId($userId),
            'manager' => self::getManagerId($userId),
            default => null,
        };
    }

    /**
     * Get supervisor ID
     */
    private static function getSupervisorId(int $userId): ?int
    {
        // This would integrate with your user hierarchy
        // For now, return admin user as default supervisor
        $adminUser = \App\Models\User::role('admin')->first();
        return $adminUser ? $adminUser->id : null;
    }

    /**
     * Get manager ID
     */
    private static function getManagerId(int $userId): ?int
    {
        // This would integrate with your user hierarchy
        // For now, return super admin user as default manager
        $superAdminUser = \App\Models\User::role('super admin')->first();
        return $superAdminUser ? $superAdminUser->id : null;
    }

    /**
     * Process escalated action
     */
    public static function processEscalatedAction(array $data, int $userId, array $escalation): array
    {
        // Create escalation record
        $escalationRecord = [
            'id' => uniqid('escalation_'),
            'user_id' => $userId,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'] ?? 'unknown',
            'entity_id' => $data['entity_id'] ?? null,
            'escalation_level' => $escalation['level'],
            'approver_id' => $escalation['approver_id'],
            'reason' => $escalation['reason'],
            'conditions' => json_encode($escalation['conditions']),
            'status' => 'pending_approval',
            'created_at' => now(),
            'data' => json_encode($data),
        ];

        // Store escalation record
        \Illuminate\Support\Facades\DB::table('escalation_requests')->insert($escalationRecord);

        // Notify approver (would integrate with notification system)
        self::notifyApprover($escalationRecord, $escalation['approver_id']);

        return [
            'success' => true,
            'escalation_id' => $escalationRecord['id'],
            'status' => 'pending_approval',
            'message' => 'Action escalated to ' . $escalation['level'] . ' for approval',
        ];
    }

    /**
     * Notify approver
     */
    private static function notifyApprover(array $escalation, int $approverId): void
    {
        // This would integrate with your notification system
        // For now, just log the notification
        \Illuminate\Support\Facades\Log::info('Escalation notification sent', [
            'escalation_id' => $escalation['id'],
            'approver_id' => $approverId,
            'level' => $escalation['escalation_level'],
            'reason' => $escalation['reason'],
        ]);
    }

    /**
     * Get user escalation status
     */
    public static function getUserEscalationStatus(int $userId): array
    {
        $status = [
            'current_level' => 'none',
            'pending_requests' => 0,
            'recent_escalations' => [],
            'escalation_history' => [],
            'restrictions' => [],
        ];

        // Get pending escalations
        $pendingEscalations = \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('user_id', $userId)
            ->where('status', 'pending_approval')
            ->get();

        $status['pending_requests'] = $pendingEscalations->count();

        // Get recent escalations
        $recentEscalations = \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentEscalations as $escalation) {
            $status['recent_escalations'][] = [
                'id' => $escalation->id,
                'action' => $escalation->action,
                'level' => $escalation->escalation_level,
                'reason' => $escalation->reason,
                'status' => $escalation->status,
                'created_at' => $escalation->created_at,
            ];
        }

        // Determine current restriction level
        if ($status['pending_requests'] > 0) {
            $highestLevel = 'supervisor';
            foreach ($pendingEscalations as $escalation) {
                if ($escalation->escalation_level === 'manager') {
                    $highestLevel = 'manager';
                    break;
                }
            }
            $status['current_level'] = $highestLevel;
        }

        // Get active restrictions
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        if ($warningDensity['risk_level'] === 'high') {
            $status['restrictions'][] = 'High warning override rate detected';
        }

        return $status;
    }

    /**
     * Approve escalated action
     */
    public static function approveEscalation(string $escalationId, int $approverId, string $comments = ''): array
    {
        $escalation = \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('id', $escalationId)
            ->where('status', 'pending_approval')
            ->first();

        if (!$escalation) {
            return [
                'success' => false,
                'error' => 'Escalation not found or already processed',
            ];
        }

        // Update escalation record
        \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('id', $escalationId)
            ->update([
                'status' => 'approved',
                'approver_id' => $approverId,
                'approved_at' => now(),
                'approver_comments' => $comments,
                'updated_at' => now(),
            ]);

        // Log approval
        \Illuminate\Support\Facades\Log::info('Escalation approved', [
            'escalation_id' => $escalationId,
            'approver_id' => $approverId,
            'comments' => $comments,
        ]);

        return [
            'success' => true,
            'message' => 'Escalation approved successfully',
        ];
    }

    /**
     * Reject escalated action
     */
    public static function rejectEscalation(string $escalationId, int $approverId, string $reason): array
    {
        $escalation = \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('id', $escalationId)
            ->where('status', 'pending_approval')
            ->first();

        if (!$escalation) {
            return [
                'success' => false,
                'error' => 'Escalation not found or already processed',
            ];
        }

        // Update escalation record
        \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->where('id', $escalationId)
            ->update([
                'status' => 'rejected',
                'approver_id' => $approverId,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'updated_at' => now(),
            ]);

        // Log rejection
        \Illuminate\Support\Facades\Log::info('Escalation rejected', [
            'escalation_id' => $escalationId,
            'approver_id' => $approverId,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'message' => 'Escalation rejected',
        ];
    }

    /**
     * Get team escalation metrics
     */
    public static function getTeamEscalationMetrics(array $userIds, string $period = '30_days'): array
    {
        $metrics = [
            'total_users' => count($userIds),
            'total_escalations' => 0,
            'pending_escalations' => 0,
            'escalation_by_level' => [
                'supervisor' => 0,
                'manager' => 0,
            ],
            'escalation_by_reason' => [],
            'users_with_restrictions' => [],
            'approval_rate' => 0,
        ];

        // Get escalations for team
        $teamEscalations = \Illuminate\Support\Facades\DB::table('escalation_requests')
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        foreach ($teamEscalations as $escalation) {
            $metrics['total_escalations']++;
            
            if ($escalation->status === 'pending_approval') {
                $metrics['pending_escalations']++;
            }
            
            $metrics['escalation_by_level'][$escalation->escalation_level]++;
            
            $reason = $escalation->reason;
            $metrics['escalation_by_reason'][$reason] = ($metrics['escalation_by_reason'][$reason] ?? 0) + 1;
        }

        // Calculate approval rate
        $approvedCount = $teamEscalations->where('status', 'approved')->count();
        if ($metrics['total_escalations'] > 0) {
            $metrics['approval_rate'] = round(($approvedCount / $metrics['total_escalations']) * 100, 2);
        }

        // Get users with restrictions
        foreach ($userIds as $userId) {
            $userStatus = self::getUserEscalationStatus($userId);
            if ($userStatus['current_level'] !== 'none' || !empty($userStatus['restrictions'])) {
                $metrics['users_with_restrictions'][] = [
                    'user_id' => $userId,
                    'level' => $userStatus['current_level'],
                    'restrictions' => $userStatus['restrictions'],
                ];
            }
        }

        return $metrics;
    }
}
