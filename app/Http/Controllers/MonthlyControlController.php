<?php

namespace App\Http\Controllers;

use App\Models\MonthlyLock;
use App\Services\BillingService;
use App\Services\BillGroupingService;
use App\Services\MonthlyLockService;
use App\Models\WorkSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MonthlyControlController extends Controller
{
    protected BillingService $billingService;
    protected BillGroupingService $groupingService;
    protected MonthlyLockService $lockService;

    public function __construct(
        BillingService $billingService,
        BillGroupingService $groupingService,
        MonthlyLockService $lockService
    ) {
        $this->billingService = $billingService;
        $this->groupingService = $groupingService;
        $this->lockService = $lockService;
    }

    /**
     * Monthly control dashboard
     */
    public function index()
    {
        $workspaceId = Auth::user()->workspace_id;
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Get current month status
        $isLocked = $this->lockService->isLocked($currentMonth, $currentYear, $workspaceId);
        $lockDetails = $isLocked ? $this->lockService->getLock($currentMonth, $currentYear, $workspaceId) : null;

        // Get billing summary
        $billingSummary = $this->billingService->getBillingSummary($currentMonth, $currentYear, $workspaceId);
        $unbilledSummary = $this->groupingService->getUnbilledSummary($currentMonth, $currentYear, $workspaceId);

        // Get available months
        $months = $this->getAvailableMonths($workspaceId);
        $years = range(now()->year - 2, now()->year + 1);

        return view('machinery.monthly-control.index', compact(
            'currentMonth',
            'currentYear',
            'isLocked',
            'lockDetails',
            'billingSummary',
            'unbilledSummary',
            'months',
            'years'
        ));
    }

    /**
     * Lock month confirmation
     */
    public function lockConfirm(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        // Check if already locked
        if ($this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} is already locked");
        }

        // Get billing summary for confirmation
        $billingSummary = $this->billingService->getBillingSummary($month, $year, $workspaceId);
        $unbilledSummary = $this->groupingService->getUnbilledSummary($month, $year, $workspaceId);

        return view('machinery.monthly-control.lock-confirm', compact(
            'month',
            'year',
            'billingSummary',
            'unbilledSummary'
        ));
    }

    /**
     * Lock month
     */
    public function lock(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'remarks' => 'nullable|string|max:500',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        try {
            $lock = $this->lockService->lock($month, $year, $workspaceId, Auth::id());
            
            return redirect()->route('monthly-control.index')
                ->with('success', "Month {$month}/{$year} locked successfully");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Generate billing for locked month
     */
    public function generateBilling(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked before generating billing");
        }

        try {
            // Delete existing draft billing items
            $this->billingService->deleteBillingItems($month, $year, $workspaceId);
            
            // Generate new billing items
            $billingItems = $this->billingService->generate($month, $year, $workspaceId);
            
            return redirect()->route('monthly-control.index')
                ->with('success', "Generated {$billingItems->count()} billing items for {$month}/{$year}");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Group billing items into bills
     */
    public function groupBills(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        // Validate month is locked
        if (!$this->lockService->isLocked($month, $year, $workspaceId)) {
            return back()->with('error', "Month {$month}/{$year} must be locked before grouping bills");
        }

        try {
            $bills = $this->groupingService->groupBySupplier($month, $year, $workspaceId);
            
            return redirect()->route('monthly-control.index')
                ->with('success', "Created {$bills->count()} bills from billing items");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get available months list
     */
    private function getAvailableMonths(int $workspaceId): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'value' => $i,
                'label' => Carbon::create(null, $i, 1)->format('F'),
            ];
        }
        return $months;
    }

    /**
     * Check month status (AJAX)
     */
    public function checkStatus(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
        ]);

        $workspaceId = Auth::user()->workspace_id;
        $month = $validated['month'];
        $year = $validated['year'];

        $isLocked = $this->lockService->isLocked($month, $year, $workspaceId);
        $hasBillingItems = $this->billingService->getBillingSummary($month, $year, $workspaceId)['total_items'] > 0;
        $hasBills = $this->groupingService->hasUnbilledItems($month, $year, $workspaceId);

        return response()->json([
            'is_locked' => $isLocked,
            'has_billing_items' => $hasBillingItems,
            'has_unbilled_items' => $hasBills,
            'actions' => [
                'can_lock' => !$isLocked,
                'can_generate_billing' => $isLocked && !$hasBillingItems,
                'can_group_bills' => $isLocked && $hasBillingItems,
            ],
        ]);
    }
}
