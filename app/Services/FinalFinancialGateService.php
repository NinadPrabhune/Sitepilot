<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;

/**
 * Final Financial Gate Service
 * Provides safety net validation before financial commitment
 */
class FinalFinancialGateService
{
    /**
     * Validate before financial posting
     */
    public static function validateBeforeFinancialPosting(array $data, int $userId): array
    {
        $validation = [
            'approved' => true,
            'block_reason' => '',
            'warnings' => [],
            'requirements' => [],
            'escalation_needed' => false,
        ];

        // Get entity details
        $entityType = $data['entity_type'] ?? 'dpr';
        $entityId = $data['entity_id'] ?? null;

        if (!$entityId) {
            $validation['approved'] = false;
            $validation['block_reason'] = 'Entity ID required for financial posting';
            return $validation;
        }

        // Check for critical warnings
        $criticalWarnings = self::getCriticalWarnings($entityType, $entityId);
        
        if (!empty($criticalWarnings)) {
            $validation['warnings'] = $criticalWarnings;
            
            // Check if any warnings require approval
            $approvalRequired = self::checkApprovalRequired($criticalWarnings);
            
            if ($approvalRequired['required']) {
                $validation['approved'] = false;
                $validation['block_reason'] = $approvalRequired['reason'];
                $validation['requirements'] = $approvalRequired['requirements'];
                $validation['escalation_needed'] = true;
                
                // Create escalation request
                self::createFinancialEscalation($data, $userId, $approvalRequired);
            }
        }

        // Check user trust level
        $trustStatus = TrustedUserService::isUserTrusted($userId);
        
        if (!$trustStatus['trusted'] && !empty($criticalWarnings)) {
            $validation['approved'] = false;
            $validation['block_reason'] = 'Financial posting requires trusted user status when warnings exist';
            $validation['requirements'][] = 'User must achieve trusted status';
        }

        // Check for recent high-risk overrides
        $recentOverrides = self::getRecentHighRiskOverrides($userId);
        if ($recentOverrides['count'] > 3) {
            $validation['approved'] = false;
            $validation['block_reason'] = 'Too many recent high-risk overrides';
            $validation['requirements'][] = 'Wait 24 hours before financial posting';
        }

        return $validation;
    }

    /**
     * Get critical warnings for entity
     */
    private static function getCriticalWarnings(string $entityType, int $entityId): array
    {
        $warnings = [];

        switch ($entityType) {
            case 'dpr':
                $warnings = self::getDprCriticalWarnings($entityId);
                break;
            case 'diesel':
                $warnings = self::getDieselCriticalWarnings($entityId);
                break;
            case 'ledger':
                $warnings = self::getLedgerCriticalWarnings($entityId);
                break;
        }

        return $warnings;
    }

    /**
     * Get DPR critical warnings
     */
    private static function getDprCriticalWarnings(int $dprId): array
    {
        $warnings = [];
        $dpr = DailyProgressReport::find($dprId);
        
        if (!$dpr) {
            return $warnings;
        }

        // Check warning overrides
        $overrides = DB::table('warning_overrides')
                      ->where('entity_type', 'dpr')
                      ->where('entity_id', $dprId)
                      ->get();

        foreach ($overrides as $override) {
            $severity = self::getWarningSeverity($override->warning_type);
            
            if ($severity === 'high') {
                $warnings[] = [
                    'type' => $override->warning_type,
                    'message' => $override->warning_message,
                    'reason' => $override->reason,
                    'created_at' => $override->created_at,
                    'severity' => $severity,
                ];
            }
        }

        // Check for data quality issues
        if ($dpr->warning_override_count > 5) {
            $warnings[] = [
                'type' => 'high_override_count',
                'message' => 'DPR has excessive warning overrides',
                'count' => $dpr->warning_override_count,
                'severity' => 'high',
            ];
        }

        return $warnings;
    }

    /**
     * Get diesel critical warnings
     */
    private static function getDieselCriticalWarnings(int $dieselId): array
    {
        $warnings = [];
        $diesel = \App\Models\DailyConsumptionMaster::find($dieselId);
        
        if (!$diesel) {
            return $warnings;
        }

        // Check warning overrides
        $overrides = DB::table('warning_overrides')
                      ->where('entity_type', 'diesel')
                      ->where('entity_id', $dieselId)
                      ->get();

        foreach ($overrides as $override) {
            $severity = self::getWarningSeverity($override->warning_type);
            
            if ($severity === 'high') {
                $warnings[] = [
                    'type' => $override->warning_type,
                    'message' => $override->warning_message,
                    'reason' => $override->reason,
                    'created_at' => $override->created_at,
                    'severity' => $severity,
                ];
            }
        }

        // Check for consumption anomalies
        $consumptionRate = self::calculateConsumptionRate($diesel);
        if ($consumptionRate > 100) { // Very high consumption
            $warnings[] = [
                'type' => 'extreme_consumption',
                'message' => "Extreme consumption rate: {$consumptionRate}L/hr",
                'rate' => $consumptionRate,
                'severity' => 'high',
            ];
        }

        return $warnings;
    }

