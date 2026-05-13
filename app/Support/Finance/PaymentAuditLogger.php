<?php

namespace App\Support\Finance;

use Illuminate\Support\Facades\Log;

class PaymentAuditLogger
{
    protected string $auditChannel = 'payment_audit'; // Critical financial events
    protected string $debugChannel = 'payment_debug'; // Dev-level logs
    
    public function logPaymentCreated(string $type, array $context): void
    {
        Log::channel($this->auditChannel)->info("{$type} payment created", $context);
    }
    
    public function logPaymentBlocked(string $type, string $reason, array $context): void
    {
        Log::channel($this->auditChannel)->warning("{$type} payment blocked: {$reason}", $context);
    }
    
    public function logStateTransition(string $type, string $from, string $to, array $context): void
    {
        Log::channel($this->auditChannel)->info("{$type} state transition: {$from} → {$to}", $context);
    }
    
    public function logCalculation(string $type, array $context): void
    {
        Log::channel($this->auditChannel)->info("{$type} calculation", $context);
    }
    
    public function logDebug(string $type, string $message, array $context): void
    {
        Log::channel($this->debugChannel)->debug("{$type}: {$message}", $context);
    }
}
