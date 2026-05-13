<?php

namespace App\Http\Controllers;

use App\Models\SupplierAdvance;
use App\Models\PurchaseOrder;
use App\Services\SupplierAdvanceService;
use App\Services\AdvanceAllocationService;
use App\Services\InvoiceAdvanceService;
use App\DataTables\SupplierAdvanceDataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierAdvanceController extends Controller
{
    protected SupplierAdvanceService $advanceService;
    protected AdvanceAllocationService $allocationService;
    protected InvoiceAdvanceService $invoiceAdvanceService;

    public function __construct(
        SupplierAdvanceService $advanceService,
        AdvanceAllocationService $allocationService,
        InvoiceAdvanceService $invoiceAdvanceService
    ) {
        $this->advanceService = $advanceService;
        $this->allocationService = $allocationService;
        $this->invoiceAdvanceService = $invoiceAdvanceService;
    }

    /**
     * Display a listing of supplier advances.
     */
    public function index(Request $request, SupplierAdvanceDataTable $dataTable)
    {
        // Calculate KPI cards
        $totalAdvances = SupplierAdvance::sum('amount');
        $availableBalance = SupplierAdvance::paid()
            ->get()
            ->sum(function ($advance) {
                return $advance->getAvailableBalanceAttribute();
            });
        $utilizedAmount = SupplierAdvance::sum('utilized_amount');
        $pendingApproval = SupplierAdvance::where('status', SupplierAdvance::STATUS_PENDING)->count();

        return $dataTable->render('supplier-advance.index', compact(
            'totalAdvances',
            'availableBalance',
            'utilizedAmount',
            'pendingApproval'
        ));
    }

    /**
     * Display the specified advance.
     */
    public function show($id)
    {
        $advance = SupplierAdvance::with(['supplier', 'po', 'site', 'utilizations.invoice', 'auditLogs.creator'])
            ->findOrFail($id);

        return view('supplier-advance.show', compact('advance'));
    }

    /**
     * Show advance request form from PO.
     */
    public function createFromPO($poId)
    {
        $po = PurchaseOrder::with('supplier')->findOrFail($poId);

        if ($po->status !== PurchaseOrder::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'Advance can only be requested for approved POs');
        }

        // Check if advance already exists for this PO
        $existingAdvance = SupplierAdvance::where('po_id', $poId)->first();
        if ($existingAdvance) {
            return redirect()->route('supplier-advance.show', $existingAdvance->id)
                ->with('info', 'Advance already exists for this PO');
        }

        return view('supplier-advance.create-from-po', compact('po'));
    }

    /**
     * Store a newly created advance request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'po_id' => 'required|exists:purchase_orders,id',
            'amount' => 'required|numeric|min:0',
            'advance_date' => 'required|date',
            'source' => 'required|in:po,manual',
            'remarks' => 'nullable|string',
        ]);

        try {
            $advance = $this->advanceService->createAdvance(
                $request->po_id,
                $request->amount,
                $request->only(['advance_date', 'source', 'remarks'])
            );

            return redirect()->route('supplier-advance.show', $advance->id)
                ->with('success', 'Advance request created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create advance request', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create advance request: ' . $e->getMessage());
        }
    }

    /**
     * Approve an advance request.
     */
    public function approve($id)
    {
        try {
            $advance = $this->advanceService->approveAdvance($id, auth()->id());

            return redirect()->route('supplier-advance.show', $advance->id)
                ->with('success', 'Advance approved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to approve advance', [
                'advance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to approve advance: ' . $e->getMessage());
        }
    }

    /**
     * Reject an advance request.
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        try {
            $advance = SupplierAdvance::findOrFail($id);

            if ($advance->status !== SupplierAdvance::STATUS_PENDING) {
                return redirect()->back()->with('error', 'Can only reject pending advances');
            }

            $advance->update([
                'status' => SupplierAdvance::STATUS_CANCELLED,
                'rejection_reason' => $request->rejection_reason,
            ]);

            Log::info('Advance rejected', [
                'advance_id' => $id,
                'reason' => $request->rejection_reason,
                'rejected_by' => auth()->id(),
            ]);

            return redirect()->route('supplier-advance.show', $advance->id)
                ->with('success', 'Advance rejected successfully');
        } catch (\Exception $e) {
            Log::error('Failed to reject advance', [
                'advance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to reject advance: ' . $e->getMessage());
        }
    }

    /**
     * Show payment recording form.
     */
    public function showPaymentForm($id)
    {
        $advance = SupplierAdvance::with(['supplier', 'po'])->findOrFail($id);

        if ($advance->status !== SupplierAdvance::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'Payment can only be recorded for approved advances');
        }

        return view('supplier-advance.payment-form', compact('advance'));
    }

    /**
     * Record payment for an advance.
     */
    public function recordPayment(Request $request, $id)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'payment_mode' => 'required|string',
            'reference_number' => 'nullable|string',
            'payment_proof_file' => 'nullable|string',
        ]);

        try {
            $advance = $this->advanceService->recordAdvancePayment($id, $request->all());

            return redirect()->route('supplier-advance.show', $advance->id)
                ->with('success', 'Payment recorded successfully');
        } catch (\Exception $e) {
            Log::error('Failed to record advance payment', [
                'advance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified advance (only if pending/cancelled).
     */
    public function destroy($id)
    {
        try {
            $advance = SupplierAdvance::findOrFail($id);

            if ($advance->status === SupplierAdvance::STATUS_PAID) {
                return redirect()->back()->with('error', 'Cannot delete paid advances');
            }

            $advance->delete();

            Log::info('Advance deleted', [
                'advance_id' => $id,
                'deleted_by' => auth()->id(),
            ]);

            return redirect()->route('supplier-advance.index')
                ->with('success', 'Advance deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete advance', [
                'advance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete advance: ' . $e->getMessage());
        }
    }

    /**
     * Show timeline view for advance allocation.
     */
    public function timeline($id)
    {
        $advance = SupplierAdvance::with(['utilizations.invoice', 'auditLogs.creator'])
            ->findOrFail($id);

        return view('supplier-advance.timeline', compact('advance'));
    }
}
