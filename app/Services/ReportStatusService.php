<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Report Status Service
 * Manages draft vs finalized report states to prevent drift
 */
class ReportStatusService
{
    /**
     * Create report with status
     */
    public static function createReport(array $reportData, string $status = 'draft'): array
    {
        $report = [
            'id' => uniqid('report_'),
            'type' => $reportData['type'] ?? 'daily',
            'date' => $reportData['date'] ?? now()->toDateString(),
            'status' => $status,
            'created_at' => now(),
            'created_by' => $reportData['created_by'] ?? null,
            'data' => $reportData['data'] ?? [],
            'metadata' => [
                'version' => 1,
                'last_modified' => now(),
                'change_count' => 0,
                'finalized_at' => null,
                'finalized_by' => null,
            ],
        ];

        if ($status === 'finalized') {
            $report['metadata']['finalized_at'] = now();
            $report['metadata']['finalized_by'] = $reportData['created_by'] ?? null;
        }

        return $report;
    }

    /**
     * Check if report can be modified
     */
    public static function canModifyReport(array $report): bool
    {
        return $report['status'] === 'draft';
    }

    /**
     * Update report data
     */
    public static function updateReport(array $report, array $newData, int $updatedBy): array
    {
        if (!self::canModifyReport($report)) {
            throw new \Exception('Cannot modify finalized report');
        }

        $report['data'] = array_merge($report['data'], $newData);
        $report['metadata']['last_modified'] = now();
        $report['metadata']['change_count']++;
        $report['metadata']['version']++;

        return $report;
    }

    /**
     * Finalize report
     */
    public static function finalizeReport(array $report, int $finalizedBy): array
    {
        if ($report['status'] === 'finalized') {
            throw new \Exception('Report is already finalized');
        }

        $report['status'] = 'finalized';
        $report['metadata']['finalized_at'] = now();
        $report['metadata']['finalized_by'] = $finalizedBy;

        return $report;
    }

    /**
     * Get report status summary
     */
    public static function getReportStatusSummary(array $report): array
    {
        return [
            'status' => $report['status'],
            'can_modify' => self::canModifyReport($report),
            'created_at' => $report['created_at'],
            'last_modified' => $report['metadata']['last_modified'],
            'change_count' => $report['metadata']['change_count'],
            'version' => $report['metadata']['version'],
            'finalized_at' => $report['metadata']['finalized_at'],
            'finalized_by' => $report['metadata']['finalized_by'],
            'is_locked' => $report['status'] === 'finalized',
        ];
    }

    /**
     * Validate report transition
     */
    public static function validateTransition(array $report, string $newStatus): array
    {
        $validation = [
            'allowed' => true,
            'reason' => '',
            'requirements' => [],
        ];

        $currentStatus = $report['status'];

        // Define allowed transitions
        $allowedTransitions = [
            'draft' => ['draft', 'finalized'],
            'finalized' => ['finalized'], // Can't go back to draft
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            $validation['allowed'] = false;
            $validation['reason'] = "Cannot transition from {$currentStatus} to {$newStatus}";
            return $validation;
        }

        // Check requirements for finalization
        if ($newStatus === 'finalized') {
            $requirements = self::getFinalizationRequirements($report);
            $validation['requirements'] = $requirements;
            
            if (!empty($requirements['missing'])) {
                $validation['allowed'] = false;
                $validation['reason'] = 'Report does not meet finalization requirements';
            }
        }

        return $validation;
    }

    /**
     * Get finalization requirements
     */
    private static function getFinalizationRequirements(array $report): array
    {
        $requirements = [
            'required' => [],
            'missing' => [],
            'optional' => [],
        ];

        // Basic requirements
        $requirements['required'] = [
            'data' => 'Report data must be present',
            'created_by' => 'Report must have a creator',
        ];

        // Check if data is present
        if (empty($report['data'])) {
            $requirements['missing'][] = 'data';
        }

        if (empty($report['created_by'])) {
            $requirements['missing'][] = 'created_by';
        }

        // Type-specific requirements
        switch ($report['type']) {
            case 'daily':
                $requirements['required']['dpr_count'] = 'At least one DPR required';
                $requirements['required']['date'] = 'Date must be specified';
                
                if (empty($report['data']['dpr_count']) || $report['data']['dpr_count'] < 1) {
                    $requirements['missing'][] = 'dpr_count';
                }
                
                if (empty($report['date'])) {
                    $requirements['missing'][] = 'date';
                }
                break;

            case 'weekly':
            case 'monthly':
                $requirements['required']['period'] = 'Period must be specified';
                $requirements['required']['summary'] = 'Summary data required';
                break;
        }

        // Optional but recommended
        $requirements['optional'] = [
            'review_notes' => 'Review notes for audit trail',
            'approvals' => 'Management approvals if required',
        ];

        return $requirements;
    }

    /**
     * Get report change history
     */
    public static function getChangeHistory(array $report): array
    {
        $history = [
            'created' => [
                'timestamp' => $report['created_at'],
                'user' => $report['created_by'],
                'action' => 'Report created',
                'version' => 1,
            ],
        ];

        if ($report['metadata']['change_count'] > 0) {
            $history['modified'] = [
                'timestamp' => $report['metadata']['last_modified'],
                'action' => 'Report modified',
                'version' => $report['metadata']['version'],
                'change_count' => $report['metadata']['change_count'],
            ];
        }

        if ($report['status'] === 'finalized') {
            $history['finalized'] = [
                'timestamp' => $report['metadata']['finalized_at'],
                'user' => $report['metadata']['finalized_by'],
                'action' => 'Report finalized',
                'version' => $report['metadata']['version'],
            ];
        }

        return $history;
    }

