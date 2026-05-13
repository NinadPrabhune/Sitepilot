<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\PurchaseInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialPeriodService
{
    /**
     * Validate that the invoice date is not in a closed financial period
     */
    public function validatePeriodNotClosed(Carbon $invoiceDate, int $workspaceId, int $siteId): void
    {
        $period = FinancialPeriod::where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->where('start_date', '<=', $invoiceDate)
            ->where('end_date', '>=', $invoiceDate)
            ->first();

        if ($period && $period->is_closed) {
            Log::channel('finance')->warning('Financial period closed violation', [
                'period_year' => $period->period_year,
                'period_month' => $period->period_month,
                'workspace_id' => $workspaceId,
                'site_id' => $site_id,
            ]);

            throw new \InvalidArgumentException(
                "Financial period {$period->period_year}-{$period->period_month} is closed. " .
                "Cannot create or modify transactions for this period."
            );
        }
    }

    /**
     * Close a financial period
     */
    public function closePeriod(int $periodId, int $userId): void
    {
        $period = FinancialPeriod::findOrFail($periodId);

        DB::transaction(function () use ($period, $userId) {
            // Validate no pending transactions
            $pendingInvoices = PurchaseInvoice::whereBetween('invoice_date', [$period->start_date, $period->end_date])
                ->whereIn('status', ['Pending', 'Draft'])
                ->count();

            if ($pendingInvoices > 0) {
                throw new \InvalidArgumentException(
                    "Cannot close period with {$pendingInvoices} pending invoices."
                );
            }

            $period->update([
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);

            Log::channel('finance')->info('Financial period closed', [
                'period_id' => $periodId,
                'period_year' => $period->period_year,
                'period_month' => $period->period_month,
                'closed_by' => $userId,
            ]);
        });
    }
}