    /**
     * Get ledger critical warnings
     */
    private static function getLedgerCriticalWarnings(int $ledgerId): array
    {
        $warnings = [];
        $ledger = MachineryLedger::find($ledgerId);
        
        if (!$ledger) {
            return $warnings;
        }

        // Check for unusual amounts
        if ($ledger->amount > 100000) { // Very high amount
            $warnings[] = [
                'type' => 'high_amount',
                'message' => "Unusually high amount: ₹{$ledger->amount}",
                'amount' => $ledger->amount,
                'severity' => 'high',
            ];
        }

        // Check for recent reversals
        $recentReversals = MachineryLedger::where('machinery_id', $ledger->machinery_id)
                                         ->where('date', '>=', now()->subDays(7))
                                         ->where('is_reversal', true)
                                         ->count();
        
        if ($recentReversals > 2) {
            $warnings[] = [
                'type' => 'frequent_reversals',
                'message' => "Recent reversals: {$recentReversals}",
                'count' => $recentReversals,
                'severity' => 'high',
            ];
        }

        return $warnings;
    }

    /**
     * Get warning severity
     */
    private static function getWarningSeverity(string $warningType): string
    {
        $severityMap = [
            'excessive_idle_hours' => 'medium',
            'operator_mismatch' => 'low',
            'duplicate_diesel' => 'medium',
            'diesel_without_dpr' => 'low',
            'high_consumption_rate' => 'high',
            'suspicious_pattern' => 'high',
            'timing_mismatch' => 'medium',
        ];

        return $severityMap[$warningType] ?? 'medium';
    }

    /**
     * Check if approval is required
     */
    private static function checkApprovalRequired(array $warnings): array
    {
        $approval = [
            'required' => false,
            'reason' => '',
            'requirements' => [],
        ];

        $highSeverityCount = 0;
        $suspiciousTypes = ['suspicious_pattern', 'high_consumption_rate', 'extreme_consumption'];

        foreach ($warnings as $warning) {
            if ($warning['severity'] === 'high') {
                $highSeverityCount++;
            }

            if (in_array($warning['type'], $suspiciousTypes)) {
                $approval['required'] = true;
                $approval['reason'] = 'Suspicious activity detected';
                $approval['requirements'][] = 'Manager approval required for suspicious patterns';
                return $approval;
            }
        }

        if ($highSeverityCount > 2) {
            $approval['required'] = true;
            $approval['reason'] = 'Multiple high-severity warnings';
            $approval['requirements'][] = 'Supervisor approval required for multiple warnings';
        }

        return $approval;
    }

    /**
     * Create financial escalation
     */
    private static function createFinancialEscalation(array $data, int $userId, array $approvalRequired): void
    {
        $escalation = [
            'id' => uniqid('financial_escalation_'),
            'user_id' => $userId,
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'escalation_type' => 'financial_gate',
            'reason' => $approvalRequired['reason'],
            'requirements' => json_encode($approvalRequired['requirements']),
            'status' => 'pending_approval',
            'created_at' => now(),
            'data' => json_encode($data),
        ];

        DB::table('financial_escalations')->insert($escalation);

        // Log escalation
        \Illuminate\Support\Facades\Log::warning('Financial gate escalation created', [
            'escalation_id' => $escalation['id'],
            'user_id' => $userId,
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'reason' => $approvalRequired['reason'],
        ]);
    }

    /**
     * Get recent high-risk overrides
     */
    private static function getRecentHighRiskOverrides(int $userId): array
    {
        $highRiskTypes = ['suspicious_pattern', 'high_consumption_rate', 'duplicate_diesel'];
        
        $count = DB::table('warning_overrides')
                  ->where('user_id', $userId)
                  ->where('created_at', '>=', now()->subHours(24))
                  ->whereIn('warning_type', $highRiskTypes)
                  ->count();

        return [
            'count' => $count,
            'types' => $highRiskTypes,
        ];
    }

