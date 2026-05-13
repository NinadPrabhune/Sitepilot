<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemHealthController extends Controller
{
    public function index()
    {
        if (!Auth::user()->isAbleTo('admin manage')) {
            abort(403, 'Unauthorized action.');
        }

        // Detect orphan ledger entries
        $orphanLedgerEntries = $this->detectOrphanLedgerEntries();

        // Detect drift between source and ledger
        $driftEntries = $this->detectDrift();

        return view('system-health.index', compact('orphanLedgerEntries', 'driftEntries'));
    }

    /**
     * Detect orphan ledger entries (entries with no matching source record)
     */
    private function detectOrphanLedgerEntries()
    {
        $orphans = collect();

        // Check DPR references
        $dprLedgerIds = MachineryLedger::where('reference_type', 'DailyProgressReport')
            ->where('is_reversal', false)
            ->pluck('id', 'reference_id');

        $existingDprIds = DailyProgressReport::whereIn('id', $dprLedgerIds->keys())->pluck('id');
        $orphanDprLedgerIds = $dprLedgerIds->keys()->diff($existingDprIds);

        foreach ($orphanDprLedgerIds as $dprId) {
            $orphans->push([
                'type' => 'DPR',
                'reference_id' => $dprId,
                'ledger_id' => $dprLedgerIds[$dprId],
                'message' => "Ledger entry #{$dprLedgerIds[$dprId]} references non-existent DPR #{$dprId}",
            ]);
        }

        // Check Diesel references
        $dieselLedgerIds = MachineryLedger::where('reference_type', 'DailyConsumptionMaster')
            ->where('is_reversal', false)
            ->pluck('id', 'reference_id');

        $existingDieselIds = DailyConsumptionMaster::whereIn('id', $dieselLedgerIds->keys())->pluck('id');
        $orphanDieselLedgerIds = $dieselLedgerIds->keys()->diff($existingDieselIds);

        foreach ($orphanDieselLedgerIds as $dieselId) {
            $orphans->push([
                'type' => 'Diesel',
                'reference_id' => $dieselId,
                'ledger_id' => $dieselLedgerIds[$dieselId],
                'message' => "Ledger entry #{$dieselLedgerIds[$dieselId]} references non-existent Diesel #{$dieselId}",
            ]);
        }

        // Check Maintenance references
        $maintenanceLedgerIds = MachineryLedger::where('reference_type', 'MaintenanceLog')
            ->where('is_reversal', false)
            ->pluck('id', 'reference_id');

        $existingMaintenanceIds = MaintenanceLog::whereIn('id', $maintenanceLedgerIds->keys())->pluck('id');
        $orphanMaintenanceLedgerIds = $maintenanceLedgerIds->keys()->diff($existingMaintenanceIds);

        foreach ($orphanMaintenanceLedgerIds as $maintenanceId) {
            $orphans->push([
                'type' => 'Maintenance',
                'reference_id' => $maintenanceId,
                'ledger_id' => $maintenanceLedgerIds[$maintenanceId],
                'message' => "Ledger entry #{$maintenanceLedgerIds[$maintenanceId]} references non-existent Maintenance #{$maintenanceId}",
            ]);
        }

        return $orphans;
    }

    /**
     * Detect drift between source amounts and ledger amounts
     */
    private function detectDrift()
    {
        $drifts = collect();

        // Check DPR drift - compare calculated amount vs ledger amount
        $dprsWithLedger = DailyProgressReport::whereNotNull('ledger_entry_id')
            ->with('ledgerEntry')
            ->get();

        foreach ($dprsWithLedger as $dpr) {
            if ($dpr->ledgerEntry) {
                // Calculate expected amount from DPR (if machinery has rate)
                $machinery = $dpr->machinery;
                $calculatedAmount = 0;
                
                if ($machinery && $machinery->rate) {
                    // Calculate billable hours
                    $billableHours = 0;
                    if ($dpr->machine_start_reading && $dpr->machine_end_reading) {
                        $billableHours = $dpr->machine_end_reading - $dpr->machine_start_reading;
                    }
                    $calculatedAmount = $billableHours * $machinery->rate;
                }

                $ledgerAmount = $dpr->ledgerEntry->amount;

                // Check for drift (allow 0.01 tolerance for floating point)
                if (abs($calculatedAmount - $ledgerAmount) > 0.01 && $calculatedAmount > 0) {
                    $drifts->push([
                        'type' => 'DPR',
                        'reference_id' => $dpr->id,
                        'ledger_id' => $dpr->ledger_entry_id,
                        'message' => "DPR #{$dpr->id} amount drift: Calculated ₹{$calculatedAmount} vs Ledger ₹{$ledgerAmount}",
                        'severity' => 'critical',
                        'calculated_amount' => $calculatedAmount,
                        'ledger_amount' => $ledgerAmount,
                    ]);
                }

                // Check if reversed
                if ($dpr->ledgerEntry->reversed_entry_id) {
                    $drifts->push([
                        'type' => 'DPR',
                        'reference_id' => $dpr->id,
                        'ledger_id' => $dpr->ledger_entry_id,
                        'message' => "DPR #{$dpr->id} ledger entry has been reversed",
                        'severity' => 'warning',
                    ]);
                }
            }
        }

        // Check Diesel drift - calculate from details vs ledger
        $dieselWithLedger = DailyConsumptionMaster::whereNotNull('ledger_entry_id')
            ->with('ledgerEntry', 'details.material')
            ->get();

        foreach ($dieselWithLedger as $diesel) {
            if ($diesel->ledgerEntry) {
                // Calculate expected amount from diesel details
                $calculatedAmount = 0;
                foreach ($diesel->details as $detail) {
                    $material = $detail->material;
                    if ($material && $material->category_id == 2) { // Fuel category
                        $calculatedAmount += $detail->quantity * ($material->price ?? 0);
                    }
                }

                $ledgerAmount = $diesel->ledgerEntry->amount;

                // Check for drift
                if (abs($calculatedAmount - $ledgerAmount) > 0.01 && $calculatedAmount > 0) {
                    $drifts->push([
                        'type' => 'Diesel',
                        'reference_id' => $diesel->id,
                        'ledger_id' => $diesel->ledger_entry_id,
                        'message' => "Diesel #{$diesel->id} amount drift: Calculated ₹{$calculatedAmount} vs Ledger ₹{$ledgerAmount}",
                        'severity' => 'critical',
                        'calculated_amount' => $calculatedAmount,
                        'ledger_amount' => $ledgerAmount,
                    ]);
                }

                // Check if reversed
                if ($diesel->ledgerEntry->reversed_entry_id) {
                    $drifts->push([
                        'type' => 'Diesel',
                        'reference_id' => $diesel->id,
                        'ledger_id' => $diesel->ledger_entry_id,
                        'message' => "Diesel #{$diesel->id} ledger entry has been reversed",
                        'severity' => 'warning',
                    ]);
                }
            }
        }

        // Check Maintenance drift - compare cost vs ledger
        $maintenanceWithLedger = MaintenanceLog::whereNotNull('ledger_entry_id')
            ->with('ledgerEntry')
            ->get();

        foreach ($maintenanceWithLedger as $maintenance) {
            if ($maintenance->ledgerEntry) {
                $calculatedAmount = $maintenance->cost;
                $ledgerAmount = $maintenance->ledgerEntry->amount;

                // Check for drift
                if (abs($calculatedAmount - $ledgerAmount) > 0.01) {
                    $drifts->push([
                        'type' => 'Maintenance',
                        'reference_id' => $maintenance->id,
                        'ledger_id' => $maintenance->ledger_entry_id,
                        'message' => "Maintenance #{$maintenance->id} amount drift: Cost ₹{$calculatedAmount} vs Ledger ₹{$ledgerAmount}",
                        'severity' => 'critical',
                        'calculated_amount' => $calculatedAmount,
                        'ledger_amount' => $ledgerAmount,
                    ]);
                }

                // Check if reversed
                if ($maintenance->ledgerEntry->reversed_entry_id) {
                    $drifts->push([
                        'type' => 'Maintenance',
                        'reference_id' => $maintenance->id,
                        'ledger_id' => $maintenance->ledger_entry_id,
                        'message' => "Maintenance #{$maintenance->id} ledger entry has been reversed",
                        'severity' => 'warning',
                    ]);
                }
            }
        }

        return $drifts;
    }

    /**
     * Get system health summary for dashboard widget
     */
    public function summary()
    {
        $orphans = $this->detectOrphanLedgerEntries();
        $drifts = $this->detectDrift();

        $orphanCount = $orphans->count();
        $driftCount = $drifts->count();

        // Determine severity
        $criticalCount = 0;
        $warningCount = 0;

        // Orphan entries are always critical
        $criticalCount += $orphanCount;

        // Drift severity depends on type
        foreach ($drifts as $drift) {
            if ($drift['severity'] === 'critical') {
                $criticalCount++;
            } else {
                $warningCount++;
            }
        }

        $healthStatus = 'healthy';
        if ($criticalCount > 0) {
            $healthStatus = 'critical';
        } elseif ($warningCount > 0) {
            $healthStatus = 'warning';
        }

        // Get last ledger entry timestamp for live monitoring
        $workspaceId = getActiveWorkSpace();
        $lastLedgerEntry = MachineryLedger::where('workspace_id', $workspaceId)
            ->where('is_reversal', false)
            ->orderBy('created_at', 'desc')
            ->first();

        $lastEntryTimestamp = $lastLedgerEntry?->created_at?->toISOString();
        $lastEntryAgeMinutes = $lastLedgerEntry ? $lastLedgerEntry->created_at->diffInMinutes(now()) : null;

        return [
            'orphan_count' => $orphanCount,
            'drift_count' => $driftCount,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'health_status' => $healthStatus,
            'block_operations' => $criticalCount > 0,
            'last_ledger_entry' => [
                'timestamp' => $lastEntryTimestamp,
                'age_minutes' => $lastEntryAgeMinutes,
                'entry_id' => $lastLedgerEntry?->id,
            ],
        ];
    }

    /**
     * Get approval delay metrics
     */
    public function approvalDelayMetrics()
    {
        $workspaceId = getActiveWorkSpace();

        $pendingRequests = \App\Domain\Machinery\Models\MachineryPaymentRequest::where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->get();

        $pendingCount = $pendingRequests->count();

        // Find oldest pending approval
        $oldestPending = $pendingRequests->sortBy('created_at')->first();
        $oldestPendingAge = null;
        $hasOverdue = false;

        if ($oldestPending) {
            $oldestPendingAge = $oldestPending->created_at->diffInHours(now());
            $hasOverdue = $oldestPendingAge > 24;
        }

        return response()->json([
            'pending_count' => $pendingCount,
            'oldest_pending_age_hours' => $oldestPendingAge,
            'has_overdue_approvals' => $hasOverdue,
            'oldest_pending_id' => $oldestPending?->id,
        ]);
    }

    /**
     * Get reversal rate metrics
     */
    public function reversalRateMetrics()
    {
        $workspaceId = getActiveWorkSpace();

        // Get total entries in last 7 days
        $sevenDaysAgo = now()->subDays(7);
        $totalEntries = MachineryLedger::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('is_reversal', false)
            ->count();

        // Get reversal entries in last 7 days
        $reversalEntries = MachineryLedger::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('is_reversal', true)
            ->count();

        $reversalRate = $totalEntries > 0 ? ($reversalEntries / $totalEntries) * 100 : 0;

        // Get total entries in last 30 days for comparison
        $thirtyDaysAgo = now()->subDays(30);
        $totalEntries30 = MachineryLedger::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('is_reversal', false)
            ->count();

        $reversalEntries30 = MachineryLedger::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('is_reversal', true)
            ->count();

        $reversalRate30 = $totalEntries30 > 0 ? ($reversalEntries30 / $totalEntries30) * 100 : 0;

        return response()->json([
            'period' => '7_days',
            'total_entries' => $totalEntries,
            'reversal_entries' => $reversalEntries,
            'reversal_rate_percent' => round($reversalRate, 2),
            'comparison_30_days' => [
                'total_entries' => $totalEntries30,
                'reversal_entries' => $reversalEntries30,
                'reversal_rate_percent' => round($reversalRate30, 2),
            ],
            'targets' => [
                'week_1_target' => 15,
                'week_2_target' => 5,
                'current_week_target_met' => $reversalRate < 15,
            ],
        ]);
    }

    /**
     * Verify payment request hashes
     */
    public function verifyHashes()
    {
        $paymentRequests = \App\Domain\Machinery\Models\MachineryPaymentRequest::all();
        $verifiedCount = 0;
        $mismatchCount = 0;

        foreach ($paymentRequests as $request) {
            if (empty($request->audit_snapshot)) {
                continue;
            }

            $ledgerEntryIds = $request->audit_snapshot['ledger_entry_ids'] ?? [];
            $storedHash = $request->audit_snapshot['entries_hash'] ?? null;

            if (!$storedHash || empty($ledgerEntryIds)) {
                continue;
            }

            // Recalculate hash
            $entries = MachineryLedger::whereIn('id', $ledgerEntryIds)
                ->where('is_reversal', false)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            $sortedEntries = $entries->sortBy(['date', 'id']);
            $calculatedHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
            ])->toArray()));

            if ($calculatedHash === $storedHash) {
                $verifiedCount++;
            } else {
                $mismatchCount++;
            }
        }

        return response()->json([
            'total_count' => $paymentRequests->count(),
            'verified_count' => $verifiedCount,
            'mismatch_count' => $mismatchCount,
        ]);
    }
}
