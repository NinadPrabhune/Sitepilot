<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use Illuminate\Support\Facades\DB;
use Exception;

class MachineryBillingProtectionService
{
    /**
     * Mark DPRs as billed when payment request is approved
     */
    public static function markDprsAsBilled(MachineryPaymentRequest $paymentRequest): void
    {
        // Get DPRs for the payment request period
        $dprs = DailyProgressReport::where('machinery_id', $paymentRequest->machinery_id)
            ->whereBetween('date', [$paymentRequest->period_start, $paymentRequest->period_end])
            ->where('is_billed', false) // Only mark unbilled DPRs
            ->get();

        foreach ($dprs as $dpr) {
            $dpr->update([
                'is_billed' => true,
                'billed_at' => now(),
                'payment_request_id' => $paymentRequest->id
            ]);
        }
    }

    /**
     * Mark ledger entries as billed
     */
    public static function markLedgerEntriesAsBilled(MachineryPaymentRequest $paymentRequest): void
    {
        // Get ledger entries linked to this payment request
        $ledgerEntries = MachineryLedger::where('payment_request_id', $paymentRequest->id)
            ->where('is_billed', false)
            ->get();

        foreach ($ledgerEntries as $entry) {
            $entry->update([
                'is_billed' => true,
                'billed_at' => now()
            ]);
        }
    }

    /**
     * Check if DPR can be billed (not already billed)
     */
    public static function canBillDpr(DailyProgressReport $dpr): bool
    {
        if ($dpr->is_billed) {
            return false;
        }

        // Check if there's an active payment request for this period
        $activeRequest = MachineryPaymentRequest::where('machinery_id', $dpr->machinery_id)
            ->where('period_start', '<=', $dpr->date)
            ->where('period_end', '>=', $dpr->date)
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->first();

        return !$activeRequest;
    }

    /**
     * Check if machinery can have payment request for period
     */
    public static function canCreatePaymentRequest(int $machineryId, string $periodStart, string $periodEnd): array
    {
        // Check for existing payment requests
        $existingRequest = MachineryPaymentRequest::where('machinery_id', $machineryId)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->first();

        if ($existingRequest) {
            return [
                'can_create' => false,
                'reason' => 'Payment request already exists for this period',
                'existing_request_id' => $existingRequest->id
            ];
        }

        // Check for overlapping periods
        $overlappingRequest = MachineryPaymentRequest::where('machinery_id', $machineryId)
            ->where(function($query) use ($periodStart, $periodEnd) {
                $query->where('period_start', '<=', $periodEnd)
                      ->where('period_end', '>=', $periodStart);
            })
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->first();

        if ($overlappingRequest) {
            return [
                'can_create' => false,
                'reason' => 'Overlapping payment request exists',
                'overlapping_request_id' => $overlappingRequest->id
            ];
        }

        // Check if all DPRs are available for billing
        $billedDprs = DailyProgressReport::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_billed', true)
            ->count();

        if ($billedDprs > 0) {
            return [
                'can_create' => false,
                'reason' => "Found {$billedDprs} already billed DPRs in period",
                'billed_count' => $billedDprs
            ];
        }

        return ['can_create' => true];
    }

    /**
     * Unmark billing when payment request is rejected
     */
    public static function unmarkBillingForRejectedRequest(MachineryPaymentRequest $paymentRequest): void
    {
        // Unmark DPRs
        DailyProgressReport::where('payment_request_id', $paymentRequest->id)
            ->update([
                'is_billed' => false,
                'billed_at' => null,
                'payment_request_id' => null
            ]);

        // Unmark ledger entries
        MachineryLedger::where('payment_request_id', $paymentRequest->id)
            ->update([
                'is_billed' => false,
                'billed_at' => null
            ]);
    }

