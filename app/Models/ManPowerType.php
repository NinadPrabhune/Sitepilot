<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;

class ManPowerType extends Model
{
    protected $table = 'man_power_types';

    protected $fillable = [
        'name',
        'status',
        'site_id',
        'workspace_id',
        'created_by',
    ];

    // Relationships (optional)
     public function site()
    {
        return $this->belongsTo(Project::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }
}
