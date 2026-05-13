<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\MachineryBillingItem;
use App\Models\DailyProgressReport;
use App\Models\MonthlyLock;
use App\Domain\Machinery\Services\DieselResponsibilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    protected MonthlyLockService $lockService;

    public function __construct(MonthlyLockService $lockService)
    {
        $this->lockService = $lockService;
    }

    /**
     * Generate billing items for a locked month
     */
    public function generate(int $month, int $year, int $workspaceId): \Illuminate\Support\Collection
    {
        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            throw new \Exception("Month {$month}/{$year} must be locked before generating billing");
        }

        $fromDate = Carbon::create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        $machines = Machinery::with('supplier')
            ->where('workspace_id', $workspaceId)
            ->get();

        $billingItems = collect();

        foreach ($machines as $machine) {
            // Skip if already billed
            if (MachineryBillingItem::existsForMachinery($machine->id, $fromDate->format('Y-m-d'), $toDate->format('Y-m-d'), $workspaceId)) {
                Log::warning("Machinery {$machine->id} already billed for period {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
                continue;
            }

            $dprs = DailyProgressReport::where('machinery_id', $machine->id)
                ->where('workspace_id', $workspaceId)
                ->whereBetween('date', [$fromDate, $toDate])
                ->get();

            if ($dprs->isEmpty()) {
                Log::info("No DPRs found for machinery {$machine->id} in period {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
                continue;
            }

            $totalHours = $this->calculateTotalHours($dprs);
            $totalDieselLiters = $dprs->sum('diesel_consumption');
            $dieselCost = $totalDieselLiters * ($machine->diesel_rate ?? 0);
            $amount = $this->calculateAmount($machine, $totalHours, $dieselCost);

            $billingItem = MachineryBillingItem::create([
                'machinery_id' => $machine->id,
                'supplier_id' => $machine->supplier_id,
                'workspace_id' => $workspaceId,
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'total_hours' => $totalHours,
                'total_diesel' => $totalDieselLiters,
                'diesel_cost_actual' => $dieselCost,
                'diesel_cost_deducted' => DieselResponsibilityService::getDeductibleDieselAmount($machine, $dieselCost),
                'diesel_responsibility' => $machine->diesel_by_company ? 'company' : 'supplier',
                'amount' => $amount,
                'rate_per_hour' => $machine->hourly_rate ?? 0,
                'diesel_rate' => $machine->diesel_rate ?? 0,
                'status' => 'draft',
            ]);

            $billingItems->push($billingItem);

            Log::info('Billing item created', [
                'machinery_id' => $machine->id,
                'period' => $fromDate->format('Y-m-d') . ' to ' . $toDate->format('Y-m-d'),
                'amount' => $amount,
            ]);
        }

        return $billingItems;
    }

    /**
     * Calculate total hours from DPRs
     */
    private function calculateTotalHours($dprs): float
    {
        $totalHours = 0;
        
        foreach ($dprs as $dpr) {
            if ($dpr->machine_start_reading && $dpr->machine_end_reading) {
                $hours = ($dpr->machine_end_reading - $dpr->machine_start_reading) / ($dpr->machinery->meter_type === 'hours' ? 1 : 60);
                $totalHours += $hours;
            }
        }

        return round($totalHours, 2);
    }

    /**
     * Calculate amount based on hours and diesel consumption
     * 
     * @param Machinery $machine The machinery record
     * @param float $hours Total billable hours
     * @param float $dieselCost Total diesel cost in ₹ (already converted from liters)
     * @return float Final calculated amount
     */
    private function calculateAmount(Machinery $machine, float $hours, float $dieselCost): float
    {
        $hourlyAmount = $hours * ($machine->hourly_rate ?? 0);
        
        // Only include diesel in bill if company pays for it
        $dieselAmount = DieselResponsibilityService::getDeductibleDieselAmount($machine, $dieselCost);
        
        return round($hourlyAmount + $dieselAmount, 2);
    }

    /**
     * Get billing summary for period
     */
    public function getBillingSummary(int $month, int $year, int $workspaceId): array
    {
        $fromDate = Carbon::create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        $items = MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereBetween('from_date', [$fromDate, $toDate])
            ->with(['machinery', 'supplier'])
            ->get();

        return [
            'total_items' => $items->count(),
            'total_amount' => $items->sum('amount'),
            'total_hours' => $items->sum('total_hours'),
            'total_diesel' => $items->sum('total_diesel'),
            'items' => $items,
        ];
    }

    /**
     * Delete billing items for period (regenerate)
     */
    public function deleteBillingItems(int $month, int $year, int $workspaceId): int
    {
        $fromDate = Carbon::create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        $count = MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereBetween('from_date', [$fromDate, $toDate])
            ->where('status', 'draft')
            ->delete();

        Log::info("Deleted {$count} billing items for regeneration", [
            'month' => $month,
            'year' => $year,
            'workspace_id' => $workspaceId,
        ]);

        return $count;
    }
}
