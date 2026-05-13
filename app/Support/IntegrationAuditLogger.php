<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class IntegrationAuditLogger
{
    /**
     * Log machinery payment integration event
     */
    public static function logMachineryPaymentEvent(string $event, array $context = []): void
    {
        Log::channel('payment_integration')->info($event, array_merge([
            'integration' => 'machinery_payment_request',
            'timestamp' => now()->toDateTimeString(),
            'user_id' => auth()->id(),
        ], $context));
    }
    
    /**
     * Log settlement calculation
     */
    public static function logSettlementCalculation(int $requestId, string $status, float $totalPaid): void
    {
        self::logMachineryPaymentEvent('settlement_calculated', [
            'payment_request_id' => $requestId,
            'settlement_status' => $status,
            'total_paid' => $totalPaid,
        ]);
    }
    
    /**
     * Log payment linkage event
     */
    public static function logPaymentLinkage(int $paymentId, string $sourceType, int $sourceId): void
    {
        self::logMachineryPaymentEvent('payment_linked', [
            'payment_id' => $paymentId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
    
    /**
     * Log orphan record detection
     */
    public static function logOrphanRecord(string $type, array $details): void
    {
        self::logMachineryPaymentEvent('orphan_detected', [
            'orphan_type' => $type,
            'details' => $details,
        ]);
    }
    
    /**
     * Log invalid source type
     */
    public static function logInvalidSourceType(string $sourceType, array $context = []): void
    {
        self::logMachineryPaymentEvent('invalid_source_type', array_merge([
            'invalid_source_type' => $sourceType,
            'valid_sources' => PaymentSources::getAll(),
        ], $context));
    }
}
