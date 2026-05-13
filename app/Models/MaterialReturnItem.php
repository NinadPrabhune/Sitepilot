<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'issue_item_id',
        'material_id',
        'quantity',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = round($value, 2);
    }

    /**
     * Get the return this item belongs to.
     */
    public function return()
    {
        return $this->belongsTo(MaterialReturn::class, 'return_id');
    }

    /**
     * Get the material for this item.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Get the issue item this return item is linked to.
     */
    public function issueItem()
    {
        return $this->belongsTo(MaterialIssueItem::class, 'issue_item_id');
    }
}
