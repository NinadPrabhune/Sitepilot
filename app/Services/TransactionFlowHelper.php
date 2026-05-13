<?php

namespace App\Services;

use Illuminate\Support\Str;

class TransactionFlowHelper
{
    const FLOW_PREFIX_PO = 'FLOW-PO';
    const FLOW_PREFIX_DIRECT_GRN = 'FLOW-DGRN';

    /**
     * Generate PO-based flow ID
     * Format: FLOW-PO-{poId}
     */
    public static function generatePOFlowId(int $poId): string
    {
        return self::FLOW_PREFIX_PO . '-' . $poId;
    }

    /**
     * Generate Direct GRN flow ID (UUID for collision safety)
     * Format: FLOW-DGRN-{uuid}
     */
    public static function generateDirectGRNFlowId(): string
    {
        return self::FLOW_PREFIX_DIRECT_GRN . '-' . Str::uuid();
    }

    /**
     * Get flow type from flow ID
     */
    public static function getFlowType(string $flowId): string
    {
        return str_starts_with($flowId, self::FLOW_PREFIX_PO) ? 'PO' : 'DIRECT';
    }
}
