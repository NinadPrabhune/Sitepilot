<?php

namespace App\Services;

use App\Models\WorkSpace;
use App\Models\Site;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class MonthlyClosureService
{
    /**
     * Close a month for workspace/site
     */
    public static function closeMonth(int $workspaceId, ?int $siteId, int $year, int $month, int $userId, ?string $remarks = null): void
    {
        // Validate month is not already closed
        if (self::isMonthClosed($workspaceId, $siteId, $year, $month)) {
            throw new Exception("Month {$year}-{$month} is already closed for workspace {$workspaceId}" . ($siteId ? " and site {$siteId}" : ""));
        }

        // Validate month is in the past or current month
        $closureDate = Carbon::create($year, $month, 1)->endOfMonth();
        if ($closureDate->isFuture()) {
            throw new Exception("Cannot close future months");
        }

        // Check for pending payment requests
        $pendingRequests = self::getPendingPaymentRequests($workspaceId, $siteId, $year, $month);
        if ($pendingRequests > 0) {
            throw new Exception("Cannot close month with {$pendingRequests} pending payment requests");
        }

        DB::transaction(function () use ($workspaceId, $siteId, $year, $month, $userId, $remarks) {
            // Create closure record
            DB::table('monthly_closures')->insert([
                'workspace_id' => $workspaceId,
                'site_id' => $siteId,
                'year' => $year,
                'month' => $month,
                'closed_by' => $userId,
                'remarks' => $remarks,
                'closed_at' => now()
            ]);

            // Lock all DPRs for the month
            self::lockDprsForMonth($workspaceId, $siteId, $year, $month);

            // Lock all diesel entries for the month
            self::lockDieselEntriesForMonth($workspaceId, $siteId, $year, $month);
        });
    }

    /**
     * Check if month is closed
     */
    public static function isMonthClosed(int $workspaceId, ?int $siteId, int $year, int $month): bool
    {
        return DB::table('monthly_closures')
            ->where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    /**
     * Validate if operation can be performed on closed month
     */
    public static function validateMonthNotClosed(int $workspaceId, ?int $siteId, Carbon $date, string $operation = 'operation'): void
    {
        $year = $date->year;
        $month = $date->month;

        if (self::isMonthClosed($workspaceId, $siteId, $year, $month)) {
            throw new Exception("Cannot perform {$operation}: Month {$year}-{$month} is closed");
        }
    }

    /**
     * Get pending payment requests for month
     */
    public static function getPendingPaymentRequests(int $workspaceId, ?int $siteId, int $year, int $month): int
    {
        $query = MachineryPaymentRequest::where('workspace_id', $workspaceId)
            ->whereYear('period_start', $year)
            ->whereMonth('period_start', $month)
            ->whereIn('status', ['draft', 'submitted']);

        if ($siteId) {
            $query->whereHas('machinery', function($q) use ($siteId) {
                $q->where('site_id', $siteId);
            });
        }

        return $query->count();
    }

    /**
     * Lock DPRs for closed month
     */
    private static function lockDprsForMonth(int $workspaceId, ?int $siteId, int $year, int $month): void
    {
        $query = DailyProgressReport::where('workspace_id', $workspaceId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $query->update(['is_locked' => true, 'locked_at' => now()]);
    }

    /**
     * Lock diesel entries for closed month
     */
    private static function lockDieselEntriesForMonth(int $workspaceId, ?int $siteId, int $year, int $month): void
    {
        // This would need to be implemented based on your diesel tracking table structure
        // For now, assuming there's a diesel_consumption table with similar structure
        if (DB::getSchemaBuilder()->hasColumn('daily_consumption_masters', 'is_locked')) {
            $query = DB::table('daily_consumption_masters')
                ->where('workspace_id', $workspaceId)
                ->whereYear('consumption_date', $year)
                ->whereMonth('consumption_date', $month);

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $query->update(['is_locked' => true, 'locked_at' => now()]);
        }
    }

    /**
     * Reopen a month (admin only)
     */
    public static function reopenMonth(int $workspaceId, ?int $siteId, int $year, int $month, int $userId, ?string $reason = null): void
    {
        if (!self::isMonthClosed($workspaceId, $siteId, $year, $month)) {
            throw new Exception("Month {$year}-{$month} is not closed");
        }

        DB::transaction(function () use ($workspaceId, $siteId, $year, $month, $userId, $reason) {
            // Delete closure record
            DB::table('monthly_closures')
                ->where('workspace_id', $workspaceId)
                ->where('site_id', $siteId)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            // Unlock DPRs for the month
            self::unlockDprsForMonth($workspaceId, $siteId, $year, $month);

            // Unlock diesel entries for the month
            self::unlockDieselEntriesForMonth($workspaceId, $siteId, $year, $month);

            // Log the reopening
            \Log::info("Month reopened", [
                'workspace_id' => $workspaceId,
                'site_id' => $siteId,
                'year' => $year,
                'month' => $month,
                'reopened_by' => $userId,
                'reason' => $reason
            ]);
        });
    }

    /**
     * Unlock DPRs for reopened month
     */
    private static function unlockDprsForMonth(int $workspaceId, ?int $siteId, int $year, int $month): void
    {
        $query = DailyProgressReport::where('workspace_id', $workspaceId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $query->update(['is_locked' => false, 'locked_at' => null]);
    }

    /**
     * Unlock diesel entries for reopened month
     */
    private static function unlockDieselEntriesForMonth(int $workspaceId, ?int $siteId, int $year, int $month): void
    {
        if (DB::getSchemaBuilder()->hasColumn('daily_consumption_masters', 'is_locked')) {
            $query = DB::table('daily_consumption_masters')
                ->where('workspace_id', $workspaceId)
                ->whereYear('consumption_date', $year)
                ->whereMonth('consumption_date', $month);

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $query->update(['is_locked' => false, 'locked_at' => null]);
        }
    }

    /**
     * Get closure status for a period
     */
    public static function getClosureStatus(int $workspaceId, ?int $siteId, int $year, int $month): array
    {
        $closure = DB::table('monthly_closures')
            ->where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return [
            'is_closed' => (bool) $closure,
            'closed_at' => $closure?->closed_at,
            'closed_by' => $closure?->closed_by,
            'remarks' => $closure?->remarks,
            'pending_payment_requests' => self::getPendingPaymentRequests($workspaceId, $siteId, $year, $month)
        ];
    }

    /**
     * Get all closed months for workspace/site
     */
    public static function getClosedMonths(int $workspaceId, ?int $siteId = null): array
    {
        $query = DB::table('monthly_closures')
            ->where('workspace_id', $workspaceId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        return $query->get()->map(function($closure) {
            return [
                'year' => $closure->year,
                'month' => $closure->month,
                'closed_at' => $closure->closed_at,
                'closed_by' => $closure->closed_by,
                'remarks' => $closure->remarks
            ];
        })->toArray();
    }

    /**
     * Get closure statistics
     */
    public static function getClosureStatistics(int $workspaceId, ?int $siteId = null): array
    {
        $query = DB::table('monthly_closures')
            ->where('workspace_id', $workspaceId);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $totalClosed = $query->count();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $currentMonthClosed = $query->where('year', $currentYear)
            ->where('month', $currentMonth)
            ->exists();

        $lastMonthClosed = $query->where('year', now()->subMonth()->year)
            ->where('month', now()->subMonth()->month)
            ->exists();

        return [
            'total_closed_months' => $totalClosed,
            'current_month_closed' => $currentMonthClosed,
            'last_month_closed' => $lastMonthClosed,
            'closure_rate' => self::calculateClosureRate($workspaceId, $siteId)
        ];
    }

    /**
     * Calculate closure rate for the last 12 months
     */
    private static function calculateClosureRate(int $workspaceId, ?int $siteId): float
    {
        $monthsToCheck = 12;
        $closedCount = 0;

        for ($i = 0; $i < $monthsToCheck; $i++) {
            $date = now()->subMonths($i);
            if (self::isMonthClosed($workspaceId, $siteId, $date->year, $date->month)) {
                $closedCount++;
            }
        }

        return round(($closedCount / $monthsToCheck) * 100, 2);
    }
}
