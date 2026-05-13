<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySystemSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_date',
        'workspace_id',
        'captured_by',
        'total_entries',
        'total_reversals',
        'system_health_status',
        'orphan_count',
        'drift_count',
        'critical_drift_count',
        'hash_mismatch_count',
        'manual_balance_check',
        'manual_balance_matched',
        'manual_balance_notes',
        'pending_approvals',
        'oldest_pending_age_hours',
        'reversal_rate_percent',
        'notes',
        'captured_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'captured_at' => 'datetime',
        'manual_balance_check' => 'boolean',
        'manual_balance_matched' => 'boolean',
        'reversal_rate_percent' => 'float',
    ];

    public function workspace()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\WorkSpace::class, 'workspace_id');
    }

    public function capturedBy()
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('snapshot_date', $date);
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeCritical($query)
    {
        return $query->where('system_health_status', 'critical');
    }

    public function scopeHasIssues($query)
    {
        return $query->where('system_health_status', '!=', 'healthy');
    }
}
