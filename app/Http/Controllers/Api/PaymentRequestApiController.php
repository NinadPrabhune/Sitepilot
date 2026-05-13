<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\NotificationService;
use App\Services\POCalculationService;
use App\Services\PaymentService;
use App\Services\AdvanceAllocationService;
use App\Services\POAdvanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * @group Payment Request
 *
 * Mobile API endpoints for payment request creation and management.
 * These endpoints replicate the exact business logic from the web implementation.
 *
 * PRODUCTION MONITORING CONSIDERATIONS:
 *
 * 1. Data Drift Detection:
 *    - Financial snapshots captured at approval time are used for payment execution
 *    - Monitor for cases where snapshot != current recalculated state
 *    - Consider logging both snapshot and recalculated values at payment time for forensic capability
 *
 * 2. Idempotency Key Usage:
 *    - Monitor for non-unique or reused keys from mobile clients
 *    - Recommended format: payment_request_id + timestamp + device_id
 *    - Log idempotency_key + user_id + request_id to detect bad client behavior early
 *
 * 3. Transaction Performance:
 *    - Monitor slow queries and transaction times under high load
 *    - Current implementation uses lockForUpdate() with multiple table locks
 *    - If deadlocks/lock wait timeouts increase, consider moving non-critical work (notifications, logs) async
 *
 * PRODUCTION GUARDRAILS (Recommended):
 *
 * 1. Enforce Idempotency Key:
 *    - Consider making idempotency_key required for payment requests to prevent duplicate payments
 *    - This single rule prevents 80% of duplicate-payment risks
 *
 * 2. Rate Limiting:
 *    - Rate limit critical endpoints (payment, approve) to 5-10 requests/min per user
 *    - Protects against buggy loops and retry storms
 *
 * 3. Monitoring Dashboard:
 *    - Core KPIs: Payments created (count + amount), approval success rate, partial vs full payments, avg processing time
 *    - Risk Indicators: % idempotency replays, % failed payments, 409 conflict rate, deadlock retry count
 *
 * 4. Alert Rules:
 *    - >5% payment failures in 10 mins
 *    - Same request gets 3+ payment attempts
 *    - Same idempotency key reused across users
 *    - Deadlock retries > threshold/min
 *
 * 5. Real-World Validation (First 48 Hours):
 *    - Manually verify random 10 payment requests: requested vs approved vs paid
 *    - Check partial payment chains, PO advance limits, ledger entries vs payments
 *
 * 6. Rollback Safety:
 *    - Keep ability to disable mobile payment endpoint via feature flag
 *    - Allow only approvals temporarily if needed
 *    - DB backup before rollout if volume is high
 */
class PaymentRequestApiController extends Controller
{
    protected NotificationService $notificationService;
    protected POCalculationService $poCalculationService;
    protected PaymentService $paymentService;
    protected AdvanceAllocationService $advanceAllocationService;
    protected POAdvanceService $poAdvanceService;

    public function __construct(
        NotificationService $notificationService,
        POCalculationService $poCalculationService,
        PaymentService $paymentService,
        AdvanceAllocationService $advanceAllocationService,
        POAdvanceService $poAdvanceService
    ) {
        $this->notificationService = $notificationService;
        $this->poCalculationService = $poCalculationService;
        $this->paymentService = $paymentService;
        $this->advanceAllocationService = $advanceAllocationService;
        $this->poAdvanceService = $poAdvanceService;
    }

    /**
     * Get Payment Request Data (Prefill)
     * 
     * Returns invoice details with calculated amounts for payment request creation.
     * This replicates the logic from PaymentRequestController@createModal.
     * 
     * @urlParam invoice_id integer required The ID of the purchase invoice
     * @queryParam workspace_id integer optional Filter by workspace ID
     * @queryParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment request data retrieved successfully",
     *   "data": {
     *     "invoice_id": 123,
     *     "invoice_number": "INV-001",
     *     "supplier_name": "ABC Supplier",
     *     "site_name": "Project A",
     *     "grand_total": 50000.00,
     *     "paid_amount": 20000.00,
     *     "advance_utilized": 10000.00,
     *     "active_requests": 5000.00,
     *     "remaining_balance": 15000.00,
     *     "max_allowed_amount": 15000.00,
     *     "suggested_amount": 15000.00,
     *     "po_id": 456,
     *     "po_number": "PO-001",
     *     "po_advance_total": 20000.00,
     *     "po_advance_used": 10000.00,
     *     "po_advance_remaining": 10000.00,
     *     "payment_terms": "Net 30 days"
     *   }
     * }
     */
    public function getPaymentRequestData(Request $request, $invoiceId)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            // Get invoice data with calculations (same as createModal)
            $query = DB::table('purchase_invoices as pi')
                ->where('pi.id', $invoiceId);

            // Apply workspace filter if provided
            if ($workspaceId) {
                $query->where('pi.workspace_id', $workspaceId);
            }

            // Apply site filter if provided
            if ($siteId) {
                $query->where('pi.site_id', $siteId);
            }

