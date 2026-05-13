<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Material;
use App\Models\Indent;

class IndentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'indent_id',
        'material_id',
        'quantity',
        'unit',
        'price',
        'subtotal',
        'remarks'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
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

    public function indent()
    {
        return $this->belongsTo(Indent::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the remaining quantity available for purchase orders
     */
    public function getRemainingQuantity(): float
    {
        return $this->indent->getRemainingQuantityForMaterial($this->material_id);
    }
}
