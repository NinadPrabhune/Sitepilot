<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;


class ManPowerMaster extends Model
{
    use HasFactory;

    protected $table = 'man_power_masters';

    protected $fillable = [
        'work_date',
        'site_id',
        'activity_completed_id',
        'workspace_id',
        'supplier_id',
        'created_by',
        'total_count',
        'reference_file',
    ];

    // Relationships
    public function details()
    {
        return $this->hasMany(ManPowerDetail::class, 'man_power_master_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function site()
    {
        return $this->belongsTo(Project::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the activity completed that owns this manpower record.
     */
    public function activityCompleted()
    {
        return $this->belongsTo(ActivityCompleted::class, 'activity_completed_id');
    }
}
