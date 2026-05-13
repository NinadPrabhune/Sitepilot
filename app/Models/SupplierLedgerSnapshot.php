<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierLedgerSnapshot extends Model
{
    protected $fillable = [
        'supplier_id',
        'site_id',
        'balance',
        'snapshot_date',
        'last_transaction_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'snapshot_date' => 'date',
        'supplier_id' => 'integer',
        'site_id' => 'integer',
        'last_transaction_id' => 'integer',
    ];

    /**
     * Get the latest snapshot for a supplier/site
     */
    public static function getLatest($supplierId, $siteId = null)
    {
        $query = self::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        return $query->orderBy('snapshot_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Create a snapshot for a supplier/site
     */
    public static function createSnapshot($supplierId, $siteId = null, $date = null)
    {
        $date = $date ?? now()->toDateString();
        
        // Get current balance
        $balance = SupplierTransaction::getCurrentBalance($supplierId, $siteId);
        
        // Get last transaction
        $lastTransaction = SupplierTransaction::where('supplier_id', $supplierId)
            ->when($siteId, fn($q) => $q->where('site_id', $siteId))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        return self::create([
            'supplier_id' => $supplierId,
            'site_id' => $siteId,
            'balance' => $balance,
            'snapshot_date' => $date,
            'last_transaction_id' => $lastTransaction?->id,
        ]);
    }
}