            $invoiceData = $query
                ->selectRaw('
                    pi.id,
                    pi.invoice_number,
                    pi.grand_total,
                    pi.po_id,
                    pi.supplier_id,
                    pi.site_id,
                    pi.workspace_id,
                    pi.invoice_date,

                    (
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments_module 
                        WHERE purchase_invoice_id = pi.id
                          AND payment_type != "advance_against_po"
                    ) as paid_amount,

                    (
                        SELECT COALESCE(SUM(utilized_amount), 0)
                        FROM advance_utilizations
                        WHERE purchase_invoice_id = pi.id
                          AND status = "applied"
                    ) as advance_used,

                    (
                        SELECT COALESCE(SUM(requested_amount), 0)
                        FROM payment_requests
                        WHERE purchase_invoice_id = pi.id
                          AND status IN ("pending","approved","partially_approved")
                    ) as active_requests
                ')
                ->first();

            if (!$invoiceData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }

            $invoice = PurchaseInvoice::with([
                'supplier',
                'site',
                'purchaseOrder',
                'creator'
            ])->findOrFail($invoiceId);

            // PO advance calculations - BEFORE allocation check
            $poAdvanceTotal = 0;
            $poAdvanceUsed = 0;
            $poAdvanceRemaining = 0;

            if ($invoiceData->po_id) {
                // Use new supplier_advances system
                $poAdvanceData = DB::table('supplier_advances as sa')
                    ->where('sa.po_id', $invoiceData->po_id)
                    ->where('sa.status', 'paid')
                    ->where('sa.locked_to_po', true)
                    ->selectRaw('
                        COALESCE(SUM(sa.amount), 0) as total_advance,
                        COALESCE(SUM(sa.utilized_amount), 0) as used_advance
                    ')
                    ->first();

                $poAdvanceTotal = $poAdvanceData->total_advance ?? 0;
                $poAdvanceUsed = $poAdvanceData->used_advance ?? 0;

                // Also include legacy payments_module advances (advance_against_po)
                $legacyAdvanceData = DB::table('payments_module as pm')
                    ->where('pm.purchase_order_id', $invoiceData->po_id)
                    ->where('pm.payment_type', 'advance_against_po')
                    ->where('pm.status', 'completed')
                    ->selectRaw('
                        COALESCE(SUM(pm.amount), 0) as total_advance,
                        COALESCE(SUM((SELECT COALESCE(SUM(au.utilized_amount), 0) FROM advance_utilizations au WHERE au.payments_module_id = pm.id AND au.status = "applied")), 0) as used_advance
                    ')
                    ->first();

                $poAdvanceTotal += $legacyAdvanceData->total_advance ?? 0;
                $poAdvanceUsed += $legacyAdvanceData->used_advance ?? 0;

                $poAdvanceRemaining = max(0, $poAdvanceTotal - $poAdvanceUsed);
            }

            // AUTO-ALLOCATION: If PO has available advance and invoice has no advance allocated yet
            $advanceUtilized = $invoiceData->advance_used;
            if ($invoiceData->po_id && $invoiceData->advance_used == 0 && $poAdvanceRemaining > 0) {
                // Check if feature flag is enabled
                if (config('finance.po_locked_advance_enabled', false)) {
                    // Use AdvanceAllocationService to calculate available advance (dry-run mode)
                    $allocationResult = $this->advanceAllocationService->calculatePotentialAllocation($invoice->id);

                    if ($allocationResult && isset($allocationResult['allocated_amount'])) {
                        $advanceUtilized = $allocationResult['allocated_amount'];
                    }
                    
                    // Recalculate remaining PO advance after allocation
                    $poAdvanceRemaining = max(0, $poAdvanceRemaining - ($allocationResult['allocated_amount'] ?? 0));
                } else {
                    // Feature flag disabled: Calculate potential allocation directly from PO advances
                    $invoiceBalance = max(0, $invoiceData->grand_total - $invoiceData->paid_amount - $invoiceData->advance_used - $invoiceData->active_requests);
                    $potentialAllocation = min($poAdvanceRemaining, $invoiceBalance);
                    
                    if ($potentialAllocation > 0) {
                        $advanceUtilized = $potentialAllocation;
                        // Recalculate remaining PO advance after allocation
                        $poAdvanceRemaining = max(0, $poAdvanceRemaining - $potentialAllocation);
                    }
                }
            }

            // Calculate values after potential allocation
            $paidAmount = $invoiceData->paid_amount;
            $activeRequestsSum = $invoiceData->active_requests;
            
            // Net payable = grand_total - paid - advance_used - active_requests
            $maxAllowedAmount = max(0, $invoiceData->grand_total - $paidAmount - $advanceUtilized - $activeRequestsSum);
            $remainingBalance = $maxAllowedAmount;

            // Get payment terms
            $paymentTerms = $invoice->purchaseOrder?->payment_terms_conditions ?? '';

            // Build response data
            $data = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'supplier_name' => $invoice->supplier?->name ?? '',
                'site_name' => $invoice->site?->name ?? '',
                'grand_total' => (float) $invoiceData->grand_total,
                'paid_amount' => (float) $paidAmount,
                'advance_utilized' => (float) $advanceUtilized,
                'active_requests' => (float) $activeRequestsSum,
                'remaining_balance' => (float) $remainingBalance,
                'max_allowed_amount' => (float) $maxAllowedAmount,
                'suggested_amount' => (float) $maxAllowedAmount,
                'po_id' => $invoice->po_id,
                'po_number' => $invoice->purchaseOrder?->po_number ?? '',
                'po_advance_total' => (float) $poAdvanceTotal,
                'po_advance_used' => (float) $poAdvanceUsed,
                'po_advance_remaining' => (float) $poAdvanceRemaining,
                'payment_terms' => $paymentTerms,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Payment request data retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Payment request data retrieval error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment request data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Payment Request
     * 
     * Creates a new payment request for an invoice.
     * This replicates the exact business logic from PaymentRequestController@store.
     * 
     * @bodyParam purchase_invoice_id integer required The ID of the purchase invoice
     * @bodyParam requested_amount numeric required The amount being requested (min: 0.01)
     * @bodyParam payment_date date required The payment date
     * @bodyParam remarks string optional Remarks for the payment request (max: 1000 characters)
     * @bodyParam idempotency_key string optional Unique key to prevent duplicate requests (max: 64 characters)
     * @bodyParam workspace_id integer optional Filter by workspace ID
     * @bodyParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment request created successfully",
     *   "data": {
     *     "id": 789,
     *     "status": "pending"
     *   }
     * }
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'purchase_invoice_id' => 'required|exists:purchase_invoices,id',
            'requested_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
            'workspace_id' => 'nullable|integer',
            'site_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $invoiceId = $request->purchase_invoice_id;
        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            return DB::transaction(function () use ($request, $invoiceId, $workspaceId, $siteId) {
                // LOCK ORDER: 1. PurchaseInvoice
                $invoice = PurchaseInvoice::where('id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

                if (!$invoice) {
                    throw new \Exception('Invoice not found.');
                }

                // Validate workspace if provided
                if ($workspaceId && $invoice->workspace_id != $workspaceId) {
                    throw new \Exception('Invoice does not belong to the specified workspace.');
                }

                // Validate site if provided
                if ($siteId && $invoice->site_id != $siteId) {
                    throw new \Exception('Invoice does not belong to the specified site.');
                }

                // CRITICAL: Direct GRN hard stop at controller level (only if feature flag enabled)
                if (config('finance.po_locked_advance_enabled', false) && empty($invoice->po_id)) {
                    throw new \InvalidArgumentException(
                        'Direct GRN invoices cannot create payment requests with advance allocation. ' .
                        'This invoice is not linked to a Purchase Order. ' .
                        'Direct GRN requires full payment without advance.'
                    );
                }

                // CRITICAL: Idempotency check
                if (!empty($request->idempotency_key)) {
                    $existingRequest = PaymentRequest::where('idempotency_key', $request->idempotency_key)
                        ->where('workspace_id', $invoice->workspace_id)
                        ->first();

                    if ($existingRequest) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment request already processed (idempotency)',
                            'data' => [
                                'id' => $existingRequest->id,
                                'status' => $existingRequest->status,
                            ]
                        ]);
                    }
                }

                // LOCK ORDER: 2. PurchaseOrder (if exists)
                $po = null;
                if ($invoice->po_id) {
                    $po = PurchaseOrder::where('id', $invoice->po_id)
                        ->lockForUpdate()
                        ->first();
                }

                // LOCK ORDER: 3. AdvanceUtilizations (if exists)
                if ($invoice->po_id) {
                    $this->lockAdvanceUtilizationsForInvoice($invoice->po_id);
                }

                // CRITICAL: Validate financial period not closed (only if feature flag enabled)
                if (config('finance.financial_period_locking_enabled', false)) {
                    $periodService = new \App\Services\FinancialPeriodService();
                    $periodService->validatePeriodNotClosed(
                        \Carbon\Carbon::parse($invoice->invoice_date),
                        $invoice->workspace_id,
                        $invoice->site_id
                    );
                }

                if ($invoice->isPaid()) {
                    throw new \Exception('Invoice is already fully paid.');
                }

                // Calculate max allowed - using SAME logic as createModal to ensure consistency
                $maxAllowed = $this->calculateMaxAllowedForPR($invoice);
                
                if ($maxAllowed <= 0) {
                    throw new \Exception('No remaining balance available for payment request.');
                }

                if ($invoice->hasPendingPaymentRequest()) {
                    throw new \Exception('A pending payment request already exists for this invoice.');
                }
                
                if ($request->requested_amount > $maxAllowed) {
                    throw new \Exception('Requested amount cannot exceed remaining invoice amount. Maximum allowed: ₹' . number_format($maxAllowed, 2));
                }

                // Allocate advance BEFORE capturing snapshots so snapshots include allocated amount
                if ($po && !$this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id)) {
                    if (config('finance.po_locked_advance_enabled', false)) {
                        // Use the full service with feature flag enabled
                        $this->advanceAllocationService->allocateToInvoice($invoice->id);
                    } else {
                        // Feature flag disabled: Perform direct allocation without feature flag check
                        $this->allocateAdvanceWithoutFeatureFlag($invoice->id);
                    }
                }

                // Capture ALL financial snapshots at creation time (AFTER potential allocation)
                $netPayableSnapshot = $invoice->getNetPayableAmount();
                $advanceUsedSnapshot = $invoice->getAdvanceUtilizedForInvoice();
                $paidAmountSnapshot = $invoice->getActualPaidAmount();
                $activeRequestsSnapshot = $invoice->getActivePaymentRequestsSum();

                $paymentRequest = PaymentRequest::create([
                    'purchase_invoice_id' => $request->purchase_invoice_id,
                    'requested_amount' => $request->requested_amount,
                    'payment_date' => $request->payment_date,
                    'status' => PaymentRequest::STATUS_PENDING,
                    'remarks' => $request->remarks,
                    'requested_by' => Auth::id(),
                    'net_payable_snapshot' => $netPayableSnapshot,
                    'advance_used_snapshot' => $advanceUsedSnapshot,
                    'paid_amount_snapshot' => $paidAmountSnapshot,
                    'active_requests_snapshot' => $activeRequestsSnapshot,
                    'idempotency_key' => $request->idempotency_key,
                    'workspace_id' => $invoice->workspace_id,
                    'transaction_flow_id' => $invoice->transaction_flow_id,
                    'type' => PaymentRequest::TYPE_INVOICE_PAYMENT,
                ]);

                Log::channel('payment_audit')->info('Payment request created via API', [
                    'payment_request_id' => $paymentRequest->id,
                    'purchase_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'po_id' => $invoice->po_id,
                    'supplier_id' => $invoice->supplier_id,
                    'site_id' => $invoice->site_id,
                    'requested_by' => Auth::id(),
                    'requested_amount' => $request->requested_amount,
                    'payment_date' => $request->payment_date,
                    'snapshots' => [
                        'net_payable' => $netPayableSnapshot,
                        'advance_used' => $advanceUsedSnapshot,
                        'paid_amount' => $paidAmountSnapshot,
                        'active_requests' => $activeRequestsSnapshot,
                    ],
                    'max_allowed' => $maxAllowed,
                    'advance_allocated' => $po && !$this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id),
                    'created_at' => $paymentRequest->created_at,
                    'source' => 'mobile_api',
                ]);

                $projectId = $invoice->site_id;
                $invoiceNumber = $invoice->invoice_number;
                $requestedBy = Auth::user()->name;

