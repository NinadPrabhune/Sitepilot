<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyHealthCheckLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_date',
        'workspace_id',
        'checked_by',
        'status',
        'orphan_count',
        'drift_count',
        'critical_drift_count',
        'hash_mismatch_count',
        'manual_balance_check',
        'manual_balance_notes',
        'action_taken',
        'action_details',
        'issue_category',
        'check_time',
    ];

    protected $casts = [
        'check_date' => 'date',
        'check_time' => 'datetime',
        'manual_balance_check' => 'boolean',
    ];

    public function workspace()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\WorkSpace::class, 'workspace_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('check_date', $date);
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeCritical($query)
    {
        return $query->where('status', 'critical');
    }

    public function scopeHasIssues($query)
    {
        return $query->where('status', '!=', 'ok');
    }
}
