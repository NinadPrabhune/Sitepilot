<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class MaterialReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'issue_id',
        'site_id',
        'return_date',
        'status',
        'remarks',
        'created_by',
        'workspace_id',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    // Status constants
    const STATUS_COMPLETED = 'Completed';

    /**
     * Get the site (project) for this return.
     */
    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    /**
     * Get the issue this return is linked to.
     */
    public function issue()
    {
        return $this->belongsTo(MaterialIssue::class, 'issue_id');
    }

    /**
     * Get the creator of this return.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the workspace for this return.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Get the items for this return.
     */
    public function items()
    {
        return $this->hasMany(MaterialReturnItem::class, 'return_id');
    }

    /**
     * Generate unique return number.
     */
    public static function generateReturnNumber()
    {
        $prefix = 'MR-';
        
        // Use database lock to prevent race conditions
        $lastReturn = self::lockForUpdate()->orderBy('id', 'desc')->first();
        
        if ($lastReturn) {
            $lastNumber = intval(substr($lastReturn->return_number, 3));
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
     * Scope to order by latest first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }
}
