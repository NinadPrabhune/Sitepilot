<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Trusted User Service
 * Implements positive reinforcement for high-quality users
 */
class TrustedUserService
{
    /**
     * Check if user is trusted
     */
    public static function isUserTrusted(int $userId): array
    {
        $trustStatus = [
            'trusted' => false,
            'level' => 'standard',
            'benefits' => [],
            'requirements' => [],
            'current_metrics' => [],
            'next_review' => null,
        ];

        // Get user metrics
        $warningDensity = WarningOverrideService::getUserWarningDensity($userId, '30_days');
        $reasonPatterns = ReasonIntelligenceService::analyzeUserReasonPatterns($userId, '30_days');
        $escalationStatus = SoftLockEscalationService::getUserEscalationStatus($userId);

        $trustStatus['current_metrics'] = [
            'override_rate' => $warningDensity['override_rate'],
            'legitimate_rate' => $reasonPatterns['legitimate_rate'] ?? 0,
            'risk_level' => $warningDensity['risk_level'],
            'pending_escalations' => $escalationStatus['pending_requests'],
        ];

        // Check trust requirements
        $trustRequirements = self::getTrustRequirements();
        $metRequirements = [];

        foreach ($trustRequirements as $requirement => $threshold) {
            $metRequirements[$requirement] = self::checkRequirement($requirement, $threshold, $trustStatus['current_metrics']);
        }

        // Determine trust level
        $metCount = count(array_filter($metRequirements));
        
        if ($metCount >= 4) {
            $trustStatus['trusted'] = true;
            $trustStatus['level'] = 'trusted';
            $trustStatus['benefits'] = self::getTrustBenefits('trusted');
        } elseif ($metCount >= 2) {
            $trustStatus['level'] = 'intermediate';
            $trustStatus['benefits'] = self::getTrustBenefits('intermediate');
        } else {
            $trustStatus['level'] = 'standard';
            $trustStatus['benefits'] = self::getTrustBenefits('standard');
        }

        $trustStatus['requirements'] = $metRequirements;
        $trustStatus['next_review'] = now()->addDays(7);

        return $trustStatus;
    }

    /**
     * Get trust requirements
     */
    private static function getTrustRequirements(): array
    {
        return [
            'override_rate' => 30, // Maximum 30% override rate
            'legitimate_rate' => 80, // Minimum 80% legitimate reasons
            'risk_level' => 'low', // Must be low risk
            'no_escalations' => 0, // No pending escalations
            'minimum_entries' => 10, // Minimum 10 entries in period
        ];
    }

    /**
     * Check if requirement is met
     */
    private static function checkRequirement(string $requirement, $threshold, array $metrics): bool
    {
        return match($requirement) {
            'override_rate' => ($metrics['override_rate'] ?? 100) <= $threshold,
            'legitimate_rate' => ($metrics['legitimate_rate'] ?? 0) >= $threshold,
            'risk_level' => ($metrics['risk_level'] ?? 'high') === $threshold,
            'no_escalations' => ($metrics['pending_escalations'] ?? 1) <= $threshold,
            'minimum_entries' => true, // Would check actual entry count
            default => false,
        };
    }

    /**
     * Get trust benefits by level
     */
    private static function getTrustBenefits(string $level): array
    {
        return match($level) {
            'trusted' => [
                'reduced_confirmations' => 'Skip confirmation dialogs for common warnings',
                'fast_save_workflow' => 'Optimized save process with fewer steps',
                'auto_approve_minor' => 'Automatic approval for minor overrides',
                'extended_session' => 'Longer session timeouts',
                'priority_support' => 'Priority in support queue',
                'advanced_features' => 'Access to advanced reporting features',
            ],
            'intermediate' => [
                'fewer_confirmations' => 'Reduced confirmation dialogs',
                'improved_workflow' => 'Streamlined data entry process',
                'session_extension' => 'Extended session timeout',
            ],
            'standard' => [
                'standard_workflow' => 'Standard validation and confirmation process',
            ],
            default => [],
        };
    }

    /**
     * Update user trust status
     */
    public static function updateUserTrustStatus(int $userId): array
    {
        $trustStatus = self::isUserTrusted($userId);
        
        // Update user record
        User::where('id', $userId)->update([
            'trust_level' => $trustStatus['level'],
            'trust_review_date' => $trustStatus['next_review'],
        ]);

        // Log trust status change
        self::logTrustStatusChange($userId, $trustStatus);

        return $trustStatus;
    }

