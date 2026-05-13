<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class MaterialIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_number',
        'site_id',
        'issue_to_type',
        'issue_to_id',
        'issue_date',
        'status',
        'remarks',
        'created_by',
        'workspace_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    // Status constants
    const STATUS_COMPLETED = 'Completed';

    // Issue to type constants
    const ISSUE_TO_USER = 'user';
    const ISSUE_TO_SUPPLIER = 'supplier';

    /**
     * Get the site (project) for this issue.
     */
    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    /**
     * Get the user (employee) this issue is to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'issue_to_id');
    }

    /**
     * Get the supplier this issue is to.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'issue_to_id');
    }

    /**
     * Get the creator of this issue.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the workspace for this issue.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Get the items for this issue.
     */
    public function items()
    {
        return $this->hasMany(MaterialIssueItem::class, 'issue_id');
    }

    /**
     * Get the issue to entity (user or supplier).
     */
    public function getIssueToAttribute()
    {
        if ($this->issue_to_type === self::ISSUE_TO_USER) {
            return $this->user;
        } elseif ($this->issue_to_type === self::ISSUE_TO_SUPPLIER) {
            return $this->supplier;
        }
        return null;
    }

    /**
     * Get the issue to name.
     */
    public function getIssueToNameAttribute()
    {
        if ($this->issue_to_type === self::ISSUE_TO_USER && $this->user) {
            return $this->user->name;
        } elseif ($this->issue_to_type === self::ISSUE_TO_SUPPLIER && $this->supplier) {
            return $this->supplier->name;
        }
        return 'N/A';
    }

    /**
     * Generate unique issue number.
     */
    public static function generateIssueNumber()
    {
        $prefix = 'MI-';
        
        // Use database lock to prevent race conditions
        $lastIssue = self::lockForUpdate()->orderBy('id', 'desc')->first();
        
        if ($lastIssue) {
            $lastNumber = intval(substr($lastIssue->issue_number, 3));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope to filter by workspace.
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope to filter by site.
     */
    public function scopeForSite($query, $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope to filter by issue to type.
     */
    public function scopeForIssueToType($query, $type)
    {
        return $query->where('issue_to_type', $type);
    }

    /**
     * Scope to order by latest first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }
}
