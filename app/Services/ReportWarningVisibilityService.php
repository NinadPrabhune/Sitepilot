<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use Illuminate\Support\Facades\DB;

/**
 * Report Warning Visibility Service
 * Adds warning indicators to reports to prevent silent data degradation
 */
class ReportWarningVisibilityService
{
    /**
     * Get report with warning indicators
     */
    public static function getReportWithWarnings(array $reportData): array
    {
        $reportWithWarnings = $reportData;
        
        // Calculate warning metrics
        $warningMetrics = self::calculateReportWarningMetrics($reportData);
        
        // Add warning indicators to report
        $reportWithWarnings['warnings'] = [
            'total_count' => $warningMetrics['total_count'],
            'severity_breakdown' => $warningMetrics['severity_breakdown'],
            'type_breakdown' => $warningMetrics['type_breakdown'],
            'affected_entities' => $warningMetrics['affected_entities'],
            'quality_score' => $warningMetrics['quality_score'],
            'requires_review' => $warningMetrics['requires_review'],
        ];
        
        return $reportWithWarnings;
    }

    /**
     * Calculate warning metrics for report
     */
    private static function calculateReportWarningMetrics(array $reportData): array
    {
        $metrics = [
            'total_count' => 0,
            'severity_breakdown' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
            ],
            'type_breakdown' => [],
            'affected_entities' => [],
            'quality_score' => 100,
            'requires_review' => false,
        ];

        // Check DPRs in report period
        if (isset($reportData['date'])) {
            $dprs = DailyProgressReport::where('date', $reportData['date'])->get();
            
            foreach ($dprs as $dpr) {
                $dprWarnings = self::getDprWarnings($dpr);
                
                $metrics['total_count'] += $dprWarnings['count'];
                $metrics['severity_breakdown']['low'] += $dprWarnings['severity']['low'];
                $metrics['severity_breakdown']['medium'] += $dprWarnings['severity']['medium'];
                $metrics['severity_breakdown']['high'] += $dprWarnings['severity']['high'];
                
                // Add to type breakdown
                foreach ($dprWarnings['types'] as $type => $count) {
                    $metrics['type_breakdown'][$type] = ($metrics['type_breakdown'][$type] ?? 0) + $count;
                }
                
                if ($dprWarnings['count'] > 0) {
                    $metrics['affected_entities'][] = [
                        'type' => 'dpr',
                        'id' => $dpr->id,
                        'machinery_name' => $dpr->machinery->name ?? 'Unknown',
                        'warning_count' => $dprWarnings['count'],
                    ];
                }
            }
        }

        // Check diesel entries in report period
        if (isset($reportData['date'])) {
            $dieselEntries = DailyConsumptionMaster::where('date', $reportData['date'])->get();
            
            foreach ($dieselEntries as $diesel) {
                $dieselWarnings = self::getDieselWarnings($diesel);
                
                $metrics['total_count'] += $dieselWarnings['count'];
                $metrics['severity_breakdown']['low'] += $dieselWarnings['severity']['low'];
                $metrics['severity_breakdown']['medium'] += $dieselWarnings['severity']['medium'];
                $metrics['severity_breakdown']['high'] += $dieselWarnings['severity']['high'];
                
                foreach ($dieselWarnings['types'] as $type => $count) {
                    $metrics['type_breakdown'][$type] = ($metrics['type_breakdown'][$type] ?? 0) + $count;
                }
                
                if ($dieselWarnings['count'] > 0) {
                    $metrics['affected_entities'][] = [
                        'type' => 'diesel',
                        'id' => $diesel->id,
                        'machinery_name' => $diesel->machinery->name ?? 'Unknown',
                        'warning_count' => $dieselWarnings['count'],
                    ];
                }
            }
        }

        // Calculate quality score
        $metrics['quality_score'] = self::calculateQualityScore($metrics);
        
        // Determine if review is required
        $metrics['requires_review'] = $metrics['quality_score'] < 80 || $metrics['severity_breakdown']['high'] > 0;

