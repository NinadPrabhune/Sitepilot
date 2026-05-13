<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierAdvanceAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_advance_id',
        'purchase_invoice_id',
        'action',
        'amount',
        'before_state',
        'after_state',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    // Action constants
    const ACTION_ALLOCATION = 'allocation';
    const ACTION_ROLLBACK = 'rollback';
    const ACTION_ADJUSTMENT = 'adjustment';
    const ACTION_LOCK = 'lock';
    const ACTION_UNLOCK = 'unlock';
    const ACTION_RESERVATION = 'reservation';
    const ACTION_UNRESERVATION = 'unreservation';

    /**
     * Get the advance that this log is for.
     */
    public function advance()
    {
        return $this->belongsTo(SupplierAdvance::class, 'supplier_advance_id');
    }

    /**
     * Get the invoice related to this log (if any).
     */
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Get the user who created this log entry.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create an audit log entry.
     * CRITICAL: This must be called within the same DB transaction as the action.
     * 
     * @param SupplierAdvance $advance
     * @param string $action
     * @param array $beforeState
     * @param array $afterState
     * @param float|null $amount
     * @param int|null $invoiceId
     * @param string|null $reason
     * @param int|null $createdBy
     * @return static
     */
    public static function log(
        SupplierAdvance $advance,
        string $action,
        array $beforeState,
        array $afterState,
        ?float $amount = null,
        ?int $invoiceId = null,
        ?string $reason = null,
        ?int $createdBy = null
    ): self {
        return self::create([
            'supplier_advance_id' => $advance->id,
            'purchase_invoice_id' => $invoiceId,
            'action' => $action,
            'amount' => $amount,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'reason' => $reason,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    /**
     * Scope to get logs for a specific advance.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $advanceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAdvance($query, int $advanceId)
    {
        return $query->where('supplier_advance_id', $advanceId);
    }

    /**
     * Scope to get logs for a specific invoice.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $invoiceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('purchase_invoice_id', $invoiceId);
    }

    /**
     * Scope to get logs by action type.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get logs in chronological order.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('created_at', 'asc');
    }
}
