<?php

namespace App\Domain\Machinery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read Model Validator
 * Validates reporting layer consistency with source data
 */
class ReadModelValidator
{
    /**
     * Validate all read models
     */
    public function validateAllReadModels(): array
    {
        $results = [];
        
        $results['dpr_totals_vs_ledger_totals'] = $this->validateDprTotalsVsLedgerTotals();
        $results['machinery_balances'] = $this->validateMachineryBalances();
        $results['daily_aggregations'] = $this->validateDailyAggregations();
        $results['monthly_aggregations'] = $this->validateMonthlyAggregations();
        $results['payment_status_reports'] = $this->validatePaymentStatusReports();
        
        $hasIssues = collect($results)->contains(function ($result) {
            return !empty($result['issues']);
        });
        
        if ($hasIssues) {
            Log::warning('Read model validation issues detected', [
                'timestamp' => now()->toISOString(),
                'results' => $results,
            ]);
        }
        
        return $results;
    }
    
    /**
     * Validate DPR totals vs Ledger totals
     */
    public function validateDprTotalsVsLedgerTotals(): array
    {
        $issues = [];
        
        // Get DPR totals by machinery
        $dprTotals = DB::select("
            SELECT 
                machinery_id,
                SUM(calculated_amount) as total_dpr_amount,
                COUNT(*) as dpr_count
            FROM daily_progress_reports
            WHERE deleted_at IS NULL
            GROUP BY machinery_id
        ");
        
        // Get Ledger totals by machinery
        $ledgerTotals = DB::select("
            SELECT 
                machinery_id,
                SUM(amount) as total_ledger_amount,
                COUNT(*) as ledger_count
            FROM machinery_ledgers
            WHERE reference_type = 'DailyProgressReport'
            AND is_reversal = false
            GROUP BY machinery_id
        ");
        
        // Create lookup arrays
        $dprLookup = [];
        foreach ($dprTotals as $dpr) {
            $dprLookup[$dpr->machinery_id] = $dpr;
        }
        
        $ledgerLookup = [];
        foreach ($ledgerTotals as $ledger) {
            $ledgerLookup[$ledger->machinery_id] = $ledger;
        }
        
        // Compare totals
        $allMachineryIds = array_unique(array_merge(
            array_keys($dprLookup),
            array_keys($ledgerLookup)
        ));
        
        foreach ($allMachineryIds as $machineryId) {
            $dprTotal = $dprLookup[$machineryId] ?? null;
            $ledgerTotal = $ledgerLookup[$machineryId] ?? null;
            
            $dprAmount = $dprTotal->total_dpr_amount ?? 0;
            $ledgerAmount = $ledgerTotal->total_ledger_amount ?? 0;
            
            if (abs($dprAmount - $ledgerAmount) > 0.01) {
                $issues[] = [
                    'type' => 'dpr_ledger_total_mismatch',
                    'machinery_id' => $machineryId,
                    'dpr_total' => $dprAmount,
                    'ledger_total' => $ledgerAmount,
                    'difference' => abs($dprAmount - $ledgerAmount),
                    'dpr_count' => $dprTotal->dpr_count ?? 0,
                    'ledger_count' => $ledgerTotal->ledger_count ?? 0,
                    'severity' => abs($dprAmount - $ledgerAmount) > 100 ? 'high' : 'medium',
                ];
            }
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Validate machinery balances
     */
    public function validateMachineryBalances(): array
    {
        $issues = [];
        
        // Get current balances from ledger
        $balances = DB::select("
            SELECT 
                machinery_id,
                running_balance,
                date,
                id
            FROM machinery_ledgers
            WHERE id IN (
                SELECT MAX(id)
                FROM machinery_ledgers
                WHERE is_reversal = false
                GROUP BY machinery_id
            )
        ");
        
        // Recalculate balances
        foreach ($balances as $balance) {
            $recalculatedBalance = DB::selectOne("
                SELECT SUM(amount) as total_balance
                FROM machinery_ledgers
                WHERE machinery_id = ?
                AND is_reversal = false
                AND date <= ?
            ", [$balance->machinery_id, $balance->date]);
            
            $actualBalance = $recalculatedBalance->total_balance ?? 0;
            
            if (abs($balance->running_balance - $actualBalance) > 0.01) {
                $issues[] = [
                    'type' => 'balance_calculation_error',
                    'machinery_id' => $balance->machinery_id,
                    'stored_balance' => $balance->running_balance,
                    'calculated_balance' => $actualBalance,
                    'difference' => abs($balance->running_balance - $actualBalance),
                    'ledger_id' => $balance->id,
                    'severity' => 'high',
                ];
            }
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Validate daily aggregations
     */
    public function validateDailyAggregations(): array
    {
        $issues = [];
        
        // Get daily DPR totals
        $dailyDprTotals = DB::select("
            SELECT 
                DATE(date) as report_date,
                SUM(calculated_amount) as daily_total
            FROM daily_progress_reports
            WHERE deleted_at IS NULL
            GROUP BY DATE(date)
        ");
        
        // Get daily Ledger totals
        $dailyLedgerTotals = DB::select("
            SELECT 
                DATE(date) as report_date,
                SUM(amount) as daily_total
            FROM machinery_ledgers
            WHERE reference_type = 'DailyProgressReport'
            AND is_reversal = false
            GROUP BY DATE(date)
        ");
        
        // Create lookup arrays
        $dprDailyLookup = [];
        foreach ($dailyDprTotals as $dpr) {
            $dprDailyLookup[$dpr->report_date] = $dpr->daily_total;
        }
        
        $ledgerDailyLookup = [];
        foreach ($dailyLedgerTotals as $ledger) {
            $ledgerDailyLookup[$ledger->report_date] = $ledger->daily_total;
        }
        
        // Compare daily totals
        $allDates = array_unique(array_merge(
            array_keys($dprDailyLookup),
            array_keys($ledgerDailyLookup)
        ));
        
        foreach ($allDates as $date) {
            $dprTotal = $dprDailyLookup[$date] ?? 0;
            $ledgerTotal = $ledgerDailyLookup[$date] ?? 0;
            
            if (abs($dprTotal - $ledgerTotal) > 0.01) {
                $issues[] = [
                    'type' => 'daily_aggregation_mismatch',
                    'date' => $date,
                    'dpr_total' => $dprTotal,
                    'ledger_total' => $ledgerTotal,
                    'difference' => abs($dprTotal - $ledgerTotal),
                    'severity' => 'medium',
                ];
            }
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Validate monthly aggregations
     */
    public function validateMonthlyAggregations(): array
    {
        $issues = [];
        
        // Get monthly DPR totals
        $monthlyDprTotals = DB::select("
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as report_month,
                SUM(calculated_amount) as monthly_total
            FROM daily_progress_reports
            WHERE deleted_at IS NULL
            GROUP BY DATE_FORMAT(date, '%Y-%m')
        ");
        
        // Get monthly Ledger totals
        $monthlyLedgerTotals = DB::select("
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as report_month,
                SUM(amount) as monthly_total
            FROM machinery_ledgers
            WHERE reference_type = 'DailyProgressReport'
            AND is_reversal = false
            GROUP BY DATE_FORMAT(date, '%Y-%m')
        ");
        
        // Create lookup arrays
        $dprMonthlyLookup = [];
        foreach ($monthlyDprTotals as $dpr) {
            $dprMonthlyLookup[$dpr->report_month] = $dpr->monthly_total;
        }
        
        $ledgerMonthlyLookup = [];
        foreach ($monthlyLedgerTotals as $ledger) {
            $ledgerMonthlyLookup[$ledger->report_month] = $ledger->monthly_total;
        }
        
        // Compare monthly totals
        $allMonths = array_unique(array_merge(
            array_keys($dprMonthlyLookup),
            array_keys($ledgerMonthlyLookup)
        ));
        
        foreach ($allMonths as $month) {
            $dprTotal = $dprMonthlyLookup[$month] ?? 0;
            $ledgerTotal = $ledgerMonthlyLookup[$month] ?? 0;
            
            if (abs($dprTotal - $ledgerTotal) > 0.01) {
                $issues[] = [
                    'type' => 'monthly_aggregation_mismatch',
                    'month' => $month,
                    'dpr_total' => $dprTotal,
                    'ledger_total' => $ledgerTotal,
                    'difference' => abs($dprTotal - $ledgerTotal),
                    'severity' => 'high',
                ];
            }
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Validate payment status reports
     */
    public function validatePaymentStatusReports(): array
    {
        $issues = [];
        
        // Get payment status counts from DPR
        $dprStatusCounts = DB::select("
            SELECT 
                payment_status,
                COUNT(*) as dpr_count,
                SUM(calculated_amount) as total_amount
            FROM daily_progress_reports
            WHERE deleted_at IS NULL
            GROUP BY payment_status
        ");
        
        // Get payment status counts from Ledger
        $ledgerStatusCounts = DB::select("
            SELECT 
                dpr_payment_status,
                COUNT(*) as ledger_count,
                SUM(amount) as total_amount
            FROM machinery_ledgers
            WHERE dpr_payment_status IS NOT NULL
            AND is_reversal = false
            GROUP BY dpr_payment_status
        ");
        
        // Create lookup arrays
        $dprStatusLookup = [];
        foreach ($dprStatusCounts as $dpr) {
            $dprStatusLookup[$dpr->payment_status] = [
                'count' => $dpr->dpr_count,
                'amount' => $dpr->total_amount,
            ];
        }
        
        $ledgerStatusLookup = [];
        foreach ($ledgerStatusCounts as $ledger) {
            $ledgerStatusLookup[$ledger->dpr_payment_status] = [
                'count' => $ledger->ledger_count,
                'amount' => $ledger->total_amount,
            ];
        }
        
        // Compare status counts
        $allStatuses = array_unique(array_merge(
            array_keys($dprStatusLookup),
            array_keys($ledgerStatusLookup)
        ));
        
        foreach ($allStatuses as $status) {
            $dprData = $dprStatusLookup[$status] ?? ['count' => 0, 'amount' => 0];
            $ledgerData = $ledgerStatusLookup[$status] ?? ['count' => 0, 'amount' => 0];
            
            if ($dprData['count'] !== $ledgerData['count'] || abs($dprData['amount'] - $ledgerData['amount']) > 0.01) {
                $issues[] = [
                    'type' => 'payment_status_report_mismatch',
                    'status' => $status,
                    'dpr_count' => $dprData['count'],
                    'ledger_count' => $ledgerData['count'],
                    'dpr_amount' => $dprData['amount'],
                    'ledger_amount' => $ledgerData['amount'],
                    'count_difference' => $dprData['count'] - $ledgerData['count'],
                    'amount_difference' => abs($dprData['amount'] - $ledgerData['amount']),
                    'severity' => 'medium',
                ];
            }
        }
        
        return ['issues' => $issues, 'count' => count($issues)];
    }
    
    /**
     * Get validation summary
     */
    public function getValidationSummary(): array
    {
        $results = $this->validateAllReadModels();
        
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
