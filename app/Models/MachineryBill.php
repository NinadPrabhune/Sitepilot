<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineryBill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'workspace_id',
        'created_by',
        'approved_by',
        'payment_request_id',
        'from_date',
        'to_date',
        'total_amount',
        'total_dprs',
        'total_hours',
        'total_diesel',
        'status',
        'remarks',
        'audit_snapshot',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_hours' => 'decimal:2',
        'total_diesel' => 'decimal:2',
        'from_date' => 'date',
        'to_date' => 'date',
        'audit_snapshot' => 'array',
    ];

    protected $dates = [
        'from_date',
        'to_date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function billingItems()
    {
        return $this->hasMany(MachineryBillingItem::class, 'bill_id');
    }

    /**
     * Scope to get bills by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get unpaid bills
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'approved']);
    }

    /**
     * Scope to get paid bills
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Get total amount from items
     */
    public function getCalculatedAmountAttribute()
    {
        return $this->billingItems()->sum('amount');
    }

    /**
     * Check if bill is locked for payment
     */
    public function isLockedForPayment(): bool
    {
        return in_array($this->status, ['submitted', 'approved']);
    }
}
