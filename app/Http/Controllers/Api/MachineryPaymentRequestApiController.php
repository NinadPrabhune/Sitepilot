<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Machinery Payment
 *
 * APIs for managing machinery payment requests, billing calculations, and ERP payment integration.
 */
class MachineryPaymentRequestApiController extends Controller
{
    protected MachineryPaymentRequestService $service;

    public function __construct(MachineryPaymentRequestService $service)
    {
        $this->service = $service;
    }

    /**
     * Recalculate Request
     *
     * Recalculate and verify payment request against current ledger state.
     * Checks for snapshot mismatches (Scenario 32: Ledger Mutation Detection).
     *
     * @authenticated
     */
    public function recalculate(int $id): JsonResponse
    {
        try {
            $paymentRequest = MachineryPaymentRequest::findOrFail($id);

            // Check for snapshot mismatches (Scenario 32: Ledger Mutation Detection)
            $mismatchResult = $this->service->verifyCalculationMismatch($paymentRequest);

            return response()->json([
                'success' => true,
                'data' => $mismatchResult
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Recalculation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug Request
     *
     * Debug endpoint for payment request validation.
     * Returns detailed breakdown of snapshots, hashes, and ledger integrity.
     *
     * @authenticated
     */
    public function debug(int $id): JsonResponse
    {
        try {
            $paymentRequest = MachineryPaymentRequest::with(['machinery', 'supplier', 'ledgerEntries'])
                ->findOrFail($id);

            $auditSnapshot = $paymentRequest->audit_snapshot;
            if (is_string($auditSnapshot)) {
                $auditSnapshot = json_decode($auditSnapshot, true);
            }

            $ledgerEntryIds = $auditSnapshot['ledger_entry_ids'] ?? [];
            $ledgerEntries = MachineryLedger::whereIn('id', $ledgerEntryIds)
                ->where('is_reversal', false)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            // Recalculate current values
            $currentCredits = $ledgerEntries->where('entry_direction', 'credit')->sum('amount');
            $currentDebits = $ledgerEntries->where('entry_direction', 'debit')->sum('amount');
            $currentNetPayable = $currentCredits - $currentDebits;

            // Recalculate hash
            $sortedEntries = $ledgerEntries->sortBy(['date', 'id']);
            $currentHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'direction' => $e->entry_direction,
            ])->values()->toArray()));

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $paymentRequest->id,
                    'status' => $paymentRequest->status,
                    'audit_snapshot' => $auditSnapshot,
                    'current_state' => [
                        'credits' => $currentCredits,
                        'debits' => $currentDebits,
                        'net_payable' => $currentNetPayable,
                        'entries_hash' => $currentHash,
                        'entry_count' => $ledgerEntries->count(),
                    ],
                    'integrity' => [
                        'credits_match' => abs($currentCredits - ($auditSnapshot['credits'] ?? 0)) < 0.01,
                        'debits_match' => abs($currentDebits - ($auditSnapshot['debits'] ?? 0)) < 0.01,
                        'net_match' => abs($currentNetPayable - ($auditSnapshot['net_payable'] ?? 0)) < 0.01,
                        'hash_match' => $currentHash === ($auditSnapshot['entries_hash'] ?? null),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Requests
     *
     * Returns a paginated list of machinery payment requests.
     *
     * @queryParam machinery_id int Filter by machinery ID. Example: 1
     * @queryParam supplier_id int Filter by supplier ID. Example: 5
     * @queryParam status string Filter by status (draft, submitted, approved, locked, paid, rejected). Example: draft
     * @queryParam period_start date Filter by period start date (Y-m-d). Example: 2024-01-01
     * @queryParam period_end date Filter by period end date (Y-m-d). Example: 2024-01-31
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MachineryPaymentRequest::with(['machinery', 'supplier']);

            // Filters
            if ($request->has('machinery_id')) {
                $query->where('machinery_id', $request->machinery_id);
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('period_start')) {
                $query->where('period_start', '>=', $request->period_start);
            }

            if ($request->has('period_end')) {
                $query->where('period_end', '<=', $request->period_end);
            }

            $paymentRequests = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $paymentRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Request Details
     *
     * Returns detailed information about a specific payment request, including billing breakdown,
     * diesel recovery details, and all linked ledger entries.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     *
     * @authenticated
     */
    public function show($id): JsonResponse
    {
        try {
            $paymentRequest = MachineryPaymentRequest::with([
                'machinery',
                'supplier',
                'requester',
                'submitter',
                'approver',
                'payer'
            ])->findOrFail($id);

            // Get ledger entries for this payment request
            $ledgerEntries = MachineryLedger::where('payment_request_id', $id)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_request' => $paymentRequest,
                    'billing_summary' => [
                        'gross_amount' => $paymentRequest->gross_amount ?? $paymentRequest->net_payable,
                        'diesel_deduction' => $paymentRequest->diesel_deduction ?? 0,
                        'net_payable' => $paymentRequest->net_payable,
                        'calculation_method' => $paymentRequest->calculation_method ?? 'legacy'
                    ],
                    'breakdown' => [
                        'billing' => $paymentRequest->billing_breakdown,
                        'diesel' => $paymentRequest->diesel_breakdown
                    ],
                    'ledger_entries' => $ledgerEntries,
                    'workflow_status' => [
                        'current_status' => $paymentRequest->status,
                        'can_submit' => $paymentRequest->status === 'draft',
                        'can_approve' => $paymentRequest->status === 'submitted',
                        'can_mark_paid' => $paymentRequest->status === 'approved',
                        'is_final' => in_array($paymentRequest->status, ['rejected', 'paid'])
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Request
     *
     * Creates a new machinery payment request by aggregating unbilled ledger entries
     * for the specified machinery and period.
     *
     * @bodyParam machinery_id int required The ID of the machinery. Example: 1
     * @bodyParam supplier_id int required The ID of the supplier. Example: 5
     * @bodyParam period_start date required The start of the billing period (Y-m-d). Example: 2024-05-01
     * @bodyParam period_end date required The end of the billing period (Y-m-d). Example: 2024-05-31
     * @bodyParam idempotency_key string Optional unique key to prevent duplicate submissions. Example: req_12345
     *
     * @authenticated
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'machinery_id' => 'required|exists:machineries,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
                'idempotency_key' => 'nullable|string|max:64',
            ]);

            $paymentRequest = $this->service->createFromLedger(
                $validated['machinery_id'],
                $validated['supplier_id'],
                $validated['period_start'],
                $validated['period_end'],
                Auth::id(),
                $validated['idempotency_key'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment request created successfully',
                'data' => [
                    'payment_request' => $paymentRequest,
                    'billing_summary' => [
                        'gross_amount' => $paymentRequest->gross_amount ?? $paymentRequest->net_payable,
                        'diesel_deduction' => $paymentRequest->diesel_deduction ?? 0,
                        'net_payable' => $paymentRequest->net_payable,
                        'calculation_method' => $paymentRequest->calculation_method ?? 'legacy'
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit Request
     *
     * Submits a draft payment request for verification/approval.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     *
     * @authenticated
     */
    public function submit(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request submit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $this->service->submit($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Payment request submitted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve Request
     *
     * Approves the payment request, locks the billing period, and generates
     * the final accounting ledger entries.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     *
     * @authenticated
     */
    public function approve(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request approve')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $this->service->approve($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Payment request approved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark as Paid (Legacy)
     *
     * Legacy endpoint to manually mark a request as paid.
     * Use `create-erp-payment` for the actual integrated flow.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     *
     * @authenticated
     */
    public function pay(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request pay')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $this->service->markAsPaid($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Payment request marked as paid successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payment request as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject Request
     *
     * Rejects the payment request and reverses any linked ledger entries.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam reason string required The reason for rejection. Example: Calculation mismatch.
     *
     * @authenticated
     */
    public function reject(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request reject')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:1000'
            ]);

            $this->service->reject($id, Auth::id(), $validated['reason']);

            return response()->json([
                'success' => true,
                'message' => 'Payment request rejected successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lock Request
     *
     * Freezes the payment request for final payment processing.
     * No further modifications to financial data are allowed after locking.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     *
     * @authenticated
     */
    public function lock(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request lock')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $this->service->lock($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Payment request locked successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to lock payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force Reject (Admin)
     *
     * Emergency admin override to reject a request regardless of current status.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam override_reason string required Reason for manual override. Example: Accidental approval.
     *
     * @authenticated
     */
    public function forceReject(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request force-reject')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'override_reason' => 'required|string',
            ]);

            $this->service->forceReject($id, Auth::id(), $validated['override_reason']);

            return response()->json([
                'success' => true,
                'message' => 'Payment request force rejected (admin override)',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to force reject payment request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force Unlock (Admin)
     *
     * Emergency admin override to unlock a frozen billing period.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam override_reason string required Reason for manual override. Example: Need to add late DPR entries.
     *
     * @authenticated
     */
    public function forceUnlock(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request force-unlock')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'override_reason' => 'required|string',
            ]);

            $this->service->forceUnlockPeriod($id, Auth::id(), $validated['override_reason']);

            return response()->json([
                'success' => true,
                'message' => 'Period force unlocked (admin override)',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to force unlock period',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add Override Note (Admin)
     *
     * Attaches an audit note to the payment request for tracking manual interventions.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam note string required The audit note content. Example: Manually adjusted for site discrepancy.
     *
     * @authenticated
     */
    public function addOverrideNote(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request override-note')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'note' => 'required|string',
            ]);

            $this->service->addOverrideNote($id, Auth::id(), $validated['note']);

            return response()->json([
                'success' => true,
                'message' => 'Override note added',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add override note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Invoice
     *
     * Uploads the supplier invoice document for the payment request.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam invoice_file file required The invoice document (PDF, JPG, PNG).
     *
     * @authenticated
     */
    public function uploadInvoice(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $request->validate([
                'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);

            if ($request->hasFile('invoice_file')) {
                $path = $this->service->uploadInvoice($id, $request->file('invoice_file'));

                return response()->json([
                    'success' => true,
                    'message' => 'Invoice uploaded successfully',
                    'path' => $path
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ERP Payment
     *
     * Triggers the integrated ERP payment flow. Creates a record in the `payments_module`
     * and handles payment proof file upload.
     *
     * @urlParam id int required The ID of the payment request. Example: 1
     * @bodyParam payment_date date required The date of payment. Example: 2024-06-01
     * @bodyParam amount number required The payment amount. Example: 15000.50
     * @bodyParam payment_mode string required The mode of payment (bank_transfer, cash, cheque, upi). Example: bank_transfer
     * @bodyParam remarks string Optional payment remarks. Example: Paid via HDFC bank.
     * @bodyParam payment_proof file required The payment confirmation document (PDF, JPG, PNG).
     *
     * @authenticated
     */
    public function createErpPayment(Request $request, int $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('machinery-payment-request pay')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_mode' => 'required|in:bank_transfer,cash,cheque,upi',
                'remarks' => 'nullable|string|max:1000',
                'payment_proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $paymentRequest = MachineryPaymentRequest::with('machinery')->findOrFail($id);

            // Validate that request is in locked status
            if ($paymentRequest->status !== 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment request must be in locked status to create ERP payment'
                ], 422);
            }

            // Use the machinery payment integration service
            $integrationService = app(\App\Services\ERPIntegration\MachineryPaymentIntegrationService::class);

            // Handle payment proof file upload
            $paymentProofPath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = 'mach_payment_' . $paymentRequest->id . '_' . time() . '.' . $file->getClientOriginalExtension();

                $uploadPath = public_path('uploads/payment_proofs');
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $file->move($uploadPath, $filename);
                $paymentProofPath = 'payment_proofs/' . $filename;
            }

            $paymentData = [
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_mode' => $validated['payment_mode'],
                'remarks' => $validated['remarks'] ?? '',
                'payment_proof_file' => $paymentProofPath,
            ];

            // Create the ERP payment
            $result = $integrationService->createPayment($paymentRequest, $paymentData, false);

            if ($result['success']) {
                // Calculate settlement status
                $totalPosted = $paymentRequest->payments()->sum('amount');
                $remainingBalance = $paymentRequest->net_payable - $totalPosted;

                $settlementStatus = match(true) {
                    $remainingBalance <= 0 => 'paid',
                    $totalPosted > 0 => 'partial',
                    default => 'unpaid'
                };

                return response()->json([
                    'success' => true,
                    'message' => 'ERP payment created successfully',
                    'payment_id' => $result['payment_id'],
                    'payment_number' => $result['payment_number'],
                    'amount' => $result['amount'],
                    'voucher_id' => $result['voucher_id'],
                    'settlement_status' => $settlementStatus,
                    'created_by' => auth()->user()->name ?? 'System',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ERP payment: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the ERP payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Available Machinery
     *
     * Returns a list of rental machinery that currently have unbilled ledger entries
     * available for creating a new payment request.
     *
     * @authenticated
     */
    public function getAvailableMachinery(Request $request): JsonResponse
    {
        try {
            $machinery = Machinery::with(['supplier'])
                ->where('owned_by', 'rental') // Only rental machinery needs payment requests
                ->where('status', 'active')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $machinery
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch machinery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview Calculation
     *
     * Generates a real-time billing and diesel deduction preview for a selected
     * machinery and date range. This does not persist any data.
     *
     * @bodyParam machinery_id int required The ID of the machinery. Example: 1
     * @bodyParam period_start date required The start of the period (Y-m-d). Example: 2024-05-01
     * @bodyParam period_end date required The end of the period (Y-m-d). Example: 2024-05-31
     *
     * @authenticated
     */
    public function previewCalculation(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'machinery_id' => 'required|exists:machineries,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
            ]);

            $machinery = Machinery::findOrFail($validated['machinery_id']);

            // Get DPRs for the period
            $dprs = \App\Models\DailyProgressReport::where('machinery_id', $machinery->id)
                ->whereBetween('date', [$validated['period_start'], $validated['period_end']])
                ->get();

            // Calculate billing preview
            $billingResult = \App\Services\MachineryBillingCalculatorService::calculate(
                $machinery,
                $dprs,
                \Carbon\Carbon::parse($validated['period_start']),
                \Carbon\Carbon::parse($validated['period_end'])
            );

            // Calculate diesel deduction
            $dieselResult = \App\Services\MachineryDieselAdjustmentService::calculateDieselDeduction(
                $machinery,
                \Carbon\Carbon::parse($validated['period_start']),
                \Carbon\Carbon::parse($validated['period_end'])
            );

            $grossAmount = $billingResult['gross_amount'];
            $dieselDeduction = $dieselResult['applicable_for_deduction'] ? $dieselResult['total_cost'] : 0;
            $netPayable = $grossAmount - $dieselDeduction;

            return response()->json([
                'success' => true,
                'data' => [
                    'machinery' => $machinery,
                    'period' => [
                        'start' => $validated['period_start'],
                        'end' => $validated['period_end']
                    ],
                    'billing_breakdown' => $billingResult,
                    'diesel_breakdown' => $dieselResult,
                    'calculation_summary' => [
                        'gross_amount' => $grossAmount,
                        'diesel_deduction' => $dieselDeduction,
                        'net_payable' => $netPayable,
                        'calculation_method' => $billingResult['calculation_type']
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview calculation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
