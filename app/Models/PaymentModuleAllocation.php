<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated This model is deprecated as of Phase 3
 * The payment_module_allocations table has been dropped
 * Payments are now directly linked to invoices via purchase_invoice_id
 * This model is kept for backward compatibility only and will be removed in Phase 8
 */
class PaymentModuleAllocation extends Model
{
    use HasFactory;

    protected $table = 'payment_module_allocations';

    protected $fillable = [
        'payment_module_id',
        'purchase_invoice_id',
        'purchase_order_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    /**
     * Get the payment that owns this allocation.
     */
    public function payment()
    {
        return $this->belongsTo(PaymentsModule::class, 'payment_module_id');
    }

    /**
     * Get the purchase invoice associated with this allocation.
     */
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Get the purchase order associated with this allocation.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
