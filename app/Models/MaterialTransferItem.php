<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterialTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_transfer_id',
        'material_id',
        'quantity',
        'unit',
        'price',
        'subtotal',
    ];

    public function transfer()
    {
        return $this->belongsTo(MaterialTransfer::class, 'material_transfer_id');
    }

    
    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
