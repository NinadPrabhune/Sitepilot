<?php

namespace App\Helpers;

use App\Models\SupplierTransaction;

class TinkerHelpers
{
    /**
     * Quick ledger view for Tinker
     * Usage in Tinker: ledger($supplierId)
     */
    public static function ledger($supplierId = null, $siteId = null)
    {
        $query = SupplierTransaction::query();
        
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        return $query->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'reference_type', 'reference_id', 'debit', 'credit', 'balance', 'transaction_date']);
    }

    /**
     * Check for duplicate ledger entries
     * Usage in Tinker: checkDuplicates()
     */
    public static function checkDuplicates()
    {
        return \Illuminate\Support\Facades\DB::select("
            SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM supplier_transactions
            GROUP BY reference_type, reference_id, supplier_id, site_id
            HAVING count > 1
        ");
    }

    /**
     * Get current balance for supplier
     * Usage in Tinker: getBalance($supplierId)
     */
    public static function getBalance($supplierId, $siteId = null)
    {
        return SupplierTransaction::getCurrentBalance($supplierId, $siteId);
    }

    /**
     * Clear test data (use with caution!)
     * Usage in Tinker: clearTestData($supplierId)
     */
    public static function clearTestData($supplierId)
    {
        SupplierTransaction::where('supplier_id', $supplierId)->delete();
        return "Cleared all ledger entries for supplier {$supplierId}";
    }
}

// Register Tinker helpers
if (app()->runningInConsole()) {
    app()->singleton('tinker-helpers', function () {
        return new \App\Helpers\TinkerHelpers();
    });
}
