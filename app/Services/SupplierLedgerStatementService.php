<?php

namespace App\Services;

use App\Models\Supplier;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupplierLedgerStatementService
{
    /**
     * Generate comprehensive supplier ledger statement
     */
    public static function generateStatement(int $supplierId, Carbon $from, Carbon $to, ?int $workspaceId = null): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        
        // Get opening balance
        $openingBalance = self::getOpeningBalance($supplierId, $from, $workspaceId);
        
        // Get work charges (credits)
        $workCharges = self::getWorkCharges($supplierId, $from, $to, $workspaceId);
        
        // Get diesel recovery (debits)
        $dieselRecovery = self::getDieselRecovery($supplierId, $from, $to, $workspaceId);
        
        // Get payments made
        $payments = self::getPayments($supplierId, $from, $to, $workspaceId);
        
        // Calculate closing balance
        $totalCredits = $workCharges['total_amount'];
        $totalDebits = $dieselRecovery['total_amount'] + $payments['total_amount'];
        $closingBalance = $openingBalance + $totalCredits - $totalDebits;
        
        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'code' => $supplier->supplier_code ?? null
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString()
            ],
            'balances' => [
                'opening_balance' => $openingBalance,
                'total_work_charges' => $totalCredits,
                'total_diesel_recovery' => $dieselRecovery['total_amount'],
                'total_payments' => $payments['total_amount'],
                'total_debits' => $totalDebits,
                'closing_balance' => $closingBalance
            ],
            'work_charges' => $workCharges,
            'diesel_recovery' => $dieselRecovery,
            'payments' => $payments,
            'summary' => [
                'total_transactions' => count($workCharges['transactions']) + count($dieselRecovery['transactions']) + count($payments['transactions']),
                'net_change' => $totalCredits - $totalDebits,
                'average_monthly_charges' => self::calculateAverageMonthlyCharges($supplierId, $from, $to, $workspaceId)
            ]
        ];
    }
    
    /**
     * Get opening balance for supplier
     */
    private static function getOpeningBalance(int $supplierId, Carbon $from, ?int $workspaceId): float
    {
        // Calculate balance from all transactions before the period
        $workChargesBefore = self::getWorkCharges($supplierId, Carbon::create(2000, 1, 1), $from->copy()->subDay(), $workspaceId);
        $dieselRecoveryBefore = self::getDieselRecovery($supplierId, Carbon::create(2000, 1, 1), $from->copy()->subDay(), $workspaceId);
        $paymentsBefore = self::getPayments($supplierId, Carbon::create(2000, 1, 1), $from->copy()->subDay(), $workspaceId);
        
        return ($workChargesBefore['total_amount'] ?? 0) - 
               (($dieselRecoveryBefore['total_amount'] ?? 0) + ($paymentsBefore['total_amount'] ?? 0));
    }
    
    /**
     * Get work charges for supplier
     */
    private static function getWorkCharges(int $supplierId, Carbon $from, Carbon $to, ?int $workspaceId): array
    {
        $query = MachineryPaymentRequest::where('supplier_id', $supplierId)
            ->whereBetween('period_start', [$from, $to])
            ->where('status', 'paid')
            ->with(['machinery']);
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        $paymentRequests = $query->orderBy('period_start')->get();
        
        $transactions = [];
        $totalAmount = 0;
        
        foreach ($paymentRequests as $pr) {
            $amount = $pr->gross_amount ?? $pr->net_payable;
            $totalAmount += $amount;
            
            $transactions[] = [
                'date' => $pr->period_start,
                'description' => "Machinery work charges - {$pr->machinery->name}",
                'reference' => "Payment Request #{$pr->id}",
                'amount' => $amount,
                'type' => 'credit',
                'machinery_id' => $pr->machinery_id,
                'machinery_name' => $pr->machinery->name,
                'period_start' => $pr->period_start,
                'period_end' => $pr->period_end,
                'calculation_method' => $pr->calculation_method ?? 'legacy'
            ];
        }
        
        return [
            'transactions' => $transactions,
            'total_amount' => $totalAmount,
            'count' => count($transactions)
        ];
    }
    
    /**
     * Get diesel recovery for supplier
     */
    private static function getDieselRecovery(int $supplierId, Carbon $from, Carbon $to, ?int $workspaceId): array
    {
        $query = MachineryPaymentRequest::where('supplier_id', $supplierId)
            ->whereBetween('period_start', [$from, $to])
            ->where('status', 'paid')
            ->where('diesel_deduction', '>', 0)
            ->with(['machinery']);
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        $paymentRequests = $query->orderBy('period_start')->get();
        
        $transactions = [];
        $totalAmount = 0;
        
        foreach ($paymentRequests as $pr) {
            $amount = $pr->diesel_deduction;
            $totalAmount += $amount;
            
            $transactions[] = [
                'date' => $pr->period_start,
                'description' => "Diesel recovery - {$pr->machinery->name}",
                'reference' => "Payment Request #{$pr->id}",
                'amount' => $amount,
                'type' => 'debit',
                'machinery_id' => $pr->machinery_id,
                'machinery_name' => $pr->machinery->name,
                'period_start' => $pr->period_start,
                'period_end' => $pr->period_end,
                'diesel_liters' => $pr->diesel_breakdown['total_liters'] ?? 0,
                'diesel_rate' => self::getAverageDieselRateForPR($pr)
            ];
        }
        
        return [
            'transactions' => $transactions,
            'total_amount' => $totalAmount,
            'total_liters' => array_sum(array_column($transactions, 'diesel_liters')),
            'count' => count($transactions)
        ];
    }
    
    /**
     * Get payments made to supplier
     */
    private static function getPayments(int $supplierId, Carbon $from, Carbon $to, ?int $workspaceId): array
    {
        // This would need to be adapted based on your actual payment tracking system
        // For now, assuming there's a payments table linked to suppliers
        
        $query = DB::table('payments')
            ->where('supplier_id', $supplierId)
            ->whereBetween('payment_date', [$from, $to])
            ->where('status', 'completed');
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        $payments = $query->orderBy('payment_date')->get();
        
        $transactions = [];
        $totalAmount = 0;
        
        foreach ($payments as $payment) {
            $amount = $payment->amount;
            $totalAmount += $amount;
            
            $transactions[] = [
                'date' => $payment->payment_date,
                'description' => $payment->description ?? "Payment to supplier",
                'reference' => "Payment #{$payment->id}",
                'amount' => $amount,
                'type' => 'debit',
                'payment_method' => $payment->payment_method ?? 'bank_transfer',
                'payment_reference' => $payment->reference_number ?? null
            ];
        }
        
        return [
            'transactions' => $transactions,
            'total_amount' => $totalAmount,
            'count' => count($transactions)
        ];
    }
    
    /**
     * Get average diesel rate for payment request
     */
    private static function getAverageDieselRateForPR(MachineryPaymentRequest $pr): float
    {
        if (!$pr->diesel_breakdown || !isset($pr->diesel_breakdown['total_cost']) || !isset($pr->diesel_breakdown['total_liters'])) {
            return 90.00; // Default rate
        }
        
        $totalCost = $pr->diesel_breakdown['total_cost'];
        $totalLiters = $pr->diesel_breakdown['total_liters'];
        
        return $totalLiters > 0 ? $totalCost / $totalLiters : 90.00;
    }
    
    /**
     * Calculate average monthly charges
     */
    private static function calculateAverageMonthlyCharges(int $supplierId, Carbon $from, Carbon $to, ?int $workspaceId): float
    {
        $months = $from->diffInMonths($to) + 1;
        
        if ($months <= 0) {
            return 0;
        }
        
        $totalCharges = self::getWorkCharges($supplierId, $from, $to, $workspaceId)['total_amount'];
        
        return $totalCharges / $months;
    }
    
    /**
     * Generate summary statement for multiple suppliers
     */
    public static function generateSupplierSummary(array $supplierIds, Carbon $from, Carbon $to, ?int $workspaceId = null): array
    {
        $summaries = [];
        $grandTotals = [
            'total_work_charges' => 0,
            'total_diesel_recovery' => 0,
            'total_payments' => 0,
            'total_closing_balance' => 0
        ];
        
        foreach ($supplierIds as $supplierId) {
            $statement = self::generateStatement($supplierId, $from, $to, $workspaceId);
            
            $summaries[] = [
                'supplier_id' => $supplierId,
                'supplier_name' => $statement['supplier']['name'],
                'work_charges' => $statement['balances']['total_work_charges'],
                'diesel_recovery' => $statement['balances']['total_diesel_recovery'],
                'payments' => $statement['balances']['total_payments'],
                'closing_balance' => $statement['balances']['closing_balance'],
                'transaction_count' => $statement['summary']['total_transactions'],
                'net_change' => $statement['summary']['net_change']
            ];
            
            $grandTotals['total_work_charges'] += $statement['balances']['total_work_charges'];
            $grandTotals['total_diesel_recovery'] += $statement['balances']['total_diesel_recovery'];
            $grandTotals['total_payments'] += $statement['balances']['total_payments'];
            $grandTotals['total_closing_balance'] += $statement['balances']['closing_balance'];
        }
        
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString()
            ],
            'supplier_summaries' => $summaries,
            'grand_totals' => $grandTotals,
            'supplier_count' => count($supplierIds)
        ];
    }
    
    /**
     * Export statement to CSV format
     */
    public static function exportToCsv(array $statement): string
    {
        $csv = [];
        
        // Header
        $csv[] = ['Date', 'Description', 'Reference', 'Type', 'Amount', 'Balance'];
        
        $runningBalance = $statement['balances']['opening_balance'];
        
        // Add opening balance
        $csv[] = [
            $statement['period']['from'],
            'Opening Balance',
            '',
            'balance',
            $statement['balances']['opening_balance'],
            $runningBalance
        ];
        
        // Add work charges
        foreach ($statement['work_charges']['transactions'] as $transaction) {
            $runningBalance += $transaction['amount'];
            $csv[] = [
                $transaction['date'],
                $transaction['description'],
                $transaction['reference'],
                $transaction['type'],
                $transaction['amount'],
                $runningBalance
            ];
        }
        
        // Add diesel recovery
        foreach ($statement['diesel_recovery']['transactions'] as $transaction) {
            $runningBalance -= $transaction['amount'];
            $csv[] = [
                $transaction['date'],
                $transaction['description'],
                $transaction['reference'],
                $transaction['type'],
                -$transaction['amount'],
                $runningBalance
            ];
        }
        
        // Add payments
        foreach ($statement['payments']['transactions'] as $transaction) {
            $runningBalance -= $transaction['amount'];
            $csv[] = [
                $transaction['date'],
                $transaction['description'],
                $transaction['reference'],
                $transaction['type'],
                -$transaction['amount'],
                $runningBalance
            ];
        }
        
        // Convert to CSV string
        $csvLines = array_map(fn($row) => implode(',', $row), $csv);
        return implode("\n", $csvLines);
    }
    
    /**
     * Get supplier aging report
     */
    public static function getAgingReport(int $supplierId, ?int $workspaceId = null): array
    {
        $asOfDate = now();
        
        // Get outstanding payment requests
        $query = MachineryPaymentRequest::where('supplier_id', $supplierId)
            ->where('status', 'approved')
            ->where('net_payable', '>', 0);
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        $paymentRequests = $query->with(['machinery'])->get();
        
        $agingBuckets = [
            'current' => ['amount' => 0, 'count' => 0], // 0-30 days
            '31_60' => ['amount' => 0, 'count' => 0],   // 31-60 days
            '61_90' => ['amount' => 0, 'count' => 0],   // 61-90 days
            'over_90' => ['amount' => 0, 'count' => 0]   // Over 90 days
        ];
        
        $totalOutstanding = 0;
        
        foreach ($paymentRequests as $pr) {
            $daysOld = $asOfDate->diffInDays($pr->approved_at);
            $amount = $pr->net_payable;
            $totalOutstanding += $amount;
            
            if ($daysOld <= 30) {
                $agingBuckets['current']['amount'] += $amount;
                $agingBuckets['current']['count']++;
            } elseif ($daysOld <= 60) {
                $agingBuckets['31_60']['amount'] += $amount;
                $agingBuckets['31_60']['count']++;
            } elseif ($daysOld <= 90) {
                $agingBuckets['61_90']['amount'] += $amount;
                $agingBuckets['61_90']['count']++;
            } else {
                $agingBuckets['over_90']['amount'] += $amount;
                $agingBuckets['over_90']['count']++;
            }
        }
        
        return [
            'supplier_id' => $supplierId,
            'as_of_date' => $asOfDate->toDateString(),
            'total_outstanding' => $totalOutstanding,
            'aging_buckets' => $agingBuckets,
            'payment_requests' => $paymentRequests->map(fn($pr) => [
                'id' => $pr->id,
                'amount' => $pr->net_payable,
                'approved_date' => $pr->approved_at,
                'days_old' => $asOfDate->diffInDays($pr->approved_at),
                'machinery_name' => $pr->machinery->name,
                'period' => "{$pr->period_start} to {$pr->period_end}"
            ])->toArray()
        ];
    }
}
