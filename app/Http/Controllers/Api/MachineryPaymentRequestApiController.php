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

class MachineryPaymentRequestApiController extends Controller
{
    protected MachineryPaymentRequestService $service;
    
    public function __construct(MachineryPaymentRequestService $service)
    {
        $this->service = $service;
    }
    
    /**
     * List payment requests
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
     * Show payment request with breakdown
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
     * Create payment request from ledger
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
     * Submit payment request
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
     * Approve payment request
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
     * Mark payment request as paid
     */
    public function markPaid(Request $request, $id): JsonResponse
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
     * Reject payment request
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
            
            $this->service->reject($id, $validated['reason'], Auth::id());
            
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
     * Get available machinery for payment request creation
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
     * Get billing calculation preview
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
