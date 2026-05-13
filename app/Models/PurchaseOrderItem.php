<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'gst_master_id',
        'quantity',
        'received_qty',
        'unit',
        'price',
        'tax_amount',
        'discount_amount',
        'subtotal',
        'indent_quantity',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'indent_quantity' => 'decimal:2',
    ];

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = round($value, 2);
    }

    public function setReceivedQtyAttribute($value)
    {
        $this->attributes['received_qty'] = round($value, 2);
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = round($value, 2);
    }

    /**
     * Get the purchase order that owns the item.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the material for this item.
     */
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the GST master for this item.
     */
    public function gstMaster()
    {
        return $this->belongsTo(GstMaster::class, 'gst_master_id');
    }

    /**
     * Get the GRN items for this PO item.
     */
    public function grnItems()
    {
        return $this->hasMany(GrnItem::class, 'po_item_id');
    }

    /**
     * Calculate subtotal automatically based on quantity, price, discount, and tax.
     */
    public function calculateSubtotal(): void
    {
        $rowTotal = $this->quantity * $this->price;
        $discountAmount = $this->discount_amount ?? 0;
        $taxableValue = max(0, $rowTotal - $discountAmount);

        // Calculate tax based on parent tax_type
        $purchaseOrder = $this->purchaseOrder;
        $gstMaster = $this->gstMaster;

        $taxAmount = 0;
        if ($gstMaster && $purchaseOrder) {
            if ($purchaseOrder->tax_type === PurchaseOrder::TAX_TYPE_IGST) {
                $taxAmount = $taxableValue * ($gstMaster->igst / 100);
            } else {
                $taxAmount = $taxableValue * (($gstMaster->cgst + $gstMaster->sgst) / 100);
            }
        }

        $this->tax_amount = $taxAmount;
        $this->subtotal = $taxableValue + $taxAmount;
    }

    /**
     * Boot method to auto-calculate subtotal before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateSubtotal();
        });
    }
}
