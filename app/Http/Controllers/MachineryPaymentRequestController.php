<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DataTables\MachineryPaymentRequestDataTable;

class MachineryPaymentRequestController extends Controller
{
    protected MachineryPaymentRequestService $service;
    
    public function __construct(MachineryPaymentRequestService $service)
    {
        $this->service = $service;
    }
    
    /**
     * Web: Index page
     */
    public function index(Request $request, MachineryPaymentRequestDataTable $dataTable)
    {
        $machineries = Machinery::all();
        
        if ($request->ajax()) {
            return $dataTable->ajax();
        }
        
        return $dataTable->render('machinery-payment.index', compact('machineries'));
    }
    
    /**
     * Web: Create page
     */
    public function create()
    {
        $machineries = Machinery::all();
        $suppliers = Supplier::all();
        return view('machinery-payment.create', compact('machineries', 'suppliers'));
    }
    
    /**
     * Web: Show page
     */
    public function show($id)
    {
        $paymentRequest = MachineryPaymentRequest::with([
            'machinery', 
            'supplier', 
            'period', 
            'payments.creator',
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
        
        return view('machinery-payment.show', compact('paymentRequest', 'ledgerEntries'));
    }
    
    /**
     * Create payment request from ledger
     */
    public function store(Request $request): JsonResponse
    {
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
                auth()->id(),
                $validated['idempotency_key'] ?? uniqid('payment_', true)
            );
            
            return response()->json([
                'success' => true,
                'data' => $paymentRequest->load('machinery', 'supplier')->toArray(),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * API: List payment requests
     */
    public function apiIndex(Request $request)
    {
        $query = MachineryPaymentRequest::with(['machinery', 'supplier', 'period']);
        
        if ($request->has('machinery_id')) {
            $query->where('machinery_id', $request->machinery_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('workspace_id')) {
            $query->where('workspace_id', $request->workspace_id);
        }
        
        $paymentRequests = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
        
        return response()->json([
            'success' => true,
            'data' => $paymentRequests->items(),
            'recordsTotal' => $paymentRequests->total(),
            'recordsFiltered' => $paymentRequests->total(),
            'draw' => $request->get('draw', 0),
        ]);
    }
    
    /**
     * API: Show payment request details
     */
    public function apiShow(int $id): JsonResponse
    {
        $paymentRequest = MachineryPaymentRequest::with(['machinery', 'supplier', 'period', 'ledgerEntries'])
            ->findOrFail($id);
        
        $data = $paymentRequest->toArray();
        $data['period_start'] = \Carbon\Carbon::parse($paymentRequest->period_start)->format('d M Y');
        $data['period_end'] = \Carbon\Carbon::parse($paymentRequest->period_end)->format('d M Y');
        
        // Format calculation_timestamp in audit_snapshot if it exists
        if (isset($data['audit_snapshot']['calculation_timestamp'])) {
            $data['audit_snapshot']['calculation_timestamp'] = \Carbon\Carbon::parse($data['audit_snapshot']['calculation_timestamp'])->format('d M Y, h:i A');
        }
        
        // Format dates in entry_details if they exist
        if (isset($data['audit_snapshot']['entry_details'])) {
            foreach ($data['audit_snapshot']['entry_details'] as &$entry) {
                if (isset($entry['date'])) {
                    $entry['date'] = \Carbon\Carbon::parse($entry['date'])->format('d M Y');
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    
    /**
     * Submit payment request
     */
    public function submit(int $id): JsonResponse
    {
        $this->service->submit($id, auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request submitted successfully',
        ]);
    }
    
    /**
     * Verify payment request
     */
    public function verify(int $id): JsonResponse
    {
        $this->service->verify($id, auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request verified successfully',
        ]);
    }
    
    /**
     * Approve payment request
     */
    public function approve(int $id): JsonResponse
    {
        $this->service->approve($id, auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request approved successfully',
        ]);
    }
    
    /**
     * Lock payment request
     */
    public function lock(int $id): JsonResponse
    {
        $this->service->lock($id, auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request locked successfully',
        ]);
    }
    
    /**
     * Mark payment request as paid
     */
    public function pay(int $id): JsonResponse
    {
        $this->service->markAsPaid($id, auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request marked as paid successfully',
        ]);
    }
    
    /**
     * Reject payment request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);
        
        $this->service->reject($id, auth()->id(), $validated['reason'] ?? null);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request rejected successfully',
        ]);
    }
    
    /**
     * Debug endpoint for payment request validation
     * Returns detailed breakdown for debugging
     */
    public function debug(int $id): JsonResponse
    {
        $paymentRequest = MachineryPaymentRequest::with(['machinery', 'supplier', 'period', 'ledgerEntries'])
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
        
        // Recalculate hash (CRITICAL: must sort before hashing for deterministic result)
        $sortedEntries = $ledgerEntries->sortBy(['date', 'id']);
        $currentHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
            'id' => $e->id,
            'date' => $e->date,
            'amount' => $e->amount,
            'entry_direction' => $e->entry_direction,
            'entry_type' => $e->entry_type,
        ])->toArray()));
        
        return response()->json([
            'success' => true,
            'data' => [
                'payment_request' => [
                    'id' => $paymentRequest->id,
                    'status' => $paymentRequest->status,
                    'period_start' => \Carbon\Carbon::parse($paymentRequest->period_start)->format('d M Y'),
                    'period_end' => \Carbon\Carbon::parse($paymentRequest->period_end)->format('d M Y'),
                    'machinery_id' => $paymentRequest->machinery_id,
                    'supplier_id' => $paymentRequest->supplier_id,
                ],
                'calculation_snapshot' => [
                    'credits' => $paymentRequest->credits,
                    'debits' => $paymentRequest->debits,
                    'net_payable' => $paymentRequest->net_payable,
                    'entries_hash' => $auditSnapshot['entries_hash'] ?? null,
                    'calculation_version' => $auditSnapshot['calculation_version'] ?? null,
                    'calculation_timestamp' => $auditSnapshot['calculation_timestamp'] ?? null,
                ],
                'current_calculation' => [
                    'credits' => $currentCredits,
                    'debits' => $currentDebits,
                    'net_payable' => $currentNetPayable,
                    'entries_hash' => $currentHash,
                ],
                'calculation_mismatch' => [
                    'credits_mismatch' => abs($currentCredits - $paymentRequest->credits) > 0.01,
                    'debits_mismatch' => abs($currentDebits - $paymentRequest->debits) > 0.01,
                    'net_payable_mismatch' => abs($currentNetPayable - $paymentRequest->net_payable) > 0.01,
                    'hash_mismatch' => $currentHash !== ($paymentRequest->audit_snapshot['entries_hash'] ?? null),
                ],
                'ledger_entries' => [
                    'count' => $ledgerEntries->count(),
                    'ids' => $ledgerEntryIds,
                    'sample' => $ledgerEntries->take(5)->map(fn($e) => [
                        'id' => $e->id,
                        'date' => \Carbon\Carbon::parse($e->date)->format('d M Y'),
                        'direction' => $e->entry_direction,
                        'type' => $e->entry_type,
                        'amount' => $e->amount,
                        'payment_request_id' => $e->payment_request_id,
                    ]),
                ],
            ],
        ]);
    }
    
