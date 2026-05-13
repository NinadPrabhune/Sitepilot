<?php

namespace App\Services;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialSnapshotVersioningService
{
    /**
     * Get current calculation versions
     */
    public static function getCurrentVersions(): array
    {
        return [
            'calculation_version' => self::getCurrentVersion('calculation'),
            'formula_version' => self::getCurrentVersion('formula'),
            'diesel_rate_version' => self::getCurrentVersion('diesel_rate')
        ];
    }

    /**
     * Get current version for a specific type
     */
    private static function getCurrentVersion(string $type): string
    {
        $version = DB::table('calculation_versions')
            ->where('type', $type)
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>', now());
            })
            ->orderBy('effective_from', 'desc')
            ->value('version');

        return $version ?: '1.0';
    }

    /**
     * Create a new calculation version
     */
    public static function createVersion(string $type, string $version, string $description, array $rules = [], int $createdBy = null): void
    {
        // Deactivate previous versions
        DB::table('calculation_versions')
            ->where('type', $type)
            ->where('is_active', true)
            ->update([
                'effective_to' => now(),
                'is_active' => false
            ]);

        // Create new version
        DB::table('calculation_versions')->insert([
            'version' => $version,
            'type' => $type,
            'description' => $description,
            'rules' => json_encode($rules),
            'is_active' => true,
            'effective_from' => now(),
            'created_by' => $createdBy ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Apply versioning to payment request snapshot
     */
    public static function applyVersioningToPaymentRequest(MachineryPaymentRequest $request): void
    {
        $currentVersions = self::getCurrentVersions();
        
        // Store calculation metadata
        $metadata = [
            'calculation_timestamp' => now()->toISOString(),
            'calculation_rules' => self::getCalculationRules($currentVersions['calculation_version']),
            'formula_applied' => self::getFormulaDetails($currentVersions['formula_version']),
            'diesel_rate_source' => self::getDieselRateDetails($currentVersions['diesel_rate_version']),
            'snapshot_hash' => self::generateSnapshotHash($request, $currentVersions)
        ];

        $request->update([
            'calculation_version' => $currentVersions['calculation_version'],
            'formula_version' => $currentVersions['formula_version'],
            'diesel_rate_version' => $currentVersions['diesel_rate_version'],
            'calculation_metadata' => $metadata
        ]);
    }

    /**
     * Apply versioning to ledger entries
     */
    public static function applyVersioningToLedgerEntries(MachineryPaymentRequest $request): void
    {
        $currentVersions = self::getCurrentVersions();
        
        MachineryLedger::where('payment_request_id', $request->id)
            ->update([
                'calculation_version' => $currentVersions['calculation_version'],
                'formula_version' => $currentVersions['formula_version']
            ]);
    }

    /**
     * Get calculation rules for a version
     */
    private static function getCalculationRules(string $version): array
    {
        $rules = DB::table('calculation_versions')
            ->where('type', 'calculation')
            ->where('version', $version)
            ->value('rules');

        return json_decode($rules, true) ?: [];
    }

    /**
     * Get formula details for a version
     */
    private static function getFormulaDetails(string $version): array
    {
        $formula = DB::table('calculation_versions')
            ->where('type', 'formula')
            ->where('version', $version)
            ->first();

        if (!$formula) {
            return [];
        }

        return [
            'version' => $formula->version,
            'description' => $formula->description,
            'effective_from' => $formula->effective_from
        ];
    }

    /**
     * Get diesel rate details for a version
     */
    private static function getDieselRateDetails(string $version): array
    {
        $rateVersion = DB::table('calculation_versions')
            ->where('type', 'diesel_rate')
            ->where('version', $version)
            ->first();

        if (!$rateVersion) {
            return [];
        }

        return [
            'version' => $rateVersion->version,
            'description' => $rateVersion->description,
            'effective_from' => $rateVersion->effective_from,
            'rules' => json_decode($rateVersion->rules, true) ?: []
        ];
    }

    /**
     * Generate snapshot hash for integrity verification
     */
    private static function generateSnapshotHash(MachineryPaymentRequest $request, array $versions): string
    {
        $snapshotData = [
            'payment_request_id' => $request->id,
            'gross_amount' => $request->gross_amount,
            'diesel_deduction' => $request->diesel_deduction,
            'net_payable' => $request->net_payable,
            'calculation_method' => $request->calculation_method,
            'calculation_version' => $versions['calculation_version'],
            'formula_version' => $versions['formula_version'],
            'diesel_rate_version' => $versions['diesel_rate_version'],
            'timestamp' => now()->timestamp
        ];

        return hash('sha256', json_encode($snapshotData));
    }

    /**
     * Verify snapshot integrity
     */
    public static function verifySnapshotIntegrity(MachineryPaymentRequest $request): array
    {
        if (!$request->calculation_metadata) {
            return [
                'valid' => false,
                'reason' => 'No calculation metadata found'
            ];
        }

        $metadata = json_decode($request->calculation_metadata, true);
        $storedHash = $metadata['snapshot_hash'] ?? null;

        if (!$storedHash) {
            return [
                'valid' => false,
                'reason' => 'No snapshot hash found in metadata'
            ];
        }

        // Recreate hash with current data
        $currentHash = self::generateSnapshotHash($request, [
            'calculation_version' => $request->calculation_version,
            'formula_version' => $request->formula_version,
            'diesel_rate_version' => $request->diesel_rate_version
        ]);

        $hashMatches = hash_equals($storedHash, $currentHash);

        return [
            'valid' => $hashMatches,
            'reason' => $hashMatches ? 'Snapshot integrity verified' : 'Snapshot tampered or modified',
            'stored_hash' => $storedHash,
            'current_hash' => $currentHash
        ];
    }

    /**
     * Recalculate payment request with current versions (for audit purposes)
     */
    public static function recalculateWithCurrentVersions(MachineryPaymentRequest $request): array
    {
        $currentVersions = self::getCurrentVersions();
        
        // Get original calculation versions
        $originalVersions = [
            'calculation_version' => $request->calculation_version,
            'formula_version' => $request->formula_version,
            'diesel_rate_version' => $request->diesel_rate_version
        ];

        // Check if versions have changed
        $versionsChanged = $originalVersions !== $currentVersions;

        if (!$versionsChanged) {
            return [
                'versions_changed' => false,
                'reason' => 'Calculation versions are current',
                'original_versions' => $originalVersions,
                'current_versions' => $currentVersions
            ];
        }

        // Perform recalculation with current versions
        $machinery = $request->machinery;
        $from = Carbon::parse($request->period_start);
        $to = Carbon::parse($request->period_end);

        // Recalculate using current billing calculator
        $dprs = \App\Models\DailyProgressReport::where('machinery_id', $machinery->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        $billingResult = \App\Services\MachineryBillingCalculatorService::calculate($machinery, $dprs, $from, $to);
        $dieselResult = \App\Services\MachineryDieselAdjustmentService::calculateDieselDeduction($machinery, $from, $to);

        $newGrossAmount = $billingResult['gross_amount'];
        $newDieselDeduction = $dieselResult['applicable_for_deduction'] ? $dieselResult['total_cost'] : 0;
        $newNetPayable = $newGrossAmount - $newDieselDeduction;

        return [
            'versions_changed' => true,
            'original_versions' => $originalVersions,
            'current_versions' => $currentVersions,
            'original_calculation' => [
                'gross_amount' => $request->gross_amount,
                'diesel_deduction' => $request->diesel_deduction,
                'net_payable' => $request->net_payable
            ],
            'recalculated_with_current' => [
                'gross_amount' => $newGrossAmount,
                'diesel_deduction' => $newDieselDeduction,
                'net_payable' => $newNetPayable
            ],
            'differences' => [
                'gross_amount_diff' => $newGrossAmount - $request->gross_amount,
                'diesel_deduction_diff' => $newDieselDeduction - $request->diesel_deduction,
                'net_payable_diff' => $newNetPayable - $request->net_payable
            ]
        ];
    }

    /**
     * Get version history for a payment request
     */
    public static function getVersionHistory(MachineryPaymentRequest $request): array
    {
        $history = [];

        $versionTypes = ['calculation', 'formula', 'diesel_rate'];
        
        foreach ($versionTypes as $type) {
            $currentVersion = $request->{$type . '_version'};
            
            $versionInfo = DB::table('calculation_versions')
                ->where('type', $type)
                ->where('version', $currentVersion)
                ->first();

            if ($versionInfo) {
                $history[$type] = [
                    'version' => $versionInfo->version,
                    'description' => $versionInfo->description,
                    'effective_from' => $versionInfo->effective_from,
                    'rules' => json_decode($versionInfo->rules, true) ?: []
                ];
            } else {
                $history[$type] = [
                    'version' => $currentVersion,
                    'description' => 'Legacy version (no detailed info available)',
                    'effective_from' => null,
                    'rules' => []
                ];
            }
        }

        return $history;
    }

    /**
     * Initialize default versions
     */
    public static function initializeDefaultVersions(): void
    {
        $defaultVersions = [
            [
                'type' => 'calculation',
                'version' => '1.0',
                'description' => 'Initial machinery billing calculation engine',
                'rules' => [
                    'daily_billing' => 'any_usage_counts_full_day',
                    'monthly_billing' => 'prorated_based_on_active_days',
                    'hourly_billing' => 'standard_hourly_rate_calculation'
                ]
            ],
            [
                'type' => 'formula',
                'version' => '1.0',
                'description' => 'Standard billing formulas',
                'rules' => [
                    'daily' => 'working_days * daily_rate',
                    'monthly' => '(monthly_rate / days_in_month) * active_days',
                    'hourly' => 'billable_hours * hourly_rate'
                ]
            ],
            [
                'type' => 'diesel_rate',
                'version' => '1.0',
                'description' => 'Default diesel rate handling',
                'rules' => [
                    'default_rate' => 90.00,
                    'rate_source' => 'system_default',
                    'freeze_at_issue' => true
                ]
            ]
        ];

        foreach ($defaultVersions as $versionData) {
            $exists = DB::table('calculation_versions')
                ->where('type', $versionData['type'])
                ->where('version', $versionData['version'])
                ->exists();

            if (!$exists) {
                DB::table('calculation_versions')->insert([
                    'version' => $versionData['version'],
                    'type' => $versionData['type'],
                    'description' => $versionData['description'],
                    'rules' => json_encode($versionData['rules']),
                    'is_active' => true,
                    'effective_from' => now(),
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Get version compatibility matrix
     */
    public static function getVersionCompatibility(): array
    {
        return [
            'compatible_versions' => [
                'calculation' => ['1.0'],
                'formula' => ['1.0'],
                'diesel_rate' => ['1.0']
            ],
            'incompatible_combinations' => [],
            'deprecated_versions' => [],
            'upgrade_paths' => [
                'calculation' => [
                    'from' => '1.0',
                    'to' => '1.1',
                    'changes' => ['Enhanced anomaly detection', 'Improved rounding logic']
                ],
                'formula' => [
                    'from' => '1.0',
                    'to' => '1.1',
                    'changes' => ['Updated monthly calculation logic']
                ],
                'diesel_rate' => [
                    'from' => '1.0',
                    'to' => '1.1',
                    'changes' => ['Added rate source tracking']
                ]
            ]
        ];
    }
}
