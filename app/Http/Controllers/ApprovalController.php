<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Models\DailyProgressReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Display pending approvals list
     */
    public function index()
    {
        if (!Auth::user()->isAbleTo('machinery-payment-requests manage')) {
            abort(403, 'Unauthorized action.');
        }

        $pendingRequests = MachineryPaymentRequest::with(['machinery', 'supplier'])
            ->whereIn('status', ['pending', 'site_approved', 'pm_approved', 'admin_approved'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('approvals.index', compact('pendingRequests'));
    }

    /**
     * Approve a payment request
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        // Lock the payment request row to prevent race conditions
        $paymentRequest = MachineryPaymentRequest::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        // Check if user can approve at current level
        $userRole = Auth::user()->roles->first()->name ?? null;
        $currentStatus = $paymentRequest->status;

        // Status check to prevent double approval
        if (!in_array($currentStatus, ['pending', 'site_approved', 'pm_approved', 'admin_approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request has already been processed.',
            ], 400);
        }

        $validTransitions = [
            'site_engineer' => ['pending' => 'site_approved'],
            'pm' => ['site_approved' => 'pm_approved'],
            'admin' => ['pm_approved' => 'admin_approved'],
            'accounts' => ['admin_approved' => 'accounts_approved'],
        ];

        if (!isset($validTransitions[$userRole]) || !isset($validTransitions[$userRole][$currentStatus])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid approval transition for your role.',
            ], 403);
        }

        $newStatus = $validTransitions[$userRole][$currentStatus];

        $paymentRequest->update([
            'status' => $newStatus,
            'remarks' => $validated['remarks'] ?? $paymentRequest->remarks,
        ]);

        \Log::info('approval.approved', [
            'event' => 'payment.request.approved',
            'payment_request_id' => $paymentRequest->id,
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
            'role' => $userRole,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ]);

        // Add approval history
        $paymentRequest->approval_history = array_merge($paymentRequest->approval_history ?? [], [
            [
                'role' => $userRole,
                'user_id' => Auth::id(),
                'action' => 'approved',
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'remarks' => $validated['remarks'] ?? null,
                'timestamp' => now()->toISOString(),
            ]
        ]);
        $paymentRequest->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment request approved successfully.',
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Reject a payment request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Lock the payment request row to prevent race conditions
        $paymentRequest = MachineryPaymentRequest::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        // Status check to prevent double rejection
        if ($paymentRequest->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Payment request has already been rejected.',
            ], 400);
        }

        $paymentRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
        ]);

        \Log::info('approval.rejected', [
            'event' => 'payment.request.rejected',
            'payment_request_id' => $paymentRequest->id,
            'reason' => $validated['reason'],
            'role' => Auth::user()->roles->first()->name ?? null,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ]);

        // Add approval history
        $paymentRequest->approval_history = array_merge($paymentRequest->approval_history ?? [], [
            [
                'role' => Auth::user()->roles->first()->name ?? null,
                'user_id' => Auth::id(),
                'action' => 'rejected',
                'from_status' => $paymentRequest->status,
                'to_status' => 'rejected',
                'reason' => $validated['reason'],
                'timestamp' => now()->toISOString(),
            ]
        ]);
        $paymentRequest->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment request rejected successfully.',
        ]);
    }

    /**
     * Approve a DPR (Daily Progress Report)
     * Ledger entry is created ONLY at this approval stage
     */
    public function approveDPR(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        // Lock the DPR row to prevent race conditions
        $dpr = DailyProgressReport::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        // Check if already approved
        if ($dpr->ledger_entry_id) {
            return response()->json([
                'success' => false,
                'message' => 'DPR has already been approved and ledger entry created.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create CREDIT ledger entry for work done
            $ledgerEntry = MachineryLedgerService::createCredit([
                'machinery_id' => $dpr->machinery_id,
                'amount' => $dpr->calculated_amount,
                'reference_type' => MachineryLedgerService::REFERENCE_TYPE_DPR,
                'reference_id' => $dpr->id,
                'entry_type' => MachineryLedgerService::ENTRY_TYPE_READING,
                'date' => $dpr->date,
                'description' => "DPR work approved - {$dpr->billable_hours} hrs",
                'metadata' => [
                    'dpr_id' => $dpr->id,
                    'billable_hours' => $dpr->billable_hours,
                    'approved_by' => Auth::id(),
                    'site_id' => $dpr->site_id,
                ],
            ]);

            // Hard enforcement: verify ledger amount matches calculated amount
            if (abs($ledgerEntry->amount - $dpr->calculated_amount) > 0.01) {
                throw new \RuntimeException("Ledger enforcement failed: DPR credit amount mismatch. Calculated ₹{$dpr->calculated_amount} vs Ledger ₹{$ledgerEntry->amount}. Cannot proceed.");
            }

            // Link ledger entry to DPR and mark as approved
            $dpr->update([
                'ledger_entry_id' => $ledgerEntry->id,
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            \Log::info('dpr.approved', [
                'event' => 'dpr.approved',
                'dpr_id' => $dpr->id,
                'ledger_entry_id' => $ledgerEntry->id,
                'approved_by' => Auth::id(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DPR approved and ledger entry created successfully.',
                'ledger_entry_id' => $ledgerEntry->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DPR approval failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve DPR: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a DPR
     */
    public function rejectDPR(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $dpr = DailyProgressReport::findOrFail($id);

        if ($dpr->ledger_entry_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject DPR after ledger entry has been created.',
            ], 400);
        }

        $dpr->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['remarks'],
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'DPR rejected successfully.',
        ]);
    }
}