        return $metrics;
    }

    /**
     * Get warnings for DPR
     */
    private static function getDprWarnings(DailyProgressReport $dpr): array
    {
        $warnings = [
            'count' => 0,
            'severity' => ['low' => 0, 'medium' => 0, 'high' => 0],
            'types' => [],
        ];

        // Check warning overrides from database
        $overrides = DB::table('warning_overrides')
                      ->where('entity_type', 'dpr')
                      ->where('entity_id', $dpr->id)
                      ->get();

        foreach ($overrides as $override) {
            $warnings['count']++;
            $severity = self::getWarningSeverity($override->warning_type);
            $warnings['severity'][$severity]++;
            $warnings['types'][$override->warning_type] = ($warnings['types'][$override->warning_type] ?? 0) + 1;
        }

        // Also check stored warning count
        if ($dpr->warning_override_count > 0) {
            $warnings['count'] = max($warnings['count'], $dpr->warning_override_count);
        }

        return $warnings;
    }

    /**
     * Get warnings for diesel entry
     */
    private static function getDieselWarnings(DailyConsumptionMaster $diesel): array
    {
        $warnings = [
            'count' => 0,
            'severity' => ['low' => 0, 'medium' => 0, 'high' => 0],
            'types' => [],
        ];

        // Check warning overrides from database
        $overrides = DB::table('warning_overrides')
                      ->where('entity_type', 'diesel')
                      ->where('entity_id', $diesel->id)
                      ->get();

        foreach ($overrides as $override) {
            $warnings['count']++;
            $severity = self::getWarningSeverity($override->warning_type);
            $warnings['severity'][$severity]++;
            $warnings['types'][$override->warning_type] = ($warnings['types'][$override->warning_type] ?? 0) + 1;
        }

        // Also check stored warning count
        if ($diesel->warning_override_count > 0) {
            $warnings['count'] = max($warnings['count'], $diesel->warning_override_count);
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
     * Calculate quality score based on warnings
     */
    private static function calculateQualityScore(array $metrics): int
    {
        $score = 100;

        // Deduct points for warnings
        $score -= $metrics['severity_breakdown']['low'] * 2;
        $score -= $metrics['severity_breakdown']['medium'] * 5;
        $score -= $metrics['severity_breakdown']['high'] * 15;

        // Additional deduction for high warning count
        if ($metrics['total_count'] > 10) {
            $score -= 10;
        } elseif ($metrics['total_count'] > 5) {
            $score -= 5;
        }

        return max(0, $score);
    }

    /**
     * Format warning indicators for UI display
     */
    public static function formatWarningIndicators(array $reportWithWarnings): array
    {
        $warnings = $reportWithWarnings['warnings'];
        
        $indicators = [
            'badge' => self::getWarningBadge($warnings),
            'summary' => self::getWarningSummary($warnings),
            'details' => self::getWarningDetails($warnings),
            'actions' => self::getWarningActions($warnings),
        ];

        return $indicators;
    }

    /**
     * Get warning badge
     */
    private static function getWarningBadge(array $warnings): array
    {
        $badge = [
            'show' => $warnings['total_count'] > 0,
            'count' => $warnings['total_count'],
            'color' => 'green',
            'icon' => 'check-circle',
        ];

        if ($warnings['severity_breakdown']['high'] > 0) {
            $badge['color'] = 'red';
            $badge['icon'] = 'exclamation-triangle';
        } elseif ($warnings['severity_breakdown']['medium'] > 0) {
            $badge['color'] = 'yellow';
            $badge['icon'] = 'exclamation-circle';
        } elseif ($warnings['total_count'] > 0) {
            $badge['color'] = 'blue';
            $badge['icon'] = 'info-circle';
        }

        return $badge;
    }

    /**
     * Get warning summary
     */
    private static function getWarningSummary(array $warnings): string
    {
        if ($warnings['total_count'] === 0) {
            return 'No warnings - data quality is good';
        }

        $summary = "{$warnings['total_count']} warning" . ($warnings['total_count'] > 1 ? 's' : '');
        
        if ($warnings['severity_breakdown']['high'] > 0) {
            $summary .= ' (including ' . $warnings['severity_breakdown']['high'] . ' high priority)';
        }

        return $summary;
    }

    /**
     * Get warning details
     */
    private static function getWarningDetails(array $warnings): array
    {
        $details = [];

        // Severity breakdown
        if ($warnings['total_count'] > 0) {
            $details[] = [
                'title' => 'Warning Severity',
                'items' => [
                    'High: ' . $warnings['severity_breakdown']['high'],
                    'Medium: ' . $warnings['severity_breakdown']['medium'],
                    'Low: ' . $warnings['severity_breakdown']['low'],
                ],
            ];
        }

        // Type breakdown
        if (!empty($warnings['type_breakdown'])) {
            $typeDetails = [];
            foreach ($warnings['type_breakdown'] as $type => $count) {
                $typeDetails[] = self::formatWarningType($type) . ': ' . $count;
            }
            
            $details[] = [
                'title' => 'Warning Types',
                'items' => $typeDetails,
            ];
        }

        // Affected entities
        if (!empty($warnings['affected_entities'])) {
            $entityDetails = [];
            foreach ($warnings['affected_entities'] as $entity) {
                $entityDetails[] = $entity['machinery_name'] . ' (' . $entity['type'] . '): ' . $entity['warning_count'];
            }
            
            $details[] = [
                'title' => 'Affected Items',
                'items' => $entityDetails,
            ];
        }

        return $details;
    }

    /**
     * Format warning type for display
     */
    private static function formatWarningType(string $type): string
    {
        $typeMap = [
            'excessive_idle_hours' => 'Excessive Idle',
            'operator_mismatch' => 'Operator Mismatch',
            'duplicate_diesel' => 'Duplicate Diesel',
            'diesel_without_dpr' => 'Unlinked Diesel',
            'high_consumption_rate' => 'High Consumption',
            'suspicious_pattern' => 'Suspicious Pattern',
            'timing_mismatch' => 'Timing Issue',
        ];

        return $typeMap[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Get warning actions
     */
    private static function getWarningActions(array $warnings): array
    {
        $actions = [];

        if ($warnings['total_count'] > 0) {
            $actions[] = [
                'label' => 'Review Warning Details',
                'action' => 'show_warnings',
                'type' => 'primary',
            ];
        }

        if ($warnings['requires_review']) {
            $actions[] = [
                'label' => 'Quality Review Required',
                'action' => 'initiate_review',
                'type' => 'warning',
            ];
        }

        if ($warnings['quality_score'] < 70) {
            $actions[] = [
                'label' => 'Contact Data Entry Team',
                'action' => 'notify_team',
                'type' => 'secondary',
            ];
        }

        return $actions;
    }

    /**
     * Get report quality trend
     */
    public static function getReportQualityTrend(string $reportType, string $period = '30_days'): array
    {
        $dateRange = match($period) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            default => now()->subDays(30),
        };

        // Get daily quality scores
        $dailyScores = DB::table('daily_progress_reports')
            ->selectRaw('DATE(created_at) as date, AVG(warning_override_count) as avg_warnings')
            ->where('created_at', '>=', $dateRange)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $trend = [
            'period' => $period,
            'data_points' => [],
            'average_score' => 0,
            'trend_direction' => 'stable',
            'quality_distribution' => [
                'excellent' => 0, // 90-100
                'good' => 0,       // 80-89
                'fair' => 0,       // 70-79
                'poor' => 0,       // <70
            ],
        ];

        $totalScore = 0;
        $count = 0;

        foreach ($dailyScores as $score) {
            $qualityScore = max(0, 100 - ($score->avg_warnings * 5)); // Simplified scoring
            $trend['data_points'][] = [
                'date' => $score->date,
                'score' => $qualityScore,
                'warnings' => $score->avg_warnings,
            ];

            $totalScore += $qualityScore;
            $count++;

            // Distribution
            if ($qualityScore >= 90) {
                $trend['quality_distribution']['excellent']++;
            } elseif ($qualityScore >= 80) {
                $trend['quality_distribution']['good']++;
            } elseif ($qualityScore >= 70) {
                $trend['quality_distribution']['fair']++;
            } else {
                $trend['quality_distribution']['poor']++;
            }
        }

        if ($count > 0) {
            $trend['average_score'] = round($totalScore / $count, 1);
            
            // Calculate trend direction
            if (count($trend['data_points']) >= 2) {
                $firstHalf = array_slice($trend['data_points'], 0, floor(count($trend['data_points']) / 2));
                $secondHalf = array_slice($trend['data_points'], floor(count($trend['data_points']) / 2));
                
                $firstAvg = array_sum(array_column($firstHalf, 'score')) / count($firstHalf);
                $secondAvg = array_sum(array_column($secondHalf, 'score')) / count($secondHalf);
                
                if ($secondAvg > $firstAvg + 5) {
                    $trend['trend_direction'] = 'improving';
                } elseif ($secondAvg < $firstAvg - 5) {
                    $trend['trend_direction'] = 'declining';
                }
            }
        }

        return $trend;
    }
}
