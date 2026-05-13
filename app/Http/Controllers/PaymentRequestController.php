<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentRequestDataTable;
use App\Models\PaymentRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Http\Requests\UpdatePurchaseInvoiceRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;
use App\Services\POCalculationService;
use App\Services\PaymentService;
use App\Services\AdvanceAllocationService;

class PaymentRequestController extends Controller {

    protected NotificationService $notificationService;
    protected POCalculationService $poCalculationService;
    protected PaymentService $paymentService;
    protected AdvanceAllocationService $advanceAllocationService;

    public function __construct(
        NotificationService $notificationService, 
        POCalculationService $poCalculationService,
        PaymentService $paymentService,
        AdvanceAllocationService $advanceAllocationService
    ) {
        $this->notificationService = $notificationService;
        $this->poCalculationService = $poCalculationService;
        $this->paymentService = $paymentService;
        $this->advanceAllocationService = $advanceAllocationService;
    }

    public function index(PaymentRequestDataTable $dataTable) {

        if (\Auth::user()->isAbleTo('manage-payment manage')) {
            try {
                return $dataTable->render('payment-request.index');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        // Get available invoices for creating payment requests
        $invoices = PurchaseInvoice::with(['supplier', 'purchaseOrder'])
            ->whereHas('supplier')
            ->where('workspace_id', \Auth::user()->active_workspace)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('payment-request.create', compact('invoices'));
    }

    public function createModal($invoiceId)
    {
        $invoiceData = DB::table('purchase_invoices as pi')
            ->where('pi.id', $invoiceId)
            ->selectRaw('
                pi.id,
                pi.invoice_number,
                pi.grand_total,
                pi.po_id,
                pi.supplier_id,
                pi.site_id,
                pi.workspace_id,

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
            abort(404);
        }

        $invoice = PurchaseInvoice::with([
            'supplier',
            'site',
            'purchaseOrder',
            'grn',
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

        // AUTO-ALLOCATION: If PO has available advance and invoice has no advance allocated yet,
        // auto-allocate advance to show correct max allowed amount in modal
        $advanceAmount = 0;
        if ($invoiceData->po_id && $invoiceData->advance_used == 0 && $poAdvanceRemaining > 0) {
            // Check if feature flag is enabled
            if (config('finance.po_locked_advance_enabled', false)) {
                // Use AdvanceAllocationService to calculate available advance (dry-run mode)
                $allocationResult = $this->advanceAllocationService->calculatePotentialAllocation($invoice->id);

                if ($allocationResult && isset($allocationResult['allocated_amount'])) {
                    $invoiceData->advance_used = $allocationResult['allocated_amount'];
                }
                
                // Recalculate remaining PO advance after allocation
                $poAdvanceRemaining = max(0, $poAdvanceRemaining - ($allocationResult['allocated_amount'] ?? 0));
            } else {
                // Feature flag disabled: Calculate potential allocation directly from PO advances
                // This ensures advance amounts show correctly even when feature flag is off
                $invoiceBalance = max(0, $invoiceData->grand_total - $invoiceData->paid_amount - $invoiceData->advance_used - $invoiceData->active_requests);
                $potentialAllocation = min($poAdvanceRemaining, $invoiceBalance);
                
                if ($potentialAllocation > 0) {
                    $invoiceData->advance_used = $potentialAllocation;
                    // Recalculate remaining PO advance after allocation
                    $poAdvanceRemaining = max(0, $poAdvanceRemaining - $potentialAllocation);
                }
            }
        }

        // Calculate values after potential allocation
        $paidAmount = $invoiceData->paid_amount;
        $advanceUtilized = $invoiceData->advance_used;
        $activeRequestsSum = $invoiceData->active_requests;
        
        // Net payable = grand_total - paid - advance_used - active_requests
        $maxAllowedAmount = max(0, $invoiceData->grand_total - $paidAmount - $advanceUtilized - $activeRequestsSum);
        $remainingBalance = $maxAllowedAmount;

        // Update advance amount to show available after allocation
        $advanceAmount = $poAdvanceRemaining;
        
        // If advance not yet allocated, auto-calculate
        if ($invoiceData->po_id && $advanceUtilized == 0) {
            $advanceAmount = $poAdvanceRemaining;
        }

        $ledgerEntries = collect();
        
        if ($invoice->purchaseOrder) {
            $entries = $this->poCalculationService->getSupplierLedger(
                $invoice->purchaseOrder->id
            );
            
            $ledgerEntries = collect($entries)->map(function ($entry) {
                return (object) [
                    'transaction_date' => $entry['datetime'],
                    'date' => $entry['date'],
                    'description' => $entry['details'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'running_balance' => $entry['running_balance'],
                    'type' => $entry['type'] ?? null,
                ];
            });
        }

        $paymentTerms = $invoice->purchaseOrder?->payment_terms_conditions ?? '';

        return view('payment-request.modal.create', compact(
            'invoice',
            'paidAmount',
            'advanceAmount',
            'advanceUtilized',
            'remainingBalance',
            'maxAllowedAmount',
            'ledgerEntries',
            'paymentTerms',
            'poAdvanceTotal',
            'poAdvanceUsed',
            'poAdvanceRemaining'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_invoice_id' => 'required|exists:purchase_invoices,id',
            'requested_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $invoiceId = $request->purchase_invoice_id;

        try {
            return DB::transaction(function () use ($request, $invoiceId) {
                // LOCK ORDER: 1. PurchaseInvoice
                $invoice = PurchaseInvoice::where('id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

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
                // This includes potential dry-run advance allocation
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
                ]);

                Log::channel('payment_audit')->info('Payment request created', [
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

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

        Log::channel('payment_audit')->info('Advance allocated without feature flag', [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice->invoice_number,
            'allocated_amount' => $totalAllocated,
            'po_id' => $invoice->po_id,
            'source' => 'hybrid (supplier_advances + payments_module legacy advances)',
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

    public function show($id)
    {
        $paymentRequest = PaymentRequest::with([
            'invoice.supplier',
            'invoice.site',
            'invoice.purchaseOrder',
            'po.supplier',
            'po.site',
            'requestedBy',
            'approvedBy',
            'payments'
        ])->findOrFail($id);

        return view('payment-request.show', compact('paymentRequest'));
    }

    public function update(Request $request, $id)
    {
        $paymentRequest = PaymentRequest::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'requested_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fix: Add type-aware maxAllowed calculation
        if ($paymentRequest->isPoAdvance()) {
            $po = $paymentRequest->po;
            $maxAllowed = $po->grand_total - $po->total_paid;
        } else {
            $invoice = $paymentRequest->invoice;
            $maxAllowed = $invoice->getMaxAllowedPaymentRequest();
        }

        if ($paymentRequest->isApproved() || $paymentRequest->isPartiallyApproved()) {
            $maxAllowed = $maxAllowed + $paymentRequest->requested_amount;
        }

        if ($request->requested_amount > $maxAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'Requested amount cannot exceed remaining balance.'
            ], 422);
        }

        $paymentRequest->update([
            'requested_amount' => $request->requested_amount,
            'payment_date' => $request->payment_date,
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment request updated successfully.'
        ]);
    }

    public function approval($id) {
        // Check if this is a payment request ID (for PO advances) or invoice ID
        $paymentRequest = PaymentRequest::find($id);

        if ($paymentRequest && $paymentRequest->isPoAdvance()) {
            // PO advance request - load the PO and payment request
            $po = PurchaseOrder::with(['supplier', 'site', 'creator'])
                ->findOrFail($paymentRequest->po_id);

            $paymentRequests = PaymentRequest::with(['requestedBy', 'approvedBy', 'payments'])
                ->where('po_id', $paymentRequest->po_id)
                ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                ->orderBy('created_at', 'desc')
                ->get();

            return view('payment-request.approval', compact('po', 'paymentRequests', 'paymentRequest'));
        } else {
            // Invoice-based payment request - load the invoice and payment requests
            if (!$paymentRequest) {
                abort(404, 'Payment request not found.');
            }

            $invoice = PurchaseInvoice::with(['supplier', 'site', 'purchaseOrder', 'creator'])
                ->findOrFail($paymentRequest->purchase_invoice_id);

            $paymentRequests = PaymentRequest::with(['requestedBy', 'approvedBy', 'payments'])
                ->where('purchase_invoice_id', $paymentRequest->purchase_invoice_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return view('payment-request.approval', compact('invoice', 'paymentRequests', 'paymentRequest'));
        }
    }

    public function approveSingle(Request $request, $id)
    {
        $paymentRequest = PaymentRequest::with(['invoice', 'po'])->findOrFail($id);

        if (!$paymentRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment request is not pending approval.'
            ], 422);
        }

        if ($paymentRequest->hasPayment()) {
            return response()->json([
                'success' => false,
                'message' => 'A payment has already been created for this request.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,partial,reject',
            'approved_amount' => 'required_if:action,approve,partial|numeric|min:0.01',
            'rejection_reason' => 'required_if:action,reject|string|nullable|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Route to appropriate handler based on type
        if ($paymentRequest->isPoAdvance()) {
            return $this->approvePoAdvance($paymentRequest, $request);
        }

        return $this->approveInvoicePayment($paymentRequest, $request);
    }

    private function approvePoAdvance(PaymentRequest $paymentRequest, Request $request)
    {
        $action = $request->action;

        try {
            return DB::transaction(function () use ($paymentRequest, $request, $action) {
                // Edge case: Check if PO exists
                $po = PurchaseOrder::where('id', $paymentRequest->po_id)->first();
                if (!$po) {
                    throw new \Exception('Purchase Order not found for this advance request.');
                }

                // Lock PO
                $po = PurchaseOrder::where('id', $paymentRequest->po_id)->lockForUpdate()->first();

                // Lock payment request
                $paymentRequest = PaymentRequest::where('id', $paymentRequest->id)->lockForUpdate()->first();

                // Max allowed is always the requested_amount - never PO grand_total
                $maxAllowedApproval = $paymentRequest->requested_amount;

                // PO-specific snapshots
                $netPayableSnapshot = $po->grand_total;
                $advanceUsedSnapshot = \App\Models\SupplierAdvance::where('po_id', $po->id)->sum('amount') ?? 0;
                $paidAmountSnapshot = $po->total_paid;
                $activeRequestsSnapshot = PaymentRequest::where('po_id', $po->id)
                    ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                    ->where('status', 'pending')
                    ->sum('requested_amount');

                // Approval logic
                if (in_array($action, ['approve', 'partial'])) {
                    $approvedAmount = $action === 'approve'
                        ? $paymentRequest->requested_amount
                        : min($request->approved_amount, $paymentRequest->requested_amount, $maxAllowedApproval);

                    if ($approvedAmount > $maxAllowedApproval) {
                        throw new \Exception('Advance amount exceeds available PO balance. Maximum allowed: ₹' . number_format($maxAllowedApproval, 2));
                    }
                }

                // Process action (reject/partial/approve)
                if ($action === 'reject') {
                    $paymentRequest->update([
                        'status' => PaymentRequest::STATUS_REJECTED,
                        'rejection_reason' => $request->rejection_reason,
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    Log::channel('payment_audit')->info('PO advance request rejected', [
                        'payment_request_id' => $paymentRequest->id,
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'rejected_by' => Auth::id(),
                        'rejection_reason' => $request->rejection_reason,
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

                    Log::channel('payment_audit')->info('PO advance request partially approved', [
                        'payment_request_id' => $paymentRequest->id,
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'approved_amount' => $approvedAmount,
                        'approved_by' => Auth::id(),
                        'snapshots' => [
                            'net_payable' => $netPayableSnapshot,
                            'advance_used' => $advanceUsedSnapshot,
                            'paid_amount' => $paidAmountSnapshot,
                            'active_requests' => $activeRequestsSnapshot,
                        ],
                    ]);
                } else {
                    // Approve - create supplier advance ledger entry
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

                    // CRITICAL: Create supplier advance ledger entry
                    app(\App\Services\SupplierAdvanceService::class)->createFromPaymentRequest($paymentRequest);

                    Log::channel('payment_audit')->info('PO advance request approved', [
                        'payment_request_id' => $paymentRequest->id,
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'approved_amount' => $approvedAmount,
                        'approved_by' => Auth::id(),
                        'snapshots' => [
                            'net_payable' => $netPayableSnapshot,
                            'advance_used' => $advanceUsedSnapshot,
                            'paid_amount' => $paidAmountSnapshot,
                            'active_requests' => $activeRequestsSnapshot,
                        ],
                    ]);
                }

                // Notification (type-aware)
                $this->notificationService->createPaymentApprovalNotification($paymentRequest, $po->site_id, $paymentRequest->fresh()->status, $paymentRequest->rejection_reason, Auth::user()->name);

                return response()->json([
                    'success' => true,
                    'message' => 'Advance request ' . ($action === 'reject' ? 'rejected' : 'approved') . ' successfully.',
                    'data' => [
                        'status' => $paymentRequest->fresh()->status,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing approval: ' . $e->getMessage()
            ], 500);
        }
    }

    private function approveInvoicePayment(PaymentRequest $paymentRequest, Request $request)
    {
        $action = $request->action;

        try {
            return DB::transaction(function () use ($paymentRequest, $request, $action) {
                // LOCK ORDER: 1. PurchaseInvoice
                $invoice = PurchaseInvoice::where('id', $paymentRequest->invoice->id)
                    ->lockForUpdate()
                    ->first();

                // LOCK ORDER: 2. PurchaseOrder (if exists)
                $po = null;
                if ($invoice->po_id) {
                    $po = PurchaseOrder::where('id', $invoice->po_id)
                        ->lockForUpdate()
                        ->first();
                }

                // LOCK ORDER: 3. PaymentRequest
                $paymentRequest = PaymentRequest::where('id', $paymentRequest->id)
                    ->lockForUpdate()
                    ->first();

                // LOCK ORDER: 4. AdvanceUtilizations (if exists)
                if ($invoice->po_id) {
                    $this->lockAdvanceUtilizationsForInvoice($invoice->po_id);
                }

                // Auto-allocate advance if not already allocated (same logic as store method)
                $advanceAlreadyAllocated = $this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id);

                if ($po && !$advanceAlreadyAllocated) {
                    $this->advanceAllocationService->allocateToInvoice($invoice->id);
                    // Refresh invoice to get updated values
                    $invoice = $invoice->fresh();
                }

                // Max allowed is always the requested_amount - never invoice total
                $maxAllowedApproval = $paymentRequest->requested_amount;

                if (in_array($action, ['approve', 'partial'])) {
                    $approvedAmount = $action === 'approve'
                        ? $paymentRequest->requested_amount
                        : min($request->approved_amount, $paymentRequest->requested_amount, $maxAllowedApproval);

                    if ($approvedAmount > $maxAllowedApproval) {
                        throw new \Exception('Payment amount exceeds remaining invoice amount. Maximum allowed: ₹' . number_format($maxAllowedApproval, 2));
                    }
                }

                // Capture ALL financial snapshots at approval time
                // IMPORTANT: Use getNetPayableWithoutRequests() for the snapshot - this is the actual
                // remaining balance that can be paid. The getNetPayableAmount() includes active requests
                // which would cause the payment creation to fail in PaymentService.
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

                    Log::channel('payment_audit')->info('Payment request rejected', [
                        'payment_request_id' => $paymentRequest->id,
                        'purchase_invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'po_id' => $invoice->po_id,
                        'supplier_id' => $invoice->supplier_id,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'rejected_by' => Auth::id(),
                        'rejection_reason' => $request->rejection_reason,
                        'advance_released' => true,
                        'approved_at' => now(),
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

                    Log::channel('payment_audit')->info('Payment request partially approved', [
                        'payment_request_id' => $paymentRequest->id,
                        'purchase_invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'po_id' => $invoice->po_id,
                        'supplier_id' => $invoice->supplier_id,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'approved_amount' => $approvedAmount,
                        'approved_by' => Auth::id(),
                        'snapshots' => [
                            'net_payable' => $netPayableSnapshot,
                            'advance_used' => $advanceUsedSnapshot,
                            'paid_amount' => $paidAmountSnapshot,
                            'active_requests' => $activeRequestsSnapshot,
                        ],
                        'approved_at' => now(),
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

                    Log::channel('payment_audit')->info('Payment request approved', [
                        'payment_request_id' => $paymentRequest->id,
                        'purchase_invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'po_id' => $invoice->po_id,
                        'supplier_id' => $invoice->supplier_id,
                        'requested_amount' => $paymentRequest->requested_amount,
                        'approved_amount' => $approvedAmount,
                        'approved_by' => Auth::id(),
                        'snapshots' => [
                            'net_payable' => $netPayableSnapshot,
                            'advance_used' => $advanceUsedSnapshot,
                            'paid_amount' => $paidAmountSnapshot,
                            'active_requests' => $activeRequestsSnapshot,
                        ],
                        'approved_at' => now(),
                    ]);
                }

                $this->notificationService->createPaymentApprovalNotification($paymentRequest, $invoice->site_id, $paymentRequest->fresh()->status, $paymentRequest->rejection_reason, Auth::user()->name);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment request ' . ($action === 'reject' ? 'rejected' : 'approved') . ' successfully.',
                    'data' => [
                        'status' => $paymentRequest->fresh()->status,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing approval: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approvalUpdate(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'payment_requests' => 'required|array',
            'payment_requests.*.id' => 'required|exists:payment_requests,id',
            'payment_requests.*.action' => 'required|in:approve,partial,reject',
            'payment_requests.*.approved_amount' => 'required_if:payment_requests.*.action,approve,partial|numeric|min:0.01',
            'payment_requests.*.rejection_reason' => 'required_if:payment_requests.*.action,reject|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors(['error' => $validator->errors()->first()]);
        }

        try {
            return DB::transaction(function () use ($request, $id) {
                // Check if this is a payment request ID (for PO advances) or invoice ID
                $paymentRequest = PaymentRequest::find($id);

                if ($paymentRequest && $paymentRequest->isPoAdvance()) {
                    // PO advance request - lock the PO
                    $po = PurchaseOrder::where('id', $paymentRequest->po_id)
                        ->lockForUpdate()
                        ->first();
                    $invoice = null;
                } else {
                    // Invoice-based payment request - lock the invoice
                    if (!$paymentRequest) {
                        abort(404, 'Payment request not found.');
                    }

                    $invoice = PurchaseInvoice::where('id', $paymentRequest->purchase_invoice_id)
                        ->lockForUpdate()
                        ->first();

                    // LOCK ORDER: 2. PurchaseOrder (if exists)
                    $po = null;
                    if ($invoice->po_id) {
                        $po = PurchaseOrder::where('id', $invoice->po_id)
                            ->lockForUpdate()
                            ->first();
                    }
                }

                // LOCK ORDER: 3. PaymentRequests (with orderBy for deterministic locking)
                $prIds = collect($request->payment_requests)->pluck('id');
                $paymentRequestsToProcess = PaymentRequest::whereIn('id', $prIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // LOCK ORDER: 4. AdvanceUtilizations (if exists)
                if ($po) {
                    $this->lockAdvanceUtilizationsForInvoice($po->id);
                }

                // Auto-allocate advance if not already allocated (only for invoice-based requests)
                if ($invoice && $po && !$this->advanceAllocationService->isAdvanceAllocatedForInvoice($invoice->id)) {
                    $this->advanceAllocationService->allocateToInvoice($invoice->id);
                    // Refresh invoice to get updated values
                    $invoice = $invoice->fresh();
                }

                // For batch approval, max allowed is always the requested_amount for each payment request
                // Never use invoice total or PO grand_total for approval limits
                $approvedTotal = 0;

                foreach ($request->payment_requests as $prData) {
                    if (in_array($prData['action'], ['approve', 'partial'])) {
                        $paymentRequest = $paymentRequestsToProcess->get($prData['id']);
                        if (!$paymentRequest) continue;

                        // Max allowed for this specific payment request is its requested_amount
                        $maxAllowedApproval = $paymentRequest->requested_amount;

                        $approvedAmount = $prData['action'] === 'approve'
                            ? $paymentRequest->requested_amount
                            : min($prData['approved_amount'], $paymentRequest->requested_amount);

                        // Validation: Cannot approve more than requested amount
                        if ($approvedAmount > $maxAllowedApproval) {
                            throw new \Exception('Cannot approve more than requested amount. Requested: ₹' . number_format($paymentRequest->requested_amount, 2) . ', Attempted: ₹' . number_format($approvedAmount, 2));
                        }

                        $approvedTotal += $approvedAmount;
                    }
                }

                // Capture ALL financial snapshots at batch approval time
                // IMPORTANT: Use getNetPayableWithoutRequests() for the snapshot - this is the actual
                // remaining balance that can be paid. The getNetPayableAmount() includes active requests
                // which would cause the payment creation to fail in PaymentService.
                if ($invoice) {
                    $netPayableSnapshot = $invoice->getNetPayableWithoutRequests();
                    $advanceUsedSnapshot = $invoice->getAdvanceUtilizedForInvoice();
                    $paidAmountSnapshot = $invoice->getActualPaidAmount();
                    $activeRequestsSnapshot = $invoice->getActivePaymentRequestsSum();
                } elseif ($po) {
                    // For PO advances, use PO-level snapshots
                    $netPayableSnapshot = $po->grand_total - $po->total_paid;
                    $advanceUsedSnapshot = $po->advance_utilized ?? 0;
                    $paidAmountSnapshot = $po->total_paid;
                    $activeRequestsSnapshot = PaymentRequest::where('po_id', $po->id)
                        ->where('status', 'pending')
                        ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                        ->sum('requested_amount');
                } else {
                    throw new \Exception('Unable to capture financial snapshots.');
                }

                foreach ($request->payment_requests as $prData) {
                    $paymentRequest = $paymentRequestsToProcess->get($prData['id']);
                    if (!$paymentRequest) continue;
                    
                    if (!$paymentRequest->isPending()) {
                        continue;
                    }

                    if ($paymentRequest->hasPayment()) {
                        continue;
                    }

                    $action = $prData['action'];

                    if ($action === 'reject') {
                        $this->advanceAllocationService->releaseReservation($paymentRequest->id);

                        $paymentRequest->update([
                            'status' => PaymentRequest::STATUS_REJECTED,
                            'rejection_reason' => $prData['rejection_reason'] ?? null,
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                        // Fix logging to use contextId
                        $contextId = $paymentRequest->isPoAdvance()
                            ? $paymentRequest->po_id
                            : $paymentRequest->purchase_invoice_id;
                        $contextType = $paymentRequest->isPoAdvance() ? 'po' : 'invoice';

                        Log::info('PaymentRequest rejected (batch)', [
                            'payment_request_id' => $paymentRequest->id,
                            'context_id' => $contextId,
                            'context_type' => $contextType,
                            'rejected_by' => Auth::id(),
                            'reason' => $prData['rejection_reason'],
                        ]);
                    } elseif ($action === 'partial') {
                        $approvedAmount = min($prData['approved_amount'], $paymentRequest->requested_amount);

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

                        // Fix logging to use contextId
                        $contextId = $paymentRequest->isPoAdvance()
                            ? $paymentRequest->po_id
                            : $paymentRequest->purchase_invoice_id;
                        $contextType = $paymentRequest->isPoAdvance() ? 'po' : 'invoice';

                        Log::info('PaymentRequest partially approved (batch)', [
                            'payment_request_id' => $paymentRequest->id,
                            'context_id' => $contextId,
                            'context_type' => $contextType,
                            'approved_by' => Auth::id(),
                            'approved_amount' => $approvedAmount,
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

                        // CRITICAL: Create supplier advance ledger entry for PO advance requests
                        if ($paymentRequest->isPoAdvance()) {
                            app(\App\Services\SupplierAdvanceService::class)->createFromPaymentRequest($paymentRequest);
                        }

                        // Fix logging to use contextId
                        $contextId = $paymentRequest->isPoAdvance()
                            ? $paymentRequest->po_id
                            : $paymentRequest->purchase_invoice_id;
                        $contextType = $paymentRequest->isPoAdvance() ? 'po' : 'invoice';

                        Log::info('PaymentRequest approved (batch)', [
                            'payment_request_id' => $paymentRequest->id,
                            'context_id' => $contextId,
                            'context_type' => $contextType,
                            'approved_by' => Auth::id(),
                            'approved_amount' => $approvedAmount,
                        ]);
                    }
                }

                // Fix notification to use type-aware method
                $projectId = $invoice ? $invoice->site_id : ($po ? $po->site_id : 0);
                $this->notificationService->createPaymentApprovalNotification(
                    $paymentRequest,
                    $projectId,
                    'batch_approved',
                    null,
                    Auth::user()->name
                );
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error processing approval: ' . $e->getMessage()]);
        }
    }
    
    public function updatePaymentRequest(Request $request)
    {
        return response()->json(['success' => false, 'message' => 'This method is deprecated.'], 410);
    }
}
