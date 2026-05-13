<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'is_locked',
        'lock_reason',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Check if system is locked for a workspace
     */
    public static function isLocked(int $workspaceId): bool
    {
        return self::where('workspace_id', $workspaceId)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Get active lock for a workspace
     */
    public static function getActiveLock(int $workspaceId): ?self
    {
        return self::where('workspace_id', $workspaceId)
            ->where('is_locked', true)
            ->first();
    }
}
