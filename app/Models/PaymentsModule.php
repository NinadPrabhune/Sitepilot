<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

/**
 * App\Models\PaymentsModule
 *
 * @property int $id
 * @property string $payment_number
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $purchase_invoice_id
 * @property int|null $site_id
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property string $amount
 * @property string $payment_type
 * @property string $mode
 * @property string|null $reference_number
 * @property int|null $created_by
 * @property int|null $workspace_id
 * @property string|null $notes
 * @property string|null $payment_proff_file
 * @property string $status
 * @property int|null $payment_request_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PaymentModuleAllocation[] $allocations
 * @property-read int|null $allocations_count
 * @property-read \App\Models\PaymentRequest|null $paymentRequest
 * @property-read \App\Models\PurchaseInvoice|null $invoice
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\Project|null $site
 * @property-read \App\Models\Supplier|null $supplier
 * @property-read \App\Models\User|null $creator
 * @method static \Database\Factories\PaymentsModuleFactory factory(...$attributes)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule findSimilarSlugs($attribute, $config, $slug)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePaymentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePaymentProffFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePaymentRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePurchaseInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereSiteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule whereWorkspaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentsModule withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, $attribute, $config, $slug)
 * @mixin \Eloquent
 */
class PaymentsModule extends Model
{
    use HasFactory;

    protected $table = 'payments_module';

    // Payment types constants - Match actual database enum values
    const PAYMENT_TYPE_ADVANCE_AGAINST_PO = 'advance_against_po';
    const PAYMENT_TYPE_AGAINST_PO = 'against_po';
    const PAYMENT_TYPE_AGAINST_INVOICE = 'against_invoice';
    const PAYMENT_TYPE_MIXED = 'mixed';
    const PAYMENT_TYPE_ON_ACCOUNT = 'on_account';
    
    // Status constants
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'purchase_order_id',
        'purchase_invoice_id',
        'site_id',
        'payment_date',
        'amount',
        'payment_type',
        'status',
        'mode',
        'reference_number',
        'created_by',
        'workspace_id',
        'notes',
        'payment_proff_file',
        'payment_request_id',
        'payment_pdf',
        'idempotency_key',
        'source_type',
        'source_id',
        'integration_reference_uuid',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Allow PO-based payments only when coming through payment request workflow
            // This ensures the new approval-driven workflow works while blocking legacy direct PO payments
            $isPaymentRequestWorkflow = !empty($model->payment_request_id);

            // HARD FREEZE: Prevent PO-based payment creation (unless via payment request workflow)
            if (in_array($model->payment_type, ['against_po', 'advance_against_po']) && !$isPaymentRequestWorkflow) {
                Log::channel('payment_audit')->error('HARD FREEZE: Model validation - Attempted to create PO-based payment without payment request', [
                    'payment_type' => $model->payment_type,
                    'purchase_order_id' => $model->purchase_order_id ?? null,
                    'purchase_invoice_id' => $model->purchase_invoice_id ?? null,
                    'payment_request_id' => $model->payment_request_id ?? null,
                    'attempted_by' => auth()->id() ?? 1,
                ]);

                throw new \InvalidArgumentException(
                    'HARD FREEZE: PO-based payments are no longer allowed. ' .
                    'Use Payment Request workflow for PO advance payments.'
                );
            }

            // For PO advance payments via payment request workflow, purchase_invoice_id can be null
            // For machinery payment request payments, purchase_invoice_id is not applicable
            $isMachineryPayment = $model->source_type === \App\Support\PaymentSources::MACHINERY_PAYMENT_REQUEST;
            if (($isPaymentRequestWorkflow && in_array($model->payment_type, ['against_po', 'advance_against_po'])) || $isMachineryPayment) {
                // PO advance and machinery payments don't require purchase_invoice_id
            } elseif (empty($model->purchase_invoice_id)) {
                Log::channel('payment_audit')->error('HARD FREEZE: Model validation - Attempted to create payment without invoice', [
                    'payment_type' => $model->payment_type ?? 'unknown',
                    'payment_request_id' => $model->payment_request_id ?? null,
                    'attempted_by' => auth()->id() ?? 1,
                ]);

                throw new \InvalidArgumentException(
                    'HARD FREEZE: All payments must have a purchase_invoice_id (unless PO advance via payment request). ' .
                    'Use Payment Request workflow for PO advance payments.'
                );
            }

