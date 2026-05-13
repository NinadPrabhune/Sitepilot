<?php

namespace App\Services;

use App\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost Accounting Validation Service
 * Ensures reporting consistency and prevents cost/payable mixing
 */
class CostAccountingValidationService
{
    /**
     * Validate cost vs payable separation
     */
    public static function validateCostPayableSeparation(): array
    {
        $issues = [];
        
        // Get totals by ledger type
        $totals = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->selectRaw('
                ledger_type,
                SUM(amount) as total_amount,
                COUNT(*) as entry_count
            ')
            ->groupBy('ledger_type')
            ->get()
            ->keyBy('ledger_type');
        
        $internalCostTotal = $totals->get('internal_cost', (object)['total_amount' => 0])->total_amount;
        $expenseTotal = $totals->get('expense', (object)['total_amount' => 0])->total_amount;
        $payableTotal = $totals->get('payable', (object)['total_amount' => 0])->total_amount;
        
        // 🔴 CRITICAL: Check for cost/payable mixing
        $totalProjectCost = $internalCostTotal + $expenseTotal;
        
        if (abs($totalProjectCost - $payableTotal) < 0.01) {
            $issues[] = [
                'type' => 'cost_payable_equal',
                'severity' => 'critical',
                'message' => 'Total project cost equals total payables - possible cost/payable mixing detected',
                'data' => [
                    'internal_cost_total' => $internalCostTotal,
                    'expense_total' => $expenseTotal,
                    'payable_total' => $payableTotal,
                    'total_project_cost' => $totalProjectCost,
                ]
            ];
        }
        
        // Validate cost categories don't overlap
        $categoryTotals = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->selectRaw('
                cost_category,
                ledger_type,
                SUM(amount) as total_amount,
                COUNT(*) as entry_count
            ')
            ->groupBy('cost_category', 'ledger_type')
            ->get()
            ->groupBy('cost_category');
        
        foreach ($categoryTotals as $category => $entries) {
            $categoryTotal = $entries->sum('total_amount');
            
            // Check for machine cost appearing in expense ledger
            $machineExpenseEntry = $entries->firstWhere('ledger_type', 'expense');
            if ($category === 'machine' && $machineExpenseEntry && $machineExpenseEntry->total_amount > 0) {
                $issues[] = [
                    'type' => 'machine_cost_in_expense',
                    'severity' => 'high',
                    'message' => 'Machine cost found in expense ledger - potential double counting',
                    'data' => [
                        'category' => $category,
                        'amount' => $machineExpenseEntry->total_amount,
                    ]
                ];
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'internal_cost_total' => $internalCostTotal,
                'expense_total' => $expenseTotal,
                'payable_total' => $payableTotal,
                'total_project_cost' => $totalProjectCost,
                'cost_payable_difference' => abs($totalProjectCost - $payableTotal),
            ]
        ];
    }
    
    /**
     * Validate cost component integrity
     */
    public static function validateCostComponentIntegrity(): array
    {
        $issues = [];
        
        // Check for double counting risk scenarios
        $dprMachineCosts = DB::table('machinery_ledgers')
            ->join('daily_progress_reports', 'machinery_ledgers.dpr_id', '=', 'daily_progress_reports.id')
            ->where('machinery_ledgers.is_reversal', false)
            ->where('machinery_ledgers.cost_category', 'machine')
            ->where('machinery_ledgers.entry_type', 'reading')
            ->selectRaw('
                daily_progress_reports.id as dpr_id,
                daily_progress_reports.date,
                daily_progress_reports.calculated_amount,
                machinery_ledgers.amount as ledger_amount,
                ABS(daily_progress_reports.calculated_amount - machinery_ledgers.amount) as difference
            ')
            ->get();
        
        foreach ($dprMachineCosts as $cost) {
            if ($cost->difference > 0.01) {
                $issues[] = [
                    'type' => 'dpr_ledger_amount_mismatch',
                    'severity' => 'medium',
                    'message' => "DPR calculated amount doesn't match ledger amount",
                    'data' => [
                        'dpr_id' => $cost->dpr_id,
                        'date' => $cost->date,
                        'dpr_amount' => $cost->calculated_amount,
                        'ledger_amount' => $cost->ledger_amount,
                        'difference' => $cost->difference,
                    ]
                ];
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'dpr_machine_costs_checked' => $dprMachineCosts->count(),
                'mismatches_found' => count($issues),
            ]
        ];
    }
    
    /**
     * Generate cost accounting report
     */
    public static function generateCostAccountingReport(): array
    {
        // Get comprehensive cost breakdown
        $costBreakdown = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->selectRaw('
                ledger_type,
                cost_category,
                SUM(amount) as total_amount,
                COUNT(*) as entry_count,
                MIN(date) as first_date,
                MAX(date) as last_date
            ')
            ->groupBy('ledger_type', 'cost_category')
            ->orderBy('ledger_type')
            ->orderBy('cost_category')
            ->get();
        
        // Get monthly trends
        $monthlyTrends = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->selectRaw('
                DATE_FORMAT(date, "%Y-%m") as month,
                ledger_type,
                SUM(amount) as total_amount
            ')
            ->groupBy('month', 'ledger_type')
            ->orderBy('month')
            ->orderBy('ledger_type')
            ->get()
            ->groupBy('month');
        
        // Get machinery breakdown
        $machineryBreakdown = DB::table('machinery_ledgers')
            ->join('machineries', 'machinery_ledgers.machinery_id', '=', 'machineries.id')
            ->where('machinery_ledgers.is_reversal', false)
            ->selectRaw('
                machineries.id,
                machineries.name,
                machineries.owned_by,
                machinery_ledgers.ledger_type,
                SUM(machinery_ledgers.amount) as total_amount
            ')
            ->groupBy('machineries.id', 'machineries.name', 'machineries.owned_by', 'machinery_ledgers.ledger_type')
            ->orderBy('machineries.name')
            ->orderBy('machinery_ledgers.ledger_type')
            ->get()
            ->groupBy('id');
        
        return [
            'cost_breakdown' => $costBreakdown,
            'monthly_trends' => $monthlyTrends,
            'machinery_breakdown' => $machineryBreakdown,
            'validation' => self::validateCostPayableSeparation(),
            'component_integrity' => self::validateCostComponentIntegrity(),
            'generated_at' => now(),
        ];
    }
    
    /**
     * Check for potential double counting scenarios
     */
    public static function checkDoubleCountingScenarios(): array
    {
        $scenarios = [];
        
        // Scenario 1: Machine cost in expense ledger
        $machineInExpense = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->where('cost_category', 'machine')
            ->where('ledger_type', 'expense')
            ->count();
        
        if ($machineInExpense > 0) {
            $scenarios[] = [
                'type' => 'machine_cost_in_expense',
                'risk' => 'high',
                'description' => 'Machine costs found in expense ledger - potential double counting',
                'count' => $machineInExpense,
                'recommendation' => 'Review and reclassify machine costs to proper ledger type',
            ];
        }
        
        // Scenario 2: Same DPR with multiple machine cost entries
        $duplicateMachineCosts = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->where('cost_category', 'machine')
            ->where('entry_type', 'reading')
            ->select('dpr_id', DB::raw('COUNT(*) as entry_count'))
            ->groupBy('dpr_id')
            ->having('entry_count', '>', 1)
            ->get();
        
        if ($duplicateMachineCosts->count() > 0) {
            $scenarios[] = [
                'type' => 'duplicate_machine_costs',
                'risk' => 'high',
                'description' => 'Multiple machine cost entries found for same DPR',
                'count' => $duplicateMachineCosts->count(),
                'affected_dprs' => $duplicateMachineCosts->pluck('dpr_id'),
                'recommendation' => 'Review DPRs with duplicate machine cost entries and create reversals',
            ];
        }
        
        // Scenario 3: Cost category inconsistencies
        $categoryInconsistencies = DB::table('machinery_ledgers')
            ->where('is_reversal', false)
            ->selectRaw('
                entry_type,
                cost_category,
                COUNT(*) as count
            ')
            ->groupBy('entry_type', 'cost_category')
            ->get();
        
        foreach ($categoryInconsistencies as $inconsistency) {
            $expectedCategory = match($inconsistency->entry_type) {
                'reading' => 'machine',
                'diesel' => 'diesel',
                'maintenance' => 'maintenance',
                'advance' => 'advance',
                default => null
            };
            
            if ($expectedCategory && $inconsistency->cost_category !== $expectedCategory) {
                $scenarios[] = [
                    'type' => 'category_inconsistency',
                    'risk' => 'medium',
                    'description' => "Entry type '{$inconsistency->entry_type}' has unexpected cost category '{$inconsistency->cost_category}'",
                    'count' => $inconsistency->count,
                    'expected_category' => $expectedCategory,
                    'actual_category' => $inconsistency->cost_category,
                    'recommendation' => 'Review and correct cost category assignments',
                ];
            }
        }
        
        return [
            'scenarios' => $scenarios,
            'total_risk_scenarios' => count($scenarios),
            'high_risk_count' => count(array_filter($scenarios, fn($s) => $s['risk'] === 'high')),
            'medium_risk_count' => count(array_filter($scenarios, fn($s) => $s['risk'] === 'medium')),
        ];
    }
}
