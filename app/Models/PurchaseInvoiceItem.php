<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Material;
use App\Models\PurchaseInvoice;
use App\Models\GrnItem;
use App\Models\GstMaster;

class PurchaseInvoiceItem extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseInvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'grn_item_id',
        'material_id',
        'gst_master_id',
        'quantity',
        'unit',
        'price',
        'discount_amount',
        'tax_amount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = round($value, 2);
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = round($value, 2);
    }

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the GRN item for this invoice item.
     */
    public function grnItem()
    {
        return $this->belongsTo(GrnItem::class);
    }

    /**
     * Get the GST master for this item.
     */
    public function gstMaster()
    {
        return $this->belongsTo(GstMaster::class, 'gst_master_id');
    }

    /**
     * Calculate subtotal automatically based on quantity, price, discount, and tax.
     */
    public function calculateSubtotal(string $taxType): void
    {
        $rowTotal = $this->quantity * $this->price;
        $discountAmount = $this->discount_amount ?? 0;
        $taxableValue = max(0, $rowTotal - $discountAmount);

        // Calculate tax based on tax_type
        $gstMaster = $this->gstMaster;

        $taxAmount = 0;
        if ($gstMaster) {
            if ($taxType === PurchaseInvoice::TAX_TYPE_IGST) {
                $taxAmount = $taxableValue * ($gstMaster->igst / 100);
            } else {
                $taxAmount = $taxableValue * (($gstMaster->cgst + $gstMaster->sgst) / 100);
            }
        }

        $this->tax_amount = $taxAmount;
        $this->subtotal = $taxableValue + $taxAmount;
    }
}
