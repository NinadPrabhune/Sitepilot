<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'is_locked',
        'locked_at',
        'locked_by',
        'workspace_id',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected $dates = [
        'locked_at',
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
     * Check if month is locked for a workspace
     */
    public static function isLocked(int $month, int $year, int $workspaceId): bool
    {
        return self::where('month', $month)
            ->where('year', $year)
            ->where('workspace_id', $workspaceId)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Get active lock for a month/year/workspace
     */
    public static function getLock(int $month, int $year, int $workspaceId): ?self
    {
        return self::where('month', $month)
            ->where('year', $year)
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    /**
     * Lock a month
     */
    public static function lock(int $month, int $year, int $workspaceId, int $userId): self
    {
        return self::updateOrCreate(
            ['month' => $month, 'year' => $year, 'workspace_id' => $workspaceId],
            [
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => $userId,
            ]
        );
    }

    /**
     * Unlock a month
     */
    public static function unlock(int $month, int $year, int $workspaceId): bool
    {
        return self::where('month', $month)
            ->where('year', $year)
            ->where('workspace_id', $workspaceId)
            ->update([
                'is_locked' => false,
                'locked_at' => null,
                'locked_by' => null,
            ]);
    }
}
