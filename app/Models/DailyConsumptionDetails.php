<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyConsumptionDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_consumption_master_id',
        'material_id',
        'quantity',
        'unit',
        'remarks',
    ];

    public function master()
    {
        return $this->belongsTo(DailyConsumptionMaster::class, 'daily_consumption_master_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    // Optional convenience accessors
    public function getMaterialNameAttribute()
    {
        return $this->material ? $this->material->name : null;
    }

    public function getUnitNameAttribute()
    {
        return $this->material ? $this->material->unit->name : $this->unit;
    }
    public function items()
    {
        return $this->hasMany(DailyConsumptionDetails::class, 'daily_progress_report_id');
    }

}