    /**
     * Log trust status change
     */
    private static function logTrustStatusChange(int $userId, array $trustStatus): array
    {
        $logEntry = [
            'user_id' => $userId,
            'trust_level' => $trustStatus['level'],
            'trusted' => $trustStatus['trusted'],
            'metrics' => json_encode($trustStatus['current_metrics']),
            'created_at' => now(),
        ];

        DB::table('user_trust_log')->insert($logEntry);

        return $logEntry;
    }

    /**
     * Get trust recommendations for user
     */
    public static function getTrustRecommendations(int $userId): array
    {
        $trustStatus = self::isUserTrusted($userId);
        $recommendations = [];

        if (!$trustStatus['trusted']) {
            // Show what needs improvement
            foreach ($trustStatus['requirements'] as $requirement => $met) {
                if (!$met) {
                    $recommendations[] = self::getRequirementRecommendation($requirement, $trustStatus['current_metrics']);
                }
            }
        } else {
            // Maintenance recommendations
            $recommendations[] = [
                'type' => 'maintenance',
                'title' => 'Maintain Trusted Status',
                'description' => 'Continue current performance to maintain trusted benefits',
                'tips' => [
                    'Keep override rate below 30%',
                    'Maintain high-quality reason documentation',
                    'Avoid escalations',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Get requirement-specific recommendation
     */
    private static function getRequirementRecommendation(string $requirement, array $metrics): array
    {
        return match($requirement) {
            'override_rate' => [
                'type' => 'improvement',
                'title' => 'Reduce Override Rate',
                'description' => "Current rate: {$metrics['override_rate']}%, Target: ≤30%",
                'actions' => [
                    'Use pre-save validation preview',
                    'Check historical data before entry',
                    'Take time to verify accuracy',
                ],
                'impact' => 'Improves data quality and reduces friction',
            ],
            'legitimate_rate' => [
                'type' => 'improvement',
                'title' => 'Improve Reason Quality',
                'description' => "Current rate: {$metrics['legitimate_rate']}%, Target: ≥80%",
                'actions' => [
                    'Provide specific operational details',
                    'Include actual conditions and circumstances',
                    'Avoid generic or vague reasons',
                ],
                'impact' => 'Better audit trail and process understanding',
            ],
            'risk_level' => [
                'type' => 'improvement',
                'title' => 'Achieve Low Risk Status',
                'description' => "Current risk: {$metrics['risk_level']}, Target: low",
                'actions' => [
                    'Reduce warning frequency',
                    'Improve data entry accuracy',
                    'Follow best practices',
                ],
                'impact' => 'Fewer restrictions and smoother workflow',
            ],
            'no_escalations' => [
                'type' => 'improvement',
                'title' => 'Avoid Escalations',
                'description' => "Current escalations: {$metrics['pending_escalations']}, Target: 0",
                'actions' => [
                    'Improve data quality',
                    'Follow validation guidelines',
                    'Seek help when unsure',
                ],
                'impact' => 'Maintain workflow independence',
            ],
            default => [
                'type' => 'general',
                'title' => 'General Improvement',
                'description' => 'Focus on overall data quality',
                'actions' => [
                    'Follow best practices',
                    'Seek training if needed',
                    'Ask for clarification',
                ],
            ],
        };
    }

    /**
     * Apply trust benefits to user experience
     */
    public static function applyTrustBenefits(int $userId, array $context): array
    {
        $trustStatus = self::isUserTrusted($userId);
        $benefits = [];

        if ($trustStatus['trusted']) {
            // Apply trusted user benefits
            $benefits = self::applyTrustedUserBenefits($context);
        } elseif ($trustStatus['level'] === 'intermediate') {
            // Apply intermediate benefits
            $benefits = self::applyIntermediateBenefits($context);
        }

        return $benefits;
    }

    /**
     * Apply trusted user benefits
     */
    private static function applyTrustedUserBenefits(array $context): array
    {
        $benefits = [];

        // Reduced confirmations
        if (isset($context['warnings'])) {
            $benefits['skip_confirmations'] = true;
            $benefits['auto_approve_warnings'] = array_filter($context['warnings'], function($warning) {
                return in_array($warning['type'], ['operator_mismatch', 'diesel_without_dpr']);
            });
        }

        // Fast save workflow
        $benefits['fast_save'] = true;
        $benefits['reduced_validation'] = true;

        // Auto-approve minor overrides
        if (isset($context['override_request'])) {
            $benefits['auto_approve'] = self::canAutoApprove($context['override_request']);
        }

        return $benefits;
    }

    /**
     * Apply intermediate benefits
     */
    private static function applyIntermediateBenefits(array $context): array
    {
        $benefits = [];

        // Fewer confirmations
        if (isset($context['warnings'])) {
            $benefits['reduced_confirmations'] = true;
        }

        // Improved workflow
        $benefits['improved_workflow'] = true;

        return $benefits;
    }

    /**
     * Check if override can be auto-approved
     */
    private static function canAutoApprove(array $overrideRequest): bool
    {
        $autoApprovableTypes = ['operator_mismatch', 'diesel_without_dpr'];
        
        return in_array($overrideRequest['warning_type'], $autoApprovableTypes) &&
               strlen($overrideRequest['reason']) > 20 &&
               !in_array(strtolower($overrideRequest['reason']), ['ok', 'fine', 'proceed']);
    }

    /**
     * Get team trust metrics
     */
    public static function getTeamTrustMetrics(array $userIds): array
    {
        $metrics = [
            'total_users' => count($userIds),
            'trusted_users' => 0,
            'intermediate_users' => 0,
            'standard_users' => 0,
            'trust_distribution' => [],
            'team_trust_score' => 0,
            'top_performers' => [],
            'improvement_needed' => [],
        ];

        $userScores = [];

        foreach ($userIds as $userId) {
            $trustStatus = self::isUserTrusted($userId);
            
            // Count by level
            switch ($trustStatus['level']) {
                case 'trusted':
                    $metrics['trusted_users']++;
                    break;
                case 'intermediate':
                    $metrics['intermediate_users']++;
                    break;
                default:
                    $metrics['standard_users']++;
                    break;
            }

            // Calculate user score
            $userScore = self::calculateUserTrustScore($trustStatus);
            $userScores[$userId] = $userScore;
        }

        // Calculate team trust score
        if (!empty($userScores)) {
            $metrics['team_trust_score'] = round(array_sum($userScores) / count($userScores), 1);
        }

        // Get top performers
        arsort($userScores);
        $metrics['top_performers'] = array_slice($userScores, 0, 5, true);

        // Get users needing improvement
        $metrics['improvement_needed'] = array_slice(array_reverse($userScores, true), 0, 5, true);

        // Trust distribution
        $metrics['trust_distribution'] = [
            'trusted' => $metrics['trusted_users'],
            'intermediate' => $metrics['intermediate_users'],
            'standard' => $metrics['standard_users'],
        ];

        return $metrics;
    }

    /**
     * Calculate user trust score
     */
    private static function calculateUserTrustScore(array $trustStatus): int
    {
        $score = 0;

        // Override rate scoring
        $overrideRate = $trustStatus['current_metrics']['override_rate'] ?? 100;
        $score += max(0, 100 - $overrideRate);

        // Legitimate rate scoring
        $legitimateRate = $trustStatus['current_metrics']['legitimate_rate'] ?? 0;
        $score += $legitimateRate;

        // Risk level scoring
        $riskLevel = $trustStatus['current_metrics']['risk_level'] ?? 'high';
        $riskScore = match($riskLevel) {
            'low' => 100,
            'medium' => 70,
            'high' => 40,
            default => 0,
        };
        $score += $riskScore;

        // Level bonus
        $levelBonus = match($trustStatus['level']) {
            'trusted' => 50,
            'intermediate' => 25,
            'standard' => 0,
            default => 0,
        };
        $score += $levelBonus;

        return round($score / 4); // Average of 4 components
    }

    /**
     * Schedule trust status review
     */
    public static function scheduleTrustReview(int $userId): string
    {
        $trustStatus = self::isUserTrusted($userId);
        $reviewDate = $trustStatus['next_review'];

        // Store review schedule
        DB::table('trust_review_schedule')->insert([
            'user_id' => $userId,
            'review_date' => $reviewDate,
            'current_level' => $trustStatus['level'],
            'created_at' => now(),
        ]);

        return $reviewDate;
    }

    /**
     * Process trust status reviews
     */
    public static function processTrustReviews(): array
    {
        $reviews = DB::table('trust_review_schedule')
                    ->where('review_date', '<=', now())
                    ->where('processed', false)
                    ->get();

        $processed = [];

        foreach ($reviews as $review) {
            $trustStatus = self::updateUserTrustStatus($review->user_id);
            
            $processed[] = [
                'user_id' => $review->user_id,
                'previous_level' => $review->current_level,
                'new_level' => $trustStatus['level'],
                'trusted' => $trustStatus['trusted'],
            ];

            // Mark as processed
            DB::table('trust_review_schedule')
              ->where('id', $review->id)
              ->update(['processed' => true, 'processed_at' => now()]);
        }

        return $processed;
    }
}
