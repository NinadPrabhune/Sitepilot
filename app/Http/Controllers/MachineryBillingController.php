<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Services\BillGroupingService;
use App\Services\MachineryPaymentService;
use App\Services\MonthlyLockService;
use App\Models\MachineryBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineryBillingController extends Controller
{
    protected BillingService $billingService;
    protected BillGroupingService $groupingService;
    protected MachineryPaymentService $paymentService;
    protected MonthlyLockService $lockService;

    public function __construct(
        BillingService $billingService,
        BillGroupingService $groupingService,
        MachineryPaymentService $paymentService,
        MonthlyLockService $lockService
    ) {
        $this->billingService = $billingService;
        $this->groupingService = $groupingService;
        $this->paymentService = $paymentService;
        $this->lockService = $lockService;
    }

    /**
     * Billing index page
     */
    public function index(Request $request)
    {
        $workspaceId = Auth::user()->workspace_id;
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked to view billing");
        }

        $bills = MachineryBill::where('workspace_id', $workspaceId)
            ->whereMonth('from_date', $month)
            ->whereYear('from_date', $year)
            ->with(['supplier', 'billingItems.machinery'])
            ->orderBy('created_at', 'desc')
            ->get();

        $unbilledSummary = $this->groupingService->getUnbilledSummary($month, $year, $workspaceId);

        return view('machinery.billing.index', compact(
            'bills',
            'unbilledSummary',
            'month',
            'year'
        ));
    }

    /**
     * Create billing page
     */
    public function create(Request $request)
    {
        $workspaceId = Auth::user()->workspace_id;
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked to create billing");
        }

        $unbilledSummary = $this->groupingService->getUnbilledSummary($month, $year, $workspaceId);

        return view('machinery.billing.create', compact(
            'unbilledSummary',
            'month',
            'year'
        ));
    }

    /**
     * Review billing before creation
     */
    public function review(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'supplier_ids' => 'required|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked to review billing");
        }

        // Get billing items for selected suppliers
        $billingItems = [];
        foreach ($validated['supplier_ids'] as $supplierId) {
            $items = MachineryBillingItem::where('workspace_id', $workspaceId)
                ->whereMonth('from_date', $month)
                ->whereYear('from_date', $year)
                ->where('supplier_id', $supplierId)
                ->whereNull('bill_id')
                ->with(['machinery', 'supplier'])
                ->get();

            if ($items->isNotEmpty()) {
                $billingItems[$supplierId] = [
                    'supplier' => $items->first()->supplier,
                    'items' => $items,
                    'total_amount' => $items->sum('amount'),
                    'total_hours' => $items->sum('total_hours'),
                    'total_diesel' => $items->sum('total_diesel'),
                ];
            }
        }

        return view('machinery.billing.review', compact(
            'billingItems',
            'month',
            'year'
        ));
    }

    /**
     * Store billing (group items into bills)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'supplier_ids' => 'required|array',
            'supplier_ids.*' => 'exists:suppliers,id',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked to create billing");
        }

        try {
            $bills = $this->groupingService->groupBySupplier($month, $year, $workspaceId);

            // Add remarks to bills
            if (!empty($validated['remarks'])) {
                foreach ($bills as $bill) {
                    $bill->update(['remarks' => $validated['remarks']]);
                }
            }

            return redirect()->route('machinery.billing.index', ['month' => $month, 'year' => $year])
                ->with('success', "Created {$bills->count()} bills successfully");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show bill details
     */
    public function show($id)
    {
        $workspaceId = Auth::user()->workspace_id;
        
        $bill = MachineryBill::where('workspace_id', $workspaceId)
            ->with(['supplier', 'billingItems.machinery', 'createdBy', 'approvedBy'])
            ->findOrFail($id);

        return view('machinery.billing.show', compact('bill'));
    }

    /**
     * Get billing items for supplier (AJAX)
     */
    public function getBillingItems(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];
        $supplierId = $validated['supplier_id'];

        $items = MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereMonth('from_date', $month)
            ->whereYear('from_date', $year)
            ->where('supplier_id', $supplierId)
            ->whereNull('bill_id')
            ->with(['machinery'])
            ->get();

        return response()->json([
            'items' => $items,
            'total_amount' => $items->sum('amount'),
            'total_hours' => $items->sum('total_hours'),
            'total_diesel' => $items->sum('total_diesel'),
        ]);
    }

    /**
     * Delete bill (only draft status)
     */
    public function destroy($id)
    {
        $workspaceId = Auth::user()->workspace_id;
        
        $bill = MachineryBill::where('workspace_id', $workspaceId)
            ->where('status', 'draft')
            ->findOrFail($id);

        try {
            // Ungroup items first
            $this->groupingService->ungroupItems([$id], $workspaceId);
            
            // Delete bill
            $bill->delete();

            return back()->with('success', 'Bill deleted successfully');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
