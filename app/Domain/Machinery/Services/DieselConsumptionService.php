<?php

namespace App\Domain\Machinery\Services;

use App\Models\DailyConsumptionMaster;
use App\Models\DailyProgressReport;
use App\Domain\Machinery\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DieselConsumptionService
{
    /**
     * Create diesel consumption record and ledger entry
     */
    public static function createDieselConsumption(array $data): DailyConsumptionMaster
    {
        return DB::transaction(function () use ($data) {
            // Create diesel consumption record
            $diesel = DailyConsumptionMaster::create([
                'consumption_date' => $data['date'],
                'consumption_type' => 'diesel',
                'machinery_id' => $data['machinery_id'],
                'site_id' => $data['site_id'] ?? 1,
                'workspace_id' => $data['workspace_id'],
                'created_by' => $data['created_by'],
            ]);

            // Create ledger entry for diesel expense
            $ledgerEntry = self::createDieselLedgerEntry($diesel, $data);

            // Link diesel to ledger entry
            $diesel->update(['ledger_entry_id' => $ledgerEntry->id]);

            Log::info('Diesel consumption created with ledger entry', [
                'diesel_id' => $diesel->id,
                'machinery_id' => $diesel->machinery_id,
                'ledger_entry_id' => $ledgerEntry->id,
                'amount' => $data['total_cost'],
            ]);

            return $diesel;
        });
    }

    /**
     * Create ledger entry for diesel consumption
     */
    private static function createDieselLedgerEntry(DailyConsumptionMaster $diesel, array $data): MachineryLedger
    {
        // Generate idempotency key
        $idempotencyKey = 'diesel_' . $diesel->machinery_id . '_' . $diesel->consumption_date;

        // Create debit entry for diesel expense
        $ledgerEntry = MachineryLedger::create([
            'machinery_id' => $diesel->machinery_id,
            'workspace_id' => $diesel->workspace_id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'ledger_type' => 'expense',
            'cost_category' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => $diesel->id,
            'amount' => $data['total_cost'],
            'running_balance' => self::calculateRunningBalance($diesel->machinery_id, -$data['total_cost']),
            'date' => $diesel->consumption_date,
            'description' => "Diesel consumption: {$data['liters']}L @ {$data['rate_per_liter']}/L",
            'idempotency_key' => $idempotencyKey,
            'is_reversal' => false,
        ]);

        return $ledgerEntry;
    }

    /**
     * Calculate running balance for machinery
     */
    private static function calculateRunningBalance(int $machineryId, float $amountChange): float
    {
        $lastBalance = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->value('running_balance') ?? 0;

        return $lastBalance + $amountChange;
    }

    /**
     * Validate diesel consumption data
     */
    public static function validateDieselConsumption(array $data): array
    {
        $warnings = [];
        $errors = [];

        // Basic validations
        if (!isset($data['liters']) || $data['liters'] <= 0) {
            $errors[] = 'Diesel consumption in liters must be greater than 0';
        }

        if (!isset($data['rate_per_liter']) || $data['rate_per_liter'] <= 0) {
            $errors[] = 'Diesel rate per liter must be greater than 0';
        }

        if (!isset($data['total_cost']) || $data['total_cost'] <= 0) {
            $errors[] = 'Total diesel cost must be greater than 0';
        }

        // Check for excessive consumption
        if (isset($data['daily_progress_report_id'])) {
            $dpr = DailyProgressReport::find($data['daily_progress_report_id']);
            if ($dpr && $dpr->machine_hours > 0) {
                $litersPerHour = $data['liters'] / $dpr->machine_hours;
                
                if ($litersPerHour > 20) { // 20L per hour threshold
                    $warnings[] = [
                        'level' => 'critical',
                        'message' => "Excessive diesel consumption: {$litersPerHour}L/hour (max: 20L/hour)",
                        'requires_override' => true
                    ];
                }
            }
        }

        // Check for duplicate diesel entries
        if (isset($data['machinery_id']) && isset($data['date'])) {
            $existingDiesel = DailyConsumptionMaster::where('machinery_id', $data['machinery_id'])
                ->where('consumption_date', $data['date'])
                ->where('consumption_type', 'diesel')
                ->first();

            if ($existingDiesel) {
                $warnings[] = [
                    'level' => 'warn',
                    'message' => 'Diesel entry already exists for this date and machinery',
                    'requires_override' => true
                ];
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'requires_override' => count($warnings) > 0 || count($errors) > 0
        ];
    }
}