            // CRITICAL: site_id is required for payment number generation
            if (empty($model->site_id)) {
                Log::channel('payment_audit')->error('site_id is required for payment number generation', [
                    'payment_data' => $model->toArray(),
                    'attempted_by' => auth()->id() ?? 1,
                ]);
                throw new \InvalidArgumentException('site_id is required for payment number generation. Per-site numbering requires a valid site_id.');
            }

            // Generate payment number if not set
            if (empty($model->payment_number)) {
                $model->payment_number = $model->generatePaymentNumber($model->site_id);
            }

            // Set workspace if not set
            if (empty($model->workspace_id)) {
                $model->workspace_id = getActiveWorkSpace();
            }

            // Set created_by if not set
            if (empty($model->created_by)) {
                $model->created_by = auth()->id() ?? 1;
            }
        });

        static::updating(function ($model) {
            // HARD FREEZE: Prevent changing payment type to PO-based
            if ($model->isDirty('payment_type') && in_array($model->payment_type, ['against_po', 'advance_against_po'])) {
                Log::channel('payment_audit')->error('HARD FREEZE: Model validation - Attempted to change payment type to PO-based', [
                    'original_payment_type' => $model->getOriginal('payment_type'),
                    'new_payment_type' => $model->payment_type,
                    'attempted_by' => auth()->id() ?? 1,
                ]);

                throw new \InvalidArgumentException(
                    'HARD FREEZE: Cannot change payment type to PO-based. ' .
                    'System now enforces invoice-only payments.'
                );
            }

            // HARD FREEZE: Prevent removing purchase_invoice_id
            if ($model->isDirty('purchase_invoice_id') && empty($model->purchase_invoice_id)) {
                Log::channel('payment_audit')->error('HARD FREEZE: Model validation - Attempted to remove purchase_invoice_id', [
                    'original_purchase_invoice_id' => $model->getOriginal('purchase_invoice_id'),
                    'attempted_by' => auth()->id() ?? 1,
                ]);

                throw new \InvalidArgumentException(
                    'HARD FREEZE: Cannot remove purchase_invoice_id from payment. ' .
                    'System now enforces invoice-only payments.'
                );
            }
        });

