<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WorkSpace;
use App\Models\ActivityCompleted;

use Workdo\Taskly\Entities\Project;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = [
        'title',
        'start_date',
        'due_date',
        'scope',
        'quantity',
        'unit',
        'priority',
        'status',
        'created_by',
        'workspace_id',
        'site_id',
        'assign_to',
        'reference_file',
    ];

    /*
    |--------------------------------------------------------------------------
    | Basic Relationships
    |--------------------------------------------------------------------------
    */

    // Creator
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Workspace
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    // Project / Site
    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Related Data
    |--------------------------------------------------------------------------
    */

    public function completeds()
    {
        return $this->hasMany(ActivityCompleted::class, 'activity_id')
                    ->orderBy('completed_date', 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | Assigned Employees (Improved)
    |--------------------------------------------------------------------------
    */

    // Current method (returns collection directly - not ideal)
    public function assignedEmployees()
    {
        if (!$this->assign_to) {
            return collect();
        }

        return User::whereIn('id', explode(',', $this->assign_to))->get();
    }
}