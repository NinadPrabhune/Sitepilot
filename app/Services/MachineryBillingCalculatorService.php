<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\DailyProgressReport;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class MachineryBillingCalculatorService
{
    /**
     * Calculate machinery billing based on rate type
     */
    public static function calculate(Machinery $machinery, Collection $dprs, Carbon $from, Carbon $to): array
    {
        return match($machinery->rate_type) {
            'hourly' => self::calculateHourly($machinery, $dprs),
            'daily' => self::calculateDaily($machinery, $dprs),
            'monthly' => self::calculateMonthly($machinery, $dprs, $from, $to),
            default => throw new Exception("Invalid rate_type: {$machinery->rate_type}")
        };
    }

    /**
     * Calculate hourly billing
     */
    private static function calculateHourly(Machinery $machinery, Collection $dprs): array
    {
        $totalHours = $dprs->sum('billable_hours');
        $grossAmount = $totalHours * $machinery->rate;

        return [
            'gross_amount' => $grossAmount,
            'total_hours' => $totalHours,
            'rate_applied' => $machinery->rate,
            'calculation_type' => 'hourly',
            'hourly_breakdown' => $dprs->map(fn($dpr) => [
                'date' => $dpr->date,
                'billable_hours' => $dpr->billable_hours,
                'amount' => $dpr->billable_hours * $machinery->rate
            ])->toArray()
        ];
    }

    /**
     * Calculate daily billing - any usage counts as full day charge
     */
    private static function calculateDaily(Machinery $machinery, Collection $dprs): array
    {
        $workingDays = $dprs->where('billable_hours', '>', 0)->count();
        $grossAmount = $workingDays * $machinery->rate;

        return [
            'gross_amount' => $grossAmount,
            'working_days' => $workingDays,
            'rate_applied' => $machinery->rate,
            'calculation_type' => 'daily',
            'daily_breakdown' => $dprs->map(fn($dpr) => [
                'date' => $dpr->date,
                'billable_hours' => $dpr->billable_hours,
                'charged' => $dpr->billable_hours > 0 ? $machinery->rate : 0
            ])->toArray()
        ];
    }

    /**
     * Calculate monthly billing - prorated based on active days
     */
    private static function calculateMonthly(Machinery $machinery, Collection $dprs, Carbon $from, Carbon $to): array
    {
        $totalDaysInMonth = $from->daysInMonth;
        $activeDays = $dprs->where('billable_hours', '>', 0)->count();
        $dailyRate = $machinery->rate / $totalDaysInMonth;
        $grossAmount = $dailyRate * $activeDays;

        return [
            'gross_amount' => $grossAmount,
            'active_days' => $activeDays,
            'total_days_in_month' => $totalDaysInMonth,
            'daily_rate' => $dailyRate,
            'calculation_type' => 'monthly_prorated',
            'monthly_breakdown' => $dprs->map(fn($dpr) => [
                'date' => $dpr->date,
                'billable_hours' => $dpr->billable_hours,
                'charged' => $dpr->billable_hours > 0 ? $dailyRate : 0
            ])->toArray()
        ];
    }

    /**
     * Calculate DPR amount for single entry (used in DPR creation)
     */
    public static function calculateDprAmount(Machinery $machinery, float $billableHours): float
    {
        return match($machinery->rate_type) {
            'hourly' => $billableHours * $machinery->rate,
            'daily' => $billableHours > 0 ? $machinery->rate : 0,
            'monthly' => 0, // Handled at month-end in payment requests
            default => 0
        };
    }

    /**
     * Validate rate type
     */
    public static function validateRateType(string $rateType): bool
    {
        return in_array($rateType, ['hourly', 'daily', 'monthly']);
    }
}
