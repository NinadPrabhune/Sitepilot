<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentsModuleDataTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\PaymentsModule;
use App\Models\PaymentModuleAllocation;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SupplierTransaction;
use App\Helpers\LedgerHelper;
use Workdo\Taskly\Entities\Project;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;
use Dompdf\Options;

class PaymentsModuleController extends Controller {

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService) {
        $this->paymentService = $paymentService;
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

    /**
     * Display a listing of the resource.
     */
    public function index(PaymentsModuleDataTable $dataTable) {
        if (!Auth::user()->isAbleTo('manage-payment manage')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            return $dataTable->render('payments-module.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load payments: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        if (!Auth::user()->isAbleTo('manage-payment create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $suppliers = Supplier::orderBy('name')->pluck('name', 'id');
            $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');

            $customFields = null;
            $selectedSiteId = getActiveProject();
            $nextPaymentNumber = PaymentsModule::generatePaymentNumber($selectedSiteId);

            return view('payments-module.create', compact('suppliers', 'sites', 'customFields', 'nextPaymentNumber', 'selectedSiteId'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: ' . $e->getMessage()]);
        }
    }

    /**
     * Create payment from Purchase Order (for advance payments).
     */
    public function createFromPo(PurchaseOrder $purchaseOrder) {
        if (!Auth::user()->isAbleTo('manage-payment create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Feature flag enforcement
            if ($this->shouldEnforcePaymentRequest()) {
                return redirect()->route('payment-request.create-from-po', $purchaseOrder->id)
                    ->with('info', 'Advance payments require Payment Request approval.');
            }
            $selectedSiteId = $purchaseOrder->site_id;
            $nextPaymentNumber = PaymentsModule::generatePaymentNumber($selectedSiteId);

            $selectedPo = $purchaseOrder->load(['supplier', 'site']);

            // Get ledger entries for PO
            $ledgerEntries = app(\App\Services\POCalculationService::class)->getLedgerEntries($purchaseOrder->id);

            return view('payments-module.create-from-po', compact(
                'nextPaymentNumber',
                'selectedPo',
                'ledgerEntries',
                'selectedSiteId'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load PO payment form: ' . $e->getMessage()]);
        }
    }

    /**
     * Create payment from Purchase Invoice.
     */
    public function createFromInvoice(PurchaseInvoice $purchaseInvoice) {
        if (!Auth::user()->isAbleTo('manage-payment create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Feature flag enforcement
            if ($this->shouldEnforcePaymentRequest()) {
                return redirect()->route('payment-request.create-modal', $purchaseInvoice->id)
                    ->with('info', 'Invoice payments require Payment Request approval.');
            }
            $selectedSiteId = $purchaseInvoice->site_id;
            $nextPaymentNumber = PaymentsModule::generatePaymentNumber($selectedSiteId);

            $selectedInvoice = $purchaseInvoice->load(['supplier', 'site', 'payments']);
            $selectedInvoiceId = $purchaseInvoice->id;

            $totalPaid = $selectedInvoice->payments->sum('amount');
            $balance = $selectedInvoice->total_amount - $totalPaid;

            return view('payments-module.create-from-invoice', compact(
                'nextPaymentNumber',
                'selectedInvoiceId',
                'selectedInvoice',
                'balance',
                'selectedSiteId'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load invoice form: ' . $e->getMessage()]);
        }
    }

    /**
     * Create payment from Payment Request.
     */
    public function createFromPaymentRequest(PaymentRequest $paymentRequest) {
        if (!Auth::user()->isAbleTo('manage-payment create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Validate payment request can accept payment
            if (!$paymentRequest->canMakePayment()) {
                if (request()->ajax()) {
                    return response()->json(['error' => 'This payment request is fully utilized. Please create a new Payment Request for remaining invoice balance.'], 400);
                }
                return redirect()->route('payment-request.index')
                    ->with('error', 'This payment request is fully utilized. Please create a new Payment Request for remaining invoice balance.');
            }

            $selectedSiteId = $paymentRequest->site_id;

            // Handle null site_id - use active workspace/project
            if (!$selectedSiteId) {
                $selectedSiteId = getActiveProject();
            }

            if (!$selectedSiteId) {
                if (request()->ajax()) {
                    return response()->json(['error' => 'No site/project selected. Please select a project first.'], 400);
                }
                return back()->withErrors(['error' => 'No site/project selected. Please select a project first.']);
            }

            $nextPaymentNumber = PaymentsModule::generatePaymentNumber($selectedSiteId);

            $paymentRequest->load(['invoice.supplier', 'invoice.site', 'invoice.payments', 'invoice.purchaseOrder', 'po.supplier', 'po.site']);
            $invoice = $paymentRequest->invoice;
            $po = $paymentRequest->po;

            $approvedAmount = $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;

            // Safety: Ensure approved_amount never exceeds requested_amount
            $approvedAmount = min($approvedAmount, $paymentRequest->requested_amount);

            // Compute financial values in controller (not blade)
            // Handle both invoice payment and PO advance types
            if ($invoice) {
                $totalPaid = $invoice->getActualPaidAmount();
                $advanceUsed = $invoice->getAdvanceUtilizedForInvoice();
                $netPayable = max(0, $invoice->grand_total - $totalPaid - $advanceUsed);
            } else {
                // PO advance type - no invoice
                $totalPaid = 0;
                $advanceUsed = 0;
                $netPayable = $approvedAmount;
            }

            $alreadyPaidForRequest = $paymentRequest->payments()->sum('amount');
            $remainingApproved = max(0, $approvedAmount - $alreadyPaidForRequest);

            // Handle AJAX modal requests
            if (request()->ajax()) {
                return view('payments-module.create-from-payment-request', compact(
                    'nextPaymentNumber',
                    'paymentRequest',
                    'invoice',
                    'po',
                    'approvedAmount',
                    'netPayable',
                    'remainingApproved',
                    'selectedSiteId'
                ));
            }

            return view('payments-module.create-from-payment-request', compact(
                'nextPaymentNumber',
                'paymentRequest',
                'invoice',
                'po',
                'approvedAmount',
                'netPayable',
                'remainingApproved',
                'selectedSiteId'
            ));
        } catch (\Exception $e) {
            if (request()->ajax()) {
                return response()->json(['error' => 'Unable to load payment request form: ' . $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => 'Unable to load payment request form: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        Log::channel('payment_audit')->info('====== PAYMENT STORE REQUEST STARTED ======', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'timestamp' => now()->toDateTimeString(),
            'all_request_data' => $request->except(['password', 'token', 'payment_proff_file']),
        ]);

        if (!Auth::user()->isAbleTo('manage-payment create')) {
            Log::channel('payment_audit')->error('Permission denied for payment creation', [
                'user_id' => auth()->id(),
                'required_permission' => 'manage-payment create',
            ]);
            abort(403, 'Unauthorized action.');
        }

        try {
            // Feature flag enforcement
            Log::channel('payment_audit')->info('Feature flag check', [
                'should_enforce_payment_request' => $this->shouldEnforcePaymentRequest(),
                'has_payment_request_id' => $request->filled('payment_request_id'),
            ]);

            if ($this->shouldEnforcePaymentRequest()) {
                if (!$request->filled('payment_request_id')) {
                    Log::channel('payment_audit')->warning('Blocked direct payment attempt', [
                        'user_id' => auth()->id(),
                        'payload' => $request->except(['password', 'token']),
                        'ip' => $request->ip(),
                    ]);
                    abort(403, 'Direct payments are disabled. Use Payment Request workflow.');
                }
            }

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'purchase_order_id' => 'required_without:payment_type|exclude_if:payment_type,advance_against_po,against_po|nullable|exists:purchase_orders,id',
                'site_id' => 'required|exists:projects,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|in:advance_against_po,against_po,against_invoice',
                'mode' => 'nullable|string|max:255',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'payment_proff_file' => 'nullable|file',
                'payment_request_id' => 'nullable|exists:payment_requests,id',
                'idempotency_key' => 'nullable|string|max:64|unique:payments_module,idempotency_key',
            ]);

            if ($validator->fails()) {
                Log::channel('payment_audit')->error('Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->except(['password', 'token', 'payment_proff_file']),
                ]);
                return back()->with('error', $validator->errors()->first());
            }

            Log::channel('payment_audit')->info('Validation passed', [
                'validated_data' => $request->except(['password', 'token', 'payment_proff_file']),
            ]);

            // If payment_request_id is present, use unified payment service
            if ($request->filled('payment_request_id')) {
                Log::channel('payment_audit')->info('Payment request workflow detected', [
                    'payment_request_id' => $request->payment_request_id,
                ]);

                $paymentRequest = PaymentRequest::findOrFail($request->payment_request_id);

                Log::channel('payment_audit')->info('Payment request loaded', [
                    'payment_request_id' => $paymentRequest->id,
                    'status' => $paymentRequest->status,
                    'po_id' => $paymentRequest->po_id,
                    'purchase_invoice_id' => $paymentRequest->purchase_invoice_id,
                    'requested_amount' => $paymentRequest->requested_amount,
                    'approved_amount' => $paymentRequest->approved_amount,
                ]);

                // Strict ownership validation - prevent cross-supplier fraud
                if ($paymentRequest->po_id) {
                    $po = PurchaseOrder::find($paymentRequest->po_id);
                    if ($po && (int) $po->supplier_id !== (int) $request->supplier_id) {
                        Log::channel('payment_audit')->warning('Blocked cross-supplier payment attempt (PO)', [
                            'user_id' => auth()->id(),
                            'payment_request_id' => $paymentRequest->id,
                            'request_supplier_id' => $request->supplier_id,
                            'po_supplier_id' => $po->supplier_id,
                        ]);
                        abort(403, 'Invalid supplier for this payment request');
                    }
                } elseif ($paymentRequest->purchase_invoice_id) {
                    $invoice = PurchaseInvoice::find($paymentRequest->purchase_invoice_id);
                    if ($invoice && (int) $invoice->supplier_id !== (int) $request->supplier_id) {
                        Log::channel('payment_audit')->warning('Blocked cross-supplier payment attempt (Invoice)', [
                            'user_id' => auth()->id(),
                            'payment_request_id' => $paymentRequest->id,
                            'request_supplier_id' => $request->supplier_id,
                            'invoice_supplier_id' => $invoice->supplier_id,
                        ]);
                        abort(403, 'Invalid supplier for this payment request');
                    }
                }

                try {
                    Log::channel('payment_audit')->info('Calling payment service', [
                        'payment_request_id' => $paymentRequest->id,
                        'amount' => (float) $request->amount,
                        'idempotency_key' => $request->idempotency_key,
                    ]);

                    $payment = $this->paymentService->createPaymentFromRequest(
                        $paymentRequest,
                        (float) $request->amount,
                        $request->idempotency_key
                    );

                    Log::channel('payment_audit')->info('Payment created successfully via payment request', [
                        'payment_id' => $payment->id ?? null,
                        'payment_number' => $payment->payment_number ?? null,
                    ]);

                    return redirect()->route('payments-module.index')->with('success', __('Payment recorded successfully.'));
                } catch (\InvalidArgumentException $e) {
                    Log::channel('payment_audit')->error('Invalid argument in payment service', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return back()->with('error', $e->getMessage());
                } catch (\Exception $e) {
                    Log::channel('payment_audit')->error('Exception in payment service', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return back()->with('error', 'Error creating payment: ' . $e->getMessage());
                }
            }

            $po = null;
            if ($request->purchase_order_id) {
                Log::channel('payment_audit')->info('Loading PO', [
                    'purchase_order_id' => $request->purchase_order_id,
                ]);

                $po = PurchaseOrder::findOrFail($request->purchase_order_id);

                Log::channel('payment_audit')->info('PO loaded', [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => $po->status,
                    'invoicing_status' => $po->invoicing_status ?? null,
                    'grand_total' => $po->grand_total,
                    'is_invoicing_eligible' => $po->isInvoicingEligible(),
                ]);

                // Use invoicing_status to determine eligibility (replaces payment_flag)
                if (!$po->isInvoicingEligible()) {
                    Log::channel('payment_audit')->warning('PO not eligible for payment', [
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                        'status' => $po->status,
                        'invoicing_status' => $po->invoicing_status ?? null,
                    ]);
                    return back()->with('error', 'PO is not eligible for payment. PO may be fully invoiced.');
                }
            } else {
                Log::channel('payment_audit')->info('No PO provided for payment');
                $po = null;
            }
            
            // Validate payment against remaining PO liability (only if PO exists)
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;
            $paymentAmount = floatval($request->amount);

            Log::channel('payment_audit')->info('====== FINAL VALIDATION CHECK ======', [
                'payment_type' => $request->payment_type,
                'has_payment_request_id' => $request->filled('payment_request_id'),
                'has_purchase_order_id' => $request->filled('purchase_order_id'),
                'requested_amount' => $paymentAmount,
                'supplier_id' => $supplierId,
                'site_id' => $siteId,
            ]);
            
            if ($po) {
                // NOTE: We use PO-based calculation directly, NOT LedgerHelper::validatePaymentAmount()
                // The Ledger-based validation counts payments via Payment Requests, causing double-deduction
                // For direct PO payments (advance_against_po, against_po), we only count direct payments
                
                // Calculate max payable against PO grand_total (not invoiced amount)
                $totalAmount = (float) $po->grand_total;
                $paidAmount = $po->payments()
                    ->whereIn('payment_type', ['advance_against_po', 'against_po'])
                    ->whereNull('payment_request_id')  // Exclude payments via payment requests
                    ->sum('amount');
                $maxPayable = max(0, $totalAmount - $paidAmount);

                Log::channel('payment_audit')->info('PO Liability Debug', [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_total' => $totalAmount,
                    'direct_paid' => $paidAmount,
                    'request_paid' => $po->payments()->whereNotNull('payment_request_id')->sum('amount'),
                    'calculated_remaining' => $maxPayable,
                    'payment_type' => $request->payment_type
                ]);
                
                if ($request->amount > $maxPayable) {
                    Log::channel('payment_audit')->warning('Payment amount exceeds PO liability', [
                        'requested_amount' => $request->amount,
                        'max_payable' => $maxPayable,
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                    ]);
                    return back()->with('error', 'Payment amount exceeds PO liability. Maximum allowable: ₹' . number_format($maxPayable, 2));
                }
            }

            $data = $request->all();
            $data['workspace_id'] = getActiveWorkSpace();
            $data['created_by'] = creatorId();
            $data['status'] = 'completed';

            // Handle file upload
            if ($request->hasFile('payment_proff_file')) {
                Log::channel('payment_audit')->info('File upload detected', [
                    'original_filename' => $request->file('payment_proff_file')->getClientOriginalName(),
                    'size' => $request->file('payment_proff_file')->getSize(),
                ]);

                $filenameWithExt = $request->file('payment_proff_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('payment_proff_file')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'payment_proff_file', $fileNameToStore, 'payments/proofs');

                Log::channel('payment_audit')->info('File upload result', [
                    'upload_flag' => $path['flag'],
                    'upload_msg' => $path['msg'] ?? null,
                    'upload_url' => $path['url'] ?? null,
                ]);

                if ($path['flag'] == 0) {
                    Log::channel('payment_audit')->error('File upload failed', [
                        'msg' => $path['msg'],
                    ]);
                    return back()->withErrors(['error' => $path['msg']]);
                }

                if (!empty($path['url'])) {
                    $data['payment_proff_file'] = $path['url'];
                }
            }

            // Use DB transaction to prevent concurrent overpayment
            Log::channel('payment_audit')->info('Starting database transaction');
            DB::beginTransaction();
            try {
                // For direct payments (not via payment request), use PO-based calculation
                // For payments via payment request, the PaymentService handles validation
                if ($po && !$request->filled('payment_request_id')) {
                    $directPaidAmount = $po->payments()
                        ->whereIn('payment_type', ['advance_against_po', 'against_po'])
                        ->whereNull('payment_request_id')
                        ->sum('amount');
                    $maxPayableDirect = max(0, (float) $po->grand_total - $directPaidAmount);
                    
                    Log::channel('payment_audit')->info('PO Liability Direct Payment Validation', [
                        'po_id' => $po->id,
                        'po_total' => (float) $po->grand_total,
                        'direct_paid' => $directPaidAmount,
                        'max_payable' => $maxPayableDirect,
                        'payment_amount' => $paymentAmount
                    ]);
                    
                    if ($paymentAmount > $maxPayableDirect) {
                        Log::channel('payment_audit')->warning('Payment amount exceeds PO liability (direct validation)', [
                            'payment_amount' => $paymentAmount,
                            'max_payable_direct' => $maxPayableDirect,
                            'po_id' => $po->id,
                        ]);
                        DB::rollBack();
                        return back()->with('error', 'Payment amount exceeds PO liability. Maximum allowable: ₹' . number_format($maxPayableDirect, 2));
                    }
                } elseif (!$po && $request->filled('payment_request_id')) {
                    // Fallback: supplier-level validation for request-based payments without PO
                    Log::channel('payment_audit')->info('Supplier-level validation for request-based payment', [
                        'supplier_id' => $supplierId,
                        'site_id' => $siteId,
                        'payment_amount' => $paymentAmount,
                    ]);
                    $remainingWithLock = LedgerHelper::getRemainingPOLiabilityWithLock($supplierId, $siteId);
                    Log::channel('payment_audit')->info('Remaining PO liability with lock', [
                        'remaining' => $remainingWithLock,
                    ]);
                    if ($paymentAmount > $remainingWithLock) {
                        Log::channel('payment_audit')->warning('Payment amount exceeds remaining PO liability', [
                            'payment_amount' => $paymentAmount,
                            'remaining_with_lock' => $remainingWithLock,
                        ]);
                        DB::rollBack();
                        return back()->with('error', 'Payment amount exceeds remaining PO liability. Maximum allowable: ₹' . number_format($remainingWithLock, 2));
                    }
                }

                // Step 1: Create payment record
                Log::channel('payment_audit')->info('Creating payment record', [
                    'data' => array_except($data, ['payment_proff_file']),
                ]);

                $payment = PaymentsModule::create($data);

                Log::channel('payment_audit')->info('Payment record created', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'payment_type' => $payment->payment_type,
                ]);

                // Send payment creation notification
                try {
                    $paymentTypeLabel = $payment->payment_type === 'advance_against_po' ? 'Advance Against PO' :
                                      ($payment->payment_type === 'against_po' ? 'Against PO' : 'Against Invoice');
                    app(\App\Services\NotificationService::class)->createPaymentNotification(
                        $payment,
                        $payment->site_id,
                        $paymentTypeLabel
                    );
                } catch (\Exception $e) {
                    Log::channel('payment_audit')->error('Failed to send payment notification', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Safer payment number assignment
                $payment->update([
                    'payment_number' => 'PAY-' . str_pad($payment->id, 4, '0', STR_PAD_LEFT)
                ]);

                Log::channel('payment_audit')->info('Payment number updated', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                ]);

                // Step 2: Process payment based on type
                Log::channel('payment_audit')->info('Processing payment allocation', [
                    'payment_type' => $request->payment_type,
                    'has_purchase_invoice_id' => $request->filled('purchase_invoice_id'),
                    'purchase_invoice_id' => $request->purchase_invoice_id ?? null,
                    'has_purchase_order_id' => $request->filled('purchase_order_id'),
                ]);

                if ($request->payment_type === 'against_po') {
                    Log::channel('payment_audit')->info('Auto-allocating to invoices for against_po payment');
                    app(\App\Services\POCalculationService::class)->autoAllocateToInvoices($payment);
                }

                if ($request->payment_type === 'against_invoice' && $request->purchase_invoice_id) {
                    $invoice = PurchaseInvoice::find($request->purchase_invoice_id);
                    if ($invoice) {
                        Log::channel('payment_audit')->info('Updating invoice payment status', [
                            'invoice_id' => $invoice->id,
                        ]);
                        app(\App\Services\PaymentService::class)->updateInvoicePaymentStatus($invoice);
                    } else {
                        Log::channel('payment_audit')->warning('Invoice not found for against_invoice payment', [
                            'purchase_invoice_id' => $request->purchase_invoice_id,
                        ]);
                    }
                }

                if ($request->payment_type === 'advance_against_po' && $request->purchase_order_id) {
                    Log::channel('payment_audit')->info('Creating advance payment allocation', [
                        'payment_id' => $payment->id,
                        'purchase_order_id' => $request->purchase_order_id,
                        'allocated_amount' => $payment->amount,
                    ]);
                    PaymentModuleAllocation::create([
                        'payment_module_id' => $payment->id,
                        'purchase_invoice_id' => null,
                        'purchase_order_id' => $request->purchase_order_id,
                        'allocated_amount' => $payment->amount,
                    ]);
                }

                // Step 3: Create supplier ledger entry (only for direct payments, not via PaymentRequest)
                // PaymentService already creates ledger entries for payment request based payments
                if (!$request->filled('payment_request_id')) {
                    try {
                        Log::channel('payment_audit')->info('Creating supplier ledger entry (direct payment)', [
                            'payment_id' => $payment->id,
                            'supplier_id' => $payment->supplier_id,
                            'amount' => $payment->amount,
                        ]);
                        LedgerHelper::createPaymentEntry($payment);
                        Log::channel('payment_audit')->info('Supplier ledger entry created successfully');
                    } catch (\Exception $e) {
                        Log::channel('payment_audit')->error('Failed to create supplier ledger entry', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // Step 3.5: Auto-allocate PO advances for invoice payments
                if ($request->payment_type === 'against_invoice' && $request->purchase_invoice_id) {
                    $invoice = PurchaseInvoice::find($request->purchase_invoice_id);
                    if ($invoice && $invoice->po_id) {
                        try {
                            Log::channel('payment_audit')->info('Allocating PO advance utilization', [
                                'payment_id' => $payment->id,
                                'invoice_id' => $invoice->id,
                                'po_id' => $invoice->po_id,
                            ]);

                            $advanceUtilizationService = new \App\Services\AdvanceUtilizationService();

                            // Generate idempotency key for this payment utilization
                            $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

                            $result = $advanceUtilizationService->allocateForInvoicePayment($invoice, $payment, $idempotencyKey);

                            // Mark reserved utilizations as applied after successful payment
                            if ($result['success'] && isset($result['utilization_ids'])) {
                                $advanceUtilizationService->applyReservedUtilizations($payment->id);
                            }

                            Log::channel('payment_audit')->info('PO advance utilization allocated', [
                                'payment_id' => $payment->id,
                                'invoice_id' => $invoice->id,
                                'po_id' => $invoice->po_id,
                                'idempotency_key' => $idempotencyKey,
                                'result' => $result,
                            ]);
                        } catch (\Exception $e) {
                            Log::channel('payment_audit')->error('Failed to allocate PO advance utilization', [
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Don't fail payment if utilization fails - it's a bonus feature
                        }
                    }
                }

                // Step 4: Auto-close PO if fully settled (NOT for advance payments)
                // Only settlement payments (against_po, against_invoice) count toward closing PO
                // Advance payments should NOT close PO
                if ($po && $request->payment_type !== 'advance_against_po') {
                    try {
                        // Calculate only settlement payments (direct payments, not via payment requests)
                        $settledAmount = $po->payments()
                            ->whereIn('payment_type', ['against_po', 'against_invoice'])
                            ->whereNull('payment_request_id')
                            ->sum('amount');
                        
                        $poTotal = (float) $po->grand_total;
                        $remaining = max(0, $poTotal - $settledAmount);
                        
                        Log::channel('payment_audit')->info('PO STATUS ANALYSIS', [
                            'po_id' => $po->id,
                            'po_total' => $poTotal,
                            'settled_amount' => $settledAmount,
                            'advance_amount' => $po->payments()
                                ->where('payment_type', 'advance_against_po')
                                ->sum('amount'),
                            'remaining' => $remaining,
                            'payment_type' => $request->payment_type,
                        ]);
                        
                        if ($remaining <= 0) {
                            $po = PurchaseOrder::find($request->purchase_order_id);
                            if ($po && $po->status !== 'Closed') {
                                Log::channel('payment_audit')->info('Auto-closing PO due to full settlement', [
                                    'po_id' => $po->id,
                                    'po_number' => $po->po_number,
                                    'old_status' => $po->status,
                                ]);
                                $po->update(['status' => 'Closed']);
                                Log::channel('payment_audit')->info('PO closed successfully', ['po_id' => $po->id]);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Failed to auto-close PO: ' . $e->getMessage());
                    }

                    // Step 5: Update payment flag
                    try {
                        Log::channel('payment_audit')->info('Updating payment flag', [
                            'purchase_order_id' => $request->purchase_order_id,
                        ]);
                        app(\App\Services\POCalculationService::class)->updatePaymentFlag($request->purchase_order_id);
                        Log::channel('payment_audit')->info('Payment flag updated successfully');
                    } catch (\Exception $e) {
                        Log::channel('payment_audit')->error('Failed to update payment flag', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } elseif ($po && $request->payment_type === 'advance_against_po') {
                    // For advance payments, only update payment flag, don't recalculate for closure
                    try {
                        Log::channel('payment_audit')->info('Updating payment flag for advance payment', [
                            'purchase_order_id' => $request->purchase_order_id,
                        ]);
                        app(\App\Services\POCalculationService::class)->updatePaymentFlag($request->purchase_order_id);
                        Log::channel('payment_audit')->info('Payment flag updated for advance payment');
                    } catch (\Exception $e) {
                        Log::channel('payment_audit')->error('Failed to update payment flag for advance', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                DB::commit();

                Log::channel('payment_audit')->info('====== PAYMENT STORE SUCCESS ======', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'supplier_id' => $payment->supplier_id,
                    'timestamp' => now()->toDateTimeString(),
                ]);

                // Generate and save PDF for payment
                Log::info('PDF DEBUG: Triggering PDF generation in store()', ['payment_id' => $payment->id]);
                try {
                    $workspaceId = getActiveWorkSpace();
                    $pdfPath = $this->generatePaymentPdf($payment, $workspaceId);
                    Log::info('PDF DEBUG: PDF generation returned', ['pdfPath' => $pdfPath]);
                    if ($pdfPath) {
                        $payment->payment_pdf = $pdfPath;
                        $payment->save();
                        Log::info('PDF DEBUG: Payment PDF path saved to DB', ['path' => $pdfPath]);
                    } else {
                        Log::error('PDF DEBUG: PDF generation returned null');
                    }
                } catch (\Exception $e) {
                    Log::error('PDF DEBUG: Failed to generate Payment PDF in store()', ['error' => $e->getMessage()]);
                }

                return redirect()->route('payments-module.index')->with('success', __('Payment recorded successfully.'));
            } catch (\Exception $e) {
                DB::rollBack();
                Log::channel('payment_audit')->error('====== PAYMENT STORE FAILED IN TRANSACTION ======', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->except(['password', 'token', 'payment_proff_file']),
                ]);
                return back()->withErrors(['error' => 'Error creating payment: ' . $e->getMessage()]);
            }
        } catch (\Exception $e) {
            Log::channel('payment_audit')->error('====== PAYMENT STORE FAILED OUTSIDE TRANSACTION ======', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'token', 'payment_proff_file']),
            ]);
            return back()->withErrors(['error' => 'Error creating payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentsModule $paymentsModule) {
        if (!Auth::user()->isAbleTo('manage-payment show')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $paymentsModule->load(['supplier', 'invoice', 'site', 'creator', 'allocations.invoice', 'allocations.purchaseOrder']);
            return view('payments-module.show', compact('paymentsModule'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to show payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentsModule $paymentsModule) {
        if (!Auth::user()->isAbleTo('manage-payment edit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');
            $suppliers = Supplier::pluck('name', 'id');
            $customFields = null;
            
            // Load existing allocations
            $paymentsModule->load('allocations');
            
            // Get the payment's PO ID for remaining payment calculation
            $poId = $paymentsModule->purchase_order_id;

            return view('payments-module.edit', compact(
                            'paymentsModule',
                            'suppliers',
                            'sites',
                            'customFields',
                            'poId'
                    ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentsModule $paymentsModule) {
        if (!Auth::user()->isAbleTo('manage-payment edit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'purchase_order_id' => 'required_without:payment_type|exclude_if:payment_type,advance_against_po,against_po|nullable|exists:purchase_orders,id',
                'site_id' => 'required|exists:projects,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|in:advance_against_po,against_po,against_invoice',
                'mode' => 'nullable|string|max:255',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'payment_proff_file' => 'nullable|file',
            ]);

            if ($validator->fails()) {
                return back()->with('error', $validator->errors()->first());
            }

            $po = null;
            if ($request->purchase_order_id) {
                $po = PurchaseOrder::findOrFail($request->purchase_order_id);
                
                // Use invoicing_status to determine eligibility (replaces payment_flag)
                if (!$po->isInvoicingEligible()) {
                    return back()->with('error', 'PO is not eligible for payment. PO may be fully invoiced.');
                }
            }

            // Calculate max payable against PO grand_total (not invoiced amount)
            if ($po) {
                $totalAmount = (float) $po->grand_total;
                // Only count direct payments (not via payment requests)
                $paidAmount = $po->payments()
                    ->whereIn('payment_type', ['advance_against_po', 'against_po'])
                    ->whereNull('payment_request_id')
                    ->sum('amount');
                $maxPayable = max(0, $totalAmount - $paidAmount);
                
                if ($request->amount > $maxPayable) {
                    return back()->with('error', 'Payment amount exceeds payable amount: ' . number_format($maxPayable, 2));
                }
            }

            $data = $request->all();
            $data['workspace_id'] = getActiveWorkSpace();
            $data['created_by'] = creatorId();
            $data['status'] = 'completed';

            // Handle file upload with helper
            if ($request->hasFile('payment_proff_file')) {
                // Delete old file if exists
                if (!empty($paymentsModule->payment_proff_file)) {
                    Storage::disk('public')->delete($paymentsModule->payment_proff_file);
                }

                // Prepare new filename
                $filenameWithExt = $request->file('payment_proff_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('payment_proff_file')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                // Upload using helper
                $path = upload_file($request, 'payment_proff_file', $fileNameToStore, 'payments/proofs');

                if ($path['flag'] == 0) {
                    return back()->withErrors(['error' => $path['msg']]);
                }

                if (!empty($path['url'])) {
                    $data['payment_proff_file'] = $path['url'];
                }
            }

            $paymentsModule->update($data);

            // Update allocations based on payment type
            $paymentsModule->allocations()->delete();

            if ($request->payment_type === 'against_po') {
                app(\App\Services\POCalculationService::class)->autoAllocateToInvoices($paymentsModule);
            }

            if ($request->payment_type === 'advance_against_po' && $request->purchase_order_id) {
                PaymentModuleAllocation::create([
                    'payment_module_id' => $paymentsModule->id,
                    'purchase_invoice_id' => null,
                    'purchase_order_id' => $request->purchase_order_id,
                    'allocated_amount' => $paymentsModule->amount,
                ]);
            }

            // Update PO invoiced amounts
            if (!empty($data['purchase_order_id'])) {
                app(\App\Services\POCalculationService::class)->updatePOInvoiceAmount($data['purchase_order_id']);
            }

            // Regenerate PDF only when relevant fields change or status becomes completed
            $relevantFields = ['amount', 'mode', 'payment_type', 'reference_number', 'status', 'notes'];
            $shouldRegenerate = false;

            Log::info('PDF DEBUG: Checking if PDF should regenerate in update()', [
                'payment_id' => $paymentsModule->id,
                'changed_fields' => $paymentsModule->getChanges(),
                'status' => $paymentsModule->status
            ]);

            // Check if status changed to completed
            if ($paymentsModule->wasChanged('status') && $paymentsModule->status === 'completed') {
                $shouldRegenerate = true;
                Log::info('PDF DEBUG: Status changed to completed, will regenerate PDF');
            }

            // Check if any relevant field changed
            if ($paymentsModule->wasChanged($relevantFields)) {
                $shouldRegenerate = true;
                Log::info('PDF DEBUG: Relevant field changed, will regenerate PDF');
            }

            Log::info('PDF DEBUG: shouldRegenerate', ['shouldRegenerate' => $shouldRegenerate]);

            if ($shouldRegenerate) {
                // Delete existing PDF if exists
                if (!empty($paymentsModule->payment_pdf)) {
                    try {
                        delete_file($paymentsModule->payment_pdf);
                        Log::info('PDF DEBUG: Deleted existing PDF', ['old_path' => $paymentsModule->payment_pdf]);
                    } catch (\Exception $e) {
                        Log::error('Failed to delete existing Payment PDF: ' . $e->getMessage());
                    }
                }

                // Generate new PDF
                Log::info('PDF DEBUG: Triggering PDF regeneration in update()', ['payment_id' => $paymentsModule->id]);
                try {
                    $workspaceId = getActiveWorkSpace();
                    $pdfPath = $this->generatePaymentPdf($paymentsModule, $workspaceId);
                    Log::info('PDF DEBUG: PDF regeneration returned', ['pdfPath' => $pdfPath]);
                    if ($pdfPath) {
                        $paymentsModule->payment_pdf = $pdfPath;
                        $paymentsModule->save();
                        Log::info('PDF DEBUG: Payment PDF path saved to DB', ['path' => $pdfPath]);
                    } else {
                        Log::error('PDF DEBUG: PDF regeneration returned null');
                    }
                } catch (\Exception $e) {
                    Log::error('PDF DEBUG: Failed to regenerate Payment PDF in update()', ['error' => $e->getMessage()]);
                }
            } else {
                Log::info('PDF DEBUG: Skipping PDF regeneration (no relevant changes)');
            }

            return redirect()->route('payments-module.index')
                            ->with('success', __('Payment updated successfully.'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error updating payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentsModule $paymentsModule) {
        if (!Auth::user()->isAbleTo('manage-payment delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $paymentType = $paymentsModule->payment_type;
            
            // Get invoice IDs from allocations before deleting
            $allocationInvoiceIds = $paymentsModule->allocations()->pluck('purchase_invoice_id')->filter()->toArray();
            
            // Also check legacy purchase_invoice_id
            $legacyInvoiceId = $paymentsModule->purchase_invoice_id;
            
            // Merge both invoice IDs
            $allInvoiceIds = array_merge($allocationInvoiceIds, $legacyInvoiceId ? [$legacyInvoiceId] : []);

            // Delete supplier ledger entries and recalculate balance
            try {
                LedgerHelper::handlePaymentDeletion($paymentsModule->id);
            } catch (\Exception $e) {
                \Log::error('Failed to delete supplier ledger entry: ' . $e->getMessage());
            }

            // Delete allocations first
            $paymentsModule->allocations()->delete();

            // Delete the payment record
            $paymentsModule->delete();

            // Update invoice payment status for all affected invoices
            foreach ($allInvoiceIds as $invoiceId) {
                if ($invoiceId) {
                    $this->updateInvoicePaymentStatus($invoiceId);
                }
            }

            // Update payment flag after payment deletion
            if ($paymentsModule->purchase_order_id) {
                try {
                    app(\App\Services\POCalculationService::class)->updatePaymentFlag($paymentsModule->purchase_order_id);
                } catch (\Exception $e) {
                    \Log::error('Failed to update payment flag after payment deletion: ' . $e->getMessage());
                }
            }

            return redirect()->route('payments-module.index')
                            ->with('success', __('Payment deleted successfully.'));
        } catch (\Exception $e) {
            \Log::error('Error deleting payment: ' . $e->getMessage());
            return redirect()->route('payments-module.index')
                            ->with('error', __('Error deleting payment: ') . $e->getMessage());
        }
    }

    /**
     * Show advance request details for modal support.
     *
     * @param PaymentRequest $paymentRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAdvanceRequest(PaymentRequest $paymentRequest)
    {
        try {
            $paymentRequest->load(['po', 'requestedBy', 'approvedBy', 'payments']);

            $summary = null;
            if ($paymentRequest->po_id) {
                $summary = $this->paymentService->getPOAdvanceSummary($paymentRequest->po_id);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $paymentRequest->id,
                    'type' => $paymentRequest->type,
                    'requested_amount' => $paymentRequest->requested_amount,
                    'approved_amount' => $paymentRequest->approved_amount,
                    'paid_amount' => $paymentRequest->paid_amount,
                    'status' => $paymentRequest->status,
                    'rejection_reason' => $paymentRequest->rejection_reason,
                    'po_summary' => $summary,
                    'requested_by' => $paymentRequest->requestedBy->name ?? null,
                    'approved_by' => $paymentRequest->approvedBy->name ?? null,
                    'approved_at' => $paymentRequest->approved_at?->format('Y-m-d H:i:s'),
                    'payments' => $paymentRequest->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'payment_number' => $payment->payment_number,
                            'amount' => $payment->amount,
                            'payment_date' => $payment->payment_date->format('Y-m-d'),
                            'status' => $payment->status,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice payment status after payment changes.
     *
     * @param int $invoiceId
     * @param int|null $excludePaymentId
     */
    protected function updateInvoicePaymentStatus($invoiceId, $excludePaymentId = null) {
        try {
            $this->paymentService->updateInvoicePaymentStatus($invoiceId, $excludePaymentId);
        } catch (\Exception $e) {
            \Log::error("Error updating invoice payment status for invoice {$invoiceId}: " . $e->getMessage());
        }
    }

    /**
     * Get unpaid invoices for a supplier (AJAX).
     */
    public function getSupplierUnpaidInvoices(Request $request) {
        try {
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;

            // Get invoices for supplier (showing all for now to debug)
            $query = PurchaseInvoice::where('supplier_id', $supplierId)
                ->where('status', '!=', 'draft');

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $invoices = $query->orderBy('invoice_date', 'desc')->get();

            $result = [];
            foreach ($invoices as $invoice) {
                $balance = getInvoiceBalance($invoice->id);
                
                // Skip if balance is 0 (already fully paid)
                if ($balance <= 0) {
                    continue;
                }
                $result[] = [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->total_amount - $balance,
                    'balance' => $balance,
                ];
            }

            return response()->json([
                'status' => 'success',
                'invoices' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get advance payments that can be adjusted (AJAX).
     */
    public function getAdjustableAdvances(Request $request) {
        try {
            $supplierId = $request->supplier_id;

            // Get advance payments for this supplier that have unallocated amounts
            $advances = PaymentsModule::where('supplier_id', $supplierId)
                ->where('payment_type', 'advance_against_po')
                ->get()
                ->filter(function ($payment) {
                    return $payment->getAvailableAdvance() > 0;
                });

            $result = [];
            foreach ($advances as $advance) {
                $result[] = [
                    'id' => $advance->id,
                    'payment_number' => $advance->payment_number,
                    'payment_date' => $advance->payment_date,
                    'amount' => $advance->amount,
                    'available_advance' => $advance->getAvailableAdvance(),
                ];
            }

            return response()->json([
                'status' => 'success',
                'advances' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get suppliers with invoices (AJAX).
     */
    public function getSuppliersWithInvoices(Request $request) {
        try {
            $siteId = $request->site_id;

            // Get suppliers that have unpaid invoices (payment_status is not 'paid')
            $query = PurchaseInvoice::where('status', '!=', 'draft')
                ->where(function($q) {
                    $q->where('payment_status', '!=', 'paid')
                      ->orWhereNull('payment_status');
                });
            
            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $supplierIds = $query->distinct()->pluck('supplier_id')->toArray();
            
            $suppliers = Supplier::whereIn('id', $supplierIds)
                ->orderBy('name')
                ->pluck('name', 'id');

            return response()->json([
                'status' => 'success',
                'suppliers' => $suppliers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get suppliers with POs that have pending payments (AJAX).
     */
    public function getSuppliersWithPendingPOs(Request $request)
    {
        try {
            $siteId = $request->site_id;

            $poQuery = PurchaseOrder::whereIn('invoiced_status', [
                    'not_invoiced',
                    'partially_invoiced'
                ])
                ->whereNotNull('supplier_id');

            if ($siteId) {
                $poQuery->where('site_id', $siteId);
            }

            $supplierIds = $poQuery->distinct()
                ->pluck('supplier_id')
                ->filter()
                ->values();

            if ($supplierIds->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'suppliers' => [],
                ]);
            }

            $suppliers = Supplier::whereIn('id', $supplierIds)
                ->orderBy('name')
                ->pluck('name', 'id');

            return response()->json([
                'status' => 'success',
                'suppliers' => $suppliers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get POs with pending payments for a supplier (AJAX).
     */
    public function getPOsWithPendingBalance(Request $request)
    {
        try {
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;

            $poQuery = PurchaseOrder::where('supplier_id', $supplierId)
                ->whereIn('invoiced_status', [
                    'not_invoiced',
                    'partially_invoiced'
                ]);

            if ($siteId) {
                $poQuery->where('site_id', $siteId);
            }

            $pos = $poQuery->with('payments')->get();

            $result = [];

            foreach ($pos as $po) {
                // Only count direct payments (not via payment requests)
                $totalPaid = $po->payments()
                    ->whereIn('payment_type', [
                        \App\Models\PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO,
                        \App\Models\PaymentsModule::PAYMENT_TYPE_AGAINST_PO
                    ])
                    ->whereNull('payment_request_id')
                    ->sum('amount');

                $grandTotal = (float) $po->grand_total;
                $remainingBalance = max(0, $grandTotal - $totalPaid);

                $result[] = [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'grand_total' => $grandTotal,
                    'remaining_balance' => $remainingBalance,
                ];
            }

            return response()->json([
                'status' => 'success',
                'pos' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get PO summary for payment (AJAX).
     * Uses POCalculationService for PO-specific calculations.
     * Supports mode parameter for legacy supplier-level behavior.
     */
    public function getPOSummary(Request $request)
    {
        try {
            $poId = $request->purchase_order_id;
            $mode = $request->get('mode', 'po'); // Default to PO mode

            if (!$poId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'PO ID is required',
                ]);
            }

            $po = PurchaseOrder::findOrFail($poId);
            $supplierId = $po->supplier_id;
            $siteId = $po->site_id;

            $poCalculationService = app(\App\Services\POCalculationService::class);

            if ($mode === 'supplier') {
                // Legacy supplier-level behavior (preserve for backward compatibility)
                $supplierData = $poCalculationService->getSupplierSummary($supplierId, $siteId);
                return response()->json([
                    'status' => 'success',
                    'po_total' => $supplierData['total_po'],
                    'invoiced_amount' => $supplierData['invoice_total'],
                    'paid_amount' => $supplierData['invoice_paid'],
                    'payable' => $supplierData['payable'],
                    'advance_paid' => $supplierData['advance_paid'],
                ]);
            } else {
                // PO-specific behavior (default)
                $poData = $poCalculationService->getPaymentModuleSummary($poId);
                return response()->json([
                    'status' => 'success',
                    'po_total' => $poData['po_total'],
                    'invoiced_amount' => $poData['invoiced_amount'],
                    'paid_amount' => $poData['total_paid'],
                    'payable' => $poData['payable'],
                    'advance_paid' => $poData['advance_paid'],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get remaining payment for a PO/Invoice/Supplier (AJAX).
     * Uses POCalculationService for PO-specific calculations.
     * Supports mode parameter for legacy supplier-level behavior.
     */
    public function getRemainingPayment(Request $request)
    {
        try {
            $poId = $request->po_id;
            $invoiceId = $request->invoice_id;
            $supplierId = $request->supplier_id;
            $mode = $request->get('mode', 'po'); // Default to PO mode
            $paymentType = $request->payment_type;

            if ($poId) {
                $po = \App\Models\PurchaseOrder::find($poId);
                if ($po) {
                    $supplierId = $po->supplier_id;
                }
            }

            if ($invoiceId) {
                $invoice = \App\Models\PurchaseInvoice::find($invoiceId);
                if ($invoice) {
                    $supplierId = $invoice->supplier_id;
                }
            }

            if (!$supplierId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier ID is required',
                ]);
            }

            $poCalculationService = app(\App\Services\POCalculationService::class);

            if ($poId) {
                // PO context - ALWAYS use PO-level calculation (ignore supplier-level logic)
                $remainingPayment = $poCalculationService->getRemainingPaymentByType($poId, $paymentType);
                $poData = $poCalculationService->calculate($poId);

                return response()->json([
                    'status' => 'success',
                    'remaining_payment' => $remainingPayment,
                    'po_total' => $poData['po_total'],
                    'paid_amount' => $poData['total_paid'],
                    'payment_type' => $paymentType,
                ]);
            } elseif ($invoiceId && $mode === 'supplier') {
                // Invoice-only flow only in supplier mode (legacy)
                // Calculate invoice-specific balance using existing helper
                $invoice = \App\Models\PurchaseInvoice::find($invoiceId);
                if (!$invoice) {
                    return response()->json(['status' => 'error', 'message' => 'Invoice not found']);
                }
                $balance = getInvoiceBalance($invoiceId);
                return response()->json([
                    'status' => 'success',
                    'remaining_payment' => $balance,
                ]);
            } else {
                // Supplier-level legacy mode (preserve current behavior)
                $invoiceAmount = \App\Models\PurchaseInvoice::where('supplier_id', $supplierId)
                    ->sum('grand_total');

                $paidFromAllocations = \App\Models\PaymentModuleAllocation::whereHas('payment', function ($query) use ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                })->sum('allocated_amount');

                $directPayments = \App\Models\PaymentsModule::where('supplier_id', $supplierId)
                    ->whereNull('purchase_order_id')
                    ->sum('amount');

                $paidAmount = $paidFromAllocations + $directPayments;
                $remainingPayment = $invoiceAmount - $paidAmount;

                return response()->json([
                    'status' => 'success',
                    'invoice_amount' => (float) $invoiceAmount,
                    'paid_amount' => (float) $paidAmount,
                    'remaining_payment' => (float) $remainingPayment,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get PO ledger entries for A/c Statement (AJAX).
     */
    public function getPOLedger(Request $request)
    {
        try {
            $poId = $request->purchase_order_id;
            $mode = $request->get('mode', 'po'); // Default to PO mode

            if (!$poId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'PO ID is required',
                ]);
            }

            $po = \App\Models\PurchaseOrder::find($poId);
            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'PO not found',
                ]);
            }

            $poCalculationService = app(\App\Services\POCalculationService::class);

            if ($mode === 'po') {
                // PO-specific ledger
                $entries = $poCalculationService->getLedgerEntries($poId);
            } else {
                // Supplier-level ledger (legacy)
                $entries = $poCalculationService->getSupplierLedgerEntries($po->supplier_id, $po->site_id);
            }

            $formattedEntries = array_map(function($entry) {
                return [
                    'date' => \Carbon\Carbon::parse($entry['datetime'])->format('d-m-Y'),
                    'details' => $entry['details'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'running_balance' => $entry['running_balance'],
                    'type' => $entry['type'],
                    'invoice_number' => $entry['invoice_number'] ?? null,
                    'amount' => $entry['amount'] ?? null,
                ];
            }, $entries);

            return response()->json([
                'status' => 'success',
                'entries' => $formattedEntries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get Supplier Ledger entries (AJAX).
     * Uses POCalculationService.
     */
    public function getSupplierLedger(Request $request)
    {
        try {
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;

            if (!$supplierId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier ID is required',
                ]);
            }

            $poCalculationService = app(\App\Services\POCalculationService::class);
            $entries = $poCalculationService->getSupplierLedgerEntries($supplierId, $siteId);

            $formattedEntries = array_map(function($entry) {
                return [
                    'date' => \Carbon\Carbon::parse($entry['datetime'])->format('d-m-Y'),
                    'details' => $entry['details'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'running_balance' => $entry['running_balance'],
                    'type' => $entry['type'],
                    'invoice_number' => $entry['invoice_number'] ?? null,
                    'amount' => $entry['amount'] ?? null,
                ];
            }, $entries);

            return response()->json([
                'status' => 'success',
                'entries' => $formattedEntries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate PDF for payment manually (endpoint for UI button).
     */
    public function generatePdf($id)
    {
        Log::info('PDF DEBUG: Manual PDF generation endpoint called', ['payment_id' => $id]);
        
        $payment = PaymentsModule::findOrFail($id);
        Log::info('PDF DEBUG: Payment found', ['payment_id' => $payment->id, 'payment_number' => $payment->payment_number]);

        try {
            $workspaceId = getActiveWorkSpace();
            Log::info('PDF DEBUG: Workspace ID', ['workspace_id' => $workspaceId]);
            
            $pdfPath = $this->generatePaymentPdf($payment, $workspaceId);
            Log::info('PDF DEBUG: Manual PDF generation returned', ['pdfPath' => $pdfPath]);
            
            if ($pdfPath) {
                $payment->payment_pdf = $pdfPath;
                $payment->save();
                Log::info('PDF DEBUG: Payment PDF path saved to DB', ['path' => $pdfPath]);
            } else {
                Log::error('PDF DEBUG: Manual PDF generation returned null');
            }

            return redirect()->back()->with('success', 'PDF generated successfully.');
        } catch (\Exception $e) {
            Log::error('PDF DEBUG: Manual PDF generation failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate and save PDF for Payment.
     *
     * @param PaymentsModule $payment
     * @param int $workspaceId
     * @return string|null
     */
    private function generatePaymentPdf(PaymentsModule $payment, int $workspaceId): ?string
    {
        try {
            Log::info('PDF DEBUG: Method called', ['payment_id' => $payment->id]);
            
            Log::info('Starting Payment PDF generation', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type' => $payment->payment_type
            ]);

            // Load relationships - use 'purchaseOrder' as defined in model
            $payment->load(['supplier', 'site', 'purchaseOrder', 'invoice', 'creator']);
            Log::info('Relationships loaded');
            
            // Verify relationship data
            Log::info('PDF DEBUG: Payment Data', [
                'payment' => $payment->toArray(),
                'supplier' => $payment->supplier ? $payment->supplier->name : 'NULL',
                'site' => $payment->site ? $payment->site->name : 'NULL',
                'po' => $payment->purchaseOrder ? $payment->purchaseOrder->po_number : 'NULL',
                'invoice' => $payment->invoice ? $payment->invoice->invoice_number : 'NULL',
            ]);
            
            // Get company settings
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'company_logo', 'company_gst'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }
            Log::info('Company settings retrieved');
            
            // Get workspace details using getActiveWorkSpace()
            $workspaceId = getActiveWorkSpace();
            $workspaceDetails = null;
            if ($workspaceId) {
                // Get workspace object from ID using correct model path
                $workspaceDetails = \App\Models\WorkSpace::find($workspaceId);
                if ($workspaceDetails) {
                    // Override settings with workspace details
                    $settings['workspace_name'] = $workspaceDetails->name;
                    $settings['workspace_contact_person'] = $workspaceDetails->contact_person;
                    $settings['workspace_phone'] = $workspaceDetails->phone;
                    $settings['workspace_email'] = $workspaceDetails->email;
                    $settings['workspace_address'] = $workspaceDetails->address;
                    $settings['workspace_city'] = $workspaceDetails->city;
                    $settings['workspace_state'] = $workspaceDetails->state;
                    $settings['workspace_pincode'] = $workspaceDetails->pincode;
                    $settings['workspace_country'] = $workspaceDetails->country;
                    $settings['workspace_gst_number'] = $workspaceDetails->gst_number;
                }
            }
            Log::info('Workspace details retrieved');
            
            // Prepare data
            $data = [
                'payment' => $payment,
                'settings' => $settings,
                'workspaceDetails' => $workspaceDetails,
                'isPdf' => true,
            ];
            
            // Generate PDF using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $dompdf = new Dompdf($options);
            
            Log::info('PDF DEBUG: Rendering blade view');
            try {
                $html = view('payments-module.pdf.payment_pdf', $data)->render();
                Log::info('PDF DEBUG: Blade rendered successfully', ['html_length' => strlen($html)]);
            } catch (\Exception $e) {
                Log::error('PDF DEBUG: Blade render failed', ['error' => $e->getMessage()]);
                throw $e;
            }
            
            Log::info('PDF DEBUG: Loading HTML into Dompdf');
            $dompdf->loadHtml($html);
            Log::info('PDF DEBUG: HTML loaded');
            
            $dompdf->setPaper('A4', 'portrait');
            
            Log::info('PDF DEBUG: Rendering PDF');
            $dompdf->render();
            Log::info('PDF DEBUG: PDF rendered');
            
            $pdfContent = $dompdf->output();
            Log::info('PDF DEBUG: PDF output generated', ['pdf_size' => strlen($pdfContent)]);
            
            if (empty($pdfContent)) {
                Log::error('PDF DEBUG: Empty PDF content');
                return null;
            }
            
            // Upload PDF - use 'pdf/payments' path
            $fileName = $payment->id . '_' . $payment->payment_number . '.pdf';
            Log::info('PDF DEBUG: Uploading PDF', ['filename' => $fileName]);
            
            $uploadResult = upload_pdf_content($pdfContent, 'pdf/payments', $fileName);
            Log::info('PDF DEBUG: Upload Result', ['result' => $uploadResult]);
            
            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                Log::info('PDF DEBUG: PDF upload successful', ['url' => $uploadResult['url']]);
                return $uploadResult['url'];
            }
            
            Log::error('PDF DEBUG: PDF upload failed', ['result' => $uploadResult]);
            return null;
        } catch (\Exception $e) {
            Log::error('PDF DEBUG: Payment PDF Generation Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Upload payment proof for existing payment
     */
    public function uploadPaymentProof(Request $request, $id)
    {
        $request->validate([
            'payment_proof_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'reference_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $payment = PaymentsModule::findOrFail($id);

        // Handle payment proof file upload
        $paymentProofPath = null;
        if ($request->hasFile('payment_proof_file')) {
            $file = $request->file('payment_proof_file');
            $filename = 'payment_proof_' . $payment->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            $uploadPath = public_path('uploads/payment_proofs');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $file->move($uploadPath, $filename);
            $paymentProofPath = 'payment_proofs/' . $filename;
        }

        // Update payment record
        $payment->update([
            'payment_proff_file' => $paymentProofPath,
            'reference_number' => $request->reference_number ?? $payment->reference_number,
            'notes' => $request->remarks ?? $payment->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment proof uploaded successfully',
            'payment_proof_file' => $paymentProofPath,
        ]);
    }
}
