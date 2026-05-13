<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PaymentRequest extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::updating(function ($model) {
            if ($model->isDirty('status')) {
                $allowed = match ($model->getOriginal('status')) {
                    'pending' => ['approved', 'partially_approved', 'rejected'],
                    'approved' => ['partially_paid', 'paid'],
                    'partially_approved' => ['partially_paid', 'paid'],
                    'partially_paid' => ['paid'],
                    default => [],
                };

                if (!in_array($model->status, $allowed)) {
                    throw new \Exception("Invalid status transition: {$model->getOriginal('status')} → {$model->status}");
                }
            }
        });
    }

    protected $fillable = [
        'idempotency_key',
        'po_id',
        'purchase_invoice_id',
        'requested_amount',
        'approved_amount',
        'payment_date',
        'status',
        'rejection_reason',
        'remarks',
        'requested_by',
        'approved_by',
        'approved_at',
        'paid_at',
        'net_payable_snapshot',
        'advance_used_snapshot',
        'paid_amount_snapshot',
        'active_requests_snapshot',
        'type',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'net_payable_snapshot' => 'decimal:2',
        'advance_used_snapshot' => 'decimal:2',
        'paid_amount_snapshot' => 'decimal:2',
        'active_requests_snapshot' => 'decimal:2',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';

    const TYPE_INVOICE_PAYMENT = 'invoice_payment';
    const TYPE_PO_ADVANCE = 'po_advance';

    public static array $statuses = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_PAID,
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function po()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(PaymentsModule::class, 'payment_request_id');
    }

    public function payment()
    {
        return $this->hasOne(PaymentsModule::class, 'payment_request_id');
    }

    public function hasPayment(): bool
    {
        return $this->payment()->exists();
    }

    public function isFullyPaid(): bool
    {
        $totalPaid = $this->payments()->sum('amount');
        return $totalPaid >= $this->requested_amount;
    }

    public function getTotalPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->requested_amount - $this->total_paid_amount);
    }

    public function canMakePayment(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
            self::STATUS_PARTIALLY_PAID
        ]) && !$this->isFullyPaid();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPartiallyApproved(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_PAID;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED, self::STATUS_PARTIALLY_PAID]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED, self::STATUS_PARTIALLY_PAID]);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIALLY_APPROVED]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('purchase_invoice_id', $invoiceId);
    }

    public function scopeForPo($query, int $poId)
    {
        return $query->where('po_id', $poId);
    }

    public function scopePoAdvance($query)
    {
        return $query->where('type', self::TYPE_PO_ADVANCE);
    }

    public function scopeInvoicePayment($query)
    {
        return $query->where('type', self::TYPE_INVOICE_PAYMENT);
    }

    public function updateStatusOnPayment(): void
    {
        if (!in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
            self::STATUS_PARTIALLY_PAID
        ])) {
            return;
        }

        if ($this->status === self::STATUS_PAID) {
            return;
        }

        $totalPaid = PaymentsModule::where('payment_request_id', $this->id)->sum('amount');

        if ($totalPaid >= $this->requested_amount) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = now();
        } elseif ($totalPaid > 0) {
            $this->status = self::STATUS_PARTIALLY_PAID;
            $this->paid_at = null;
        } else {
            return;
        }

        $this->saveQuietly();
        
        if ($this->status === self::STATUS_PAID) {
            Log::info('PaymentRequest status updated to PAID via payment', [
                'id' => $this->id,
                'total_paid' => $totalPaid,
                'requested_amount' => $this->requested_amount,
            ]);
        } else {
            Log::info('PaymentRequest status updated to PARTIALLY_PAID via payment', [
                'id' => $this->id,
                'total_paid' => $totalPaid,
                'requested_amount' => $this->requested_amount,
            ]);
        }
    }

    public function getNetPayableForAudit(): float
    {
        return $this->net_payable_snapshot ?? $this?->invoice?->getNetPayableAmount() ?? 0;
    }

    public function getAdvanceUsedForAudit(): float
    {
        return $this->advance_used_snapshot ?? $this?->invoice?->getAdvanceUtilizedForInvoice() ?? 0;
    }

    public function getPaidAmountForAudit(): float
    {
        return $this->paid_amount_snapshot ?? $this?->invoice?->getActualPaidAmount() ?? 0;
    }

    public function hasFinancialSnapshot(): bool
    {
        return !is_null($this->net_payable_snapshot);
    }

    public function isPoAdvance(): bool
    {
        return $this->type === self::TYPE_PO_ADVANCE;
    }

    public function isInvoicePayment(): bool
    {
        return $this->type === self::TYPE_INVOICE_PAYMENT;
    }
}