    /**
     * Get billing statistics for machinery
     */
    public static function getBillingStatistics(int $machineryId, string $periodStart, string $periodEnd): array
    {
        $totalDprs = DailyProgressReport::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->count();

        $billedDprs = DailyProgressReport::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_billed', true)
            ->count();

        $availableDprs = $totalDprs - $billedDprs;

        $totalLedgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_reversal', false)
            ->count();

        $billedLedgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_billed', true)
            ->where('is_reversal', false)
            ->count();

        return [
            'total_dprs' => $totalDprs,
            'billed_dprs' => $billedDprs,
            'available_dprs' => $availableDprs,
            'total_ledger_entries' => $totalLedgerEntries,
            'billed_ledger_entries' => $billedLedgerEntries,
            'billing_progress_percentage' => $totalDprs > 0 ? round(($billedDprs / $totalDprs) * 100, 2) : 0
        ];
    }

    /**
     * Validate billing integrity for payment request
     */
    public static function validateBillingIntegrity(MachineryPaymentRequest $paymentRequest): array
    {
        $issues = [];

        // Check if all linked DPRs are properly marked as billed
        $linkedDprs = DailyProgressReport::where('payment_request_id', $paymentRequest->id)->get();
        foreach ($linkedDprs as $dpr) {
            if (!$dpr->is_billed) {
                $issues[] = "DPR {$dpr->id} is linked but not marked as billed";
            }
        }

        // Check if all linked ledger entries are marked as billed
        $linkedLedgers = MachineryLedger::where('payment_request_id', $paymentRequest->id)->get();
        foreach ($linkedLedgers as $ledger) {
            if (!$ledger->is_billed) {
                $issues[] = "Ledger entry {$ledger->id} is linked but not marked as billed";
            }
        }

        // Check for orphaned billing markers (marked as billed but not linked)
        $orphanedDprs = DailyProgressReport::where('machinery_id', $paymentRequest->machinery_id)
            ->whereBetween('date', [$paymentRequest->period_start, $paymentRequest->period_end])
            ->where('is_billed', true)
            ->where('payment_request_id', '!=', $paymentRequest->id)
            ->count();

        if ($orphanedDprs > 0) {
            $issues[] = "Found {$orphanedDprs} DPRs marked as billed but not linked to this payment request";
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check for potential duplicate billing scenarios
     */
    public static function detectDuplicateBillingRisks(): array
    {
        $risks = [];

        // Check for DPRs marked as billed without payment request
        $orphanedBilling = DailyProgressReport::where('is_billed', true)
            ->whereNull('payment_request_id')
            ->count();

        if ($orphanedBilling > 0) {
            $risks[] = [
                'type' => 'orphaned_billing',
                'severity' => 'high',
                'description' => "Found {$orphanedBilling} DPRs marked as billed without payment request",
                'count' => $orphanedBilling
            ];
        }

        // Check for ledger entries marked as billed without payment request
        $orphanedLedgerBilling = MachineryLedger::where('is_billed', true)
            ->whereNull('payment_request_id')
            ->count();

        if ($orphanedLedgerBilling > 0) {
            $risks[] = [
                'type' => 'orphaned_ledger_billing',
                'severity' => 'high',
                'description' => "Found {$orphanedLedgerBilling} ledger entries marked as billed without payment request",
                'count' => $orphanedLedgerBilling
            ];
        }

        // Check for multiple payment requests for same machinery/period
        $duplicateRequests = DB::select("
            SELECT machinery_id, period_start, period_end, COUNT(*) as count
            FROM machinery_payment_requests
            WHERE status IN ('draft', 'submitted', 'approved')
            GROUP BY machinery_id, period_start, period_end
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicateRequests as $duplicate) {
            $risks[] = [
                'type' => 'duplicate_payment_requests',
                'severity' => 'critical',
                'description' => "Machinery {$duplicate->machinery_id} has {$duplicate->count} active payment requests for period {$duplicate->period_start} to {$duplicate->period_end}",
                'machinery_id' => $duplicate->machinery_id,
                'period_start' => $duplicate->period_start,
                'period_end' => $duplicate->period_end,
                'count' => $duplicate->count
            ];
        }

        return $risks;
    }
}
