<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\DailyProgressReport;
use Carbon\Carbon;

class MeterReadingValidationService
{
    /**
     * Validate meter reading data for Daily Progress Report
     */
    public static function validateReading(array $data, ?Machinery $machinery = null): array
    {
        $errors = [];
        $warnings = [];

        // Validate working hours <= 24
        if (isset($data['billable_hours']) && $data['billable_hours'] > 24) {
            $errors[] = 'Billable hours cannot exceed 24 hours per day';
        }

        if (isset($data['billable_hours']) && $data['billable_hours'] < 0) {
            $errors[] = 'Billable hours cannot be negative';
        }

        // Validate meter reading progression
        if ($machinery && isset($data['machine_end_reading']) && isset($data['machine_start_reading'])) {
            if ($data['machine_end_reading'] < $data['machine_start_reading']) {
                $errors[] = 'End reading cannot be less than start reading';
            }

            // Check for unreasonable meter jumps
            $readingDifference = $data['machine_end_reading'] - $data['machine_start_reading'];
            if ($readingDifference > 1000) {
                $warnings[] = 'Large meter jump detected (>1000 units). Please verify readings.';
            }

            // Check against previous day's reading
            $previousReading = DailyProgressReport::where('machinery_id', $machinery->id)
                ->where('date', '<', $data['date'])
                ->orderBy('date', 'desc')
                ->value('machine_end_reading');
                
            if ($previousReading && $data['machine_start_reading'] < $previousReading) {
                $errors[] = "Start reading cannot be less than previous day's reading ({$previousReading})";
            }

            // Check for negative production (end reading less than previous day's end reading)
            if ($previousReading && $data['machine_end_reading'] < $previousReading) {
                $errors[] = "End reading cannot be less than previous day's end reading ({$previousReading})";
            }
        }

        // Validate idle hours (idle_reading represents hours, not meter position)
        if (isset($data['machine_idle_reading'])) {
            // Idle hours should be non-negative and reasonable
            if ($data['machine_idle_reading'] < 0) {
                $errors[] = 'Idle hours cannot be negative';
            }
            
            // Idle hours should not exceed working hours
            if (isset($data['machine_start_reading']) && isset($data['machine_end_reading'])) {
                $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
                if ($workingHours > 0 && $data['machine_idle_reading'] > $workingHours) {
                    $errors[] = 'Idle hours cannot exceed working hours';
                }
            }
            
            // Idle hours should not exceed 24 hours in a day
            if ($data['machine_idle_reading'] > 24) {
                $errors[] = 'Idle hours cannot exceed 24 hours';
            }
        }

        // Validate date
        if (isset($data['date'])) {
            try {
                $date = Carbon::parse($data['date']);
                if ($date > now()->startOfDay()) {
                    $errors[] = 'Date cannot be in the future';
                }
                if ($date < now()->subMonths(3)->startOfDay()) {
                    $warnings[] = 'Date is more than 3 months old. Please verify.';
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid date format';
            }
        }

        // Check for duplicate DPR
        if ($machinery && isset($data['date'])) {
            $existingDPR = DailyProgressReport::where('machinery_id', $machinery->id)
                ->where('date', $data['date'])
                ->first();

            if ($existingDPR) {
                $errors[] = "Daily Progress Report already exists for this machinery on {$data['date']}";
            }
        }

        // Validate number of operators
        if (isset($data['number_of_operators'])) {
            if ($data['number_of_operators'] < 0) {
                $errors[] = 'Number of operators cannot be negative';
            }
            if ($data['number_of_operators'] > 10) {
                $warnings[] = 'High number of operators detected (>10). Please verify.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate meter reading for update operation
     */
    public static function validateReadingUpdate(array $data, DailyProgressReport $existingDPR, ?Machinery $machinery = null): array
    {
        $errors = [];
        $warnings = [];

        // Get basic validation
        $basicValidation = self::validateReading($data, $machinery);
        $errors = array_merge($errors, $basicValidation['errors']);
        $warnings = array_merge($warnings, $basicValidation['warnings']);

        // Additional validations for updates
        if ($machinery && isset($data['date'])) {
            // Check if date change would cause conflict
            if ($data['date'] !== $existingDPR->date) {
                $conflictingDPR = DailyProgressReport::where('machinery_id', $machinery->id)
                    ->where('date', $data['date'])
                    ->where('id', '!=', $existingDPR->id)
                    ->first();

                if ($conflictingDPR) {
                    $errors[] = "Another Daily Progress Report exists for this machinery on {$data['date']}";
                }
            }
        }

        // Check if DPR is already approved (should not allow modification)
        if ($existingDPR->approved_at) {
            $errors[] = 'Cannot modify approved Daily Progress Report';
        }

        // Check if ledger entries exist (should not allow modification)
        if ($existingDPR->ledger_entry_id) {
            $errors[] = 'Cannot modify Daily Progress Report with existing ledger entries';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Calculate billable hours from meter readings
     */
    public static function calculateBillableHours(array $data): float
    {
        $startReading = $data['machine_start_reading'] ?? 0;
        $endReading = $data['machine_end_reading'] ?? 0;
        $idleHours = $data['machine_idle_reading'] ?? 0;

        $workingHours = $endReading - $startReading;

        return max(0, $workingHours - $idleHours);
    }

    /**
     * Get previous day's reading for machinery
     */
    public static function getPreviousDayReading(Machinery $machinery, Carbon $date): ?float
    {
        return DailyProgressReport::where('machinery_id', $machinery->id)
            ->where('date', '<', $date->toDateString())
            ->orderBy('date', 'desc')
            ->value('machine_end_reading');
    }

    /**
     * Check for meter reading anomalies with enhanced fraud detection
     */
    public static function checkForAnomalies(Machinery $machinery, Carbon $from, Carbon $to): array
    {
        $dprs = DailyProgressReport::where('machinery_id', $machinery->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $anomalies = [];
        $previousReading = null;
        $previousDate = null;

        foreach ($dprs as $dpr) {
            // Check for negative production (critical fraud indicator)
            if ($previousReading && $dpr->machine_end_reading < $previousReading) {
                $anomalies[] = [
                    'date' => $dpr->date,
                    'type' => 'negative_production',
                    'message' => "End reading ({$dpr->machine_end_reading}) less than previous day's reading ({$previousReading})",
                    'severity' => 'critical',
                    'previous_reading' => $previousReading,
                    'current_reading' => $dpr->machine_end_reading,
                    'difference' => $dpr->machine_end_reading - $previousReading
                ];
            }

            // Check for large jumps (potential tampering)
            if ($previousReading) {
                $jump = $dpr->machine_end_reading - $previousReading;
                $jumpThreshold = self::getJumpThreshold($machinery);
                
                if ($jump > $jumpThreshold) {
                    $anomalies[] = [
                        'date' => $dpr->date,
                        'type' => 'large_jump',
                        'message' => "Unusually large meter jump detected: {$jump} units (threshold: {$jumpThreshold})",
                        'severity' => 'high',
                        'jump_amount' => $jump,
                        'threshold' => $jumpThreshold,
                        'requires_admin_override' => true
                    ];
                }
            }

            // Check for excessive billable hours
            if ($dpr->billable_hours > 24) {
                $anomalies[] = [
                    'date' => $dpr->date,
                    'type' => 'excessive_hours',
                    'message' => "Billable hours exceed 24 hours: {$dpr->billable_hours}",
                    'severity' => 'error',
                    'billable_hours' => $dpr->billable_hours
                ];
            }

            // Check for impossible idle readings (idle_reading represents hours)
            if ($dpr->machine_idle_reading !== null) {
                $workingHours = $dpr->machine_end_reading - $dpr->machine_start_reading;
                
                // Idle hours should not exceed working hours
                if ($workingHours > 0 && $dpr->machine_idle_reading > $workingHours) {
                    $anomalies[] = [
                        'date' => $dpr->date,
                        'type' => 'invalid_idle_reading',
                        'message' => "Idle hours ({$dpr->machine_idle_reading}) cannot exceed working hours ({$workingHours})",
                        'severity' => 'warning',
                        'working_hours' => $workingHours,
                        'idle_hours' => $dpr->machine_idle_reading
                    ];
                }
                
                // Idle hours should not exceed 24 hours
                if ($dpr->machine_idle_reading > 24) {
                    $anomalies[] = [
                        'date' => $dpr->date,
                        'type' => 'excessive_idle_hours',
                        'message' => "Idle hours exceed 24 hours: {$dpr->machine_idle_reading}",
                        'severity' => 'warning',
                        'idle_hours' => $dpr->machine_idle_reading
                    ];
                }
            }

            // Check for consecutive zero readings (potential data manipulation)
            if ($previousDate) {
                $daysDiff = $dpr->date->diffInDays($previousDate);
                if ($daysDiff === 1 && $dpr->machine_end_reading === $previousReading && $dpr->billable_hours > 0) {
                    $anomalies[] = [
                        'date' => $dpr->date,
                        'type' => 'suspicious_zero_progression',
                        'message' => "Zero meter progression with positive billable hours: {$dpr->billable_hours} hours",
                        'severity' => 'medium',
                        'billable_hours' => $dpr->billable_hours,
                        'reading_unchanged' => true
                    ];
                }
            }

            $previousReading = $dpr->machine_end_reading;
            $previousDate = $dpr->date;
        }

        return $anomalies;
    }

    /**
     * Get jump threshold based on machinery type and historical patterns
     */
    private static function getJumpThreshold(Machinery $machinery): int
    {
        // Base thresholds by machinery type (these should be configurable)
        $baseThresholds = [
            'hourly' => 500,    // Hourly meters: 500 units per day max
            'km_based' => 200,  // KM meters: 200 km per day max
            'hmr' => 1000,      // HMR meters: 1000 units per day max
            'default' => 1000    // Default fallback
        ];

        // Get machinery type from category or other field
        $machineryType = self::getMachineryType($machinery);
        $baseThreshold = $baseThresholds[$machineryType] ?? $baseThresholds['default'];

        // Adjust based on historical patterns
        $historicalAverage = self::getHistoricalAverageDaily($machinery);
        $adjustedThreshold = max($baseThreshold, $historicalAverage * 3); // 3x historical average

        return (int) $adjustedThreshold;
    }

    /**
     * Get machinery type for anomaly detection
     */
    private static function getMachineryType(Machinery $machinery): string
    {
        // This should be adapted based on your machinery categorization
        // For now, using a simple heuristic based on rate type and category
        if ($machinery->rate_type === 'hourly') {
            return 'hourly';
        }
        
        // Check if machinery has KM-based readings (you might have a field for this)
        if (str_contains(strtolower($machinery->name ?? ''), 'km') || 
            str_contains(strtolower($machinery->category->name ?? ''), 'km')) {
            return 'km_based';
        }
        
        return 'default';
    }

    /**
     * Get historical average daily progression for machinery
     */
    private static function getHistoricalAverageDaily(Machinery $machinery): float
    {
        // Get average daily progression from last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        
        $averageProgression = DailyProgressReport::where('machinery_id', $machinery->id)
            ->where('date', '>=', $thirtyDaysAgo)
            ->whereNotNull('machine_end_reading')
            ->whereNotNull('machine_start_reading')
            ->selectRaw('AVG(machine_end_reading - machine_start_reading) as avg_progression')
            ->value('avg_progression');

        return $averageProgression ?? 100; // Default if no data
    }

    /**
     * Validate meter reading with advanced fraud detection
     */
    public static function validateWithFraudDetection(array $data, ?Machinery $machinery = null): array
    {
        // Get basic validation
        $basicValidation = self::validateReading($data, $machinery);
        
        if (!$basicValidation['valid'] || !$machinery) {
            return $basicValidation;
        }

        // Add fraud detection checks
        $fraudChecks = self::performFraudChecks($data, $machinery);
        
        // Combine results
        $allErrors = array_merge($basicValidation['errors'], $fraudChecks['errors']);
        $allWarnings = array_merge($basicValidation['warnings'], $fraudChecks['warnings']);
        
        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors,
            'warnings' => $allWarnings,
            'fraud_risk_score' => $fraudChecks['risk_score'],
            'requires_admin_override' => $fraudChecks['requires_admin_override']
        ];
    }

    /**
     * Perform advanced fraud detection checks
     */
    private static function performFraudChecks(array $data, Machinery $machinery): array
    {
        $errors = [];
        $warnings = [];
        $riskScore = 0;
        $requiresAdminOverride = false;

        // Check for unusual time patterns (e.g., submissions at odd hours)
        $submissionHour = now()->hour;
        if ($submissionHour < 6 || $submissionHour > 22) {
            $warnings[] = "Data submitted during unusual hours: {$submissionHour}:00";
            $riskScore += 10;
        }

        // Check for rapid consecutive submissions
        $recentSubmissions = DailyProgressReport::where('machinery_id', $machinery->id)
            ->where('created_by', auth()->id() ?? 1)
            ->where('created_at', '>', now()->subMinutes(30))
            ->count();

        if ($recentSubmissions > 3) {
            $warnings[] = "High frequency submissions: {$recentSubmissions} in last 30 minutes";
            $riskScore += 15;
        }

        // Check for pattern anomalies
        if (isset($data['machine_start_reading']) && isset($data['machine_end_reading'])) {
            $progression = $data['machine_end_reading'] - $data['machine_start_reading'];
            
            // Check for round numbers (potential manual manipulation)
            if ($progression > 0 && in_array($progression % 100, [0])) {
                $warnings[] = "Round number progression detected: {$progression} units";
                $riskScore += 5;
            }

            // Check for perfect multiples (too perfect to be real)
            if ($progression > 0 && $progression % 50 === 0 && $data['billable_hours'] > 0) {
                $unitsPerHour = $progression / $data['billable_hours'];
                if ($unitsPerHour === round($unitsPerHour, 0) && $unitsPerHour > 10) {
                    $warnings[] = "Perfect hourly rate: {$unitsPerHour} units/hour (unusually consistent)";
                    $riskScore += 8;
                }
            }
        }

        // Determine if admin override is required
        $requiresAdminOverride = $riskScore > 25 || !empty($errors);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'risk_score' => $riskScore,
            'requires_admin_override' => $requiresAdminOverride
        ];
    }

    /**
     * Create admin override record for suspicious readings
     */
    public static function createAdminOverride(DailyProgressReport $dpr, int $adminUserId, string $reason): void
    {
        // This would create a record in an admin_overrides table
        // For now, just log the override
        \Log::warning("Admin override for suspicious meter reading", [
            'dpr_id' => $dpr->id,
            'machinery_id' => $dpr->machinery_id,
            'date' => $dpr->date,
            'admin_user_id' => $adminUserId,
            'reason' => $reason,
            'readings' => [
                'start' => $dpr->machine_start_reading,
                'end' => $dpr->machine_end_reading,
                'idle' => $dpr->machine_idle_reading,
                'billable_hours' => $dpr->billable_hours
            ]
        ]);
    }

    /**
     * Get fraud detection statistics for machinery
     */
    public static function getFraudStatistics(Machinery $machinery, Carbon $from, Carbon $to): array
    {
        $dprs = DailyProgressReport::where('machinery_id', $machinery->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        $anomalies = self::checkForAnomalies($machinery, $from, $to);
        
        $criticalCount = collect($anomalies)->where('severity', 'critical')->count();
        $highCount = collect($anomalies)->where('severity', 'high')->count();
        $mediumCount = collect($anomalies)->where('severity', 'medium')->count();
        $warningCount = collect($anomalies)->where('severity', 'warning')->count();

        return [
            'total_dprs' => $dprs->count(),
            'anomalies_detected' => count($anomalies),
            'severity_breakdown' => [
                'critical' => $criticalCount,
                'high' => $highCount,
                'medium' => $mediumCount,
                'warning' => $warningCount
            ],
            'anomaly_rate' => $dprs->count() > 0 ? round((count($anomalies) / $dprs->count()) * 100, 2) : 0,
            'risk_level' => self::calculateRiskLevel($criticalCount, $highCount, $mediumCount, $dprs->count()),
            'requires_investigation' => $criticalCount > 0 || $highCount > 2
        ];
    }

    /**
     * Calculate overall risk level for machinery
     */
    private static function calculateRiskLevel(int $critical, int $high, int $medium, int $totalDprs): string
    {
        if ($critical > 0) {
            return 'critical';
        }
        
        if ($high > 2) {
            return 'high';
        }
        
        if ($high > 0 || $medium > 3) {
            return 'medium';
        }
        
        if ($medium > 0) {
            return 'low';
        }
        
        return 'minimal';
    }
}
