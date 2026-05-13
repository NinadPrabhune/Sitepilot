<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Financial Integrity Watchdog
 * Runtime monitoring for DPR system financial consistency
 */
class FinancialIntegrityWatchdog
{
    /**
     * Run all integrity checks
     */
    public function runAllChecks(): array
    {
        $results = [];
        
        $results['dpr_vs_ledger_mismatch'] = $this->checkDprVsLedgerMismatch();
        $results['duplicate_ledger_entries'] = $this->checkDuplicateLedgerEntries();
        $results['orphan_ledger_entries'] = $this->checkOrphanLedgerEntries();
        $results['negative_balances'] = $this->checkNegativeBalances();
        $results['calculation_hash_integrity'] = $this->checkCalculationHashIntegrity();
        $results['payment_status_consistency'] = $this->checkPaymentStatusConsistency();
        $results['period_lock_violations'] = $this->checkPeriodLockViolations();
        
        // Log overall health
        $hasIssues = collect($results)->contains(function ($result) {
            return !empty($result['issues']);
        });
        
        if ($hasIssues) {
            Log::warning('Financial integrity issues detected', [
                'timestamp' => now()->toISOString(),
                'results' => $results,
            ]);
        } else {
            Log::info('Financial integrity check passed', [
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        return $results;
    }
    
    /**
     * Check DPR vs Ledger amount mismatch
     */
    public function checkDprVsLedgerMismatch(): array
    {
        $issues = [];
        
        $mismatches = DB::select("
            SELECT 
                d.id as dpr_id,
                d.calculated_amount as dpr_amount,
                COALESCE(SUM(l.amount), 0) as ledger_amount,
                ABS(d.calculated_amount - COALESCE(SUM(l.amount), 0)) as difference
            FROM daily_progress_reports d
            LEFT JOIN machinery_ledgers l ON l.dpr_id = d.id AND l.is_reversal = false
            WHERE d.deleted_at IS NULL
            GROUP BY d.id, d.calculated_amount
            HAVING ABS(d.calculated_amount - COALESCE(SUM(l.amount), 0)) > 0.01
        ");
        
        foreach ($mismatches as $mismatch) {
            $issues[] = [
                'type' => 'dpr_ledger_mismatch',
                'dpr_id' => $mismatch->dpr_id,
                'dpr_amount' => $mismatch->dpr_amount,
                'ledger_amount' => $mismatch->ledger_amount,
                'difference' => $mismatch->difference,
                'severity' => $mismatch->difference > 100 ? 'high' : 'medium',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check for duplicate ledger entries
     */
    public function checkDuplicateLedgerEntries(): array
    {
        $issues = [];
        
        $duplicates = DB::select("
            SELECT 
                reference_type,
                reference_id,
                COUNT(*) as duplicate_count,
                GROUP_CONCAT(id) as ledger_ids
            FROM machinery_ledgers
            WHERE is_reversal = false
            GROUP BY reference_type, reference_id
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicates as $duplicate) {
            $issues[] = [
                'type' => 'duplicate_ledger_entries',
                'reference_type' => $duplicate->reference_type,
                'reference_id' => $duplicate->reference_id,
                'duplicate_count' => $duplicate->duplicate_count,
                'ledger_ids' => $duplicate->ledger_ids,
                'severity' => 'high',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check for orphan ledger entries
     */
    public function checkOrphanLedgerEntries(): array
    {
        $issues = [];
        
        // Orphan DPR ledger entries
        $orphanDprLedgers = DB::select("
            SELECT l.id, l.dpr_id, l.reference_id
            FROM machinery_ledgers l
            LEFT JOIN daily_progress_reports d ON l.dpr_id = d.id
            WHERE l.dpr_id IS NOT NULL 
            AND d.id IS NULL
            AND l.is_reversal = false
        ");
        
        foreach ($orphanDprLedgers as $orphan) {
            $issues[] = [
                'type' => 'orphan_dpr_ledger',
                'ledger_id' => $orphan->id,
                'dpr_id' => $orphan->dpr_id,
                'reference_id' => $orphan->reference_id,
                'severity' => 'medium',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check for negative balances
     */
    public function checkNegativeBalances(): array
    {
        $issues = [];
        
        $negativeBalances = DB::select("
            SELECT 
                machinery_id,
                running_balance,
                date,
                id
            FROM machinery_ledgers
            WHERE running_balance < 0
            AND is_reversal = false
            ORDER BY machinery_id, date
        ");
        
        foreach ($negativeBalances as $balance) {
            $issues[] = [
                'type' => 'negative_balance',
                'machinery_id' => $balance->machinery_id,
                'ledger_id' => $balance->id,
                'balance' => $balance->running_balance,
                'date' => $balance->date,
                'severity' => 'high',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check calculation hash integrity
     */
    public function checkCalculationHashIntegrity(): array
    {
        $issues = [];
        
        $invalidHashes = DB::select("
            SELECT d.id, d.calculation_hash
            FROM daily_progress_reports d
            WHERE d.deleted_at IS NULL
            AND d.calculation_hash IS NOT NULL
            AND d.calculation_hash != SHA2(
                CONCAT(
                    COALESCE(d.machine_start_reading, 0), '|',
                    COALESCE(d.machine_end_reading, 0), '|',
                    COALESCE(d.machine_idle_reading, 0), '|',
                    COALESCE(d.rate_snapshot, 0)
                ), 256
            )
        ");
        
        foreach ($invalidHashes as $invalid) {
            $issues[] = [
                'type' => 'calculation_hash_invalid',
                'dpr_id' => $invalid->id,
                'stored_hash' => $invalid->calculation_hash,
                'severity' => 'high',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check payment status consistency
     */
    public function checkPaymentStatusConsistency(): array
    {
        $issues = [];
        
        $inconsistentStatus = DB::select("
            SELECT 
                d.id as dpr_id,
                d.payment_status as dpr_status,
                l.dpr_payment_status as ledger_status
            FROM daily_progress_reports d
            JOIN machinery_ledgers l ON l.dpr_id = d.id
            WHERE d.payment_status != l.dpr_payment_status
            AND l.is_reversal = false
        ");
        
        foreach ($inconsistentStatus as $inconsistent) {
            $issues[] = [
                'type' => 'payment_status_inconsistent',
                'dpr_id' => $inconsistent->dpr_id,
                'dpr_status' => $inconsistent->dpr_status,
                'ledger_status' => $inconsistent->ledger_status,
                'severity' => 'medium',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Check period lock violations
     */
    public function checkPeriodLockViolations(): array
    {
        $issues = [];
        
        $violations = DB::select("
            SELECT 
                d.id as dpr_id,
                d.date as dpr_date,
                p.period_start,
                p.period_end,
                p.status as period_status
            FROM daily_progress_reports d
            JOIN financial_periods p ON d.date BETWEEN p.period_start AND p.period_end
            WHERE p.status = 'locked'
            AND d.deleted_at IS NULL
        ");
        
        foreach ($violations as $violation) {
            $issues[] = [
                'type' => 'period_lock_violation',
                'dpr_id' => $violation->dpr_id,
                'dpr_date' => $violation->dpr_date,
                'period_start' => $violation->period_start,
                'period_end' => $violation->period_end,
                'severity' => 'high',
            ];
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Get system health summary
     */
    public function getHealthSummary(): array
    {
        $results = $this->runAllChecks();
        
        $totalIssues = collect($results)->sum('count');
        $highSeverityIssues = collect($results)
            ->flatMap(fn($result) => $result['issues'])
            ->where('severity', 'high')
            ->count();
        
        return [
            'overall_health' => $totalIssues === 0 ? 'healthy' : ($highSeverityIssues > 0 ? 'critical' : 'warning'),
            'total_issues' => $totalIssues,
            'high_severity_issues' => $highSeverityIssues,
            'last_check' => now()->toISOString(),
            'checks_performed' => array_keys($results),
        ];
    }
}
