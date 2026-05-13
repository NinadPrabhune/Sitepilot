<?php

namespace App\Services;

use App\Models\DailyConsumptionMaster;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DieselStockReconciliationService
{
    /**
     * Get comprehensive diesel stock reconciliation for a period
     */
    public static function getReconciliationReport(int $workspaceId, ?int $siteId, Carbon $from, Carbon $to): array
    {
        // Get diesel purchased (from purchases table - assuming structure)
        $dieselPurchased = self::getDieselPurchased($workspaceId, $siteId, $from, $to);
        
        // Get diesel issued to machinery
        $dieselIssued = self::getDieselIssued($workspaceId, $siteId, $from, $to);
        
        // Get diesel recovered from suppliers (through payment requests)
        $dieselRecovered = self::getDieselRecovered($workspaceId, $siteId, $from, $to);
        
        // Calculate current stock
        $openingStock = self::getOpeningStock($workspaceId, $siteId, $from);
        $currentStock = $openingStock + $dieselPurchased - $dieselIssued;
        
        // Calculate expected vs actual recovery
        $expectedRecovery = $dieselIssued; // Should match issued amount
        $recoveryVariance = $dieselRecovered - $expectedRecovery;
        
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString()
            ],
            'stock_movements' => [
                'opening_stock' => $openingStock,
                'diesel_purchased' => $dieselPurchased,
                'diesel_issued' => $dieselIssued,
                'diesel_recovered' => $dieselRecovered,
                'current_stock' => $currentStock
            ],
            'reconciliation' => [
                'expected_recovery' => $expectedRecovery,
                'actual_recovery' => $dieselRecovered,
                'recovery_variance' => $recoveryVariance,
                'recovery_rate' => $dieselIssued > 0 ? round(($dieselRecovered / $dieselIssued) * 100, 2) : 0
            ],
            'integrity_check' => [
                'stock_balanced' => abs($currentStock) < 0.01, // Allow small rounding differences
                'recovery_balanced' => abs($recoveryVariance) < 0.01,
                'issues' => self::identifyReconciliationIssues($openingStock, $dieselPurchased, $dieselIssued, $dieselRecovered, $currentStock)
            ]
        ];
    }

    /**
     * Get diesel purchased in period (assuming purchases table exists)
     */
    private static function getDieselPurchased(int $workspaceId, ?int $siteId, Carbon $from, Carbon $to): float
    {
        // This would need to be adapted based on your actual diesel purchase tracking
        // For now, assuming there's a diesel_purchases table
        if (DB::getSchemaBuilder()->hasTable('diesel_purchases')) {
            $query = DB::table('diesel_purchases')
                ->where('workspace_id', $workspaceId)
                ->whereBetween('purchase_date', [$from, $to]);
            
            if ($siteId) {
                $query->where('site_id', $siteId);
            }
            
            return $query->sum('quantity_liters') ?? 0;
        }
        
        // Alternative: Calculate from consumption entries with purchase source
        $query = DailyConsumptionMaster::whereHas('dailyProgressReport', function($q) use ($workspaceId, $siteId) {
            $q->where('workspace_id', $workspaceId);
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
        })
        ->whereBetween('consumption_date', [$from, $to])
        ->where('source_type', 'purchase'); // Assuming source_type field
        
        return $query->sum('diesel_consumed_liters') ?? 0;
    }

    /**
     * Get diesel issued to machinery
     */
    private static function getDieselIssued(int $workspaceId, ?int $siteId, Carbon $from, Carbon $to): float
    {
        $query = DailyConsumptionMaster::whereHas('dailyProgressReport', function($q) use ($workspaceId, $siteId) {
            $q->where('workspace_id', $workspaceId);
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
        })
        ->whereBetween('consumption_date', [$from, $to]);
        
        return $query->sum('diesel_consumed_liters') ?? 0;
    }

    /**
     * Get diesel recovered from suppliers through payment requests
     */
    private static function getDieselRecovered(int $workspaceId, ?int $siteId, Carbon $from, Carbon $to): float
    {
        $query = MachineryPaymentRequest::where('workspace_id', $workspaceId)
            ->whereBetween('period_start', [$from, $to])
            ->where('status', 'paid')
            ->where('diesel_deduction', '>', 0);
        
        if ($siteId) {
            $query->whereHas('machinery', function($q) use ($siteId) {
                $q->where('site_id', $siteId);
            });
        }
        
        // Get diesel deduction amounts and convert to liters (assuming average rate)
        $totalDeduction = $query->sum('diesel_deduction') ?? 0;
        
        // Convert monetary amount back to liters (using average rate from the period)
        $averageRate = self::getAverageDieselRate($workspaceId, $siteId, $from, $to);
        return $averageRate > 0 ? $totalDeduction / $averageRate : 0;
    }

    /**
     * Get opening stock for period
     */
    private static function getOpeningStock(int $workspaceId, ?int $siteId, Carbon $from): float
    {
        // Calculate stock before the period starts
        $yearStart = Carbon::create($from->year, 1, 1);
        
        $purchasesToDate = self::getDieselPurchased($workspaceId, $siteId, $yearStart, $from->copy()->subDay());
        $issuedToDate = self::getDieselIssued($workspaceId, $siteId, $yearStart, $from->copy()->subDay());
        
        return $purchasesToDate - $issuedToDate;
    }

    /**
     * Get average diesel rate for period
     */
    private static function getAverageDieselRate(int $workspaceId, ?int $siteId, Carbon $from, Carbon $to): float
    {
        $query = DailyConsumptionMaster::whereHas('dailyProgressReport', function($q) use ($workspaceId, $siteId) {
            $q->where('workspace_id', $workspaceId);
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
        })
        ->whereBetween('consumption_date', [$from, $to])
        ->where('diesel_rate', '>', 0);
        
        $totalLiters = $query->sum('diesel_consumed_liters') ?? 0;
        $totalCost = $query->sum('diesel_total_cost') ?? 0;
        
        return $totalLiters > 0 ? $totalCost / $totalLiters : 90; // Default rate
    }

    /**
     * Identify reconciliation issues
     */
    private static function identifyReconciliationIssues(float $openingStock, float $purchased, float $issued, float $recovered, float $currentStock): array
    {
        $issues = [];
        
        // Check for negative stock
        if ($currentStock < -1) { // Allow small negative for rounding
            $issues[] = [
                'type' => 'negative_stock',
                'severity' => 'high',
                'description' => "Negative stock detected: {$currentStock} liters",
                'value' => $currentStock
            ];
        }
        
        // Check for recovery mismatch
        $recoveryVariance = $recovered - $issued;
        if (abs($recoveryVariance) > 10) { // Allow 10 liters variance
            $issues[] = [
                'type' => 'recovery_mismatch',
                'severity' => 'medium',
                'description' => "Recovery variance: {$recoveryVariance} liters (Expected: {$issued}, Actual: {$recovered})",
                'expected' => $issued,
                'actual' => $recovered,
                'variance' => $recoveryVariance
            ];
        }
        
        // Check for unusual consumption patterns
        if ($issued > $purchased && $openingStock >= 0) {
            $issues[] = [
                'type' => 'over_consumption',
                'severity' => 'medium',
                'description' => "Diesel issued ({$issued}) exceeds purchases ({$purchased}) plus opening stock ({$openingStock})",
                'issued' => $issued,
                'available' => $purchased + $openingStock
            ];
        }
        
        return $issues;
    }

    /**
     * Get diesel reconciliation summary by month
     */
    public static function getMonthlyReconciliation(int $workspaceId, ?int $siteId, int $year): array
    {
        $monthlyData = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $from = Carbon::create($year, $month, 1);
            $to = $from->copy()->endOfMonth();
            
            $reconciliation = self::getReconciliationReport($workspaceId, $siteId, $from, $to);
            
            $monthlyData[] = [
                'month' => $month,
                'month_name' => $from->format('F'),
                'purchased' => $reconciliation['stock_movements']['diesel_purchased'],
                'issued' => $reconciliation['stock_movements']['diesel_issued'],
                'recovered' => $reconciliation['stock_movements']['diesel_recovered'],
                'recovery_rate' => $reconciliation['reconciliation']['recovery_rate'],
                'issues_count' => count($reconciliation['integrity_check']['issues']),
                'has_issues' => !$reconciliation['integrity_check']['stock_balanced'] || !$reconciliation['integrity_check']['recovery_balanced']
            ];
        }
        
        return $monthlyData;
    }

    /**
     * Get diesel stock alerts
     */
    public static function getStockAlerts(int $workspaceId, ?int $siteId): array
    {
        $alerts = [];
        $currentDate = now();
        
        // Check current stock levels
        $currentStock = self::getCurrentStock($workspaceId, $siteId);
        
        if ($currentStock < 100) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => 'high',
                'message' => "Low diesel stock: {$currentStock} liters remaining",
                'value' => $currentStock
            ];
        }
        
        // Check recent reconciliation issues
        $lastMonth = $currentDate->copy()->subMonth();
        $lastMonthReconciliation = self::getReconciliationReport($workspaceId, $siteId, $lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth());
        
        if (!$lastMonthReconciliation['integrity_check']['stock_balanced']) {
            $alerts[] = [
                'type' => 'reconciliation_issue',
                'severity' => 'medium',
                'message' => 'Stock reconciliation issues detected in previous month',
                'issues' => $lastMonthReconciliation['integrity_check']['issues']
            ];
        }
        
        // Check for unusual consumption patterns
        $recentConsumption = self::getRecentConsumptionPattern($workspaceId, $siteId, 7); // Last 7 days
        if ($recentConsumption['average_daily'] > 500) { // Unusually high consumption
            $alerts[] = [
                'type' => 'high_consumption',
                'severity' => 'medium',
                'message' => "Unusually high diesel consumption: {$recentConsumption['average_daily']} liters/day average",
                'data' => $recentConsumption
            ];
        }
        
        return $alerts;
    }

    /**
     * Get current stock level
     */
    private static function getCurrentStock(int $workspaceId, ?int $siteId): float
    {
        $yearStart = Carbon::create(now()->year, 1, 1);
        $today = now();
        
        $purchased = self::getDieselPurchased($workspaceId, $siteId, $yearStart, $today);
        $issued = self::getDieselIssued($workspaceId, $siteId, $yearStart, $today);
        
        return $purchased - $issued;
    }

    /**
     * Get recent consumption pattern
     */
    private static function getRecentConsumptionPattern(int $workspaceId, ?int $siteId, int $days): array
    {
        $from = now()->subDays($days);
        $to = now();
        
        $query = DailyConsumptionMaster::whereHas('dailyProgressReport', function($q) use ($workspaceId, $siteId) {
            $q->where('workspace_id', $workspaceId);
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
        })
        ->whereBetween('consumption_date', [$from, $to]);
        
        $totalConsumption = $query->sum('diesel_consumed_liters') ?? 0;
        $averageDaily = $totalConsumption / $days;
        
        return [
            'period_days' => $days,
            'total_consumption' => $totalConsumption,
            'average_daily' => round($averageDaily, 2),
            'daily_breakdown' => $query->selectRaw('DATE(consumption_date) as date, SUM(diesel_consumed_liters) as daily_consumption')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray()
        ];
    }

    /**
     * Validate diesel transaction before posting
     */
    public static function validateDieselTransaction(array $transactionData, int $workspaceId, ?int $siteId): array
    {
        $issues = [];
        
        // Validate sufficient stock for issuance
        if (isset($transactionData['type']) && $transactionData['type'] === 'issuance') {
            $currentStock = self::getCurrentStock($workspaceId, $siteId);
            $issuanceAmount = $transactionData['quantity_liters'] ?? 0;
            
            if ($issuanceAmount > $currentStock + 10) { // Allow 10 liters buffer
                $issues[] = "Insufficient diesel stock: Available {$currentStock} liters, trying to issue {$issuanceAmount} liters";
            }
        }
        
        // Validate reasonable quantities
        $quantity = $transactionData['quantity_liters'] ?? 0;
        if ($quantity < 0) {
            $issues[] = "Quantity cannot be negative";
        }
        
        if ($quantity > 1000) {
            $issues[] = "Unusually large quantity: {$quantity} liters";
        }
        
        // Validate rate
        $rate = $transactionData['diesel_rate'] ?? 0;
        if ($rate <= 0) {
            $issues[] = "Diesel rate must be positive";
        }
        
        if ($rate > 200) {
            $issues[] = "Unusually high diesel rate: ₹{$rate}/liter";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
}
