<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'po_item_id',
        'material_id',
        'ordered_qty',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'price',
        'tax_amount',
        'subtotal',
        'gst_master_id',
        'remarks',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'accepted_qty' => 'decimal:2',
        'rejected_qty' => 'decimal:2',
        'price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function setOrderedQtyAttribute($value)
    {
        $this->attributes['ordered_qty'] = round($value, 2);
    }

    public function setReceivedQtyAttribute($value)
    {
        $this->attributes['received_qty'] = round($value, 2);
    }

    public function setAcceptedQtyAttribute($value)
    {
        $this->attributes['accepted_qty'] = round($value, 2);
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = round($value, 2);
    }

    /**
     * Get the GRN for this item.
     */
    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    /**
     * Get the PO item for this GRN item.
     */
    public function poItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }

    /**
     * Get the material for this GRN item.
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
     * Get remaining quantity for this PO item.
     */
    public function getRemainingQtyAttribute()
    {
        if (!$this->poItem) {
            return 0;
        }
        return $this->ordered_qty - ($this->poItem->received_qty ?? 0);
    }

    /**
     * Calculate and set rejected qty based on received and accepted.
     */
    public function setRejectedQtyAttribute($value)
    {
        // If received_qty and accepted_qty are set, calculate rejected
        if (isset($this->attributes['received_qty']) && isset($this->attributes['accepted_qty'])) {
            $this->attributes['rejected_qty'] = $this->attributes['received_qty'] - $this->attributes['accepted_qty'];
        } else {
            $this->attributes['rejected_qty'] = $value;
        }
    }

    /**
     * Calculate subtotal for direct GRN item.
     */
    public function calculateSubtotal(): void
    {
        if (!$this->grn || !$this->grn->isDirectGrn()) {
            return;
        }

        $quantity = (float) $this->received_qty;
        $price = (float) $this->price;

        $rowTotal = $quantity * $price;
        $taxableValue = $rowTotal;

        // Calculate tax based on parent tax_type
        $grn = $this->grn;
        $gstMaster = $this->gstMaster;

        $taxAmount = 0;
        if ($gstMaster && $grn) {
            if ($grn->tax_type === Grn::TAX_TYPE_IGST) {
                $igstRate = (float) ($gstMaster->igst ?? 0);
                $taxAmount = $taxableValue * ($igstRate / 100);
            } else {
                $cgstRate = (float) ($gstMaster->cgst ?? 0);
                $sgstRate = (float) ($gstMaster->sgst ?? 0);
                $taxAmount = $taxableValue * (($cgstRate + $sgstRate) / 100);
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
            if ($item->grn && $item->grn->isDirectGrn()) {
                $item->calculateSubtotal();
            }
        });
    }
}