    /**
     * Recalculate endpoint for pre-approval validation
     * Returns diff between original and current calculation
     */
    public function recalculate(int $id): JsonResponse
    {
        $paymentRequest = MachineryPaymentRequest::with(['machinery', 'supplier', 'period'])
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
        
        // Calculate diffs
        $creditsDiff = $currentCredits - $paymentRequest->credits;
        $debitsDiff = $currentDebits - $paymentRequest->debits;
        $netPayableDiff = $currentNetPayable - $paymentRequest->net_payable;
        
        return response()->json([
            'success' => true,
            'data' => [
                'original' => [
                    'credits' => $paymentRequest->credits,
                    'debits' => $paymentRequest->debits,
                    'net_payable' => $paymentRequest->net_payable,
                ],
                'current' => [
                    'credits' => $currentCredits,
                    'debits' => $currentDebits,
                    'net_payable' => $currentNetPayable,
                ],
                'diff' => [
                    'credits' => $creditsDiff,
                    'debits' => $debitsDiff,
                    'net_payable' => $netPayableDiff,
                ],
                'has_mismatch' => abs($netPayableDiff) > 0.01,
                'can_approve' => abs($netPayableDiff) <= 0.01,
            ],
        ]);
    }
    
    /**
     * ADMIN: Force reject after approval
     */
    public function forceReject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'override_reason' => 'required|string',
        ]);
        
        $this->service->forceReject($id, auth()->id(), $validated['override_reason']);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment request force rejected (admin override)',
        ]);
    }
    
    /**
     * ADMIN: Force unlock period
     */
    public function forceUnlock(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'override_reason' => 'required|string',
        ]);
        
        $this->service->forceUnlockPeriod($id, auth()->id(), $validated['override_reason']);
        
        return response()->json([
            'success' => true,
            'message' => 'Period force unlocked (admin override)',
        ]);
    }
    
    /**
     * ADMIN: Add override note
     */
    public function addOverrideNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'note' => 'required|string',
        ]);
        
        $this->service->addOverrideNote($id, auth()->id(), $validated['note']);
        
        return response()->json([
            'success' => true,
            'message' => 'Override note added',
        ]);
    }

    /**
     * Upload invoice for payment request
     */
    public function uploadInvoice(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $paymentRequest = MachineryPaymentRequest::findOrFail($id);

        if ($request->hasFile('invoice_file')) {
            $file = $request->file('invoice_file');
            $filename = $paymentRequest->id . '_invoice_' . time() . '.' . $file->getClientOriginalExtension();

            $uploadPath = public_path('uploads/payment_invoices');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $file->move($uploadPath, $filename);

            $paymentRequest->update([
                'invoice_file' => 'payment_invoices/' . $filename,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice uploaded successfully'
        ]);
    }

    /**
     * Upload payment proof for payment request
     */
    public function uploadPaymentProof(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'payment_proof_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'payment_mode' => 'required|in:bank_transfer,cash,cheque,upi',
            'payment_date' => 'required|date',
            'payment_amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $paymentRequest = MachineryPaymentRequest::findOrFail($id);

        // Validate that request is in locked status
        if ($paymentRequest->status !== 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'Payment request must be in locked status to upload payment proof'
            ], 422);
        }

        // Handle payment proof file upload
        $paymentProofPath = null;
        if ($request->hasFile('payment_proof_file')) {
            $file = $request->file('payment_proof_file');
            $filename = 'mach_payment_proof_' . $paymentRequest->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            $uploadPath = public_path('uploads/payment_proofs');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $file->move($uploadPath, $filename);
            $paymentProofPath = 'payment_proofs/' . $filename;
        }

        // Create ERP payment record
        $paymentData = [
            'payment_date' => $request->payment_date,
            'amount' => $request->payment_amount,
            'payment_mode' => $request->payment_mode,
            'reference_number' => $request->reference_number,
            'notes' => $request->remarks,
            'payment_proff_file' => $paymentProofPath,
        ];

        // Use the machinery payment integration service
        $integrationService = app(\App\Services\ERPIntegration\MachineryPaymentIntegrationService::class);

        // Create the ERP payment
        $result = $integrationService->createPayment($paymentRequest, $paymentData, false);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment proof uploaded and ERP payment created successfully',
                'payment_id' => $result['payment_id'],
                'payment_number' => $result['payment_number'],
                'amount' => $result['amount'],
                'voucher_id' => $result['voucher_id'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ERP payment: ' . ($result['message'] ?? 'Unknown error')
            ], 500);
        }
    }

    /**
     * Debug endpoint to analyze ledger entries query
     * Helps identify why "No eligible ledger entries found" error occurs
     */
    public function debugLedgerQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machinery_id' => 'required|exists:machineries,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);
        
        $machineryId = $validated['machinery_id'];
        $periodStart = $validated['period_start'];
        $periodEnd = $validated['period_end'];
        
        // Get all entries for the machinery in the period (no filters)
        $allEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        
        // Apply the same filters as lockLedgerEntries()
        $eligibleEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_reversal', false)
            ->whereNull('payment_request_id')
            ->where('amount', '>', 0) // Exclude zero-amount entries
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        
        // Analyze filtering step by step
        $step1 = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();
            
        $step2 = $step1->where('is_reversal', false);
        
        $step3 = $step2->whereNull('payment_request_id');
        
        $step4 = $step3->where('amount', '>', 0);
        
        // Get entries that are filtered out at each step
        $reversalEntries = $step1->where('is_reversal', true);
        $alreadyLinkedEntries = $step2->whereNotNull('payment_request_id');
        $zeroAmountEntries = $step3->where('amount', '<=', 0);
        
        return response()->json([
            'success' => true,
            'data' => [
                'query_parameters' => [
                    'machinery_id' => $machineryId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ],
                'all_entries_in_period' => [
                    'count' => $allEntries->count(),
                    'entries' => $allEntries->map(fn($e) => [
                        'id' => $e->id,
                        'date' => $e->date,
                        'amount' => $e->amount,
                        'entry_direction' => $e->entry_direction,
                        'entry_type' => $e->entry_type,
                        'is_reversal' => $e->is_reversal,
                        'payment_request_id' => $e->payment_request_id,
                    ])->toArray(),
                ],
                'filtering_analysis' => [
                    'step1_all_in_period' => $step1->count(),
                    'step2_excluding_reversals' => $step2->count(),
                    'step3_excluding_linked' => $step3->count(),
                    'step4_excluding_zero_amount' => $step4->count(),
                    'final_eligible_count' => $eligibleEntries->count(),
                ],
                'filtered_out_entries' => [
                    'reversal_entries' => [
                        'count' => $reversalEntries->count(),
                        'entries' => $reversalEntries->map(fn($e) => [
                            'id' => $e->id,
                            'date' => $e->date,
                            'amount' => $e->amount,
                            'entry_direction' => $e->entry_direction,
                            'entry_type' => $e->entry_type,
                        ])->toArray(),
                    ],
                    'already_linked_entries' => [
                        'count' => $alreadyLinkedEntries->count(),
                        'entries' => $alreadyLinkedEntries->map(fn($e) => [
                            'id' => $e->id,
                            'date' => $e->date,
                            'amount' => $e->amount,
                            'entry_direction' => $e->entry_direction,
                            'entry_type' => $e->entry_type,
                            'payment_request_id' => $e->payment_request_id,
                        ])->toArray(),
                    ],
                    'zero_amount_entries' => [
                        'count' => $zeroAmountEntries->count(),
                        'entries' => $zeroAmountEntries->map(fn($e) => [
                            'id' => $e->id,
                            'date' => $e->date,
                            'amount' => $e->amount,
                            'entry_direction' => $e->entry_direction,
                            'entry_type' => $e->entry_type,
                        ])->toArray(),
                    ],
                ],
                'sql_query' => [
                    'raw_sql' => MachineryLedger::where('machinery_id', $machineryId)
                        ->whereBetween('date', [$periodStart, $periodEnd])
                        ->where('is_reversal', false)
                        ->whereNull('payment_request_id')
                        ->where('amount', '>', 0)
                        ->orderBy('date')
                        ->orderBy('id')
                        ->toSql(),
                    'bindings' => [
                        $machineryId,
                        $periodStart,
                        $periodEnd,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Show payment modal content
     */
    public function paymentModal(int $id)
    {
        $paymentRequest = MachineryPaymentRequest::with(['machinery', 'supplier'])->findOrFail($id);
        return view('machinery-payment.create-payment-modal', compact('paymentRequest'));
    }

    /**
     * Create ERP Payment for Machinery Payment Request
     * Phase B2: Real ERP Flow Implementation
     */
    public function createErpPayment(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_mode' => 'required|in:bank_transfer,cash,cheque,upi',
                'remarks' => 'nullable|string|max:1000',
                'payment_proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $paymentRequest = MachineryPaymentRequest::findOrFail($id);

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
            \Log::error('ERP Payment Creation Error', [
                'payment_request_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the ERP payment: ' . $e->getMessage()
            ], 500);
        }
    }
}