                try {
                    $this->notificationService->createPaymentRequestNotification(
                        $invoice->po_id ?? 0,
                        $projectId,
                        $invoiceNumber,
                        $requestedBy
                    );
                } catch (\Exception $e) {
                    // Notification failure should not fail the payment request creation
                    Log::warning('Payment request notification failed', [
                        'payment_request_id' => $paymentRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment request created successfully.',
                    'data' => [
                        'id' => $paymentRequest->id,
                        'status' => $paymentRequest->status,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Payment request creation error via API', [
                'invoice_id' => $invoiceId,
                'requested_amount' => $request->requested_amount,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Lock advance utilizations for invoice (to prevent race conditions)
     */
    protected function lockAdvanceUtilizationsForInvoice(int $poId): void
    {
        $invoiceIds = PurchaseInvoice::where('po_id', $poId)->pluck('id');

        if ($invoiceIds->isNotEmpty()) {
            \App\Models\AdvanceUtilization::whereIn('purchase_invoice_id', $invoiceIds)
                ->lockForUpdate()
                ->get();
        }
    }

    /**
     * Allocate advance to invoice without feature flag check.
     * This is used when PO_LOCKED_ADVANCE_ENABLED is disabled to ensure
     * advance amounts are still deducted from invoices.
     * Handles both new supplier_advances and legacy payments_module advances.
     */
    protected function allocateAdvanceWithoutFeatureFlag(int $invoiceId): void
    {
        $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

        if (empty($invoice->po_id)) {
            return; // Direct GRN - no advance allocation
        }

        // Calculate invoice balance
        $paidAmount = DB::table('payments_module')
            ->where('purchase_invoice_id', $invoice->id)
            ->where('payment_type', '!=', 'advance_against_po')
            ->sum('amount');

        $activeRequests = DB::table('payment_requests')
            ->where('purchase_invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'approved', 'partially_approved'])
            ->sum('requested_amount');

        $invoiceBalance = max(0, $invoice->grand_total - $paidAmount - $activeRequests);

        if ($invoiceBalance <= 0) {
            return;
        }

        $totalAllocated = 0;

        // First, try to allocate from new supplier_advances table
        $advances = DB::table('supplier_advances')
            ->where('po_id', $invoice->po_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->where('workspace_id', $invoice->workspace_id)
            ->where('site_id', $invoice->site_id)
            ->where('status', 'paid')
            ->where('locked_to_po', true)
            ->whereRaw('(amount - utilized_amount) > 0')
            ->lockForUpdate()
            ->orderBy('advance_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($advances as $advance) {
            if ($invoiceBalance <= 0) {
                break;
            }

            $availableBalance = $advance->amount - $advance->utilized_amount;

            if ($availableBalance <= 0) {
                continue;
            }

            $toAllocate = min($availableBalance, $invoiceBalance);

            // Create utilization record
            \App\Models\AdvanceUtilization::create([
                'supplier_advance_id' => $advance->id,
                'purchase_invoice_id' => $invoice->id,
                'utilized_amount' => $toAllocate,
                'status' => 'applied',
                'applied_at' => now(),
                'transaction_flow_id' => $invoice->transaction_flow_id,
            ]);

            $totalAllocated += $toAllocate;
            $invoiceBalance -= $toAllocate;
        }

        // Update advance utilized_amount using SUM (idempotent)
        foreach ($advances as $advance) {
            $totalApplied = \App\Models\AdvanceUtilization::where('supplier_advance_id', $advance->id)
                ->where('status', 'applied')
                ->sum('utilized_amount');

            DB::table('supplier_advances')
                ->where('id', $advance->id)
                ->update(['utilized_amount' => $totalApplied]);
        }

        // Second, if still need more, check legacy payments_module for advance_against_po
        if ($invoiceBalance > 0) {
            $legacyAdvances = DB::table('payments_module')
                ->where('purchase_order_id', $invoice->po_id)
                ->where('payment_type', 'advance_against_po')
                ->where('status', 'completed')
                ->whereRaw('amount > COALESCE((SELECT SUM(utilized_amount) FROM advance_utilizations WHERE payments_module_id = payments_module.id), 0)')
                ->lockForUpdate()
                ->orderBy('payment_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($legacyAdvances as $legacyAdvance) {
                if ($invoiceBalance <= 0) {
                    break;
                }

                // Calculate already utilized amount from advance_utilizations
                $alreadyUtilized = DB::table('advance_utilizations')
                    ->where('payments_module_id', $legacyAdvance->id)
                    ->where('status', 'applied')
                    ->sum('utilized_amount');

                $availableBalance = $legacyAdvance->amount - $alreadyUtilized;

                if ($availableBalance <= 0) {
                    continue;
                }

                $toAllocate = min($availableBalance, $invoiceBalance);

                // Create utilization record linked to payments_module
                \App\Models\AdvanceUtilization::create([
                    'payments_module_id' => $legacyAdvance->id,
                    'purchase_invoice_id' => $invoice->id,
                    'utilized_amount' => $toAllocate,
                    'status' => 'applied',
                    'applied_at' => now(),
                    'transaction_flow_id' => $invoice->transaction_flow_id,
                ]);
            }
        }

        Log::channel('payment_audit')->info('Advance allocated without feature flag via API', [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice->invoice_number,
            'allocated_amount' => $totalAllocated,
            'po_id' => $invoice->po_id,
            'source' => 'mobile_api',
        ]);
    }

    /**
     * Calculate max allowed amount for payment request - same logic as createModal
     * This ensures frontend and backend validation are consistent
     */
    protected function calculateMaxAllowedForPR(PurchaseInvoice $invoice): float
    {
        // Get current advance used from DB (applied status only)
        $advanceUsed = $invoice->getAdvanceUtilizedForInvoice();
        $paidAmount = $invoice->getActualPaidAmount();
        $activeRequestsSum = $invoice->getActivePaymentRequestsSum();
        $grandTotal = (float) $invoice->grand_total;

        // If no advance allocated yet but PO has available advance, calculate allocation
        if ($invoice->po_id && $advanceUsed == 0) {
            // Use new supplier_advances system
            $poAdvanceRemaining = DB::table('supplier_advances')
                ->where('po_id', $invoice->po_id)
                ->where('status', 'paid')
                ->where('locked_to_po', true)
                ->selectRaw('COALESCE(SUM(amount - utilized_amount), 0) as remaining')
                ->value('remaining');

            // Also include legacy payments_module advances (advance_against_po)
            $legacyAdvanceRemaining = DB::table('payments_module as pm')
                ->where('pm.purchase_order_id', $invoice->po_id)
                ->where('pm.payment_type', 'advance_against_po')
                ->where('pm.status', 'completed')
                ->selectRaw('COALESCE(SUM(pm.amount - COALESCE((SELECT SUM(au.utilized_amount) FROM advance_utilizations au WHERE au.payments_module_id = pm.id AND au.status = "applied"), 0)), 0) as remaining')
                ->value('remaining');

            $totalAdvanceRemaining = ($poAdvanceRemaining ?? 0) + ($legacyAdvanceRemaining ?? 0);

            if ($totalAdvanceRemaining > 0) {
                // Calculate what would be allocated (dry-run)
                $invoiceBalance = max(0, $grandTotal - $paidAmount - $advanceUsed - $activeRequestsSum);
                $potentialAllocation = min($totalAdvanceRemaining, $invoiceBalance);
                $advanceUsed = $potentialAllocation;
            }
        }

        return max(0, $grandTotal - $paidAmount - $advanceUsed - $activeRequestsSum);
    }

    /**
     * Get PO Advance Request Data (Prefill)
     * 
     * Returns PO details with calculated amounts for PO advance request creation.
     * This replicates the logic from PurchaseOrderController@advanceRequestModal.
     * 
     * @urlParam po_id integer required The ID of the purchase order
     * @queryParam workspace_id integer optional Filter by workspace ID
     * @queryParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "PO advance request data retrieved successfully",
     *   "data": {
     *     "po": {...},
     *     "supplier": {...},
     *     "site": {...},
     *     "grand_total": 100000.00,
     *     "existing_advances": 20000.00,
     *     "pending_advances": 10000.00,
     *     "available_balance": 70000.00,
     *     "payment_terms_conditions": "Net 30 days"
     *   }
     * }
     */
    public function getPoAdvanceRequestData(Request $request, $poId)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            $modalData = $this->poAdvanceService->getModalData($poId);

            // Validate workspace if provided
            if ($workspaceId && $modalData['po']->workspace_id != $workspaceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'PO does not belong to the specified workspace.'
                ], 403);
            }

            // Validate site if provided
            if ($siteId && $modalData['po']->site_id != $siteId) {
                return response()->json([
                    'success' => false,
                    'message' => 'PO does not belong to the specified site.'
                ], 403);
            }

            // Transform PO and related objects to arrays for JSON response
            $data = [
                'po' => [
                    'id' => $modalData['po']->id,
                    'po_number' => $modalData['po']->po_number,
                    'po_date' => $modalData['po']->po_date,
                    'grand_total' => (float) $modalData['po']->grand_total,
                    'status' => $modalData['po']->status,
                ],
                'supplier' => [
                    'id' => $modalData['supplier']?->id,
                    'name' => $modalData['supplier']?->name ?? '',
                ],
                'site' => [
                    'id' => $modalData['site']?->id,
                    'name' => $modalData['site']?->name ?? '',
                ],
                'grand_total' => (float) $modalData['grand_total'],
                'existing_advances' => (float) $modalData['existing_advances'],
                'pending_advances' => (float) $modalData['pending_advances'],
                'available_balance' => (float) $modalData['available_balance'],
                'payment_terms_conditions' => $modalData['payment_terms_conditions'] ?? '',
            ];

            return response()->json([
                'success' => true,
                'message' => 'PO advance request data retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('PO advance request data retrieval error', [
                'po_id' => $poId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving PO advance request data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create PO Advance Request
     * 
     * Creates a new PO advance request.
     * This replicates the exact business logic from PurchaseOrderController@storeAdvanceRequest.
     * 
     * @bodyParam po_id integer required The ID of the purchase order
     * @bodyParam percentage integer required The percentage of PO total (1-100)
     * @bodyParam advance_amount numeric required The advance amount
     * @bodyParam payment_date date required The payment date
     * @bodyParam notes string optional Notes for the advance request (max: 1000 characters)
     * @bodyParam workspace_id integer optional Filter by workspace ID
     * @bodyParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Advance request created successfully",
     *   "data": {
     *     "id": 789,
     *     "status": "pending"
     *   }
     * }
     */
    public function storePoAdvanceRequest(Request $request, $poId)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'percentage' => 'required|integer|min:1|max:100',
            'advance_amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'workspace_id' => 'nullable|integer',
            'site_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $percentage = $request->percentage;
        $advanceAmount = $request->advance_amount;
        $paymentDate = $request->payment_date;
        $notes = $request->notes;
        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            $po = PurchaseOrder::findOrFail($poId);

            // Validate workspace if provided
            if ($workspaceId && $po->workspace_id != $workspaceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'PO does not belong to the specified workspace.'
                ], 403);
            }

            // Validate site if provided
            if ($siteId && $po->site_id != $siteId) {
                return response()->json([
                    'success' => false,
                    'message' => 'PO does not belong to the specified site.'
                ], 403);
            }

            // Server-side validation: Check if PO payment is completed
            if ($po->isPaymentCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already completed for this PO. Cannot request advance.'
                ], 422);
            }

            // Server-side validation: Check if active advance request already exists
            // Allow new request if previous one was rejected
            if ($po->hasActiveAdvanceRequest()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active advance request already exists for this PO. Only one active advance request allowed per PO.'
                ], 422);
            }

            // Validate business rules
            $validationErrors = $this->poAdvanceService->validateAdvanceRequest($po, $percentage, $advanceAmount);

            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $validationErrors),
                ], 422);
            }

            // Check pending requests
            $pendingErrors = $this->poAdvanceService->checkPendingRequests($poId, $advanceAmount);

            if (!empty($pendingErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $pendingErrors),
                ], 422);
            }

            // Create advance request
            $paymentRequest = $this->poAdvanceService->createAdvanceRequest(
                $poId,
                $percentage,
                $advanceAmount,
                $notes,
                $paymentDate,
                Auth::id()
            );

            Log::channel('payment_audit')->info('PO advance request created via API', [
                'payment_request_id' => $paymentRequest->id,
                'po_id' => $poId,
                'po_number' => $po->po_number,
                'percentage' => $percentage,
                'advance_amount' => $advanceAmount,
                'requested_by' => Auth::id(),
                'source' => 'mobile_api',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Advance request created successfully',
                'data' => [
                    'id' => $paymentRequest->id,
                    'status' => $paymentRequest->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PO advance request creation error via API', [
                'po_id' => $poId,
                'advance_amount' => $advanceAmount,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create advance request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @group Payment Request Management
     *
     * Payment Request listing, details, approval, and payment endpoints.
     * These provide mobile access to the complete Payment Request workflow.
     */

    /**
     * List Payment Requests
     * 
     * Returns paginated list of payment requests with filters.
     * This replicates the logic from PaymentRequestDataTable.
     * 
     * @queryParam page integer Page number (default: 1)
     * @queryParam per_page integer Items per page (default: 10)
     * @queryParam status enum Filter by status (pending, approved, partially_approved, rejected, paid, partially_paid)
     * @queryParam supplier_id integer Filter by supplier ID
     * @queryParam start_date date Filter by creation date (from)
     * @queryParam end_date date Filter by creation date (to)
     * @queryParam workspace_id integer Filter by workspace ID
     * @queryParam site_id integer Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment requests retrieved successfully",
     *   "data": {
     *     "current_page": 1,
     *     "data": [...],
     *     "total": 100,
     *     "per_page": 10,
     *     "last_page": 10
     *   }
     * }
     */
    public function list(Request $request)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        try {
            $status = $request->input('status');
            $supplierId = $request->input('supplier_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $type = $request->input('type');
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');
            $search = $request->input('search');

            // Build base query with conditional eager loading
            $query = PaymentRequest::with(['requestedBy', 'approvedBy', 'payments'])
                ->select('payment_requests.*');

            // Add workspace filter (only if workspace is set)
            $query->when($workspaceId, function ($q) use ($workspaceId) {
                $q->where(function ($q) {
                    // Include payment requests with invoice (check workspace via invoice)
                    $q->whereHas('invoice', function ($q) use ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    })
                    // OR include PO advance requests (check PO's workspace_id)
                    ->orWhere(function ($q) use ($workspaceId) {
                        $q->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                          ->whereHas('po', function ($q) use ($workspaceId) {
                              $q->where('workspace_id', $workspaceId);
                          });
                    });
                });
            });

            // Apply explicit site_id filter (overrides implicit project filter if provided)
            if ($siteId) {
                // CRITICAL: Verify user has access to this site_id to prevent data leakage
                $userSites = Auth::user()->projects()->pluck('id')->toArray();
                if (!in_array($siteId, $userSites) && !Auth::user()->hasRole('admin')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this project.'
                    ], 403);
                }

                $query->where(function ($q) use ($siteId) {
                    // Filter by site for invoice-based requests
                    $q->whereHas('invoice', fn($q) => $q->where('site_id', $siteId))
                      // OR include PO advance requests (check PO's site_id)
                      ->orWhere(function ($q) use ($siteId) {
                          $q->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                            ->whereHas('po', fn($q) => $q->where('site_id', $siteId));
                      });
                });
            } else {
                // Fall back to implicit project filter if no explicit site_id
                $query->when($siteId, function ($q) use ($siteId) {
                    $q->where(function ($q) {
                        // Filter by project for invoice-based requests
                        $q->whereHas('invoice', fn($q) => $q->where('site_id', $siteId))
                          // OR include PO advance requests (check PO's site_id)
                          ->orWhere(function ($q) {
                              $q->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                                ->whereHas('po', fn($q) => $q->where('site_id', $siteId));
                          });
                    });
                });
            }

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($supplierId) {
                $query->where(function ($q) use ($supplierId) {
                    // Filter by supplier for invoice-based requests
                    $q->whereHas('invoice', fn($q) => $q->where('supplier_id', $supplierId))
                      // OR include PO advance requests (check PO's supplier)
                      ->orWhere(function ($q) use ($supplierId) {
                          $q->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                            ->whereHas('po', fn($q) => $q->where('supplier_id', $supplierId));
                      });
                });
            }

            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Search by invoice_number, po_number, or request id
            // Optimized: exact match for id, prefix search for invoice/po numbers
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    // Exact match for numeric IDs (faster)
                    if (is_numeric($search)) {
                        $q->where('payment_requests.id', $search);
                    }
                    // Prefix search for invoice/po numbers (better performance than LIKE '%...%')
                    $q->orWhereHas('invoice', fn($q) => $q->where('invoice_number', 'like', "{$search}%"))
                      ->orWhereHas('po', fn($q) => $q->where('po_number', 'like', "{$search}%"));
                });
            }

            // Order by latest first
            $query->orderBy('created_at', 'desc');

            // Get all results without pagination
            $paymentRequests = $query->get();

            // Load related data for the results (avoid eager loading on deleted records)
            $paymentRequests->load(['requestedBy', 'approvedBy', 'payments']);

            // Transform response with computed fields
            $transformedData = $paymentRequests->map(function ($pr) {
                try {
                    $totalPaid = $pr->payments()->sum('amount');
                    $approvedAmount = $pr->approved_amount ?? $pr->requested_amount;
                    $remainingAmount = max(0, $approvedAmount - $totalPaid);

                    // Safely load invoice and PO data (handle missing records)
                    $invoice = null;
                    $po = null;
                    $supplierName = null;

                    if ($pr->isInvoicePayment() && $pr->purchase_invoice_id) {
                        $invoice = $pr->invoice;
                        if ($invoice) {
                            $supplierName = $invoice->supplier?->name;
                        }
                    } elseif ($pr->isPoAdvance() && $pr->po_id) {
                        $po = $pr->po;
                        if ($po) {
                            $supplierName = $po->supplier?->name;
                        }
                    }

                    return [
                        'id' => $pr->id,
                        'type' => $pr->type,
                        'status' => $pr->status,
                        'requested_amount' => (float) $pr->requested_amount,
                        'approved_amount' => $pr->approved_amount ? (float) $pr->approved_amount : null,
                        'payment_date' => $pr->payment_date ? $pr->payment_date->format('Y-m-d') : null,
                        'remarks' => $pr->remarks,
                        'requested_by' => $pr->requestedBy ? [
                            'id' => $pr->requestedBy->id,
                            'name' => $pr->requestedBy->name,
                        ] : null,
                        'approved_by' => $pr->approvedBy ? [
                            'id' => $pr->approvedBy->id,
                            'name' => $pr->approvedBy->name,
                        ] : null,
                        'approved_at' => $pr->approved_at ? $pr->approved_at->toIso8601String() : null,
                        'rejection_reason' => $pr->rejection_reason,
                        'created_at' => $pr->created_at ? $pr->created_at->toIso8601String() : null,
                        'invoice_number' => $invoice?->invoice_number,
                        'po_number' => $po?->po_number,
                        'supplier_name' => $supplierName,
                        'invoice_date' => $invoice?->invoice_date ?? $po?->po_date,
                        'total_paid' => (float) $totalPaid,
                        'remaining_amount' => (float) $remainingAmount,
                        'has_missing_invoice' => $pr->isInvoicePayment() && !$invoice,
                        'has_missing_po' => $pr->isPoAdvance() && !$po,
                    ];
                } catch (\Exception $e) {
                    Log::error('Error transforming payment request', [
                        'payment_request_id' => $pr->id,
                        'error' => $e->getMessage()
                    ]);
                    // Return minimal data if transformation fails
                    return [
                        'id' => $pr->id,
                        'type' => $pr->type,
                        'status' => $pr->status,
                        'requested_amount' => (float) $pr->requested_amount,
                        'error' => 'Data incomplete',
                    ];
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment requests retrieved successfully',
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Payment request list error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Payment Request Details
     * 
     * Returns full payment request details including invoice/PO data, snapshots, and payment history.
     * 
     * @urlParam id integer required The ID of the payment request
     * @queryParam workspace_id integer optional Filter by workspace ID
     * @queryParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment request details retrieved successfully",
     *   "data": {...}
     * }
     */
    public function show(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            $paymentRequest = PaymentRequest::with([
                'invoice.supplier',
                'invoice.site',
                'invoice.purchaseOrder',
                'invoice.creator',
                'po.supplier',
                'po.site',
                'po.creator',
                'requestedBy',
                'approvedBy',
                'payments'
            ])->findOrFail($id);

            // Validate workspace if provided
            if ($workspaceId) {
                if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                    if ($paymentRequest->invoice->workspace_id != $workspaceId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified workspace.'
                        ], 403);
                    }
                } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                    if ($paymentRequest->po->workspace_id != $workspaceId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified workspace.'
                        ], 403);
                    }
                }
            }

            // Validate site if provided
            if ($siteId) {
                if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                    if ($paymentRequest->invoice->site_id != $siteId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified site.'
                        ], 403);
                    }
                } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                    if ($paymentRequest->po->site_id != $siteId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified site.'
                        ], 403);
                    }
                }
            }

            $totalPaid = $paymentRequest->payments()->sum('amount');
            $approvedAmount = $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;
            $remainingAmount = max(0, $approvedAmount - $totalPaid);

            // Calculate can_perform flags for mobile UI
            $canApprove = $paymentRequest->isPending() && !$paymentRequest->hasPayment();
            $canReject = $paymentRequest->isPending() && !$paymentRequest->hasPayment();
            
            // CRITICAL: can_pay edge case checks
            // 1. Status must be in payable states
            $payableStatuses = [
                PaymentRequest::STATUS_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_APPROVED,
                PaymentRequest::STATUS_PARTIALLY_PAID,
            ];
            $isPayableStatus = in_array($paymentRequest->status, $payableStatuses);
            
            // 2. Must have approved_amount (or fallback to requested_amount)
            $approvedAmountExists = !empty($paymentRequest->approved_amount) || !empty($paymentRequest->requested_amount);
            
            // 3. Must not be fully paid (remaining > 0)
            $hasRemainingBalance = $remainingAmount > 0;
            
            // 4. Must not be rejected or paid
            $notRejectedOrPaid = !in_array($paymentRequest->status, [
                PaymentRequest::STATUS_REJECTED,
                PaymentRequest::STATUS_PAID,
            ]);
            
            $canPay = $isPayableStatus && $approvedAmountExists && $hasRemainingBalance && $notRejectedOrPaid;

            $data = [
                'id' => $paymentRequest->id,
                'type' => $paymentRequest->type,
                'status' => $paymentRequest->status,
                'requested_amount' => (float) $paymentRequest->requested_amount,
                'approved_amount' => $paymentRequest->approved_amount ? (float) $paymentRequest->approved_amount : null,
                'payment_date' => $paymentRequest->payment_date ? $paymentRequest->payment_date->format('Y-m-d') : null,
                'remarks' => $paymentRequest->remarks,
                'requested_by' => $paymentRequest->requestedBy ? [
                    'id' => $paymentRequest->requestedBy->id,
                    'name' => $paymentRequest->requestedBy->name,
                ] : null,
                'approved_by' => $paymentRequest->approvedBy ? [
                    'id' => $paymentRequest->approvedBy->id,
                    'name' => $paymentRequest->approvedBy->name,
                ] : null,
                'approved_at' => $paymentRequest->approved_at ? $paymentRequest->approved_at->toIso8601String() : null,
                'paid_at' => $paymentRequest->paid_at ? $paymentRequest->paid_at->toIso8601String() : null,
                'rejection_reason' => $paymentRequest->rejection_reason,
                'created_at' => $paymentRequest->created_at->toIso8601String(),
                'updated_at' => $paymentRequest->updated_at->toIso8601String(),
                'snapshots' => [
                    'net_payable' => $paymentRequest->net_payable_snapshot ? (float) $paymentRequest->net_payable_snapshot : null,
                    'advance_used' => $paymentRequest->advance_used_snapshot ? (float) $paymentRequest->advance_used_snapshot : null,
                    'paid_amount' => $paymentRequest->paid_amount_snapshot ? (float) $paymentRequest->paid_amount_snapshot : null,
                    'active_requests' => $paymentRequest->active_requests_snapshot ? (float) $paymentRequest->active_requests_snapshot : null,
                ],
                'total_paid' => (float) $totalPaid,
                'remaining_amount' => (float) $remainingAmount,
                'can_approve' => $canApprove,
                'can_reject' => $canReject,
                'can_pay' => $canPay,
            ];

            // Add invoice data if invoice payment request
            if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                $invoice = $paymentRequest->invoice;
                $data['invoice'] = [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'grand_total' => (float) $invoice->grand_total,
                    'paid_amount' => (float) $invoice->getActualPaidAmount(),
                    'advance_utilized' => (float) $invoice->getAdvanceUtilizedForInvoice(),
                    'remaining_balance' => (float) $invoice->getRemainingBalance(),
                    'supplier' => [
                        'id' => $invoice->supplier?->id,
                        'name' => $invoice->supplier?->name ?? '',
                    ],
                    'site' => [
                        'id' => $invoice->site?->id,
                        'name' => $invoice->site?->name ?? '',
                    ],
                ];
            }

            // Add PO data if PO advance request
            if ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                $po = $paymentRequest->po;
                $data['po'] = [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date,
                    'grand_total' => (float) $po->grand_total,
                    'total_paid' => (float) $po->total_paid,
                    'remaining_balance' => (float) ($po->grand_total - $po->total_paid),
                    'supplier' => [
                        'id' => $po->supplier?->id,
                        'name' => $po->supplier?->name ?? '',
                    ],
                    'site' => [
                        'id' => $po->site?->id,
                        'name' => $po->site?->name ?? '',
                    ],
                ];
            }

            // Add payment history (sorted by payment_date DESC)
            $data['payments'] = collect($paymentRequest->payments)
                ->sortByDesc('payment_date')
                ->values()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'amount' => (float) $payment->amount,
                        'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
                        'payment_type' => $payment->payment_type,
                        'mode' => $payment->mode,
                        'reference_number' => $payment->reference_number,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at ? $payment->created_at->toIso8601String() : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Payment request details retrieved successfully',
                'data' => $data
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Payment request details error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment request details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve / Partial Approve / Reject Payment Request
     * 
     * Processes approval actions on payment requests.
     * This replicates the logic from PaymentRequestController@approveSingle.
     * 
     * @urlParam id integer required The ID of the payment request
     * @bodyParam action string required Action: approve, partial, or reject
     * @bodyParam approved_amount numeric required for approve/partial The amount to approve
     * @bodyParam rejection_reason string required for reject Reason for rejection (max 500 chars)
     * @bodyParam workspace_id integer optional Filter by workspace ID
     * @bodyParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment request approved successfully",
     *   "data": {
     *     "id": 789,
     *     "status": "approved",
     *     "approved_amount": 50000.00
     *   }
     * }
     */
    public function approve(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,partial,reject',
            'approved_amount' => 'required_if:action,approve,partial|numeric|min:0.01',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
            'workspace_id' => 'nullable|integer',
            'site_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            return DB::transaction(function () use ($request, $id, $workspaceId, $siteId) {
                $paymentRequest = PaymentRequest::with(['invoice', 'po'])->findOrFail($id);

                // Validate workspace if provided
                if ($workspaceId) {
                    if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                        if ($paymentRequest->invoice->workspace_id != $workspaceId) {
                            throw new \Exception('Payment request does not belong to the specified workspace.');
                        }
                    } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                        if ($paymentRequest->po->workspace_id != $workspaceId) {
                            throw new \Exception('Payment request does not belong to the specified workspace.');
                        }
                    }
                }

                // Validate site if provided
                if ($siteId) {
                    if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                        if ($paymentRequest->invoice->site_id != $siteId) {
                            throw new \Exception('Payment request does not belong to the specified site.');
                        }
                    } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                        if ($paymentRequest->po->site_id != $siteId) {
                            throw new \Exception('Payment request does not belong to the specified site.');
                        }
                    }
                }

                // CRITICAL: Fail-fast check - prevent duplicate approval attempts from UI retries
                if ($paymentRequest->status !== PaymentRequest::STATUS_PENDING) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This payment request has already been processed.'
                    ], 409);
                }

                // Validation: Only pending requests can be approved
                if (!$paymentRequest->isPending()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This payment request is not pending approval.'
                    ], 422);
                }

                // Validation: Cannot approve if payment already created
                if ($paymentRequest->hasPayment()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A payment has already been created for this request.'
                    ], 409);
                }

                $action = $request->action;

                // Additional validation for approve/partial actions
                if (in_array($action, ['approve', 'partial'])) {
                    $approvedAmount = (float) $request->approved_amount;

                    // Ensure approved_amount > 0
                    if ($approvedAmount <= 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approved amount must be greater than 0.'
                        ], 400);
                    }

                    // For partial approval, ensure approved_amount < requested_amount (strictly)
                    if ($action === 'partial' && $approvedAmount >= $paymentRequest->requested_amount) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Partial approval amount must be less than requested amount. Use "approve" action for full approval.'
                        ], 400);
                    }

                    // Ensure approved_amount <= requested_amount
                    if ($approvedAmount > $paymentRequest->requested_amount) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approved amount cannot exceed requested amount.'
                        ], 400);
                    }
                }

                // Route to appropriate handler based on type
                if ($paymentRequest->isPoAdvance()) {
                    return $this->approvePoAdvance($paymentRequest, $request);
                }

                return $this->approveInvoicePayment($paymentRequest, $request);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Payment request approval error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve PO Advance Request
     */
    private function approvePoAdvance(PaymentRequest $paymentRequest, Request $request)
    {
        $action = $request->action;

        // Lock PO
        $po = PurchaseOrder::where('id', $paymentRequest->po_id)->lockForUpdate()->first();
        if (!$po) {
            throw new \Exception('Purchase Order not found for this advance request.');
        }

        // Lock payment request
        $paymentRequest = PaymentRequest::where('id', $paymentRequest->id)->lockForUpdate()->first();

        // Max allowed is always the requested_amount
        $maxAllowedApproval = $paymentRequest->requested_amount;

        // PO-specific snapshots
        $netPayableSnapshot = $po->grand_total;
        $advanceUsedSnapshot = \App\Models\SupplierAdvance::where('po_id', $po->id)->sum('amount') ?? 0;
        $paidAmountSnapshot = $po->total_paid;
        $activeRequestsSnapshot = PaymentRequest::where('po_id', $po->id)
            ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
            ->where('status', 'pending')
            ->sum('requested_amount');

        if ($action === 'reject') {
            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_REJECTED,
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            Log::channel('payment_audit')->info('PO advance request rejected via API', [
                'payment_request_id' => $paymentRequest->id,
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'requested_amount' => $paymentRequest->requested_amount,
                'rejected_by' => Auth::id(),
                'rejection_reason' => $request->rejection_reason,
                'action' => 'reject',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_REJECTED,
                'source' => 'mobile_api',
            ]);
        } elseif ($action === 'partial') {
            $approvedAmount = min($request->approved_amount, $paymentRequest->requested_amount);

            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_PARTIALLY_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'net_payable_snapshot' => $netPayableSnapshot,
                'advance_used_snapshot' => $advanceUsedSnapshot,
                'paid_amount_snapshot' => $paidAmountSnapshot,
                'active_requests_snapshot' => $activeRequestsSnapshot,
            ]);

            Log::channel('payment_audit')->info('PO advance request partially approved via API', [
                'payment_request_id' => $paymentRequest->id,
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'requested_amount' => $paymentRequest->requested_amount,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'action' => 'partial_approve',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_PARTIALLY_APPROVED,
                'source' => 'mobile_api',
            ]);
        } else {
            // Approve
            $approvedAmount = $paymentRequest->requested_amount;

            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'net_payable_snapshot' => $netPayableSnapshot,
                'advance_used_snapshot' => $advanceUsedSnapshot,
                'paid_amount_snapshot' => $paidAmountSnapshot,
                'active_requests_snapshot' => $activeRequestsSnapshot,
            ]);

            // Create supplier advance ledger entry
            app(\App\Services\SupplierAdvanceService::class)->createFromPaymentRequest($paymentRequest);

            Log::channel('payment_audit')->info('PO advance request approved via API', [
                'payment_request_id' => $paymentRequest->id,
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'requested_amount' => $paymentRequest->requested_amount,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'action' => 'approve',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_APPROVED,
                'source' => 'mobile_api',
            ]);
        }

        // Notification
        $this->notificationService->createPaymentApprovalNotification(
            $paymentRequest,
            $po->site_id,
            $paymentRequest->fresh()->status,
            $paymentRequest->rejection_reason,
            Auth::user()->name
        );

        return response()->json([
            'success' => true,
            'message' => 'Advance request ' . ($action === 'reject' ? 'rejected' : 'approved') . ' successfully.',
            'data' => [
                'id' => $paymentRequest->id,
                'status' => $paymentRequest->fresh()->status,
                'approved_amount' => $paymentRequest->fresh()->approved_amount,
            ]
        ]);
    }

    /**
     * Approve Invoice Payment Request
     */
    private function approveInvoicePayment(PaymentRequest $paymentRequest, Request $request)
    {
        $action = $request->action;

        // Lock invoice
        $invoice = PurchaseInvoice::where('id', $paymentRequest->invoice->id)
            ->lockForUpdate()
            ->first();

        // Lock PO if exists
        $po = null;
        if ($invoice->po_id) {
            $po = PurchaseOrder::where('id', $invoice->po_id)
                ->lockForUpdate()
                ->first();
        }

        // Lock payment request
        $paymentRequest = PaymentRequest::where('id', $paymentRequest->id)
            ->lockForUpdate()
            ->first();

        // Lock advance utilizations if PO exists
        if ($invoice->po_id) {
            $this->lockAdvanceUtilizationsForInvoice($invoice->po_id);
        }

        // Auto-allocate advance if not already allocated
        $advanceAlreadyAllocated = $this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id);

        if ($po && !$advanceAlreadyAllocated) {
            $this->advanceAllocationService->allocateToInvoice($invoice->id);
            $invoice = $invoice->fresh();
        }

        // Max allowed is always the requested_amount
        $maxAllowedApproval = $paymentRequest->requested_amount;

        if (in_array($action, ['approve', 'partial'])) {
            $approvedAmount = $action === 'approve'
                ? $paymentRequest->requested_amount
                : min($request->approved_amount, $paymentRequest->requested_amount, $maxAllowedApproval);

            if ($approvedAmount > $maxAllowedApproval) {
                throw new \Exception('Payment amount exceeds remaining invoice amount. Maximum allowed: ₹' . number_format($maxAllowedApproval, 2));
            }
        }

        // Capture financial snapshots at approval time
        $netPayableSnapshot = $invoice->getNetPayableWithoutRequests();
        $advanceUsedSnapshot = $invoice->getAdvanceUtilizedForInvoice();
        $paidAmountSnapshot = $invoice->getActualPaidAmount();
        $activeRequestsSnapshot = $invoice->getActivePaymentRequestsSum();

        if ($action === 'reject') {
            $this->advanceAllocationService->releaseReservation($paymentRequest->id);

            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_REJECTED,
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            Log::channel('payment_audit')->info('Payment request rejected via API', [
                'payment_request_id' => $paymentRequest->id,
                'purchase_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'po_id' => $invoice->po_id,
                'requested_amount' => $paymentRequest->requested_amount,
                'rejected_by' => Auth::id(),
                'rejection_reason' => $request->rejection_reason,
                'action' => 'reject',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_REJECTED,
                'source' => 'mobile_api',
            ]);
        } elseif ($action === 'partial') {
            $approvedAmount = min($request->approved_amount, $paymentRequest->requested_amount);

            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_PARTIALLY_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'net_payable_snapshot' => $netPayableSnapshot,
                'advance_used_snapshot' => $advanceUsedSnapshot,
                'paid_amount_snapshot' => $paidAmountSnapshot,
                'active_requests_snapshot' => $activeRequestsSnapshot,
            ]);

            Log::channel('payment_audit')->info('Payment request partially approved via API', [
                'payment_request_id' => $paymentRequest->id,
                'purchase_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'requested_amount' => $paymentRequest->requested_amount,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'action' => 'partial_approve',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_PARTIALLY_APPROVED,
                'source' => 'mobile_api',
            ]);
        } else {
            $approvedAmount = $paymentRequest->requested_amount;

            $paymentRequest->update([
                'status' => PaymentRequest::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'net_payable_snapshot' => $netPayableSnapshot,
                'advance_used_snapshot' => $advanceUsedSnapshot,
                'paid_amount_snapshot' => $paidAmountSnapshot,
                'active_requests_snapshot' => $activeRequestsSnapshot,
            ]);

            Log::channel('payment_audit')->info('Payment request approved via API', [
                'payment_request_id' => $paymentRequest->id,
                'purchase_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'requested_amount' => $paymentRequest->requested_amount,
                'approved_amount' => $approvedAmount,
                'approved_by' => Auth::id(),
                'action' => 'approve',
                'user_id' => Auth::id(),
                'before_status' => $paymentRequest->getOriginal('status'),
                'after_status' => PaymentRequest::STATUS_APPROVED,
                'source' => 'mobile_api',
            ]);
        }

        $this->notificationService->createPaymentApprovalNotification(
            $paymentRequest,
            $invoice->site_id,
            $paymentRequest->fresh()->status,
            $paymentRequest->rejection_reason,
            Auth::user()->name
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment request ' . ($action === 'reject' ? 'rejected' : 'approved') . ' successfully.',
            'data' => [
                'id' => $paymentRequest->id,
                'status' => $paymentRequest->fresh()->status,
                'approved_amount' => $paymentRequest->fresh()->approved_amount,
            ]
        ]);
    }

    /**
     * Create Payment Against Payment Request
     * 
     * Creates a payment against an approved payment request.
     * This uses PaymentService@createPaymentFromRequest to ensure exact business logic.
     * 
     * @urlParam id integer required The ID of the payment request
     * @bodyParam amount numeric required The payment amount
     * @bodyParam payment_date date required The payment date
     * @bodyParam mode string optional Payment mode (default: bank_transfer)
     * @bodyParam reference_number string optional Reference number
     * @bodyParam notes string optional Payment notes
     * @bodyParam idempotency_key string optional Unique key to prevent duplicate payments
     * @bodyParam workspace_id integer optional Filter by workspace ID
     * @bodyParam site_id integer optional Filter by site ID
     * 
     * @response {
     *   "success": true,
     *   "message": "Payment created successfully",
     *   "data": {
     *     "payment_id": 456,
     *     "payment_number": "PAY-0001",
     *     "amount": 25000.00,
     *     "payment_request": {
     *       "id": 789,
     *       "status": "partially_paid",
     *       "total_paid": 25000.00,
     *       "remaining_amount": 25000.00
     *     }
     *   }
     * }
     */
    public function createPayment(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('manage-payment create')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'mode' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:64',
            'workspace_id' => 'nullable|integer',
            'site_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            $paymentRequest = PaymentRequest::with(['invoice', 'po'])->findOrFail($id);

            // Validate workspace if provided
            if ($workspaceId) {
                if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                    if ($paymentRequest->invoice->workspace_id != $workspaceId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified workspace.'
                        ], 403);
                    }
                } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                    if ($paymentRequest->po->workspace_id != $workspaceId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified workspace.'
                        ], 403);
                    }
                }
            }

            // Validate site if provided
            if ($siteId) {
                if ($paymentRequest->isInvoicePayment() && $paymentRequest->invoice) {
                    if ($paymentRequest->invoice->site_id != $siteId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified site.'
                        ], 403);
                    }
                } elseif ($paymentRequest->isPoAdvance() && $paymentRequest->po) {
                    if ($paymentRequest->po->site_id != $siteId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment request does not belong to the specified site.'
                        ], 403);
                    }
                }
            }

            // CRITICAL: Extra safety checks at API layer (defense-in-depth)
            $paymentAmount = (float) $request->amount;

            if ($paymentAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount must be greater than 0.'
                ], 400);
            }

            // Calculate remaining amount before calling service
            $totalPaid = $paymentRequest->payments()->sum('amount');
            $approvedAmount = (float) ($paymentRequest->approved_amount ?? $paymentRequest->requested_amount);
            $remainingAmount = max(0, $approvedAmount - $totalPaid);

            if ($remainingAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nothing left to pay for this payment request.'
                ], 409);
            }

            if ($paymentAmount > $remainingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance. Maximum: ₹' . number_format($remainingAmount, 2)
                ], 400);
            }

            // Capture before state for audit
            $beforeStatus = $paymentRequest->status;
            $beforePaid = $totalPaid;

            // Use PaymentService to create payment (ensures exact business logic)
            try {
                $payment = $this->paymentService->createPaymentFromRequest(
                    $paymentRequest,
                    $paymentAmount,
                    $request->idempotency_key
                );
            } catch (\InvalidArgumentException $e) {
                // Check if this is an idempotency replay (payment already exists)
                if ($request->idempotency_key && str_contains($e->getMessage(), 'idempotent')) {
                    // Find the existing payment by idempotency key
                    $existingPayment = \App\Models\PaymentsModule::where('idempotency_key', $request->idempotency_key)
                        ->where('payment_request_id', $paymentRequest->id)
                        ->first();

                    if ($existingPayment) {
                        // Return existing payment with 200 status (not error)
                        $paymentRequest->refresh();
                        $totalPaid = $paymentRequest->payments()->sum('amount');
                        $approvedAmount = $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;
                        $remainingAmount = max(0, $approvedAmount - $totalPaid);

                        Log::channel('payment_audit')->info('Payment idempotency replay - returning existing payment via API', [
                            'payment_request_id' => $paymentRequest->id,
                            'existing_payment_id' => $existingPayment->id,
                            'payment_number' => $existingPayment->payment_number,
                            'idempotency_key' => $request->idempotency_key,
                            'source' => 'mobile_api',
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Payment already processed (idempotent replay)',
                            'data' => [
                                'payment_id' => $existingPayment->id,
                                'payment_number' => $existingPayment->payment_number,
                                'amount' => (float) $existingPayment->amount,
                                'payment_date' => $existingPayment->payment_date ? $existingPayment->payment_date->format('Y-m-d') : null,
                                'payment_type' => $existingPayment->payment_type,
                                'mode' => $existingPayment->mode,
                                'reference_number' => $existingPayment->reference_number,
                                'status' => $existingPayment->status,
                                'payment_request' => [
                                    'id' => $paymentRequest->id,
                                    'status' => $paymentRequest->status,
                                    'total_paid' => (float) $totalPaid,
                                    'remaining_amount' => (float) $remainingAmount,
                                ]
                            ]
                        ]);
                    }
                }
                // Re-throw if not an idempotency replay
                throw $e;
            }

            // Update payment request with mode and notes if provided
            if ($request->filled('mode') || $request->filled('notes')) {
                $payment->update([
                    'mode' => $request->mode ?? $payment->mode,
                    'notes' => $request->notes ?? $payment->notes,
                ]);
            }

            if ($request->filled('reference_number')) {
                $payment->update(['reference_number' => $request->reference_number]);
            }

            // Refresh payment request to get updated status
            $paymentRequest->refresh();
            $totalPaid = $paymentRequest->payments()->sum('amount');
            $approvedAmount = $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;
            $remainingAmount = max(0, $approvedAmount - $totalPaid);

            // Log payment creation with before/after state
            Log::channel('payment_audit')->info('Payment created via API', [
                'payment_request_id' => $paymentRequest->id,
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_amount' => (float) $payment->amount,
                'action' => 'payment',
                'user_id' => Auth::id(),
                'before_status' => $beforeStatus,
                'after_status' => $paymentRequest->status,
                'before_paid' => (float) $beforePaid,
                'after_paid' => (float) $totalPaid,
                'approved_amount' => (float) $approvedAmount,
                'remaining_amount' => (float) $remainingAmount,
                'idempotency_key' => $request->idempotency_key,
                'source' => 'mobile_api',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => (float) $payment->amount,
                    'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
                    'payment_type' => $payment->payment_type,
                    'mode' => $payment->mode,
                    'reference_number' => $payment->reference_number,
                    'status' => $payment->status,
                    'payment_request' => [
                        'id' => $paymentRequest->id,
                        'status' => $paymentRequest->status,
                        'total_paid' => (float) $totalPaid,
                        'remaining_amount' => (float) $remainingAmount,
                    ]
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request not found.'
            ], 404);
        } catch (\InvalidArgumentException $e) {
            Log::error('Payment creation validation error', [
                'payment_request_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment creation error', [
                'payment_request_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Purchase Invoice Payment Request Data (Prefill)
     *
     * Returns invoice details with calculated amounts for payment request creation.
     * This replicates the logic from PaymentRequestController@createModal.
     * Follows the same pattern as PO Advance Request endpoints.
     *
     * @urlParam invoice_id integer required The ID of the purchase invoice
     * @queryParam workspace_id integer optional Filter by workspace ID
     * @queryParam site_id integer optional Filter by site ID
     *
     * @response {
     *   "success": true,
     *   "message": "Purchase invoice payment request data retrieved successfully",
     *   "data": {
     *     "invoice": {
     *       "id": 123,
     *       "invoice_number": "INV-001",
     *       "invoice_date": "2024-01-15",
     *       "grand_total": 50000.00
     *     },
     *     "supplier": {
     *       "id": 1,
     *       "name": "ABC Supplier"
     *     },
     *     "site": {
     *       "id": 5,
     *       "name": "Project A"
     *     },
     *     "po": {
     *       "id": 456,
     *       "po_number": "PO-001"
     *     },
     *     "grand_total": 50000.00,
     *     "paid_amount": 20000.00,
     *     "advance_utilized": 10000.00,
     *     "active_requests": 5000.00,
     *     "remaining_balance": 15000.00,
     *     "max_allowed_amount": 15000.00,
     *     "suggested_amount": 15000.00,
     *     "po_advance_total": 20000.00,
     *     "po_advance_used": 10000.00,
     *     "po_advance_remaining": 10000.00,
     *     "payment_terms": "Net 30 days"
     *   }
     * }
     */
    public function getPurchaseInvoicePaymentRequestData(Request $request, $invoiceId)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            // Get invoice data with calculations (same as createModal)
            $query = DB::table('purchase_invoices as pi')
                ->where('pi.id', $invoiceId);

            // Apply workspace filter if provided
            if ($workspaceId) {
                $query->where('pi.workspace_id', $workspaceId);
            }

            // Apply site filter if provided
            if ($siteId) {
                $query->where('pi.site_id', $siteId);
            }

            $invoiceData = $query
                ->selectRaw('
                    pi.id,
                    pi.invoice_number,
                    pi.invoice_date,
                    pi.grand_total,
                    pi.po_id,
                    pi.supplier_id,
                    pi.site_id,
                    pi.workspace_id,
                    pi.tax_type,
                    pi.invoice_type,
                    pi.grn_id,

                    (
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments_module
                        WHERE purchase_invoice_id = pi.id
                          AND payment_type != "advance_against_po"
                    ) as paid_amount,

                    (
                        SELECT COALESCE(SUM(utilized_amount), 0)
                        FROM advance_utilizations
                        WHERE purchase_invoice_id = pi.id
                          AND status = "applied"
                    ) as advance_used,

                    (
                        SELECT COALESCE(SUM(requested_amount), 0)
                        FROM payment_requests
                        WHERE purchase_invoice_id = pi.id
                          AND status IN ("pending","approved","partially_approved")
                    ) as active_requests
                ')
                ->first();

            if (!$invoiceData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }

            $invoice = PurchaseInvoice::with([
                'supplier',
                'site',
                'purchaseOrder',
                'grn',
                'creator'
            ])->findOrFail($invoiceId);

            // Validate workspace if provided
            if ($workspaceId && $invoice->workspace_id != $workspaceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice does not belong to the specified workspace.'
                ], 403);
            }

            // Validate site if provided
            if ($siteId && $invoice->site_id != $siteId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice does not belong to the specified site.'
                ], 403);
            }

            // PO advance calculations - BEFORE allocation check
            $poAdvanceTotal = 0;
            $poAdvanceUsed = 0;
            $poAdvanceRemaining = 0;

            if ($invoiceData->po_id) {
                // Use new supplier_advances system
                $poAdvanceData = DB::table('supplier_advances as sa')
                    ->where('sa.po_id', $invoiceData->po_id)
                    ->where('sa.status', 'paid')
                    ->where('sa.locked_to_po', true)
                    ->selectRaw('
                        COALESCE(SUM(sa.amount), 0) as total_advance,
                        COALESCE(SUM(sa.utilized_amount), 0) as used_advance
                    ')
                    ->first();

                $poAdvanceTotal = $poAdvanceData->total_advance ?? 0;
                $poAdvanceUsed = $poAdvanceData->used_advance ?? 0;

                // Also include legacy payments_module advances (advance_against_po)
                $legacyAdvanceData = DB::table('payments_module as pm')
                    ->where('pm.purchase_order_id', $invoiceData->po_id)
                    ->where('pm.payment_type', 'advance_against_po')
                    ->where('pm.status', 'completed')
                    ->selectRaw('
                        COALESCE(SUM(pm.amount), 0) as total_advance,
                        COALESCE(SUM((SELECT COALESCE(SUM(au.utilized_amount), 0) FROM advance_utilizations au WHERE au.payments_module_id = pm.id AND au.status = "applied")), 0) as used_advance
                    ')
                    ->first();

                $poAdvanceTotal += $legacyAdvanceData->total_advance ?? 0;
                $poAdvanceUsed += $legacyAdvanceData->used_advance ?? 0;

                $poAdvanceRemaining = max(0, $poAdvanceTotal - $poAdvanceUsed);
            }

            // AUTO-ALLOCATION: If PO has available advance and invoice has no advance allocated yet
            $advanceUtilized = $invoiceData->advance_used;
            if ($invoiceData->po_id && $invoiceData->advance_used == 0 && $poAdvanceRemaining > 0) {
                // Check if feature flag is enabled
                if (config('finance.po_locked_advance_enabled', false)) {
                    // Use AdvanceAllocationService to calculate available advance (dry-run mode)
                    $allocationResult = $this->advanceAllocationService->calculatePotentialAllocation($invoice->id);

                    if ($allocationResult && isset($allocationResult['allocated_amount'])) {
                        $advanceUtilized = $allocationResult['allocated_amount'];
                    }

                    // Recalculate remaining PO advance after allocation
                    $poAdvanceRemaining = max(0, $poAdvanceRemaining - ($allocationResult['allocated_amount'] ?? 0));
                } else {
                    // Feature flag disabled: Calculate potential allocation directly from PO advances
                    $invoiceBalance = max(0, $invoiceData->grand_total - $invoiceData->paid_amount - $invoiceData->advance_used - $invoiceData->active_requests);
                    $potentialAllocation = min($poAdvanceRemaining, $invoiceBalance);

                    if ($potentialAllocation > 0) {
                        $advanceUtilized = $potentialAllocation;
                        // Recalculate remaining PO advance after allocation
                        $poAdvanceRemaining = max(0, $poAdvanceRemaining - $potentialAllocation);
                    }
                }
            }

            // Calculate values after potential allocation
            $paidAmount = $invoiceData->paid_amount;
            $activeRequestsSum = $invoiceData->active_requests;

            // Net payable = grand_total - paid - advance_used - active_requests
            $maxAllowedAmount = max(0, $invoiceData->grand_total - $paidAmount - $advanceUtilized - $activeRequestsSum);
            $remainingBalance = $maxAllowedAmount;

            // Get payment terms
            $paymentTerms = $invoice->purchaseOrder?->payment_terms_conditions ?? '';

            // Build response data following PO Advance Request pattern
            $data = [
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'grand_total' => (float) $invoiceData->grand_total,
                    'tax_type' => $invoiceData->tax_type ?? '',
                    'invoice_type' => $invoiceData->invoice_type ?? '',
                    'grn_number' => $invoice->grn?->grn_number ?? '',
                ],
                'supplier' => [
                    'id' => $invoice->supplier?->id,
                    'name' => $invoice->supplier?->name ?? '',
                ],
                'site' => [
                    'id' => $invoice->site?->id,
                    'name' => $invoice->site?->name ?? '',
                ],
                'po' => [
                    'id' => $invoice->po_id,
                    'po_number' => $invoice->purchaseOrder?->po_number ?? '',
                ],
                'grand_total' => (float) $invoiceData->grand_total,
                'paid_amount' => (float) $paidAmount,
                'advance_utilized' => (float) $advanceUtilized,
                'active_requests' => (float) $activeRequestsSum,
                'remaining_balance' => (float) $remainingBalance,
                'max_allowed_amount' => (float) $maxAllowedAmount,
                'suggested_amount' => (float) $maxAllowedAmount,
                'po_advance_total' => (float) $poAdvanceTotal,
                'po_advance_used' => (float) $poAdvanceUsed,
                'po_advance_remaining' => (float) $poAdvanceRemaining,
                'payment_terms' => $paymentTerms,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Purchase invoice payment request data retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Purchase invoice payment request data retrieval error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving purchase invoice payment request data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Purchase Invoice Payment Request
     *
     * Creates a new payment request for a purchase invoice.
     * This replicates the exact business logic from PaymentRequestController@store.
     * Follows the same pattern as PO Advance Request endpoints.
     *
     * @urlParam invoice_id integer required The ID of the purchase invoice
     * @bodyParam requested_amount numeric required The amount being requested (min: 0.01)
     * @bodyParam payment_date date required The payment date
     * @bodyParam remarks string optional Remarks for the payment request (max: 1000 characters)
     * @bodyParam idempotency_key string optional Unique key to prevent duplicate requests (max: 64 characters)
     * @bodyParam workspace_id integer optional Filter by workspace ID
     * @bodyParam site_id integer optional Filter by site ID
     *
     * @response {
     *   "success": true,
     *   "message": "Purchase invoice payment request created successfully",
     *   "data": {
     *     "id": 789,
     *     "status": "pending"
     *   }
     * }
     */
    public function storePurchaseInvoicePaymentRequest(Request $request, $invoiceId)
    {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'requested_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
            'workspace_id' => 'nullable|integer',
            'site_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        try {
            return DB::transaction(function () use ($request, $invoiceId, $workspaceId, $siteId) {
                // LOCK ORDER: 1. PurchaseInvoice
                $invoice = PurchaseInvoice::where('id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

                if (!$invoice) {
                    throw new \Exception('Invoice not found.');
                }

                // Validate workspace if provided
                if ($workspaceId && $invoice->workspace_id != $workspaceId) {
                    throw new \Exception('Invoice does not belong to the specified workspace.');
                }

                // Validate site if provided
                if ($siteId && $invoice->site_id != $siteId) {
                    throw new \Exception('Invoice does not belong to the specified site.');
                }

                // CRITICAL: Direct GRN hard stop at controller level (only if feature flag enabled)
                if (config('finance.po_locked_advance_enabled', false) && empty($invoice->po_id)) {
                    throw new \InvalidArgumentException(
                        'Direct GRN invoices cannot create payment requests with advance allocation. ' .
                        'This invoice is not linked to a Purchase Order. ' .
                        'Direct GRN requires full payment without advance.'
                    );
                }

                // CRITICAL: Idempotency check
                if (!empty($request->idempotency_key)) {
                    $existingRequest = PaymentRequest::where('idempotency_key', $request->idempotency_key)
                        ->where('workspace_id', $invoice->workspace_id)
                        ->first();

                    if ($existingRequest) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment request already processed (idempotency)',
                            'data' => [
                                'id' => $existingRequest->id,
                                'status' => $existingRequest->status,
                            ]
                        ]);
                    }
                }

                // LOCK ORDER: 2. PurchaseOrder (if exists)
                $po = null;
                if ($invoice->po_id) {
                    $po = PurchaseOrder::where('id', $invoice->po_id)
                        ->lockForUpdate()
                        ->first();
                }

                // LOCK ORDER: 3. AdvanceUtilizations (if exists)
                if ($invoice->po_id) {
                    $this->lockAdvanceUtilizationsForInvoice($invoice->po_id);
                }

                // CRITICAL: Validate financial period not closed (only if feature flag enabled)
                if (config('finance.financial_period_locking_enabled', false)) {
                    $periodService = new \App\Services\FinancialPeriodService();
                    $periodService->validatePeriodNotClosed(
                        \Carbon\Carbon::parse($invoice->invoice_date),
                        $invoice->workspace_id,
                        $invoice->site_id
                    );
                }

                if ($invoice->isPaid()) {
                    throw new \Exception('Invoice is already fully paid.');
                }

                // Calculate max allowed - using SAME logic as createModal to ensure consistency
                $maxAllowed = $this->calculateMaxAllowedForPR($invoice);

                if ($maxAllowed <= 0) {
                    throw new \Exception('No remaining balance available for payment request.');
                }

                if ($invoice->hasPendingPaymentRequest()) {
                    throw new \Exception('A pending payment request already exists for this invoice.');
                }

                if ($request->requested_amount > $maxAllowed) {
                    throw new \Exception('Requested amount cannot exceed remaining invoice amount. Maximum allowed: ₹' . number_format($maxAllowed, 2));
                }

                // Allocate advance BEFORE capturing snapshots so snapshots include allocated amount
                if ($po && !$this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id)) {
                    if (config('finance.po_locked_advance_enabled', false)) {
                        // Use the full service with feature flag enabled
                        $this->advanceAllocationService->allocateToInvoice($invoice->id);
                    } else {
                        // Feature flag disabled: Perform direct allocation without feature flag check
                        $this->allocateAdvanceWithoutFeatureFlag($invoice->id);
                    }
                }

                // Capture ALL financial snapshots at creation time (AFTER potential allocation)
                $netPayableSnapshot = $invoice->getNetPayableAmount();
                $advanceUsedSnapshot = $invoice->getAdvanceUtilizedForInvoice();
                $paidAmountSnapshot = $invoice->getActualPaidAmount();
                $activeRequestsSnapshot = $invoice->getActivePaymentRequestsSum();

                $paymentRequest = PaymentRequest::create([
                    'purchase_invoice_id' => $invoiceId,
                    'requested_amount' => $request->requested_amount,
                    'payment_date' => $request->payment_date,
                    'status' => PaymentRequest::STATUS_PENDING,
                    'remarks' => $request->remarks,
                    'requested_by' => Auth::id(),
                    'net_payable_snapshot' => $netPayableSnapshot,
                    'advance_used_snapshot' => $advanceUsedSnapshot,
                    'paid_amount_snapshot' => $paidAmountSnapshot,
                    'active_requests_snapshot' => $activeRequestsSnapshot,
                    'idempotency_key' => $request->idempotency_key,
                    'workspace_id' => $invoice->workspace_id,
                    'transaction_flow_id' => $invoice->transaction_flow_id,
                    'type' => PaymentRequest::TYPE_INVOICE_PAYMENT,
                ]);

                Log::channel('payment_audit')->info('Purchase invoice payment request created via API', [
                    'payment_request_id' => $paymentRequest->id,
                    'purchase_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'po_id' => $invoice->po_id,
                    'supplier_id' => $invoice->supplier_id,
                    'site_id' => $invoice->site_id,
                    'requested_by' => Auth::id(),
                    'requested_amount' => $request->requested_amount,
                    'payment_date' => $request->payment_date,
                    'snapshots' => [
                        'net_payable' => $netPayableSnapshot,
                        'advance_used' => $advanceUsedSnapshot,
                        'paid_amount' => $paidAmountSnapshot,
                        'active_requests' => $activeRequestsSnapshot,
                    ],
                    'max_allowed' => $maxAllowed,
                    'advance_allocated' => $po && !$this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id),
                    'created_at' => $paymentRequest->created_at,
                    'source' => 'mobile_api',
                ]);

                $projectId = $invoice->site_id;
                $invoiceNumber = $invoice->invoice_number;
                $requestedBy = Auth::user()->name;

                try {
                    $this->notificationService->createPaymentRequestNotification(
                        $invoice->po_id ?? 0,
                        $projectId,
                        $invoiceNumber,
                        $requestedBy
                    );
                } catch (\Exception $e) {
                    // Notification failure should not fail the payment request creation
                    Log::warning('Purchase invoice payment request notification failed', [
                        'payment_request_id' => $paymentRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase invoice payment request created successfully.',
                    'data' => [
                        'id' => $paymentRequest->id,
                        'status' => $paymentRequest->status,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Purchase invoice payment request creation error via API', [
                'invoice_id' => $invoiceId,
                'requested_amount' => $request->requested_amount,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