    /**
     * Calculate consumption rate
     */
    private static function calculateConsumptionRate($diesel): float
    {
        // Get corresponding DPR for working hours
        $dpr = DailyProgressReport::where('machinery_id', $diesel->machinery_id)
                                 ->where('date', $diesel->date)
                                 ->first();
        
        if (!$dpr) {
            return 0;
        }

        $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
        
        if ($workingHours <= 0) {
            return 0;
        }

        return $diesel->quantity / $workingHours;
    }

    /**
     * Process financial posting
     */
    public static function processFinancialPosting(array $data, int $userId): array
    {
        // First validate
        $validation = self::validateBeforeFinancialPosting($data, $userId);
        
        if (!$validation['approved']) {
            return [
                'success' => false,
                'blocked' => true,
                'reason' => $validation['block_reason'],
                'requirements' => $validation['requirements'],
                'warnings' => $validation['warnings'],
            ];
        }

        // Process the financial posting
        try {
            $result = self::executeFinancialPosting($data, $userId);
            
            return [
                'success' => true,
                'blocked' => false,
                'result' => $result,
                'warnings' => $validation['warnings'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'blocked' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute financial posting
     */
    private static function executeFinancialPosting(array $data, int $userId): array
    {
        // This would integrate with your actual financial posting logic
        // For now, simulate the posting
        
        $posting = [
            'id' => uniqid('posting_'),
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'amount' => $data['amount'] ?? 0,
            'posted_by' => $userId,
            'posted_at' => now(),
            'status' => 'posted',
        ];

        // Store posting record
        DB::table('financial_postings')->insert($posting);

        // Log successful posting
        \Illuminate\Support\Facades\Log::info('Financial posting completed', [
            'posting_id' => $posting['id'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'user_id' => $userId,
        ]);

        return $posting;
    }

    /**
     * Get financial gate statistics
     */
    public static function getFinancialGateStats(string $period = '30_days'): array
    {
        $dateRange = match($period) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $stats = [
            'total_postings' => 0,
            'blocked_postings' => 0,
            'escalations' => 0,
            'approval_rate' => 0,
            'common_block_reasons' => [],
            'high_risk_periods' => [],
        ];

        // Get posting statistics
        $postings = DB::table('financial_postings')
                    ->where('created_at', '>=', $dateRange)
                    ->get();

        $stats['total_postings'] = $postings->count();

        // Get blocked postings
        $blocked = DB::table('financial_gate_blocks')
                   ->where('created_at', '>=', $dateRange)
                   ->get();

        $stats['blocked_postings'] = $blocked->count();

        // Get escalations
        $escalations = DB::table('financial_escalations')
                        ->where('created_at', '>=', $dateRange)
                        ->get();

        $stats['escalations'] = $escalations->count();

        // Calculate approval rate
        if ($stats['total_postings'] + $stats['blocked_postings'] > 0) {
            $stats['approval_rate'] = round(($stats['total_postings'] / ($stats['total_postings'] + $stats['blocked_postings'])) * 100, 2);
        }

        // Common block reasons
        $blockReasons = $blocked->pluck('reason')->toArray();
        $reasonCounts = array_count_values($blockReasons);
        arsort($reasonCounts);
        $stats['common_block_reasons'] = array_slice($reasonCounts, 0, 5, true);

        return $stats;
    }

    /**
     * Get financial gate health check
     */
    public static function getFinancialGateHealth(): array
    {
        $health = [
            'overall_health' => 'good',
            'score' => 100,
            'issues' => [],
            'recommendations' => [],
        ];

        $stats = self::getFinancialGateStats('30_days');

        // Check approval rate
        if ($stats['approval_rate'] < 80) {
            $health['score'] -= 20;
            $health['issues'][] = 'Low approval rate: ' . $stats['approval_rate'] . '%';
            $health['recommendations'][] = 'Review warning patterns and user training';
        }

        // Check escalation rate
        $escalationRate = $stats['total_postings'] > 0 ? ($stats['escalations'] / $stats['total_postings']) * 100 : 0;
        if ($escalationRate > 10) {
            $health['score'] -= 15;
            $health['issues'][] = 'High escalation rate: ' . round($escalationRate, 1) . '%';
            $health['recommendations'][] = 'Investigate common escalation reasons';
        }

        // Determine overall health
        if ($health['score'] >= 90) {
            $health['overall_health'] = 'excellent';
        } elseif ($health['score'] >= 80) {
            $health['overall_health'] = 'good';
        } elseif ($health['score'] >= 70) {
            $health['overall_health'] = 'fair';
        } else {
            $health['overall_health'] = 'poor';
        }

        return $health;
    }
}