static::saving(function ($model) {
            // For invoice-based payments, purchase_invoice_id is required
            // EXCEPTION: Machinery payment request payments don't require a purchase invoice
            $isMachineryPayment = $model->source_type === \App\Support\PaymentSources::MACHINERY_PAYMENT_REQUEST;
            if ($model->payment_type === self::PAYMENT_TYPE_AGAINST_INVOICE && empty($model->purchase_invoice_id) && !$isMachineryPayment) {
                throw new \Exception(
                    'HARD FREEZE: Cannot remove purchase_invoice_id from payment. ' .
                    'System now enforces invoice-only payments.'
                );
            }

            // Log warning for PO-based payments (will be migrated in Phase 3)
            if ($model->payment_type === self::PAYMENT_TYPE_AGAINST_PO) {
                \Log::channel('payment_audit')->warning('PO-based payment created (will be migrated in Phase 3)', [
                    'payment_id' => $model->id ?? null,
                    'payment_type' => $model->payment_type,
                    'purchase_order_id' => $model->purchase_order_id,
                    'purchase_invoice_id' => $model->purchase_invoice_id,
                ]);
            }
        });
    }

    /**
     * Get the allocations for this payment.
     */
    public function allocations()
    {
        return $this->hasMany(PaymentModuleAllocation::class, 'payment_module_id');
    }

    /**
     * Get the supplier associated with this payment.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the purchase order associated with this payment.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Get the invoice associated with this payment (legacy - for backward compatibility).
     * Use allocations() for new implementations.
     */
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Get the site associated with this payment.
     */
    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }

    /**
     * Get the user who created this payment.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payment request associated with this payment.
     */
    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    /**
     * Get the machinery payment request associated with this payment.
     * Phase A: Explicit relationship for ERP integration
     */
    public function machineryPaymentRequest()
    {
        return $this->belongsTo(\App\Domain\Machinery\Models\MachineryPaymentRequest::class, 'source_id')
            ->where('source_type', \App\Support\PaymentSources::MACHINERY_PAYMENT_REQUEST);
    }

    /**
     * Get total allocated amount from all allocations.
     */
    public function getTotalAllocatedAmount()
    {
        return $this->allocations()->sum('allocated_amount');
    }

    /**
     * Get unallocated amount (payment amount - allocated amount).
     */
    public function getUnallocatedAmount()
    {
        return $this->amount - $this->getTotalAllocatedAmount();
    }

    /**
     * Get advance adjustments for this payment.
     */
    public function advanceAdjustments()
    {
        return $this->hasMany(AdvanceAdjustment::class, 'payment_id');
    }

    /**
     * Get total utilized advance amount (excludes soft-deleted).
     */
    public function getUtilizedAdvanceAmount(): float
    {
        return $this->advanceAdjustments()->withoutTrashed()->sum('utilized_amount');
    }

    /**
     * Get available advance balance (excludes soft-deleted).
     */
    public function getAvailableAdvance(): float
    {
        return max(0, $this->amount - $this->getUtilizedAdvanceAmount());
    }

    /**
     * Check if this is an advance payment against PO.
     */
    public function isAdvanceAgainstPo()
    {
        return $this->payment_type === self::PAYMENT_TYPE_ADVANCE_AGAINST_PO;
    }

    /**
     * Check if this is a against_po payment.
     */
    public function isAgainstPo()
    {
        return $this->payment_type === self::PAYMENT_TYPE_AGAINST_PO;
    }

    /**
     * Generate unique payment number with per-site reset.
     */
    public static function generatePaymentNumber(?int $siteId = null): string
    {
        return app(\App\Services\NumberGeneratorService::class)->generate('payment', $siteId);
    }

    /**
     * Scope: Filter payments for machinery payment requests.
     * Phase A: Prevent scattered where clauses
     */
    public function scopeForMachineryPaymentRequest($query)
    {
        return $query->where('source_type', \App\Support\PaymentSources::MACHINERY_PAYMENT_REQUEST);
    }

    /**
     * Scope: Filter only posted (finalized) payments.
     * Phase A: Payment isolation for settlement calculations
     * Note: ERP table doesn't have status column - all payments are considered posted
     */
    public function scopePosted($query)
    {
        // In actual ERP schema, all payments in payments_module are considered posted
        // The existence of a payment record means it's finalized
        return $query;
    }

    /**
     * Scope: Filter payments by source type.
     * Phase A: Generic source filtering
     */
    public function scopeForSource($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Get invoice balance (total - paid).
     *
     * @param int $invoiceId
     * @return float
     */
    public static function getInvoiceBalance($invoiceId)
    {
        $invoice = PurchaseInvoice::find($invoiceId);
        
        if (!$invoice) {
            return 0;
        }

        $invoiceTotal = $invoice->total_amount;
        
        // Get paid amounts from allocations
        $paidFromAllocations = PaymentModuleAllocation::where('purchase_invoice_id', $invoiceId)
            ->sum('allocated_amount');
        
        // Get paid amounts from legacy payments (direct invoice payments)
        $paidFromLegacy = PaymentsModule::where('purchase_invoice_id', $invoiceId)
            ->where('payment_type', 'against_invoice')
            ->sum('amount');
        
        $totalPaid = $paidFromAllocations + $paidFromLegacy;
        
        return max(0, $invoiceTotal - $totalPaid);
    }
}
