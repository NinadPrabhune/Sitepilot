<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\DailyConsumptionMaster;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class MachineryDieselAdjustmentService
{
    /**
     * Calculate diesel deduction for machinery in a given period
     */
    public static function calculateDieselDeduction(Machinery $machinery, Carbon $from, Carbon $to): array
    {
        $dieselEntries = DailyConsumptionMaster::whereHas('dailyProgressReport', function($query) use ($machinery) {
            $query->where('machinery_id', $machinery->id);
        })->whereBetween('consumption_date', [$from, $to])
        ->with(['dailyProgressReport' => function($query) {
            $query->select('id', 'date', 'machinery_id');
        }])
        ->get();

        $totalLiters = $dieselEntries->sum('diesel_consumed_liters');
        
        // Use frozen diesel_total_cost if available, otherwise calculate with current/default rate
        $totalCost = $dieselEntries->sum('diesel_total_cost');
        if ($totalCost == 0 && $totalLiters > 0) {
            $defaultRate = self::getDefaultDieselRate();
            $totalCost = $totalLiters * $defaultRate;
        }

        return [
            'total_liters' => $totalLiters,
            'total_cost' => $totalCost,
            'entries' => $dieselEntries->map(fn($entry) => [
                'date' => $entry->consumption_date,
                'dpr_id' => $entry->daily_progress_report_id,
                'liters' => $entry->diesel_consumed_liters,
                'rate' => $entry->diesel_rate ?? self::getDefaultDieselRate(),
                'amount' => $entry->diesel_total_cost ?? ($entry->diesel_consumed_liters * ($entry->diesel_rate ?? self::getDefaultDieselRate()))
            ])->toArray(),
            'applicable_for_deduction' => $machinery->diesel_by_company,
            'diesel_responsibility' => $machinery->diesel_by_company ? 'company' : 'supplier'
        ];
    }

    /**
     * Get default diesel rate for calculation when historical rate not available
     */
    private static function getDefaultDieselRate(): float
    {
        // This could be from system settings, or a reasonable default
        return config('machinery.default_diesel_rate', 90.00); // Default ₹90/liter
    }

    /**
     * Update diesel entry with frozen rate and total cost
     */
    public static function updateDieselEntryWithFrozenRate(DailyConsumptionMaster $entry, float $rate): void
    {
        $totalCost = $entry->diesel_consumed_liters * $rate;
        
        $entry->update([
            'diesel_rate' => $rate,
            'diesel_total_cost' => $totalCost
        ]);
    }

    /**
     * Validate diesel consumption entry
     */
    public static function validateDieselEntry(array $data, ?Machinery $machinery = null): array
    {
        $errors = [];
        
        if (!isset($data['diesel_consumed_liters']) || $data['diesel_consumed_liters'] <= 0) {
            $errors[] = 'Diesel consumption must be greater than 0';
        }

        if (isset($data['diesel_consumed_liters']) && $data['diesel_consumed_liters'] > 1000) {
            $errors[] = 'Diesel consumption seems excessive (>1000 liters). Please verify.';
        }

        if (isset($data['diesel_rate']) && $data['diesel_rate'] <= 0) {
            $errors[] = 'Diesel rate must be greater than 0';
        }

        if (isset($data['diesel_rate']) && $data['diesel_rate'] > 200) {
            $errors[] = 'Diesel rate seems excessive (>₹200/liter). Please verify.';
        }

        // Check for duplicate diesel entries
        if (isset($data['daily_progress_report_id']) && isset($data['machinery_id'])) {
            $existing = DailyConsumptionMaster::where('daily_progress_report_id', $data['daily_progress_report_id'])
                ->whereHas('dailyProgressReport', function($query) use ($data) {
                    $query->where('machinery_id', $data['machinery_id']);
                })
                ->first();

            if ($existing) {
                $errors[] = 'Diesel entry already exists for this DPR and machinery';
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Calculate diesel cost for a single entry
     */
    public static function calculateEntryCost(array $data): float
    {
        $liters = $data['diesel_consumed_liters'] ?? 0;
        $rate = $data['diesel_rate'] ?? self::getDefaultDieselRate();
        
        return $liters * $rate;
    }

    /**
     * Get diesel consumption summary for machinery in period
     */
    public static function getDieselConsumptionSummary(Machinery $machinery, Carbon $from, Carbon $to): array
    {
        $dieselEntries = DailyConsumptionMaster::whereHas('dailyProgressReport', function($query) use ($machinery) {
            $query->where('machinery_id', $machinery->id);
        })->whereBetween('consumption_date', [$from, $to])
        ->get();

        $totalLiters = $dieselEntries->sum('diesel_consumed_liters');
        $totalCost = $dieselEntries->sum('diesel_total_cost');
        $averageRate = $totalLiters > 0 ? $totalCost / $totalLiters : 0;

        return [
            'total_liters' => $totalLiters,
            'total_cost' => $totalCost,
            'average_rate' => $averageRate,
            'entry_count' => $dieselEntries->count(),
            'daily_average' => $dieselEntries->count() > 0 ? $totalLiters / $dieselEntries->count() : 0
        ];
    }
}
