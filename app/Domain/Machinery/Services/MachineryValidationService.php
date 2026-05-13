<?php

namespace App\Domain\Machinery\Services;

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\Machinery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MachineryValidationService
{
    // Warning levels
    const WARNING_LEVEL_INFO = 'info';
    const WARNING_LEVEL_WARN = 'warn';
    const WARNING_LEVEL_ERROR = 'error';
    const WARNING_LEVEL_CRITICAL = 'critical';

    // Validation rules
    const MAX_IDLE_PERCENTAGE = 50; // Idle time > 50% of working time
    const MAX_DIESEL_PER_HOUR = 20; // Liters per hour
    const MIN_OPERATORS_FOR_WARNING = 2;
    const WARNING_ESCALATION_THRESHOLD = 60; // 60% warning rate triggers escalation

    /**
     * Validate DPR creation and return warnings
     */
    public static function validateDPRCreation(array $data): array
    {
        $warnings = [];
        $errors = [];
        $requiresOverride = false;

        // Basic validations
        if (isset($data['machine_start_reading']) && isset($data['machine_end_reading'])) {
            if ($data['machine_end_reading'] <= $data['machine_start_reading']) {
                $errors[] = 'End reading must be greater than start reading';
            }

            $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
            $idleHours = $data['machine_idle_reading'] ?? 0;

            // Idle time validation
            if ($idleHours > 0 && $workingHours > 0) {
                $idlePercentage = ($idleHours / $workingHours) * 100;
                if ($idlePercentage > self::MAX_IDLE_PERCENTAGE) {
                    $warnings[] = [
                        'level' => self::WARNING_LEVEL_WARN,
                        'message' => "Idle time ({$idlePercentage}%) exceeds recommended maximum (" . self::MAX_IDLE_PERCENTAGE . "%)",
                        'field' => 'machine_idle_reading',
                        'requires_override' => true
                    ];
                    $requiresOverride = true;
                }
            }

            // Duplicate DPR check
            if (isset($data['machinery_id']) && isset($data['date'])) {
                $existingDPR = DailyProgressReport::where('machinery_id', $data['machinery_id'])
                    ->where('date', $data['date'])
                    ->first();

                if ($existingDPR) {
                    $errors[] = 'DPR already exists for this machinery and date';
                }
            }
        }

        // Operator validation
        if (isset($data['number_of_operators']) && isset($data['operator_names'])) {
            $operatorCount = $data['number_of_operators'];
            $nameCount = count(array_filter(explode(',', $data['operator_names'])));
            
            if ($operatorCount >= self::MIN_OPERATORS_FOR_WARNING && $nameCount < $operatorCount) {
                $warnings[] = [
                    'level' => self::WARNING_LEVEL_WARN,
                    'message' => "Operator count ({$operatorCount}) doesn't match name count ({$nameCount})",
                    'field' => 'operator_names',
                    'requires_override' => true
                ];
                $requiresOverride = true;
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'requires_override' => $requiresOverride,
            'validation_score' => self::calculateValidationScore($warnings, $errors)
        ];
    }

    /**
     * Validate diesel consumption and return warnings
     */
    public static function validateDieselConsumption(array $data): array
    {
        $warnings = [];
        $errors = [];
        $requiresOverride = false;

        // Basic validations
        if (!isset($data['diesel_consumed_liters']) || $data['diesel_consumed_liters'] <= 0) {
            $errors[] = 'Diesel consumption must be greater than 0';
            return ['warnings' => $warnings, 'errors' => $errors, 'requires_override' => $requiresOverride];
        }

        // Check for duplicate diesel entries
        if (isset($data['date']) && isset($data['machinery_id'])) {
            $existingDiesel = DailyConsumptionMaster::where('date', $data['date'])
                ->whereHas('dailyProgressReport', function ($query) use ($data) {
                    $query->where('machinery_id', $data['machinery_id']);
                })
                ->first();

            if ($existingDiesel) {
                $warnings[] = [
                    'level' => self::WARNING_LEVEL_WARN,
                    'message' => 'Diesel entry already exists for this date and machinery',
                    'field' => 'diesel_consumed_liters',
                    'requires_override' => true
                ];
                $requiresOverride = true;
            }
        }

        // Excessive consumption check
        if (isset($data['daily_progress_report_id'])) {
            $dpr = DailyProgressReport::find($data['daily_progress_report_id']);
            if ($dpr && $dpr->machine_hours > 0) {
                $litersPerHour = $data['diesel_consumed_liters'] / $dpr->machine_hours;
                
                if ($litersPerHour > self::MAX_DIESEL_PER_HOUR) {
                    $warnings[] = [
                        'level' => self::WARNING_LEVEL_CRITICAL,
                        'message' => "Excessive diesel consumption: {$litersPerHour}L/hour (max: " . self::MAX_DIESEL_PER_HOUR . "L/hour)",
                        'field' => 'diesel_consumed_liters',
                        'requires_override' => true
                    ];
                    $requiresOverride = true;
                }
            }
        }

        // Diesel without DPR warning
        if (!isset($data['daily_progress_report_id']) || $data['daily_progress_report_id'] === null) {
            $warnings[] = [
                'level' => self::WARNING_LEVEL_INFO,
                'message' => 'Diesel entry not linked to DPR - ensure this is intentional',
                'field' => 'daily_progress_report_id',
                'requires_override' => false
            ];
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'requires_override' => $requiresOverride,
            'validation_score' => self::calculateValidationScore($warnings, $errors)
        ];
    }

    /**
     * Validate machinery master data
     */
    public static function validateMachineryCreation(array $data): array
    {
        $warnings = [];
        $errors = [];

        // Ownership validation
        if (!isset($data['owned_by'])) {
            $errors[] = 'Ownership type (owned_by) is required';
        } else {
            if ($data['owned_by'] === 'owned') {
                // Owned machinery should NOT have supplier
                if (isset($data['supplier_id']) && $data['supplier_id'] !== null) {
                    $errors[] = 'Owned machinery cannot have a supplier';
                }
                
                // Owned machinery should have rate
                if (!isset($data['rate']) || $data['rate'] <= 0) {
                    $errors[] = 'Owned machinery must have a valid rate';
                }
                
                // Owned machinery validation for required fields
                $requiredOwnedFields = ['purchase_value', 'insurance_due_date', 'puc_due_date', 'fitness_due_date', 'last_service_date'];
                foreach ($requiredOwnedFields as $field) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        $warnings[] = [
                            'level' => self::WARNING_LEVEL_WARN,
                            'message' => "Owned machinery should have {$field} specified",
                            'field' => $field,
                            'requires_override' => false
                        ];
                    }
                }
                
            } elseif ($data['owned_by'] === 'rental') {
                // Rental machinery MUST have supplier
                if (!isset($data['supplier_id']) || $data['supplier_id'] === null) {
                    $errors[] = 'Rental machinery must have a supplier';
                }
                
                // Rental machinery should have rate_type
                if (!isset($data['rate_type']) || !in_array($data['rate_type'], ['hourly', 'daily', 'monthly'])) {
                    $errors[] = 'Rental machinery must have a valid rate_type (hourly, daily, monthly)';
                }
                
                // Rental machinery should have minimum billing hours
                if (!isset($data['minimum_billing_hours']) || $data['minimum_billing_hours'] <= 0) {
                    $warnings[] = [
                        'level' => self::WARNING_LEVEL_WARN,
                        'message' => 'Rental machinery should have minimum billing hours specified',
                        'field' => 'minimum_billing_hours',
                        'requires_override' => false
                    ];
                }
                
                // Validate operator consistency
                if (isset($data['operator_by_supplier']) && $data['operator_by_supplier'] && (!isset($data['number_of_operators']) || $data['number_of_operators'] <= 0)) {
                    $warnings[] = [
                        'level' => self::WARNING_LEVEL_WARN,
                        'message' => 'Operator provided by supplier but number of operators not specified',
                        'field' => 'number_of_operators',
                        'requires_override' => false
                    ];
                }
                
            } else {
                $errors[] = 'Invalid ownership type. Must be "owned" or "rental"';
            }
        }

        // Rate validation
        if (isset($data['rate']) && $data['rate'] <= 0) {
            $errors[] = 'Rate must be greater than 0';
        }
        
        // Machine ID validation
        if (isset($data['machine_id']) && !empty($data['machine_id'])) {
            if (!preg_match('/^MCH-\d{3,4}$/', $data['machine_id'])) {
                $errors[] = 'Machine ID must be in format MCH-XXX or MCH-XXXX';
            }
        }
        
        // File validation
        if (isset($data['rental_agreement_file']) && $data['owned_by'] !== 'rental') {
            $warnings[] = [
                'level' => self::WARNING_LEVEL_INFO,
                'message' => 'Rental agreement file uploaded for non-rental machinery',
                'field' => 'rental_agreement_file',
                'requires_override' => false
            ];
        }
        
        if (isset($data['ownership_documents_file']) && $data['owned_by'] !== 'owned') {
            $warnings[] = [
                'level' => self::WARNING_LEVEL_INFO,
                'message' => 'Ownership documents file uploaded for non-owned machinery',
                'field' => 'ownership_documents_file',
                'requires_override' => false
            ];
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'requires_override' => count($errors) > 0,
            'validation_score' => self::calculateValidationScore($warnings, $errors)
        ];
    }

    /**
     * Calculate validation score based on warnings and errors
     */
    private static function calculateValidationScore(array $warnings, array $errors): int
    {
        $baseScore = 100;
        
        // Deduct points for warnings
        foreach ($warnings as $warning) {
            switch ($warning['level']) {
                case self::WARNING_LEVEL_INFO:
                    $baseScore -= 5;
                    break;
                case self::WARNING_LEVEL_WARN:
                    $baseScore -= 10;
                    break;
                case self::WARNING_LEVEL_CRITICAL:
                    $baseScore -= 20;
                    break;
                case self::WARNING_LEVEL_ERROR:
                    $baseScore -= 30;
                    break;
            }
        }
        
        // Deduct points for errors
        $baseScore -= (count($errors) * 25);
        
        return max(0, $baseScore);
    }

    /**
     * Check if escalation is needed based on warning rate
     */
    public static function requiresEscalation(int $validationScore): bool
    {
        return $validationScore < (100 - self::WARNING_ESCALATION_THRESHOLD);
    }

    /**
     * Get warning statistics for a user or workspace
     */
    public static function getWarningStatistics($userId = null, $workspaceId = null): array
    {
        $query = DailyProgressReport::query();

        if ($userId) {
            $query->where('created_by', $userId);
        }

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $totalDprs = $query->count();
        $dprsWithOverrides = $query->whereNotNull('override_reason')->count();
        $dprsWithWarnings = $query->whereNotNull('override_reason')->count(); // Simplified for now

        $warningRate = $totalDprs > 0 ? ($dprsWithWarnings / $totalDprs) * 100 : 0;

        return [
            'total_entries' => $totalDprs,
            'entries_with_warnings' => $dprsWithWarnings,
            'entries_with_overrides' => $dprsWithOverrides,
            'warning_rate' => round($warningRate, 2),
            'requires_escalation' => $warningRate > self::WARNING_ESCALATION_THRESHOLD
        ];
    }

    /**
     * Log validation warnings for audit trail
     */
    public static function logValidationWarnings(string $referenceType, int $referenceId, array $warnings, ?int $userId = null): void
    {
        foreach ($warnings as $warning) {
            Log::warning('Validation warning triggered', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'warning_level' => $warning['level'],
                'warning_message' => $warning['message'],
                'field' => $warning['field'] ?? null,
                'requires_override' => $warning['requires_override'] ?? false,
                'user_id' => $userId ?? auth()->id(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Create override record for audit trail
     */
    public static function createOverrideRecord(string $referenceType, int $referenceId, string $reason, ?int $userId = null): array
    {
        $overrideData = [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'override_reason' => $reason,
            'override_by' => $userId ?? auth()->id(),
            'override_at' => now(),
        ];

        Log::info('Override recorded', array_merge($overrideData, [
            'timestamp' => now()->toISOString(),
        ]));

        return $overrideData;
    }
}
