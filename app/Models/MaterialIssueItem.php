<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'material_id',
        'quantity',
        'rate',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = round($value, 2);
    }

    public function setRateAttribute($value)
    {
        $this->attributes['rate'] = round($value, 2);
    }

    /**
     * Get the issue this item belongs to.
     */
    public function issue()
    {
        return $this->belongsTo(MaterialIssue::class, 'issue_id');
    }

    /**
     * Get the material for this item.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Calculate amount before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if ($item->rate && $item->quantity) {
                $item->amount = $item->rate * $item->quantity;
            }
        });
    }
}
