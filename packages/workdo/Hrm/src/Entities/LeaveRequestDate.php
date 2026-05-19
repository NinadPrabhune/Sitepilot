<?php

namespace Workdo\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class LeaveRequestDate extends Model
{
    use HasFactory;

    protected $table = 'leave_request_dates';

    protected $fillable = [
        'leave_request_id',
        'leave_date',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
        'is_half_day',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'is_half_day' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationship to Leave
     */
    public function leaveRequest()
    {
        return $this->belongsTo(Leave::class, 'leave_request_id');
    }

    /**
     * Relationship to User (approver)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for approved dates
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected dates
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for pending dates
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if date is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if date is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if date is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Get day count (0.5 for half-day, 1 for full day)
     */
    public function getDayCount()
    {
        return $this->is_half_day ? 0.5 : 1;
    }
}
