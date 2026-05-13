<?php

namespace App\Domain\Machinery\Models;

use App\Domain\Machinery\Enums\MachineryPaymentStatus;
use App\Support\Finance\HandlesDeadlocks;
use Illuminate\Database\Eloquent\Model;

class MachineryPaymentRequest extends Model
{
    use HandlesDeadlocks;
    
    protected $fillable = [
        'machinery_id',
        'supplier_id',
        'workspace_id',
        'period_start',
        'period_end',
        'credits',
        'debits',
        'net_payable',
        'status',
        'audit_snapshot',
        'idempotency_key',
        'remarks',
        'requested_by',
        'submitted_by',
        'verified_by',
        'approved_by',
        'locked_by',
        'paid_by',
        'submitted_at',
        'verified_at',
        'approved_at',
        'locked_at',
        'paid_at',
    ];
    
    protected $casts = [
        'credits' => 'decimal:2',
        'debits' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'audit_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'paid_at' => 'datetime',
    ];
    
    protected static function booted()
    {
        static::creating(function ($model) {
            // Validate machinery ownership - payment requests only for rental machinery
            if ($model->machinery_id) {
                $machinery = \App\Models\Machinery::find($model->machinery_id);
                if ($machinery && $machinery->owned_by === 'owned') {
                    throw new \RuntimeException('Payment requests are not allowed for owned machinery. Only rental machinery can have payment requests.');
                }
            }
        });

        static::updating(function ($model) {
            // Phase B1.5: Immutable financial snapshot enforcement
            if ($model->payments()->posted()->exists()) {
                $financialFields = ['credits', 'debits', 'net_payable'];
                $dirtyFinancialFields = array_intersect($financialFields, $model->getDirty());
                
                if (!empty($dirtyFinancialFields)) {
                    throw new \RuntimeException(
                        'Cannot modify financial fields after posted payments exist. Posted payments: ' . 
                        $model->payments()->posted()->count() . 
                        '. Dirty fields: ' . implode(', ', $dirtyFinancialFields)
                    );
                }
            }

            if ($model->isDirty('status')) {
                $from = MachineryPaymentStatus::from($model->getOriginal('status'));
                $to = MachineryPaymentStatus::from($model->status);
                
                if (!$from->canTransitionTo($to)) {
                    throw new \InvalidArgumentException(
                        "Invalid status transition: {$from->value} → {$to->value}"
                    );
                }
            }
        });
    }
    
    /**
     * Centralized status transition method
     * CRITICAL: Enforce transitions at model level, not controller
     */
    public function transitionTo(MachineryPaymentStatus $newStatus, int $userId): void
    {
        $from = MachineryPaymentStatus::from($this->status);
        
        if (!$from->canTransitionTo($newStatus)) {
            throw new \RuntimeException(
                "Invalid status transition: {$from->value} → {$newStatus->value}"
            );
        }
        
        $this->status = $newStatus->value;
        $this->save();
    }
    
    public function machinery()
    {
        return $this->belongsTo(\App\Models\Machinery::class);
    }
    
    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }
    
    public function ledgerEntries()
    {
        return $this->hasMany(MachineryLedger::class, 'payment_request_id');
    }
    
    public function period()
    {
        return $this->hasOne(MachineryPaymentPeriod::class, 'payment_request_id');
    }
    
    /**
     * Get ERP payments linked to this machinery payment request.
     * Phase A: Explicit relationship for ERP integration
     */
    public function payments()
    {
        return $this->hasMany(\App\Models\PaymentsModule::class, 'source_id')
            ->where('source_type', \App\Support\PaymentSources::MACHINERY_PAYMENT_REQUEST);
    }
    
    // User relationships for timeline
    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }
    
    public function submitter()
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }
    
    public function verifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }
    
    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
    
    public function locker()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by');
    }
    
    public function payer()
    {
        return $this->belongsTo(\App\Models\User::class, 'paid_by');
    }
    
    public function getStatusAttribute(): string
    {
        return $this->attributes['status'];
    }
    
    public function setStatusAttribute(string $value): void
    {
        $this->attributes['status'] = MachineryPaymentStatus::from($value)->value;
    }
    
    /**
     * Get computed settlement status based on actual ERP payments.
     * Phase A: Read-only computation, never persisted to DB
     */
    public function getSettlementStatusAttribute()
    {
        // Only count finalized ERP payments (posted status)
        $totalPaid = $this->payments()->posted()->sum('amount');
        
        $netPayable = $this->net_payable;
        
        if ($totalPaid == 0) return 'unpaid';
        if ($totalPaid < $netPayable) return 'partial';
        if ($totalPaid == $netPayable) return 'paid';
        return 'overpaid';
    }
}
