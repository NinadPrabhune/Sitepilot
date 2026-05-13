<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\TransactionFlowHelper;

class SupplierAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'po_id',
        'site_id',
        'workspace_id',
        'created_by',
        'advance_number',
        'advance_date',
        'source',
        'amount',
        'allocated_amount',
        'utilized_amount',
        'is_locked',
        'status',
        'reserved_at',
        'reservation_expires_at',
        'payment_date',
        'payment_mode',
        'reference_number',
        'payment_proof_file',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'remarks',
        'transaction_flow_id',
        'locked_to_po',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'utilized_amount' => 'decimal:2',
        'is_locked' => 'boolean',
        'reserved_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_to_po' => 'boolean',
    ];

    protected $appends = ['remaining_amount', 'reserved_amount'];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    // Source constants
    const SOURCE_PO = 'po';
    const SOURCE_MANUAL = 'manual';

    /**
     * Model boot method for transaction flow ID generation
     * FEATURE FLAG: Transaction flow ID only generated if feature flag enabled
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (config('finance.po_locked_advance_enabled', false)) {
                if (empty($model->transaction_flow_id) && !empty($model->po_id)) {
                    $model->transaction_flow_id = TransactionFlowHelper::generatePOFlowId($model->po_id);
                }
            }
        });
    }

    /**
     * Get remaining amount (derived from utilization table)
     * CRITICAL: Single source of truth = advance_utilizations table
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->utilized_amount;
    }

    /**
     * Get reserved amount (derived from utilization table - reserved status only)
     * CRITICAL: Single source of truth = advance_utilizations table
     */
    public function getReservedAmountAttribute(): float
    {
        return AdvanceUtilization::where('supplier_advance_id', $this->id)
            ->where('status', 'reserved')
            ->sum('utilized_amount');
    }

    /**
     * Get the PO that owns this advance.
     */
    public function po()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    /**
     * Get the supplier that owns this advance.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the site for this advance.
     */
    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }

    /**
     * Get the user who created this advance.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this advance.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the utilization records for this advance.
     */
    public function utilizations()
    {
        return $this->hasMany(AdvanceUtilization::class, 'supplier_advance_id');
    }

    /**
     * Get the audit logs for this advance.
     */
    public function auditLogs()
    {
        return $this->hasMany(AdvanceAuditLog::class, 'advance_id');
    }

    /**
     * Get the available balance for allocation.
     * CRITICAL: Derived from amount - utilized_amount (single source of truth)
     *
     * @return float
     */
    public function getAvailableBalanceAttribute(): float
    {
        return max(0, $this->amount - $this->utilized_amount);
    }

    /**
     * Check if the advance is paid.
     * 
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID && $this->payment_date !== null;
    }

    /**
     * Check if the advance can be utilized for a specific invoice.
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function canUtilizeForInvoice(int $invoiceId): bool
    {
        $invoice = PurchaseInvoice::find($invoiceId);
        
        if (!$invoice) {
            return false;
        }

        // Must be paid advance
        if (!$this->isPaid()) {
            return false;
        }

        // Must have available balance
        if ($this->getAvailableBalanceAttribute() <= 0) {
            return false;
        }

        // Must belong to same supplier (supplier-level credit pool)
        if ($this->supplier_id !== $invoice->supplier_id) {
            return false;
        }

        // Must not be locked
        if ($this->is_locked) {
            return false;
        }

        return true;
    }

    /**
     * Lock the advance for allocation.
     * 
     * @return bool
     */
    public function lock(): bool
    {
        return $this->update(['is_locked' => true]);
    }

    /**
     * Unlock the advance after allocation.
     * 
     * @return bool
     */
    public function unlock(): bool
    {
        return $this->update(['is_locked' => false]);
    }

    // NOTE: Reservation lifecycle moved to AdvanceAllocationService
    // reserveForPaymentRequest(), applyReservation(), releaseReservation()

    /**
     * Scope to get only paid advances.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope to get advances with available balance.
     * CRITICAL: Calculate available balance using derived logic (amount - utilized_amount)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAvailableBalance($query)
    {
        return $query->whereRaw('(amount - utilized_amount) > 0');
    }

    /**
     * Scope to get advances for a specific supplier.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $supplierId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope to get unlocked advances.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Scope to order by FIFO (oldest first).
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFifo($query)
    {
        return $query->orderBy('advance_date', 'asc')->orderBy('id', 'asc');
    }
}