    /**
     * Check for report conflicts
     */
    public static function checkForConflicts(array $report, array $existingReports): array
    {
        $conflicts = [];

        foreach ($existingReports as $existing) {
            if ($existing['id'] === $report['id']) {
                continue; // Skip self
            }

            // Check for same date and type
            if ($existing['date'] === $report['date'] && $existing['type'] === $report['type']) {
                $conflicts[] = [
                    'type' => 'duplicate_report',
                    'existing_id' => $existing['id'],
                    'message' => 'Report already exists for this date and type',
                    'resolution' => 'Update existing report or choose different parameters',
                ];
            }

            // Check for overlapping periods (for weekly/monthly reports)
            if (in_array($report['type'], ['weekly', 'monthly']) && $existing['type'] === $report['type']) {
                $overlap = self::checkPeriodOverlap($report, $existing);
                if ($overlap) {
                    $conflicts[] = [
                        'type' => 'period_overlap',
                        'existing_id' => $existing['id'],
                        'message' => 'Report period overlaps with existing report',
                        'resolution' => 'Adjust report period or update existing report',
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check period overlap
     */
    private static function checkPeriodOverlap(array $report1, array $report2): bool
    {
        // Simplified overlap check - would need actual period logic
        return $report1['date'] === $report2['date'];
    }

    /**
     * Get report statistics
     */
    public static function getReportStatistics(array $reports): array
    {
        $stats = [
            'total_reports' => count($reports),
            'draft_count' => 0,
            'finalized_count' => 0,
            'by_type' => [],
            'by_status' => [
                'draft' => 0,
                'finalized' => 0,
            ],
            'recent_activity' => [],
            'change_frequency' => [
                'avg_changes' => 0,
                'max_changes' => 0,
            ],
        ];

        $totalChanges = 0;
        $maxChanges = 0;

        foreach ($reports as $report) {
            // Count by status
            $stats['by_status'][$report['status']]++;
            
            if ($report['status'] === 'draft') {
                $stats['draft_count']++;
            } else {
                $stats['finalized_count']++;
            }

            // Count by type
            $type = $report['type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;

            // Track changes
            $changes = $report['metadata']['change_count'] ?? 0;
            $totalChanges += $changes;
            $maxChanges = max($maxChanges, $changes);

            // Recent activity
            $lastModified = $report['metadata']['last_modified'] ?? $report['created_at'];
            $stats['recent_activity'][] = [
                'report_id' => $report['id'],
                'type' => $report['type'],
                'status' => $report['status'],
                'last_modified' => $lastModified,
            ];
        }

        // Calculate averages
        if (count($reports) > 0) {
            $stats['change_frequency']['avg_changes'] = round($totalChanges / count($reports), 2);
            $stats['change_frequency']['max_changes'] = $maxChanges;
        }

        // Sort recent activity
        usort($stats['recent_activity'], function ($a, $b) {
            return strtotime($b['last_modified']) - strtotime($a['last_modified']);
        });

        $stats['recent_activity'] = array_slice($stats['recent_activity'], 0, 10);

        return $stats;
    }

    /**
     * Generate report recommendations
     */
    public static function generateRecommendations(array $reports): array
    {
        $recommendations = [];
        $stats = self::getReportStatistics($reports);

        // High draft count recommendation
        if ($stats['draft_count'] > $stats['finalized_count']) {
            $recommendations[] = [
                'type' => 'process',
                'priority' => 'medium',
                'title' => 'Finalize Pending Reports',
                'message' => 'There are more draft reports than finalized ones',
                'suggestions' => [
                    'Review and finalize pending reports',
                    'Set up regular report finalization schedule',
                    'Consider automatic finalization for routine reports',
                ],
            ];
        }

        // High change frequency recommendation
        if ($stats['change_frequency']['avg_changes'] > 3) {
            $recommendations[] = [
                'type' => 'data_quality',
                'priority' => 'low',
                'title' => 'Review Report Creation Process',
                'message' => 'Reports are being modified frequently on average',
                'suggestions' => [
                    'Improve pre-report validation',
                    'Provide report templates',
                    'Train users on report requirements',
                ],
            ];
        }

        // Stale drafts recommendation
        $staleDrafts = 0;
        foreach ($reports as $report) {
            if ($report['status'] === 'draft') {
                $created = strtotime($report['created_at']);
                $daysOld = (time() - $created) / (24 * 60 * 60);
                if ($daysOld > 7) {
                    $staleDrafts++;
                }
            }
        }

        if ($staleDrafts > 0) {
            $recommendations[] = [
                'type' => 'maintenance',
                'priority' => 'low',
                'title' => 'Clean Up Stale Drafts',
                'message' => "{$staleDrafts} draft reports are more than 7 days old",
                'suggestions' => [
                    'Review and finalize or delete stale drafts',
                    'Set up automatic cleanup of old drafts',
                    'Notify users about pending drafts',
                ],
            ];
        }

        return $recommendations;
    }
}
