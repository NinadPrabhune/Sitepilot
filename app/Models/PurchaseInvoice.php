<?php

namespace App\Models;

use App\Traits\HasAssignTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;

use App\Models\PaymentsModule;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\Grn;
use App\Models\WorkSpace;
use App\Models\AdvanceAdjustment;
use App\Services\TransactionFlowHelper;
use App\Services\FinancialPeriodService;
use Carbon\Carbon;

class PurchaseInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseInvoiceFactory> */
    use HasFactory, HasAssignTo;

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'invoice_date',
        'supplier_invoice_number',
        'supplier_id',
        'total_amount',
        'status',
        'site_id',
        'po_id',
        'grn_id',
        'grn_type',
        'assign_to',
        'tax_type',
        'total_taxable_value',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_tax',
        'total_discount',
        'grand_total',
        'paid_amount',
        'created_by',
        'workspace_id',
        'invoice_file',
        'pi_pdf',
        'payment_status',
        'ac_payment_status',
        'rejection_reason',
        'payment_request_flag',
        'is_financially_locked',
        'financially_locked_at',
        'financially_locked_by',
        'is_locked',
        'locked_at',
        'locked_by',
        'idempotency_key',
        'transaction_flow_id',
    ];

    protected $casts = [
        'total_taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'is_financially_locked' => 'boolean',
        'financially_locked_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'payment_request_flag' => 'boolean',
    ];

    /**
     * Model boot method for transaction flow ID generation and financial period validation
     * FEATURE FLAG: Transaction flow ID and period validation only run if feature flag enabled
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            // Generate transaction flow ID based on PO or Direct GRN (only if feature flag enabled)
            if (config('finance.po_locked_advance_enabled', false)) {
                if (empty($model->transaction_flow_id)) {
                    if (!empty($model->po_id)) {
                        $model->transaction_flow_id = TransactionFlowHelper::generatePOFlowId($model->po_id);
                        $model->grn_type = 'PO';
                    } else {
                        $model->transaction_flow_id = TransactionFlowHelper::generateDirectGRNFlowId();
                        $model->grn_type = 'DIRECT';
                    }
                }

                // Validate financial period not closed (only if feature flag enabled)
                if (config('finance.financial_period_locking_enabled', false)) {
                    $periodService = new FinancialPeriodService();
                    $periodService->validatePeriodNotClosed(
                        Carbon::parse($model->invoice_date),
                        $model->workspace_id,
                        $model->site_id
                    );
                }
            }
        });

        static::updating(function ($model) {
            // Validate financial period not closed on update if invoice_date or amount changes (only if feature flag enabled)
            if (config('finance.po_locked_advance_enabled', false) && config('finance.financial_period_locking_enabled', false)) {
                if ($model->isDirty('invoice_date') || $model->isDirty('grand_total')) {
                    $periodService = new FinancialPeriodService();
                    $periodService->validatePeriodNotClosed(
                        Carbon::parse($model->invoice_date),
                        $model->workspace_id,
                        $model->site_id
                    );
                }
            }
        });
    }

    // Tax type constants
    const TAX_TYPE_CGST = 'cgst';
    const TAX_TYPE_IGST = 'igst';

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentsModule::class, 'purchase_invoice_id');
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function advanceUtilizations()
    {
        return $this->hasMany(AdvanceUtilization::class, 'purchase_invoice_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the purchase order for this invoice.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    /**
     * Get the GRN for this invoice.
     */
    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    /**
     * Get the workspace for this invoice.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Check if invoice exists for a specific GRN.
     */
    public static function existsForGrn(int $grnId): bool
    {
        return self::where('grn_id', $grnId)->exists();
    }

    /**
     * Get invoice by GRN ID.
     */
    public static function getByGrnId(int $grnId)
    {
        return self::where('grn_id', $grnId)->first();
    }

    /**
     * Calculate totals from items (reusing PO logic).
     */
    public function calculateTotals(): void
    {
        $this->load('items.gstMaster');

        $totalTaxableValue = 0;
        $totalDiscount = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($this->items as $item) {
            $quantity = (float) $item->quantity;
            $price = (float) $item->price;
            $discountAmount = (float) ($item->discount_amount ?? 0);

            $rowTotal = $quantity * $price;

            // Prevent discount overflow
            if ($discountAmount > $rowTotal) {
                $discountAmount = $rowTotal;
            }

            $taxableValue = $rowTotal - $discountAmount;

            $totalTaxableValue += $taxableValue;
            $totalDiscount += $discountAmount;

            $gstMaster = $item->gstMaster;

            if ($gstMaster) {
                if ($this->tax_type === self::TAX_TYPE_IGST) {
                    $igstRate = (float) ($gstMaster->igst ?? 0);
                    $igstAmount = ($taxableValue * $igstRate) / 100;
                    $totalIgst += $igstAmount;
                } else {
                    $cgstRate = (float) ($gstMaster->cgst ?? 0);
                    $sgstRate = (float) ($gstMaster->sgst ?? 0);

                    $cgstAmount = ($taxableValue * $cgstRate) / 100;
                    $sgstAmount = ($taxableValue * $sgstRate) / 100;

                    $totalCgst += $cgstAmount;
                    $totalSgst += $sgstAmount;
                }
            }
        }

        $totalTax = ($this->tax_type === self::TAX_TYPE_IGST)
            ? $totalIgst
            : ($totalCgst + $totalSgst);

        // Assign rounded values
        $this->total_taxable_value = round($totalTaxableValue, 2);
        $this->total_discount = round($totalDiscount, 2);
        $this->total_cgst = round($totalCgst, 2);
        $this->total_sgst = round($totalSgst, 2);
        $this->total_igst = round($totalIgst, 2);
        $this->total_tax = round($totalTax, 2);

        // Grand Total = Taxable Value + Tax + Additional Charges - Deductions
        $grandTotal = $totalTaxableValue + $totalTax;
        $this->grand_total = round(max($grandTotal, 0), 2);

        // Update total_amount for backward compatibility
        $this->total_amount = $this->grand_total;
    }

    /**
     * Generate unique invoice number with per-site reset.
     */
    public static function generateInvoiceNumber(?int $siteId = null): string
    {
        return app(\App\Services\NumberGeneratorService::class)->generate('invoice', $siteId);
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->getActualPaidAmount());
    }

    public function getPaymentStatusAttribute(): string
    {
        $paid = $this->getActualPaidAmount();
        $total = (float) $this->grand_total;

        if ($paid <= 0) {
            return 'unpaid';
        } elseif ($paid >= $total) {
            return 'paid';
        } else {
            return 'partially_paid';
        }
    }

    public function isPaid(): bool
    {
        return $this->getActualPaidAmount() >= (float) $this->grand_total;
    }

    public function hasApprovedPaymentRequests(): bool
    {
        return $this->paymentRequests()
            ->whereIn('status', [
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_PAID
            ])
            ->exists();
    }

    public function getPayablePaymentRequests()
    {
        return $this->paymentRequests()
            ->whereIn('status', [
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_PAID
            ])
            ->whereDoesntHave('payment')
            ->get();
    }

    public function isUnpaid(): bool
    {
        return $this->getActualPaidAmount() <= 0;
    }

    public function getAvailableAdvanceForPo(): float
    {
        if (!$this->po_id) {
            return 0;
        }

        $payments = \App\Models\PaymentsModule::where('purchase_order_id', $this->po_id)
            ->where('payment_type', 'advance_against_po')
            ->get();

        $totalAdvance = $payments->sum('amount');
        $utilized = \App\Models\AdvanceAdjustment::withoutTrashed()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->sum('utilized_amount');

        return max(0, $totalAdvance - $utilized);
    }

    public function getRemainingBalance(): float
    {
        return $this->getRemainingApprovalAmount();
    }

    public function getActualPaidAmount(): float
    {
        return (float) $this->payments()
            ->where('payment_type', '!=', PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO)
            ->sum('amount');
    }

    public function getActualRemainingBalance(): float
    {
        return max(0, (float) $this->grand_total - $this->getActualPaidAmount());
    }

    public function getActivePaymentRequestsSum(): float
    {
        return $this->paymentRequests()
            ->whereIn('status', [
                PaymentRequest::STATUS_PENDING,
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED
            ])
            ->sum('requested_amount');
    }

    public function getApprovedPaymentRequestsSum(): float
    {
        return $this->paymentRequests()
            ->whereIn('status', ['approved', 'partially_approved'])
            ->sum('approved_amount');
    }

    public function canCreatePaymentRequest(): bool
    {
        return !$this->isPaid();
    }

    public function hasActivePaymentRequest(): bool
    {
        return $this->paymentRequests()
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->exists();
    }

    public function hasPendingPaymentRequest(): bool
    {
        return $this->paymentRequests()
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->exists();
    }

    public function getPendingPaymentRequest(): ?PaymentRequest
    {
        return $this->paymentRequests()
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->first();
    }

    public function hasRemainingAmount(): bool
    {
        return $this->getRemainingBalance() > 0;
    }

    public function getApprovedAmountSum(): float
    {
        return $this->paymentRequests()
            ->whereIn('status', [
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED,
                PaymentRequest::STATUS_PAID,
                PaymentRequest::STATUS_PARTIALLY_PAID
            ])
            ->sum('approved_amount');
    }

    public function getRemainingApprovalAmount(): float
    {
        $grandTotal = (float) $this->grand_total;
        $actualPaid = $this->getActualPaidAmount();
        $advanceUtilized = $this->getAdvanceUtilizedForInvoice();
        return max(0, $grandTotal - $actualPaid - $advanceUtilized);
    }

    public function getAdvanceUtilizedForInvoice(): float
    {
        return (float) AdvanceUtilization::where('purchase_invoice_id', $this->id)
            ->where('status', 'applied')
            ->sum('utilized_amount');
    }

    public function getNetPayableAmount(): float
    {
        $grandTotal = (float) $this->grand_total;
        $directPayments = $this->getActualPaidAmount();
        $advanceUtilized = $this->getAdvanceUtilizedForInvoice();
        $activeRequests = $this->getActivePaymentRequestsSum();
        
        return max(0, $grandTotal - $directPayments - $advanceUtilized - $activeRequests);
    }

    public function getNetPayableWithoutRequests(): float
    {
        $grandTotal = (float) $this->grand_total;
        $directPayments = $this->getActualPaidAmount();
        $advanceUtilized = $this->getAdvanceUtilizedForInvoice();
        
        return max(0, $grandTotal - $directPayments - $advanceUtilized);
    }

    public function getMaxAllowedPaymentRequest(): float
    {
        return $this->getNetPayableAmount();
    }

    public function getPO(): ?PurchaseOrder
    {
        return $this->po_id ? PurchaseOrder::find($this->po_id) : null;
    }

    public static function getPOWithLock(int $invoiceId): ?PurchaseOrder
    {
        $invoice = self::find($invoiceId);
        if (!$invoice || !$invoice->po_id) {
            return null;
        }

        return PurchaseOrder::where('id', $invoice->po_id)
            ->lockForUpdate()
            ->first();
    }

    public static function lockAdvanceAdjustments(int $poId): \Illuminate\Database\Eloquent\Collection
    {
        $invoiceIds = self::where('po_id', $poId)->pluck('id');
        
        return AdvanceAdjustment::withoutTrashed()
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->lockForUpdate()
            ->get();
    }

    public function getTotalAdvancePaidForPO(): float
    {
        if (!$this->po_id) {
            return 0;
        }

        return PaymentsModule::where('purchase_order_id', $this->po_id)
            ->where('payment_type', 'advance_against_po')
            ->sum('amount');
    }

    public function getTotalAdvanceUsedForPO(): float
    {
        if (!$this->po_id) {
            return 0;
        }

        $invoiceIds = self::where('po_id', $this->po_id)->pluck('id');
        
        return AdvanceAdjustment::withoutTrashed()
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->sum('utilized_amount');
    }

    public function getRemainingAdvanceForPO(): float
    {
        return max(0, $this->getTotalAdvancePaidForPO() - $this->getTotalAdvanceUsedForPO());
    }
}
