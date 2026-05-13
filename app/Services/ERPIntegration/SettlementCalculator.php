<?php

namespace App\Services\ERPIntegration;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Support\IntegrationAuditLogger;

class SettlementCalculator
{
    /**
     * Calculate settlement status for machinery payment request
     * Phase A: Read-only calculation
     */
    public static function calculateStatus(MachineryPaymentRequest $request): string
    {
        // Only count finalized ERP payments (posted status)
        $totalPaid = $request->payments()->posted()->sum('amount');
        $netPayable = $request->net_payable;
        
        $status = self::determineStatus($totalPaid, $netPayable);
        
        // Log calculation for audit trail
        IntegrationAuditLogger::logSettlementCalculation(
            $request->id,
            $status,
            $totalPaid
        );
        
        return $status;
    }
    
    /**
     * Determine settlement status based on amounts
     */
    private static function determineStatus(float $totalPaid, float $netPayable): string
    {
        if ($totalPaid == 0) return 'unpaid';
        if ($totalPaid < $netPayable) return 'partial';
        if ($totalPaid == $netPayable) return 'paid';
        return 'overpaid';
    }
    
    /**
     * Get payment totals breakdown
     */
    public static function getPaymentBreakdown(MachineryPaymentRequest $request): array
    {
        $postedPayments = $request->payments()->posted()->get();
        $totalPosted = $postedPayments->sum('amount');
        
        $allPayments = $request->payments()->get();
        $totalAll = $allPayments->sum('amount');
        
        return [
            'net_payable' => $request->net_payable,
            'total_posted' => $totalPosted,
            'total_all_payments' => $totalAll,
            'balance' => max(0, $request->net_payable - $totalPosted),
            'settlement_status' => self::determineStatus($totalPosted, $request->net_payable),
            'payment_count' => $postedPayments->count(),
        ];
    }
}
