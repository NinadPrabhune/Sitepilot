<?php

namespace App\Console\Commands;

use App\Http\Controllers\SystemHealthController;
use App\Models\SystemHealthLog;
use App\Mail\SystemAlertMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';
    protected $description = 'Run system health checks and log results';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting system health check...');

        $healthController = new SystemHealthController();

        // Run health checks
        $orphans = $healthController->detectOrphanLedgerEntries();
        $drifts = $healthController->detectDrift();

        $orphanCount = $orphans->count();
        $driftCount = $drifts->count();

        // Determine severity
        $criticalCount = 0;
        $warningCount = 0;

        $criticalCount += $orphanCount;

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

        // Hash verification
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

            $entries = \App\Domain\Machinery\Models\MachineryLedger::whereIn('id', $ledgerEntryIds)
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

        // Log results for each workspace
        $workspaces = \Workdo\Taskly\Entities\WorkSpace::all();

        foreach ($workspaces as $workspace) {
            SystemHealthLog::create([
                'workspace_id' => $workspace->id,
                'orphan_count' => $orphanCount,
                'drift_count' => $driftCount,
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'health_status' => $healthStatus,
                'block_operations' => $criticalCount > 0,
                'total_payment_requests' => $paymentRequests->count(),
                'verified_payment_requests' => $verifiedCount,
                'mismatch_payment_requests' => $mismatchCount,
                'details' => [
                    'orphans' => $orphans->toArray(),
                    'drifts' => $drifts->toArray(),
                ],
            ]);
        }

        $this->info('Health check completed:');
        $this->info("  - Orphan entries: {$orphanCount}");
        $this->info("  - Drift entries: {$driftCount}");
        $this->info("  - Critical issues: {$criticalCount}");
        $this->info("  - Warnings: {$warningCount}");
        $this->info("  - Health status: {$healthStatus}");
        $this->info("  - Payment requests verified: {$verifiedCount}/{$paymentRequests->count()}");
        $this->info("  - Hash mismatches: {$mismatchCount}");

        // Send email alerts for critical conditions (one alert per issue type per cycle)
        if ($criticalCount > 0) {
            $this->sendCriticalAlerts($orphanCount, $drifts, $mismatchCount);
        }

        return Command::SUCCESS;
    }

    private function sendCriticalAlerts(int $orphanCount, $drifts, int $mismatchCount)
    {
        $admins = \App\Models\User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super admin', 'admin', 'company']);
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found to send alerts to.');
            return;
        }

        $adminEmails = $admins->pluck('email')->toArray();

        // Alert for orphan entries
        if ($orphanCount > 0) {
            Mail::to($adminEmails)->queue(new SystemAlertMail([
                'issue_type' => 'Orphan Ledger Entries Detected',
                'details' => [
                    'orphan_count' => $orphanCount,
                    'severity' => 'critical',
                ],
                'action_required' => 'Review orphan entries in System Health and reverse or link them appropriately.',
                'link' => url('/system-health'),
            ]));
            $this->info("Sent orphan alert to " . count($adminEmails) . " admins.");
        }

        // Alert for critical drifts (one alert for all critical drifts, not per drift)
        $criticalDrifts = collect($drifts)->filter(fn($d) => $d['severity'] === 'critical');
        if ($criticalDrifts->isNotEmpty()) {
            $firstDrift = $criticalDrifts->first();
            Mail::to($adminEmails)->queue(new SystemAlertMail([
                'issue_type' => 'Critical Drift Detected',
                'details' => [
                    'drift_count' => $criticalDrifts->count(),
                    'reference_type' => $firstDrift['reference_type'] ?? 'Unknown',
                    'reference_id' => $firstDrift['reference_id'] ?? 'Unknown',
                    'expected_amount' => $firstDrift['expected_amount'] ?? 'Unknown',
                    'actual_amount' => $firstDrift['actual_amount'] ?? 'Unknown',
                ],
                'action_required' => 'Review drift entries in System Health and reverse incorrect entries.',
                'link' => url('/system-health'),
            ]));
            $this->info("Sent drift alert to " . count($adminEmails) . " admins.");
        }

        // Alert for hash mismatches
        if ($mismatchCount > 0) {
            Mail::to($adminEmails)->queue(new SystemAlertMail([
                'issue_type' => 'Hash Verification Failed',
                'details' => [
                    'mismatch_count' => $mismatchCount,
                    'severity' => 'critical',
                ],
                'action_required' => 'Review payment requests with hash mismatches and investigate potential tampering.',
                'link' => url('/system-health'),
            ]));
            $this->info("Sent hash mismatch alert to " . count($adminEmails) . " admins.");
        }
    }
}
