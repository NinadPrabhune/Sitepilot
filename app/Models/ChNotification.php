<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\WorkSpace;

class ChNotification extends Model
{
    use HasFactory;

    protected $table = 'ch_notifications';

    protected $fillable = [
        'workspace_id',
        'project_id',
        'type',
        'title',
        'message',
        'icon_type',
        'related_id',
        'related_type',
        'action_url',
        'message_arr',
        'hash',
    ];

    protected $casts = [
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'message_arr'  => 'array',   // ✅ Cast JSON column to array automatically
    ];

    /**
     * Get all user notifications for this notification.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(ChNotificationUser::class, 'notification_id');
    }

    /**
     * Get all users who have been notified.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'ch_notification_users', 'notification_id', 'user_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Get workspace.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    /**
     * Get project.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereHas('userNotifications', function ($q) {
            $q->whereNull('read_at');
        });
    }

    /**
     * Scope for filtering by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by workspace.
     */
    public function scopeByWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope for filtering by project.
     */
    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Get the full action URL with app base URL prepended dynamically.
     * This ensures URLs work across local and live environments.
     */
    public function getFullActionUrlAttribute(): ?string
    {
        if (empty($this->action_url)) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($this->action_url, 'http://') || str_starts_with($this->action_url, 'https://')) {
            return $this->action_url;
        }

        // Prepend the app base URL for relative paths
        return url($this->action_url);
    }
}
