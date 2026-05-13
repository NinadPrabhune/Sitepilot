<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'site_id',
        'reference_type',
        'reference_id',
        'reference_amount',
        'transaction_date',
        'debit',
        'credit',
        'balance',
        'description',
        'meta',
        'workspace_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'reference_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'reference_id' => 'integer',
        'supplier_id' => 'integer',
        'site_id' => 'integer',
        'workspace_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $validTypes = [self::TYPE_PO, self::TYPE_INVOICE, self::TYPE_PAYMENT, self::TYPE_ADVANCE, self::TYPE_GRN, self::TYPE_ADJUSTMENT];
            
            if (empty($model->reference_type) || !in_array($model->reference_type, $validTypes)) {
                throw new \InvalidArgumentException("Invalid reference_type: " . ($model->reference_type ?? 'null'));
            }

            $financialTypes = [self::TYPE_PO, self::TYPE_INVOICE, self::TYPE_PAYMENT, self::TYPE_ADVANCE];
            if (in_array($model->reference_type, $financialTypes)) {
                if (empty($model->reference_amount) || $model->reference_amount <= 0) {
                    throw new \InvalidArgumentException("reference_amount must be > 0 for type: " . $model->reference_type);
                }
            }
        });

        // Immutable ledger rule: prevent updates
        static::updating(function ($model) {
            throw new \Exception('Ledger entries are immutable. Use reversal entries instead of updating.');
        });

        // Immutable ledger rule: prevent deletes
        static::deleting(function ($model) {
            throw new \Exception('Ledger entries cannot be deleted. Use reversal entries instead.');
        });
    }

    // Reference type constants
    const TYPE_INVOICE = 'invoice';
    const TYPE_PAYMENT = 'payment';
    const TYPE_ADVANCE = 'advance';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_PO = 'po';
    const TYPE_GRN = 'grn';

    /**
     * Get the supplier for this transaction.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the site (project) for this transaction.
     */
    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    /**
     * Get the creator of this transaction.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the workspace for this transaction.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Get the reference model (PurchaseInvoice, PaymentsModule, etc.) if applicable.
     */
    public function getReferenceModel()
    {
        if ($this->reference_type && $this->reference_id) {
            // Map reference_type to actual model class
            $classMap = [
                self::TYPE_INVOICE => \App\Models\PurchaseInvoice::class,
                self::TYPE_PAYMENT => \App\Models\PaymentsModule::class,
                self::TYPE_ADVANCE => \App\Models\PaymentsModule::class,
                self::TYPE_ADJUSTMENT => \App\Models\PurchaseInvoice::class,
                self::TYPE_PO => \App\Models\PurchaseOrder::class,
                self::TYPE_GRN => \App\Models\Grn::class,
            ];
            
            $class = $classMap[$this->reference_type] ?? null;
            
            if ($class) {
                return $class::find($this->reference_id);
            }
        }
        return null;
    }

    /**
     * Scope to filter by supplier.
     */
    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope to filter by site.
     */
    public function scopeForSite($query, $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope to filter by reference type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('reference_type', $type);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope to order by transaction date chronologically.
     * Uses created_at as primary order for accurate chronological sequence.
     */
    public function scopeOrderedByDate($query)
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Get human-readable reference type label.
     */
    public function getReferenceTypeLabelAttribute()
    {
        $labels = [
            self::TYPE_INVOICE => 'Purchase Invoice',
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_ADVANCE => 'Advance',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_PO => 'Purchase Order',
            self::TYPE_GRN => 'GRN',
        ];
        return $labels[$this->reference_type] ?? $this->reference_type;
    }

    /**
     * Get the reference number for display.
     */
    public function getReferenceNumberAttribute()
    {
        $reference = $this->getReferenceModel();
        if (!$reference) {
            return '#' . $this->reference_id;
        }

        if ($reference instanceof PurchaseInvoice) {
            return $reference->invoice_number;
        }

        if ($reference instanceof PaymentsModule) {
            return $reference->payment_number;
        }

        if ($reference instanceof PurchaseOrder) {
            return $reference->po_number;
        }

        if ($reference instanceof Grn) {
            return $reference->grn_number;
        }

        return '#' . $this->reference_id;
    }

    /**
     * Get total debits for a supplier.
     */
    public static function getTotalDebits($supplierId)
    {
        return self::where('supplier_id', $supplierId)->sum('debit');
    }

    /**
     * Get total credits for a supplier.
     */
    public static function getTotalCredits($supplierId)
    {
        return self::where('supplier_id', $supplierId)->sum('credit');
    }

    /**
     * Get current balance for a supplier.
     */
    public static function getCurrentBalance($supplierId, ?int $siteId = null)
    {
        $query = self::where('supplier_id', $supplierId);
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        
        $lastTransaction = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance : 0;
    }
}
