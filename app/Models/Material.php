<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MaterialCategory;

class Material extends Model
{
    protected $fillable = [
        'name', 
        'sku', 
        'hsn_sac',
        'gst_master_id',
        'category_id', 
        'unit_id',
        'description', 
        'price', 
        'reorder_level',
        'status', 
        'image',
        'created_by',
    ];

    public function category()
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function unit() {
        return $this->belongsTo(Unit::class);
    }

    public function gstMaster() {
        return $this->belongsTo(GstMaster::class, 'gst_master_id');
    }
}

