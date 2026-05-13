<?php

namespace App\Models;

use App\Traits\HasAssignTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, HasAssignTo;

    protected $fillable = [
        'po_number',
        'po_date',
        'supplier_invoice_number',
        'supplier_id',
        'tax_type',
        'total_taxable_value',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_tax',
        'total_discount',
        'additional_charge',
        'additional_deduction',
        'additional_discount',
        'grand_total',
        'status',
        'site_id',
        'created_by',
        'workspace_id',
        'indent_id',
        'description',
        'rejection_reason',
        'delivery_date',
        'delivery_address',
        'reference_file',
        'delivery_terms_conditions',
        'payment_terms_conditions',
        'remark',
        'po_pdf',
        'rejection_reason',
        'flag_reason',
        'short_close_reason',
        'short_closed_at',
        'short_closed_by',
        'payment_flag_deprecated', // DEPRECATED: Will be removed after Phase 8
        'invoiced_amount',
        'invoiced_status',
        'assign_to',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'po_date' => 'date',
        'delivery_date' => 'date',
        'closed_date' => 'date',
        'total_taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'additional_charge' => 'decimal:2',
        'additional_deduction' => 'decimal:2',
        'additional_discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'invoiced_amount' => 'decimal:2',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Tax type constants
    const TAX_TYPE_CGST = 'cgst';
    const TAX_TYPE_IGST = 'igst';

    // Status constants
    const STATUS_DRAFT = 'Draft';
    const STATUS_APPROVED = 'Approved';
    const STATUS_PARTIAL_RECEIVED = 'Partial Received';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_REJECTED = 'Rejected';
    const STATUS_FLAGGED = 'Flagged';
    const STATUS_SHORT_CLOSED = 'Short Closed';
    const STATUS_PARTIAL = 'Partial';
    const STATUS_CLOSED = 'Closed';
    const STATUS_CANCELLED = 'Cancelled';

    // Rejectable statuses (constants for validation)
    const REJECTABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_FLAGGED,
    ];

    // Payment Flag constants
    const PAYMENT_FLAG_PENDING = 'Pending';
    const PAYMENT_FLAG_PARTIAL_RECEIVED = 'Partial Received';
    const PAYMENT_FLAG_FULLY_RECEIVED = 'Fully Received';

    /**
     * Get the items for the purchase order.
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the supplier for the purchase order.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the site for the purchase order.
     */
    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    /**
     * Get the creator of the purchase order.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the indent for the purchase order.
     */
    public function indent()
    {
        return $this->belongsTo(Indent::class);
    }

    /**
     * Get the workspace for the purchase order.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Get the status logs for the purchase order.
     */
    public function statusLogs()
    {
        return $this->hasMany(PoStatusLog::class, 'purchase_order_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the GRNs for the purchase order.
     */
    public function grns()
    {
        return $this->hasMany(Grn::class, 'po_id');
    }

    /**
     * Get the display status - virtual attribute for 'Flagged - Corrected' status.
     * 
     * Logic:
     * - If status = 'Flagged'
     * - Get the latest status log where new_status = 'Flagged'
     * - If PO's updated_at > log's created_at, return 'Flagged - Corrected'
     * - Otherwise return original status
     */
    public function getDisplayStatusAttribute()
    {
        // Only apply this logic if status is Flagged
        if ($this->status !== self::STATUS_FLAGGED) {
            return $this->status;
        }

        // Get the latest status log where new_status = 'Flagged'
        $flaggedLog = $this->statusLogs()
            ->where('new_status', self::STATUS_FLAGGED)
            ->orderBy('created_at', 'desc')
            ->first();

        // If no flagged log exists, return original status
        if (!$flaggedLog) {
            return $this->status;
        }

        // If PO was updated after the flag was applied, it's been corrected
        if ($this->updated_at > $flaggedLog->created_at) {
            return 'Flagged - Corrected';
        }

        return $this->status;
    }

    /**
     * Calculate totals from items (backend calculation).
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

    // Correct Grand Total Formula
    $grandTotal =
        $totalTaxableValue
        + $totalTax
        + (float) ($this->additional_charge ?? 0)
        - (float) ($this->additional_deduction ?? 0)
        - (float) ($this->additional_discount ?? 0);

    $this->grand_total = round(max($grandTotal, 0), 2);
}

    /**
     * Update indent status after changes.
     */
    public function updateIndentStatus(): void
    {
        if ($this->indent) {
            $this->indent->updateStatus();
        }
    }

    /**
     * Validate quantity against indent.
     */
    public function validateQuantityAgainstIndent(): bool
    {
        if (!$this->indent) {
            return true;
        }

        foreach ($this->items as $item) {
            $indentItem = $this->indent->items()
                ->where('material_id', $item->material_id)
                ->first();

            if ($indentItem && $item->quantity > $indentItem->remaining_quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope for filtering by workspace.
     */
    public function scopeWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by supplier.
     */
    public function scopeSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('po_date', [$startDate, $endDate]);
    }

    /**
     * Generate unique PO number with per-workspace reset.
     * CRITICAL: PO uses workspace scope, not site scope
     */
    public static function generatePONumber(?int $workspaceId = null): string
    {
        return app(\App\Services\NumberGeneratorService::class)->generate('po', $workspaceId);
    }

    /**
     * Format PO number with prefix and padded number.
     */
    public static function purchaseOrderNumberFormat($number, $company_id = null, $workspace = null)
    {
        if (!empty($company_id) && empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id, $workspace);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        
        $data = !empty($company_settings['po_prefix']) ? $company_settings['po_prefix'] : 'PO';

        return $data . sprintf("%05d", $number);
    }

    /**
     * Check if PO can be edited.
     * Only Draft and Flagged POs can be edited.
     * After edit, Flagged POs can move back to Approved or Rejected.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FLAGGED]);
    }

    /**
     * Check if PO can be approved.
     * Only Draft POs can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if PO can be rejected.
     * Draft, Approved, or Flagged POs can be rejected.
     */
    public function canReject(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_APPROVED,
            self::STATUS_FLAGGED
        ]);
    }

    /**
     * Check if PO can be flagged.
     * Only Approved POs can be flagged.
     */
    public function canFlag(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if PO can have GRN created.
     * Only Approved or Partial Received POs can have GRN.
     * Also handles legacy 'Partial' status.
     */
    public function canCreateGrn(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIAL_RECEIVED,
            'Partial'  // Handle legacy status value
        ]);
    }

    /**
     * Check if PO can be short closed.
     * Only Partial Received POs can be short closed.
     */
    public function canShortClose(): bool
    {
        return $this->status === self::STATUS_PARTIAL_RECEIVED;
    }

    /**
     * Validate status transition.
     * Returns true if transition is allowed, false otherwise.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_FLAGGED],
            self::STATUS_APPROVED => [],
            self::STATUS_FLAGGED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_FLAGGED],
            self::STATUS_PARTIAL_RECEIVED => [self::STATUS_SHORT_CLOSED],
            self::STATUS_COMPLETED => [],
            self::STATUS_REJECTED => [],
            self::STATUS_SHORT_CLOSED => [],
        ];

        return isset($transitions[$this->status]) && in_array($newStatus, $transitions[$this->status]);
    }

    /**
     * Get allowed status transitions for current status.
     */
    public function getAllowedTransitions(): array
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_FLAGGED],
            self::STATUS_APPROVED => [self::STATUS_FLAGGED, self::STATUS_REJECTED],
            self::STATUS_FLAGGED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_FLAGGED],
            self::STATUS_PARTIAL_RECEIVED => [self::STATUS_SHORT_CLOSED],
            self::STATUS_COMPLETED => [],
            self::STATUS_REJECTED => [],
            self::STATUS_SHORT_CLOSED => [],
        ];

        return $transitions[$this->status] ?? [];
    }

    /**
     * Check if all PO items are fully received.
     */
    public function isFullyReceived(): bool
    {
        $this->load('items');
        
        foreach ($this->items as $item) {
            $receivedQty = floatval($item->received_qty ?? 0);
            if ($receivedQty < floatval($item->quantity)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if PO has any received quantity.
     */
    public function hasPartialReceived(): bool
    {
        $this->load('items');
        
        foreach ($this->items as $item) {
            $receivedQty = floatval($item->received_qty ?? 0);
            if ($receivedQty > 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Update PO status based on received quantities.
     */
    public function updateStatusFromGrn(): void
    {
        if ($this->isFullyReceived()) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($this->hasPartialReceived()) {
            $this->status = self::STATUS_PARTIAL_RECEIVED;
        }
        
        $this->save();
    }

    /**
     * Update payment flag based only on grand_total and payments made.
     * Ignores GRN and invoice amounts - payment eligibility reflects PO value only.
     */
    /**
     * @deprecated Use updateInvoicedStatus() instead
     * This method is deprecated and will be removed after Phase 8
     */
    public function updatePaymentFlag(): void
    {
        \Log::channel('payment_audit')->warning('updatePaymentFlag() is deprecated, use updateInvoicedStatus()', [
            'po_id' => $this->id,
            'po_number' => $this->po_number,
        ]);

        $this->load('payments');

        $totalPaid = $this->payments()
            ->whereIn('payment_type', [
                \App\Models\PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO,
                \App\Models\PaymentsModule::PAYMENT_TYPE_AGAINST_PO
            ])
            ->sum('amount');

        $grandTotal = (float) $this->grand_total;

        if ($grandTotal <= 0) {
            $this->payment_flag_deprecated = self::PAYMENT_FLAG_PENDING;
        } elseif ($totalPaid >= $grandTotal) {
            $this->payment_flag_deprecated = self::PAYMENT_FLAG_FULLY_RECEIVED;
        } elseif ($totalPaid > 0) {
            $this->payment_flag_deprecated = self::PAYMENT_FLAG_PARTIAL_RECEIVED;
        } else {
            $this->payment_flag_deprecated = self::PAYMENT_FLAG_PENDING;
        }

        $this->save();
    }

    /**
     * Update invoicing status based on invoiced amount
     * This replaces the deprecated updatePaymentFlag() method
     */
    public function updateInvoicedStatus(): void
    {
        $invoicedAmount = $this->invoices()->sum('grand_total');
        $poTotal = (float) $this->grand_total;

        if ($invoicedAmount >= $poTotal) {
            $this->invoiced_status = 'fully_invoiced';
        } elseif ($invoicedAmount > 0) {
            $this->invoiced_status = 'partially_invoiced';
        } else {
            $this->invoiced_status = 'not_invoiced';
        }

        $this->invoiced_amount = $invoicedAmount;
        $this->save();

        \Log::channel('payment_audit')->info('PO invoicing status updated', [
            'po_id' => $this->id,
            'po_number' => $this->po_number,
            'invoiced_amount' => $invoicedAmount,
            'po_total' => $poTotal,
            'invoiced_status' => $this->invoiced_status,
        ]);
    }

    /**
     * @deprecated Use scopeInvoicingEligible() instead
     */
    public function scopePaymentEligible($query)
    {
        \Log::channel('payment_audit')->warning('scopePaymentEligible() is deprecated', [
            'context' => 'Query scope using deprecated payment_flag',
        ]);

        return $query->whereIn('payment_flag_deprecated', [
            self::PAYMENT_FLAG_PENDING,
            self::PAYMENT_FLAG_PARTIAL_RECEIVED
        ]);
    }

    /**
     * Scope for POs that can still receive invoices
     */
    public function scopeInvoicingEligible($query)
    {
        return $query->whereIn('invoiced_status', [
            'not_invoiced',
            'partially_invoiced'
        ]);
    }

    /**
     * @deprecated Use getInvoicedStatusDisplay() instead
     * Get the payment flag display text with color class.
     */
    public function getPaymentFlagDisplay(): array
    {
        $flag = $this->payment_flag_deprecated ?? self::PAYMENT_FLAG_PENDING;
        
        $colors = [
            self::PAYMENT_FLAG_PENDING => ['text' => 'text-muted', 'bg' => 'bg-secondary', 'label' => 'Pending'],
            self::PAYMENT_FLAG_PARTIAL_RECEIVED => ['text' => 'text-primary', 'bg' => 'bg-info', 'label' => 'Partial Received'],
            self::PAYMENT_FLAG_FULLY_RECEIVED => ['text' => 'text-success', 'bg' => 'bg-success', 'label' => 'Fully Received'],
        ];

        return $colors[$flag] ?? $colors[self::PAYMENT_FLAG_PENDING];
    }

    /**
     * Get the invoicing status display text with color class.
     */
    public function getInvoicedStatusDisplay(): array
    {
        $status = $this->invoiced_status ?? 'not_invoiced';

        $colors = [
            'not_invoiced' => ['text' => 'text-muted', 'bg' => 'bg-secondary', 'label' => 'Not Invoiced'],
            'partially_invoiced' => ['text' => 'text-primary', 'bg' => 'bg-info', 'label' => 'Partially Invoiced'],
            'fully_invoiced' => ['text' => 'text-success', 'bg' => 'bg-success', 'label' => 'Fully Invoiced'],
        ];

        return $colors[$status] ?? $colors['not_invoiced'];
    }

    /**
     * Log status change to history.
     */
    public function logStatusChange(string $newStatus, ?string $reason = null, ?int $changedBy = null): void
    {
        \App\Models\PoStatusLog::logStatusChange(
            $this->id,
            $this->status,
            $newStatus,
            $reason,
            $changedBy
        );
    }

    public function invoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'po_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentsModule::class, 'purchase_order_id');
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class, 'po_id');
    }

    public function getInvoicedAmountAttribute(): float
    {
        if (isset($this->attributes['invoiced_amount'])) {
            return (float) $this->attributes['invoiced_amount'];
        }
        return $this->invoices()->sum('grand_total');
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()
            ->where('payment_type', 'against_po')
            ->sum('amount');
    }

    public function getPOBalanceAttribute(): float
    {
        return max(0, $this->grand_total - $this->invoiced_amount);
    }

    public function getPayableAttribute(): float
    {
        return max(0, $this->invoiced_amount - $this->total_paid);
    }

    public function getAdvancePaidAttribute(): float
    {
        return $this->payments()
            ->where('payment_type', 'advance_against_po')
            ->sum('amount');
    }

    public function getAdvanceBalanceAttribute(): float
    {
        $utilized = \App\Models\AdvanceAdjustment::withoutTrashed()
            ->whereIn('purchase_invoice_id', $this->invoices()->pluck('id'))
            ->sum('utilized_amount');
        return max(0, $this->advance_paid - $utilized);
    }

    public function canMakePayment(): bool
    {
        return in_array($this->payment_flag ?? self::PAYMENT_FLAG_PENDING, [
            self::PAYMENT_FLAG_PENDING,
            self::PAYMENT_FLAG_PARTIAL_RECEIVED
        ]);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if PO payment is 100% completed based on actual payments
     * NOT based on invoice status
     */
    public function isPaymentCompleted(): bool
    {
        return $this->total_paid >= $this->grand_total;
    }

    /**
     * Check if an advance request already exists for this PO
     * Checks for ANY advance request regardless of status
     */
    public function hasAdvanceRequest(): bool
    {
        return \App\Models\PaymentRequest::where('po_id', $this->id)
            ->where('type', \App\Models\PaymentRequest::TYPE_PO_ADVANCE)
            ->exists();
    }

    /**
     * Check if an active (non-rejected) advance request exists for this PO
     * Allows creating new requests if previous one was rejected
     */
    public function hasActiveAdvanceRequest(): bool
    {
        return \App\Models\PaymentRequest::where('po_id', $this->id)
            ->where('type', \App\Models\PaymentRequest::TYPE_PO_ADVANCE)
            ->where('status', '!=', \App\Models\PaymentRequest::STATUS_REJECTED)
            ->exists();
    }
}
