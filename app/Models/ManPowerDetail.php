<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ManPowerDetail extends Model
{
    use HasFactory;

    protected $table = 'man_power_details';

    protected $fillable = [
        'man_power_master_id',
        'man_power_type_id',
        'count',
    ];

    public function master()
    {
        return $this->belongsTo(ManPowerMaster::class, 'man_power_master_id');
    }

    public function type()
    {
        return $this->belongsTo(ManPowerType::class, 'man_power_type_id');
    }
}
