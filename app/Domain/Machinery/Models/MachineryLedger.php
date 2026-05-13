<?php

namespace App\Domain\Machinery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineryLedger extends Model
{
    use SoftDeletes;
    
    protected $table = 'machinery_ledger';
    
    /**
     * Boot the model and add immutability enforcement
     * 
     * CRITICAL: Ledger entries are immutable. Critical fields cannot be updated directly.
     * Corrections must be made via reversal entries.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            // Hard link validation: Ledger entries cannot exist without source reference
            if (!$model->reference_type || !$model->reference_id) {
                throw new \RuntimeException('Ledger entries cannot be created without a source reference. Both reference_type and reference_id are required.');
            }
            
            // Validate source exists (except for adjustment entries)
            if (!in_array($model->reference_type, ['OpeningBalance', 'Correction', 'MachineryLedger'])) {
                $sourceClass = 'App\\Models\\' . $model->reference_type;
                if (class_exists($sourceClass)) {
                    $source = $sourceClass::find($model->reference_id);
                    if (!$source) {
                        throw new \RuntimeException('Invalid source reference. Source record does not exist.');
                    }
                }
            }
        });

        static::updating(function ($model) {
            // IMMUTABLE LEDGER: Only allow specific fields to be updated
            $allowedUpdates = [
                'is_settled',
                'payment_request_id',
                'is_reversed',
                'reversal_reference_id',
                'running_balance',
                'is_locked',
                'locked_at',
                'locked_by',
            ];
            
            $dirtyKeys = array_keys($model->getDirty());
            
            foreach ($dirtyKeys as $key) {
                if (!in_array($key, $allowedUpdates)) {
                    throw new \RuntimeException("Ledger entries are immutable. Cannot update: {$key}. Use reversal instead.");
                }
            }
        });

        static::deleting(function () {
            throw new \RuntimeException('Ledger entries cannot be deleted. Use reversal instead.');
        });
    }
    
    protected $fillable = [
        'machinery_id',
        'workspace_id',
        'entry_direction',
        'entry_type',
        'ledger_type',
        'cost_category',
        'amount',
        'running_balance',
        'reference_type',
        'reference_id',
        'dpr_id',
        'date',
        'description',
        'metadata',
        'idempotency_key',
        'is_reversal',
        'reversed_entry_id',
        'is_locked',
        'locked_at',
        'locked_by',
        'payment_request_id',
        // New fields for dual flow and payment safety
        'source_type',
        'entry_source',
        'entry_source_id',
        'is_settled',
        'reversal_reference_id',
        'calculation_snapshot',
        'cost_center',
        'site_id',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'date' => 'date',
        'metadata' => 'array',
        'is_reversal' => 'boolean',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'is_settled' => 'boolean',
        'is_reversed' => 'boolean',
        'calculation_snapshot' => 'array',
    ];
    
    public function machinery()
    {
        return $this->belongsTo(\App\Models\Machinery::class);
    }
    
    public function workspace()
    {
        return $this->belongsTo(\App\Models\Workspace::class);
    }
    
    public function reversedEntry()
    {
        return $this->belongsTo(MachineryLedger::class, 'reversed_entry_id');
    }
    
    public function reversals()
    {
        return $this->hasMany(MachineryLedger::class, 'reversed_entry_id');
    }
    
    public function lockedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by');
    }
    
    public function paymentRequest()
    {
        return $this->belongsTo(MachineryPaymentRequest::class);
    }
    
    public function scopeCredits($query)
    {
        return $query->where('entry_direction', 'credit');
    }
    
    public function scopeDebits($query)
    {
        return $query->where('entry_direction', 'debit');
    }
    
    public function scopeNotReversal($query)
    {
        return $query->where('is_reversal', false);
    }
    
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
    
    public function scopeUnpaid($query)
    {
        return $query->whereNull('payment_request_id');
    }

    // New scopes for dual flow
    public function scopeUnsettled($query)
    {
        return $query->where('is_settled', false)
                     ->where('is_reversed', false);
    }

    public function scopeAvailableForPayment($query)
    {
        return $query->whereNull('payment_request_id')
                     ->where('is_settled', false)
                     ->where('is_reversed', false);
    }

    public function scopeBySourceType($query, $type)
    {
        return $query->where('source_type', $type);
    }

    /**
     * Create reversal entry with double-entry integrity
     */
    public function createReversal(): self
    {
        if ($this->is_reversed) {
            throw new \RuntimeException('Entry already reversed');
        }
        if ($this->is_settled) {
            throw new \RuntimeException('Cannot reverse settled entry');
        }

        return \DB::transaction(function () {
            $reversal = self::create([
                'machinery_id' => $this->machinery_id,
                'amount' => $this->amount,
                'entry_direction' => $this->entry_direction === 'credit' ? 'debit' : 'credit',
                'entry_type' => 'reversal',
                'source_type' => $this->source_type,
                'entry_source' => $this->entry_source,
                'entry_source_id' => $this->entry_source_id,
                'reference_type' => 'MachineryLedger',
                'reference_id' => $this->id,
                'date' => now(),
                'workspace_id' => $this->workspace_id,
                'site_id' => $this->site_id,
                'is_reversed' => false,
                'idempotency_key' => 'reversal_' . $this->id . '_' . uniqid(),
                'description' => 'Reversal of entry #' . $this->id,
            ]);

            $this->update([
                'is_reversed' => true,
                'reversal_reference_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }
}
