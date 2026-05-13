<?php

namespace App\Support;

class PaymentSources
{
    public const MACHINERY_PAYMENT_REQUEST = 'machinery_payment_request';
    public const PURCHASE_INVOICE = 'purchase_invoice';
    public const PO_ADVANCE = 'po_advance';
    
    /**
     * Check if source type is valid
     */
    public static function isValid(string $source): bool
    {
        return in_array($source, [
            self::MACHINERY_PAYMENT_REQUEST,
            self::PURCHASE_INVOICE,
            self::PO_ADVANCE,
        ]);
    }
    
    /**
     * Get all valid source types
     */
    public static function getAll(): array
    {
        return [
            self::MACHINERY_PAYMENT_REQUEST,
            self::PURCHASE_INVOICE,
            self::PO_ADVANCE,
        ];
    }
    
    /**
     * Get human-readable label for source type
     */
    public static function getLabel(string $source): string
    {
        return match($source) {
            self::MACHINERY_PAYMENT_REQUEST => 'Machinery Payment Request',
            self::PURCHASE_INVOICE => 'Purchase Invoice',
            self::PO_ADVANCE => 'PO Advance',
            default => 'Unknown Source',
        };
    }
}
