<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLedger extends Model
{
    use HasFactory;

    protected $table = 'supplier_ledger';

    protected $fillable = [
        'supplier_id',
        'workspace_id',
        'entry_direction',
        'entry_type',
        'amount',
        'running_balance',
        'reference_type',
        'reference_id',
        'date',
        'description',
        'metadata',
        'idempotency_key',
        'is_reversal',
        'reversed_entry_id',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'date' => 'date',
        'metadata' => 'array',
        'is_reversal' => 'boolean',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
