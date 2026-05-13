<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceUtilization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'idempotency_key',
        'supplier_advance_id',
        'purchase_invoice_id',
        'utilized_amount',
        'created_by',
        'status',
        'reserved_at',
        'applied_at',
        'reversed_at',
        'workspace_id',
        'site_id',
        'transaction_flow_id',
    ];

    protected $casts = [
        'utilized_amount' => 'decimal:2',
        'reserved_at' => 'datetime',
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_RESERVED = 'reserved';
    const STATUS_APPLIED = 'applied';
    const STATUS_REVERSED = 'reversed';

    /**
     * Get the advance that was utilized.
     */
    public function advance()
    {
        return $this->belongsTo(SupplierAdvance::class, 'supplier_advance_id');
    }

    /**
     * Get the invoice that used this advance.
     */
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Get the payment that triggered this utilization.
     */
    public function payment()
    {
        return $this->belongsTo(PaymentsModule::class, 'payments_module_id');
    }

    /**
     * Get the user who created this utilization record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get total utilized amount for a specific advance.
     * 
     * @param int $advanceId
     * @return float
     */
    public static function getTotalUtilizedForAdvance(int $advanceId): float
    {
        return self::withoutTrashed()
            ->where('supplier_advance_id', $advanceId)
            ->sum('utilized_amount');
    }

    /**
     * Get total utilized amount for a specific invoice.
     * 
     * @param int $invoiceId
     * @return float
     */
    public static function getTotalUtilizedForInvoice(int $invoiceId): float
    {
        return self::withoutTrashed()
            ->where('purchase_invoice_id', $invoiceId)
            ->sum('utilized_amount');
    }

    /**
     * Get utilization records for an invoice with advance details.
     * 
     * @param int $invoiceId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUtilizationWithAdvanceDetails(int $invoiceId)
    {
        return self::withoutTrashed()
            ->where('purchase_invoice_id', $invoiceId)
            ->with('advance:id,advance_number,po_id,amount,remaining_amount')
            ->get();
    }
}
