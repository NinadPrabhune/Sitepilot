<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\PaymentModuleAllocation;
use App\Models\AdvanceAdjustment;
use App\Models\PurchaseOrder;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $poCalculationService;

    public function __construct(POCalculationService $poCalculationService)
    {
        $this->poCalculationService = $poCalculationService;
    }

    /**
     * @deprecated Use createAgainstInvoice() instead
     * This method is deprecated and will be removed after Phase 8
     * 
     * HARD FREEZE: PO-based payments are no longer allowed
     * System now enforces invoice-only payment creation
     */
    public function create(array $data): PaymentsModule
    {
        // HARD FREEZE: Prevent PO-based payment creation
        if (isset($data['payment_type']) && in_array($data['payment_type'], ['against_po', 'advance_against_po'])) {
            Log::channel('payment_audit')->error('HARD FREEZE: Attempted to create PO-based payment', [
                'payment_type' => $data['payment_type'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'attempted_by' => auth()->id() ?? 1,
            ]);

            throw new \InvalidArgumentException(
                'HARD FREEZE: PO-based payments are no longer allowed. ' .
                'System now enforces invoice-only payment creation. ' .
                'Use createAgainstInvoice() with a valid purchase_invoice_id.'
            );
        }

        // HARD FREEZE: Require purchase_invoice_id for all payments
        if (empty($data['purchase_invoice_id'])) {
            Log::channel('payment_audit')->error('HARD FREEZE: Attempted to create payment without invoice', [
                'payment_type' => $data['payment_type'] ?? 'unknown',
                'attempted_by' => auth()->id() ?? 1,
            ]);

            throw new \InvalidArgumentException(
                'HARD FREEZE: All payments must have a purchase_invoice_id. ' .
                'System now enforces invoice-only payment creation.'
            );
        }

        return DB::transaction(function () use ($data) {

            // Double-spend protection: lock invoice if provided
            if (!empty($data['purchase_invoice_id'])) {
                $invoice = PurchaseInvoice::where('id', $data['purchase_invoice_id'])
                    ->lockForUpdate()
                    ->first();

                if ($invoice) {
                    $totalPaid = PaymentsModule::where('purchase_invoice_id', $invoice->id)
                        ->sum('amount');
                    
                    $newPaymentAmount = (float) $data['amount'];
                    $totalAfterPayment = $totalPaid + $newPaymentAmount;

                    if ($totalAfterPayment > $invoice->grand_total) {
                        throw new \InvalidArgumentException(
                            'Payment exceeds invoice amount. Invoice: ₹' . number_format($invoice->grand_total, 2) . 
                            ', Already paid: ₹' . number_format($totalPaid, 2) . 
                            ', Attempting: ₹' . number_format($newPaymentAmount, 2)
                        );
                    }
                }
            }

            $payment = PaymentsModule::create($data);

            Log::channel('payment_audit')->info('Payment created (legacy create method - HARD FREEZE ENFORCED)', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type' => $payment->payment_type,
                'amount' => $payment->amount,
                'purchase_invoice_id' => $payment->purchase_invoice_id,
                'purchase_order_id' => $payment->purchase_order_id,
                'supplier_id' => $payment->supplier_id,
                'site_id' => $payment->site_id,
                'created_by' => $payment->created_by,
                'created_at' => $payment->created_at,
            ]);

            // Update PO invoicing status if PO exists
            if (!empty($data['purchase_order_id'])) {
                $po = PurchaseOrder::find($data['purchase_order_id']);
                if ($po) {
                    $po->updateInvoicedStatus();
                }
            }

            return $payment;
        });
    }

    protected function processInvoicePayment(PaymentsModule $payment, array $data): void
    {
        $invoice = PurchaseInvoice::findOrFail($data['purchase_invoice_id']);
        $paymentAmount = (float) $data['amount'];

        $invoicePaidAmount = (float) ($invoice->paid_amount ?? 0);
        $newPaidAmount = $invoicePaidAmount + $paymentAmount;
        $invoice->paid_amount = $newPaidAmount;
        $invoice->save();

        $this->updateInvoicePaymentStatus($invoice);
    }

    public function updateInvoicePaymentStatus(PurchaseInvoice $invoice): void
    {
        $paidAmount = (float) ($invoice->paid_amount ?? 0);
        $totalAmount = (float) $invoice->grand_total;

        if ($paidAmount <= 0) {
            $status = 'unpaid';
        } elseif ($paidAmount >= $totalAmount) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        $invoice->payment_status = $status;
        $invoice->save();
    }

    public function adjustAdvance(PaymentsModule $advancePayment, PurchaseInvoice $invoice, float $amount): AdvanceAdjustment
    {
        $availableAdvance = $advancePayment->getAvailableAdvance();

        if ($amount > $availableAdvance) {
            $amount = $availableAdvance;
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Invalid advance adjustment amount');
        }

        $adjustment = AdvanceAdjustment::create([
            'payment_id' => $advancePayment->id,
            'purchase_invoice_id' => $invoice->id,
            'utilized_amount' => $amount,
        ]);

        Log::channel('payment_audit')->info('Advance adjustment created', [
            'adjustment_id' => $adjustment->id,
            'payment_id' => $advancePayment->id,
            'payment_number' => $advancePayment->payment_number,
            'purchase_invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'utilized_amount' => $amount,
            'available_advance_before' => $availableAdvance,
            'created_at' => $adjustment->created_at,
        ]);

        $this->processInvoicePayment($advancePayment, [
            'purchase_invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        return $adjustment;
    }

    /**
     * @deprecated Use createAgainstInvoice() instead
     */
    public function createWithAdvanceAllocation(array $data): PaymentsModule
    {
        return DB::transaction(function () use ($data) {
            $payment = PaymentsModule::create($data);

            Log::channel('payment_audit')->info('Payment created with advance allocation (DEPRECATED)', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type' => $payment->payment_type,
                'amount' => $payment->amount,
                'purchase_invoice_id' => $payment->purchase_invoice_id,
                'purchase_order_id' => $payment->purchase_order_id,
                'advance_payment_id' => $data['advance_payment_id'] ?? null,
                'advance_amount' => $data['advance_amount'] ?? 0,
                'created_by' => $payment->created_by,
                'created_at' => $payment->created_at,
            ]);

            if (!empty($data['invoice_id'])) {
                $invoice = PurchaseInvoice::findOrFail($data['invoice_id']);

                if (!empty($data['advance_payment_id'])) {
                    $advancePayment = PaymentsModule::findOrFail($data['advance_payment_id']);
                    $advanceAmount = $data['advance_amount'] ?? 0;

                    if ($advanceAmount > 0) {
                        $this->adjustAdvance($advancePayment, $invoice, $advanceAmount);
                    }
                } else {
                    $this->processInvoicePayment($payment, $data);
                }

                // Update PO invoicing status if PO exists
                if (!empty($data['purchase_order_id'])) {
                    $po = PurchaseOrder::find($data['purchase_order_id']);
                    if ($po) {
                        $po->updateInvoicedStatus();
                    }
                }
            }

            return $payment;
        });
    }

    public function updateInvoicePaymentStatusWithExclude(?int $invoiceId, ?int $excludePaymentId = null): void
    {
        if (!$invoiceId) {
            return;
        }

        $invoice = PurchaseInvoice::findOrFail($invoiceId);

        $paidAmount = PaymentsModule::where('purchase_invoice_id', $invoiceId)
            ->when($excludePaymentId, fn($q) => $q->where('id', '!=', $excludePaymentId))
            ->sum('amount');

        $totalAmount = (float) $invoice->grand_total;

        if ($paidAmount <= 0) {
            $status = 'unpaid';
        } elseif ($paidAmount >= $totalAmount) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partially_paid';
        } else {
            $status = 'unpaid';
        }

        $invoice->payment_status = $status;
        $invoice->save();
    }

    public function createAgainstInvoice(PaymentRequest $paymentRequest, ?float $customAmount = null): PaymentsModule
    {
        return DB::transaction(function () use ($paymentRequest, $customAmount) {
            // LOCK ORDER: 1. PurchaseInvoice first
            $invoice = PurchaseInvoice::where('id', $paymentRequest->purchase_invoice_id)
                ->lockForUpdate()
                ->first();

            // CRITICAL: Direct GRN hard stop at service level (only if feature flag enabled)
            if (config('finance.po_locked_advance_enabled', false) && empty($invoice->po_id)) {
                throw new \InvalidArgumentException(
                    'Direct GRN invoices cannot use advance allocation. ' .
                    'This invoice is not linked to a Purchase Order. ' .
                    'Direct GRN requires full payment without advance.'
                );
            }

            // LOCK ORDER: 2. PaymentRequest (after invoice)
            $paymentRequest = PaymentRequest::where('id', $paymentRequest->id)
                ->lockForUpdate()
                ->first();

            // STEP 1: STRICT VALIDATION - Only approved/partially_approved/partially_paid requests can be paid
            $allowedStatuses = [
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_PAID  // Required for multiple partial payments
            ];

            if (!in_array($paymentRequest->status, $allowedStatuses)) {
                throw new \InvalidArgumentException(
                    'Only approved requests can be paid. Current status: ' . $paymentRequest->status
                );
            }

            // STEP 2: Check if already fully paid (idempotency)
            $totalPaid = PaymentsModule::where('payment_request_id', $paymentRequest->id)->sum('amount');
            $approvedAmount = (float) ($paymentRequest->approved_amount ?? $paymentRequest->requested_amount);
            $remainingApproved = $approvedAmount - $totalPaid;

            Log::channel('payment_audit')->info('Payment validation', [
                'payment_request_id' => $paymentRequest->id,
                'status' => $paymentRequest->status,
                'total_paid' => $totalPaid,
                'approved_amount' => $approvedAmount,
                'remaining_approved' => $remainingApproved,
            ]);

            if ($totalPaid >= $approvedAmount) {
                Log::channel('payment_audit')->warning('Payment blocked - already fully paid', [
                    'payment_request_id' => $paymentRequest->id,
                    'total_paid' => $totalPaid,
                    'approved_amount' => $approvedAmount,
                ]);
                throw new \InvalidArgumentException('Payment request is already fully paid.');
            }

            // STEP 3: Determine payment amount
            $paymentAmount = $customAmount ?? $approvedAmount;

            // Validate payment amount doesn't exceed remaining approved amount
            if ($paymentAmount > $remainingApproved) {
                Log::channel('payment_audit')->warning('Payment blocked - exceeds remaining approved', [
                    'payment_request_id' => $paymentRequest->id,
                    'payment_amount' => $paymentAmount,
                    'remaining_approved' => $remainingApproved,
                ]);
                throw new \InvalidArgumentException(
                    'Approved amount exhausted for this request. Please create a new Payment Request for remaining invoice balance.'
                );
            }

            // Use snapshot values instead of recalculating
            $pendingBalance = $paymentRequest->net_payable_snapshot
                ?? max(0, $invoice->grand_total - $invoice->getActualPaidAmount() - $invoice->getAdvanceUtilizedForInvoice());

            if ($paymentAmount > $pendingBalance) {
                throw new \InvalidArgumentException('Payment amount exceeds remaining invoice amount. Maximum allowed: ₹' . number_format($pendingBalance, 2));
            }

            // LOCK ORDER: 3. PurchaseOrder (if exists)
            $po = null;
            if ($invoice->po_id) {
                $po = PurchaseOrder::where('id', $invoice->po_id)
                    ->lockForUpdate()
                    ->first();
            }

            // LOCK ORDER: 4. AdvanceUtilizations (if exists)
            if ($invoice->po_id) {
                $this->lockAdvanceUtilizationsForInvoice($invoice->po_id);
            }

            // STEP 4: Create payment with the validated amount
            $payment = PaymentsModule::create([
                'payment_number' => PaymentsModule::generatePaymentNumber($invoice->site_id),
                'supplier_id' => $invoice->supplier_id,
                'purchase_invoice_id' => $invoice->id,
                'purchase_order_id' => $invoice->po_id,
                'site_id' => $invoice->site_id,
                'payment_date' => $paymentRequest->payment_date,
                'amount' => $paymentAmount,
                'payment_type' => PaymentsModule::PAYMENT_TYPE_AGAINST_INVOICE,
                'mode' => $paymentRequest->mode ?? 'bank_transfer',
                'status' => PaymentsModule::STATUS_COMPLETED,
                'created_by' => $paymentRequest->approved_by ?? auth()->id(),
                'workspace_id' => $invoice->workspace_id,
                'notes' => $paymentRequest->remarks,
                'payment_request_id' => $paymentRequest->id,
            ]);

            Log::channel('payment_audit')->info('Payment created from payment request', [
                'payment_request_id' => $paymentRequest->id,
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type' => $payment->payment_type,
                'amount' => $payment->amount,
                'purchase_invoice_id' => $payment->purchase_invoice_id,
                'purchase_order_id' => $payment->purchase_order_id,
                'supplier_id' => $payment->supplier_id,
                'site_id' => $payment->site_id,
                'total_paid_before' => $totalPaid,
                'approved_amount' => $approvedAmount,
                'remaining_approved_after' => $remainingApproved - $paymentAmount,
                'user_id' => $paymentRequest->approved_by ?? auth()->id(),
                'created_at' => $payment->created_at,
            ]);

            // STEP 5: Apply reservation on payment success (only if feature flag enabled)
            if (config('finance.po_locked_advance_enabled', false)) {
                $allocationService = new AdvanceAllocationService();
                $allocationService->applyReservation($paymentRequest->id);
            }

            // STEP 6: Update PaymentRequest status
            $paymentRequest->updateStatusOnPayment();

            $this->updateInvoicePaymentStatus($invoice);

            if ($invoice->po_id) {
                $po = PurchaseOrder::find($invoice->po_id);
                if ($po) {
                    $po->updateInvoicedStatus();
                }
            }

            // Create supplier ledger entry for the payment
            try {
                app(\App\Services\LedgerService::class)->createPaymentEntry($payment, fromPaymentService: true);
                Log::channel('payment_audit')->info('Ledger entry created for payment', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                ]);
            } catch (\Exception $e) {
                Log::channel('payment_audit')->error('Failed to create supplier ledger entry for payment', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'error' => $e->getMessage(),
                ]);
                \Log::error('Failed to create supplier ledger entry for payment: ' . $e->getMessage());
                throw $e; // Rollback transaction
            }

            // Send payment creation notification
            try {
                app(\App\Services\NotificationService::class)->createPaymentNotification(
                    $payment,
                    $invoice->site_id,
                    'Against Invoice'
                );
            } catch (\Exception $e) {
                Log::channel('payment_audit')->error('Failed to send payment notification', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $payment;
        });
    }

    protected function lockAdvanceUtilizationsForInvoice(int $poId): void
    {
        $invoiceIds = \App\Models\PurchaseInvoice::where('po_id', $poId)->pluck('id');

        if ($invoiceIds->isNotEmpty()) {
            \App\Models\AdvanceUtilization::whereIn('purchase_invoice_id', $invoiceIds)
                ->lockForUpdate()
                ->get();
        }
    }

    /**
     * Create payment from approved payment request (unified entry point)
     * Handles both PO Advance and Invoice Payment types with all safety validations
     * Includes deadlock retry mechanism for production reliability
     *
     * @param PaymentRequest $paymentRequest
     * @param float|null $customAmount
     * @param string|null $idempotencyKey
     * @return PaymentsModule
     * @throws \InvalidArgumentException
     */
    public function createPaymentFromRequest(PaymentRequest $paymentRequest, ?float $customAmount = null, ?string $idempotencyKey = null): PaymentsModule
    {
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                return DB::transaction(function () use ($paymentRequest, $customAmount, $idempotencyKey) {
                    // STEP 1: Row locking for concurrency protection
                    $lockedRequest = PaymentRequest::where('id', $paymentRequest->id)
                        ->lockForUpdate()
                        ->first();

                    // Lock related records based on type
                    if ($lockedRequest->isPoAdvance()) {
                        PurchaseOrder::where('id', $lockedRequest->po_id)->lockForUpdate()->first();
                    } elseif ($lockedRequest->isInvoicePayment()) {
                        PurchaseInvoice::where('id', $lockedRequest->purchase_invoice_id)->lockForUpdate()->first();
                    }

                    // STEP 2: Idempotency check
                    if (!empty($idempotencyKey)) {
                        $existingPayment = PaymentsModule::where('idempotency_key', $idempotencyKey)->first();
                        if ($existingPayment) {
                            Log::channel('payment_audit')->info('Payment idempotent - returning existing', [
                                'existing_payment_id' => $existingPayment->id,
                                'idempotency_key' => $idempotencyKey,
                            ]);
                            return $existingPayment;
                        }
                    }

                    // STEP 3: Status validation
                    $allowedStatuses = [
                        PaymentRequest::STATUS_APPROVED,
                        PaymentRequest::STATUS_PARTIALLY_APPROVED,
                        PaymentRequest::STATUS_PARTIALLY_PAID,
                    ];

                    if (!in_array($lockedRequest->status, $allowedStatuses)) {
                        throw new \InvalidArgumentException('Payment not allowed. Current status: ' . $lockedRequest->status);
                    }

                    // STEP 4: Additional safety validations
                    if ($lockedRequest->status === PaymentRequest::STATUS_REJECTED) {
                        throw new \InvalidArgumentException('Cannot pay rejected request');
                    }

                    // STEP 5: Amount validation
                    $totalPaid = $lockedRequest->payments()->sum('amount');
                    $approvedAmount = (float) ($lockedRequest->approved_amount ?? $lockedRequest->requested_amount);
                    $remainingApproved = $approvedAmount - $totalPaid;

                    $paymentAmount = $customAmount ?? $approvedAmount;

                    // Prevent negative or zero payments
                    if ($paymentAmount <= 0) {
                        throw new \InvalidArgumentException('Payment amount must be greater than zero');
                    }

                    // Double approval change protection
                    // Calculate paid_amount dynamically (not stored in DB)
                    $currentPaid = $lockedRequest->payments()->sum('amount');
                    if ($currentPaid > $lockedRequest->approved_amount) {
                        throw new \InvalidArgumentException('Invalid state: paid exceeds approved');
                    }

                    if ($paymentAmount > $remainingApproved) {
                        throw new \InvalidArgumentException(
                            'Payment amount exceeds approved limit. Maximum: ₹' . number_format($remainingApproved, 2)
                        );
                    }

                    // STEP 6: PO limit validation at payment time
                    if ($lockedRequest->isPoAdvance()) {
                        $summary = $this->getPOAdvanceSummary($lockedRequest->po_id);

                        if (($summary['total_paid'] + $paymentAmount) > $summary['total_po_amount']) {
                            throw new \InvalidArgumentException(
                                'PO limit exceeded. Total paid would be ₹' . number_format($summary['total_paid'] + $paymentAmount, 2) .
                                ' against PO total of ₹' . number_format($summary['total_po_amount'], 2)
                            );
                        }
                    }

                    // STEP 7: Create payment based on type
                    if ($lockedRequest->isPoAdvance()) {
                        $payment = $this->createPoAdvancePayment($lockedRequest, $paymentAmount, $idempotencyKey);
                    } elseif ($lockedRequest->isInvoicePayment()) {
                        $payment = $this->createAgainstInvoice($lockedRequest, $paymentAmount);
                    } else {
                        throw new \InvalidArgumentException('Unknown payment request type: ' . $lockedRequest->type);
                    }

                    // STEP 8: Update status using centralized helper
                    // Note: paid_amount is calculated dynamically by summing payments, not stored in DB
                    $this->updatePaymentRequestStatus($lockedRequest);

                    // STEP 10: Create ledger entry with integrity check
                    try {
                        app(\App\Services\LedgerService::class)->createPaymentEntry($payment, fromPaymentService: true);
                        Log::channel('payment_audit')->info('Ledger entry created for payment', [
                            'payment_id' => $payment->id,
                            'payment_number' => $payment->payment_number,
                            'amount' => $payment->amount,
                        ]);
                    } catch (\Exception $e) {
                        Log::channel('payment_audit')->error('Failed to create ledger entry', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e; // Rollback transaction
                    }

                    // STEP 11: Audit log with before/after state
                    $newPaid = $lockedRequest->payments()->sum('amount');
                    Log::channel('payment_audit')->info('Payment state transition', [
                        'request_id' => $lockedRequest->id,
                        'before_paid' => $totalPaid,
                        'after_paid' => $newPaid,
                        'approved_amount' => $approvedAmount,
                        'payment_amount' => $paymentAmount,
                        'request_type' => $lockedRequest->type,
                        'executed_by' => auth()->id(),
                    ]);

                    return $payment;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), 'deadlock')) {
                    $attempts++;
                    Log::channel('payment_audit')->warning('Deadlock detected, retrying', [
                        'attempt' => $attempts,
                        'max_attempts' => $maxAttempts,
                        'payment_request_id' => $paymentRequest->id,
                    ]);
                    usleep(100000); // 100ms delay before retry
                    continue;
                }
                throw $e;
            }
        }

        throw new \Exception('Transaction failed after ' . $maxAttempts . ' attempts due to deadlocks');
    }

    /**
     * Create PO advance payment
     *
     * @param PaymentRequest $paymentRequest
     * @param float $amount
     * @param string|null $idempotencyKey
     * @return PaymentsModule
     */
    private function createPoAdvancePayment(PaymentRequest $paymentRequest, float $amount, ?string $idempotencyKey): PaymentsModule
    {
        $po = PurchaseOrder::findOrFail($paymentRequest->po_id);

        $payment = PaymentsModule::create([
            'payment_number' => PaymentsModule::generatePaymentNumber($po->site_id),
            'supplier_id' => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'site_id' => $po->site_id,
            'payment_date' => $paymentRequest->payment_date ?? now(),
            'amount' => $amount,
            'payment_type' => PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO,
            'mode' => 'bank_transfer',
            'status' => PaymentsModule::STATUS_COMPLETED,
            'created_by' => auth()->id(),
            'workspace_id' => $po->workspace_id,
            'notes' => $paymentRequest->remarks,
            'payment_request_id' => $paymentRequest->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Create allocation
        PaymentModuleAllocation::create([
            'payment_module_id' => $payment->id,
            'purchase_order_id' => $po->id,
            'allocated_amount' => $amount,
        ]);

        // Send payment creation notification
        try {
            app(\App\Services\NotificationService::class)->createPaymentNotification(
                $payment,
                $po->site_id,
                'Advance Against PO'
            );
        } catch (\Exception $e) {
            Log::channel('payment_audit')->error('Failed to send payment notification', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment;
    }

    /**
     * Update payment request status based on paid amount
     * Centralized status transition logic
     *
     * @param PaymentRequest $request
     * @return void
     */
    private function updatePaymentRequestStatus(PaymentRequest $request): void
    {
        // Calculate paid_amount dynamically (not stored in DB)
        $paid = $request->payments()->sum('amount');
        $approved = $request->approved_amount ?? $request->requested_amount;

        if ($paid == 0) {
            return; // No change
        }

        if ($paid < $approved) {
            $request->status = PaymentRequest::STATUS_PARTIALLY_PAID;
            $request->paid_at = null;
        } else {
            $request->status = PaymentRequest::STATUS_PAID;
            $request->paid_at = now();
        }

        $request->saveQuietly();
    }

    /**
     * Get PO advance summary for tracking
     *
     * @param int $poId
     * @return array
     */
    public function getPOAdvanceSummary(int $poId): array
    {
        $po = PurchaseOrder::findOrFail($poId);

        // Calculate totals for TYPE_PO_ADVANCE requests
        $totalRequested = PaymentRequest::forPo($poId)
            ->poAdvance()
            ->sum('requested_amount');

        $totalApproved = PaymentRequest::forPo($poId)
            ->poAdvance()
            ->whereIn('status', [PaymentRequest::STATUS_APPROVED, PaymentRequest::STATUS_PARTIALLY_APPROVED, PaymentRequest::STATUS_PAID, PaymentRequest::STATUS_PARTIALLY_PAID])
            ->sum('approved_amount');

        // Calculate total paid from payments linked to PO advance requests
        $advanceRequestIds = PaymentRequest::forPo($poId)
            ->poAdvance()
            ->pluck('id');

        $totalPaid = PaymentsModule::whereIn('payment_request_id', $advanceRequestIds)
            ->sum('amount');

        $remainingAdvance = max(0, $totalApproved - $totalPaid);

        return [
            'total_po_amount' => (float) $po->grand_total,
            'total_requested' => (float) $totalRequested,
            'total_approved' => (float) $totalApproved,
            'total_paid' => (float) $totalPaid,
            'remaining_advance' => $remainingAdvance,
        ];
    }

    /**
     * Apply advance to invoice (wrapper around AdvanceUtilizationService)
     *
     * @param int $invoiceId
     * @param float $amount
     * @return AdvanceUtilization
     */
    public function applyAdvanceToInvoice(int $invoiceId, float $amount): AdvanceUtilization
    {
        $utilizationService = new AdvanceUtilizationService();
        return $utilizationService->apply($invoiceId, $amount);
    }

    /**
     * Check if payment request workflow should be enforced
     *
     * @return bool
     */
    private function shouldEnforcePaymentRequest(): bool
    {
        return config('payments.enforce_request', true);
    }
}