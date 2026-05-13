<?php

namespace App\Services;

use App\Models\Indent;
use App\Models\PurchaseOrder;
use App\Models\Grn;
use App\Models\PurchaseInvoice;
use App\Models\Activity;
use App\Models\ActivityCompleted;
use App\Models\ManPowerMaster;
use App\Models\ManPowerDetail;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\MaterialTransfer;
use App\Models\Material;
use App\Models\Spent;
use Workdo\Taskly\Entities\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProjectDashboardService
{
    protected $project;
    protected $workspaceId;
    protected $cacheExpiry = 15; // minutes

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->workspaceId = getActiveWorkSpace();
    }

    /**
     * Get complete dashboard data for a project
     */
    public function getDashboardData(bool $forApi = false): array
    {
        $cacheKey = "project_dashboard_{$this->project->id}_{$this->workspaceId}";

        // Use cache for web view, not for API
        if (!$forApi && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $data = [
            'project' => $this->getProjectSummary(),
            'procurement' => [
                'indent' => $this->getIndentMetrics(),
                'po' => $this->getPurchaseOrderMetrics(),
                'grn' => $this->getGrnMetrics(),
                'invoice' => $this->getInvoiceMetrics(),
            ],
            'activities' => $this->getActivityMetrics(),
            'manpower' => $this->getManpowerMetrics(),
            'consumption' => $this->getConsumptionMetrics(),
            'transfers' => $this->getTransferMetrics(),
            'recent' => $this->getRecentData(),
            'charts' => $this->getChartData(),
        ];

        if (!$forApi) {
            Cache::put($cacheKey, $data, now()->addMinutes($this->cacheExpiry));
        }

        return $data;
    }

    /**
     * Clear dashboard cache for a project
     */
    public static function clearCache(int $projectId, int $workspaceId): void
    {
        Cache::forget("project_dashboard_{$projectId}_{$workspaceId}");
    }

    /**
     * Get project summary metrics
     */
    protected function getProjectSummary(): array
    {
        $startDate = Carbon::parse($this->project->start_date);
        $endDate = Carbon::parse($this->project->end_date);
        $today = Carbon::now();

        // Prevent division errors
        $totalProjectDays = max($startDate->diffInDays($endDate), 1);
        $daysPassed = min($startDate->diffInDays($today), $totalProjectDays);

        $timeProgress = round(($daysPassed / $totalProjectDays) * 100, 2);

        $daysLeft = $today->lt($endDate) ? $today->diffInDays($endDate) : 0;

        // 💰 Budget Calculation
        $totalSpent = PurchaseInvoice::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->sum('grand_total');

        // Add spent.amount from Spent model
        $spentAmount = Spent::where('project_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->sum('amount');

        $totalSpent += $spentAmount;

        $budget = (float) $this->project->budget;
        $remainingBudget = $budget - $totalSpent;

        $spentPercent = $budget > 0 
            ? round(($totalSpent / $budget) * 100, 2) 
            : 0;

        // 📊 Activity Progress
        $totalActivities = Activity::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->count();

        $completedActivities = Activity::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('status', 'completed')
            ->count();

        $activityProgress = $totalActivities > 0 
            ? round(($completedActivities / $totalActivities) * 100, 2) 
            : 0;

        // 🧠 OVERALL PROGRESS (Weighted)
        $overallProgress = round(
            ($activityProgress * 0.6) + 
            ($timeProgress * 0.2) + 
            ($spentPercent * 0.2),
            2
        );

        // ⚠️ Schedule Status
        $scheduleStatus = 'on_track';
        if ($activityProgress < $timeProgress - 10) {
            $scheduleStatus = 'delayed';
        } elseif ($activityProgress > $timeProgress + 10) {
            $scheduleStatus = 'ahead';
        }

        // 💰 Budget Status
        $budgetStatus = 'within_budget';
        if ($spentPercent > 100) {
            $budgetStatus = 'over_budget';
        } elseif ($spentPercent > 85) {
            $budgetStatus = 'near_limit';
        }

        // 🚨 Risk Level
        $riskLevel = 'low';

        if ($scheduleStatus === 'delayed' && $budgetStatus === 'over_budget') {
            $riskLevel = 'high';
        } elseif ($scheduleStatus === 'delayed' || $budgetStatus === 'near_limit') {
            $riskLevel = 'medium';
        }

        // 📊 Health Score (out of 100)
        $healthScore = round(
            (100 - abs($activityProgress - $timeProgress)) * 0.4 + 
            (100 - max(0, $spentPercent - 100)) * 0.3 + 
            $activityProgress * 0.3,
            2
        );

        return [
            'project_name' => $this->project->name,
            'project_status' => $this->project->status,

            'start_date' => $this->project->start_date,
            'end_date' => $this->project->end_date,
            'days_left' => $daysLeft,

            // 💰 Budget
            'budget' => $budget,
            'total_spent' => $totalSpent,
            'remaining_budget' => $remainingBudget,
            'spent_percent' => $spentPercent,
            'budget_status' => $budgetStatus,

            // 📊 Progress
            'activity_progress' => $activityProgress,
            'time_progress' => $timeProgress,
            'overall_progress' => $overallProgress,

            // ⚠️ Status Indicators
            'schedule_status' => $scheduleStatus,
            'risk_level' => $riskLevel,
            'health_score' => $healthScore,
        ];
    }

    /**
     * Get Indent metrics
     */
    protected function getIndentMetrics(): array
    {
        $today = Carbon::today();

        $baseQuery = Indent::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId);

        $totalIndent = (clone $baseQuery)->count();
        $openIndent = (clone $baseQuery)->where('status', 'Open')->count();
        $closedIndent = (clone $baseQuery)->where('status', 'Closed')->count();
        $todayIndent = (clone $baseQuery)->whereDate('created_at', $today)->count();

        return [
            'total_indent' => $totalIndent,
            'open_indent' => $openIndent,
            'closed_indent' => $closedIndent,
            'today_indent' => $todayIndent,
        ];
    }

    /**
     * Get Purchase Order metrics
     */
    protected function getPurchaseOrderMetrics(): array
    {
        $today = Carbon::today();

        $baseQuery = PurchaseOrder::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId);

        $totalPo = (clone $baseQuery)->count();
        $draftPo = (clone $baseQuery)->where('status', 'Draft')->count();
        $approvedPo = (clone $baseQuery)->where('status', 'Approved')->count();
        $pendingPo = (clone $baseQuery)->where('status', 'Pending')->count();
        $completedPo = (clone $baseQuery)->where('status', 'Completed')->count();
        $partialReceivedPo = (clone $baseQuery)->where('status', 'Partial Received')->count();
        $rejectedPo = (clone $baseQuery)->where('status', 'Rejected')->count();
        $flaggedPo = (clone $baseQuery)->where('status', 'Flagged')->count();
        $shortClosedPo = (clone $baseQuery)->where('status', 'Short Closed')->count();
        $todayPo = (clone $baseQuery)->whereDate('created_at', $today)->count();

        return [
            'total_po' => $totalPo,
            'draft_po' => $draftPo,
            'approved_po' => $approvedPo,
            'pending_po' => $pendingPo,
            'completed_po' => $completedPo,
            'partial_received_po' => $partialReceivedPo,
            'rejected_po' => $rejectedPo,
            'flagged_po' => $flaggedPo,
            'short_closed_po' => $shortClosedPo,
            'today_po' => $todayPo,
        ];
    }

    /**
     * Get GRN metrics
     */
    protected function getGrnMetrics(): array
    {
        $today = Carbon::today();

        $baseQuery = Grn::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId);

        $totalGrn = (clone $baseQuery)->count();
        $pendingGrn = (clone $baseQuery)->where('status', 'Pending')->count();
        $completedGrn = (clone $baseQuery)->where('status', 'Completed')->count();
        $partialGrn = (clone $baseQuery)->where('status', 'Partial')->count();
        $todayGrn = (clone $baseQuery)->whereDate('created_at', $today)->count();

        return [
            'total_grn' => $totalGrn,
            'pending_grn' => $pendingGrn,
            'completed_grn' => $completedGrn,
            'partial_grn' => $partialGrn,
            'today_grn' => $todayGrn,
        ];
    }

    /**
     * Get Invoice metrics
     */
    protected function getInvoiceMetrics(): array
    {
        $today = Carbon::today();

        $baseQuery = PurchaseInvoice::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId);

        $totalInvoice = (clone $baseQuery)->count();
        $paidInvoice = (clone $baseQuery)->whereRaw('LOWER(payment_status) = ?', ['paid'])->count();
        $unpaidInvoice = (clone $baseQuery)->whereRaw('LOWER(payment_status) = ?', ['unpaid'])->count();
        $partiallyPaidInvoice = (clone $baseQuery)->whereRaw('LOWER(payment_status) = ?', ['partially paid'])->count();
        
        // Overdue invoices (due date < today and unpaid)
        $overdueInvoice = (clone $baseQuery)
            ->whereRaw('LOWER(payment_status) = ?', ['unpaid'])
            ->where('invoice_date', '<', Carbon::now()->subDays(30))
            ->count();

        $todayInvoice = (clone $baseQuery)->whereDate('created_at', $today)->count();
        
        $totalInvoiceAmount = (clone $baseQuery)->sum('grand_total');

        return [
            'total_invoice' => $totalInvoice,
            'paid_invoice' => $paidInvoice,
            'unpaid_invoice' => $unpaidInvoice,
            'partially_paid_invoice' => $partiallyPaidInvoice,
            'overdue_invoice' => $overdueInvoice,
            'today_invoice' => $todayInvoice,
            'total_invoice_amount' => $totalInvoiceAmount,
        ];
    }

    /**
     * Get Activity metrics
     */
    protected function getActivityMetrics(): array
    {
        $baseQuery = Activity::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId);

        $totalActivities = (clone $baseQuery)->count();
        $completedActivities = (clone $baseQuery)->where('status', 'completed')->count();
        $pendingActivities = (clone $baseQuery)->whereIn('status', ['pending', 'in_progress'])->count();

        $overallActivityProgress = $totalActivities > 0 
            ? round(($completedActivities / $totalActivities) * 100, 2) 
            : 0;

        return [
            'total_activities' => $totalActivities,
            'completed_activities' => $completedActivities,
            'pending_activities' => $pendingActivities,
            'overall_activity_progress_percentage' => $overallActivityProgress,
        ];
    }

    /**
     * Get Manpower metrics
     */
    protected function getManpowerMetrics(): array
    {
        $today = Carbon::today();

        // Today's manpower records
        $manpowerToday = ManPowerMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->whereDate('work_date', $today)
            ->with(['details', 'supplier'])
            ->get();

        $totalWorkersToday = $manpowerToday->sum('total_count');
        $manpowerRecordsToday = $manpowerToday->count();

        // Total unique contractors (suppliers)
        $totalContractors = ManPowerMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->distinct('supplier_id')
            ->count('supplier_id');

        // Get manpower trend for last 7 days
        $manpowerTrend = ManPowerMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('work_date', '>=', Carbon::now()->subDays(7))
            ->select('work_date', DB::raw('SUM(total_count) as total_workers'))
            ->groupBy('work_date')
            ->orderBy('work_date')
            ->get();

        return [
            'total_workers_today' => $totalWorkersToday,
            'manpower_records_today' => $manpowerRecordsToday,
            'total_contractors' => $totalContractors,
            'manpower_trend' => $manpowerTrend,
        ];
    }

    /**
     * Get Material Consumption metrics
     */
    protected function getConsumptionMetrics(): array
    {
        $today = Carbon::today();

        // Today's consumption
        $consumptionToday = DailyConsumptionMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->whereDate('consumption_date', $today)
            ->with('details.material')
            ->get();

        $totalConsumptionToday = $consumptionToday->sum(function($master) {
            return $master->details->sum(function($detail) {
                $rate = $detail->material ? ($detail->material->price ?? 0) : 0;
                return $detail->quantity * $rate;
            });
        });

        // Top consumed materials (last 30 days)
        $topConsumedMaterials = DailyConsumptionDetails::whereHas('master', function($query) {
            $query->where('site_id', $this->project->id)
                ->where('workspace_id', $this->workspaceId)
                ->where('consumption_date', '>=', Carbon::now()->subDays(30));
        })
        ->select('material_id', DB::raw('SUM(quantity) as total_quantity'))
        ->groupBy('material_id')
        ->orderByDesc('total_quantity')
        ->limit(5)
        ->with('material')
        ->get()
        ->map(function($item) {
            // Calculate total amount from material rate if available
            $rate = $item->material ? ($item->material->price ?? 0) : 0;
            $item->total_amount = $item->total_quantity * $rate;
            return $item;
        });

        // Monthly consumption for chart
        $monthlyConsumption = DailyConsumptionMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('consumption_date', '>=', Carbon::now()->subMonths(6))
            ->with('details.material')
            ->get()
            ->groupBy(function($master) {
                return Carbon::parse($master->consumption_date)->format('Y-m');
            })
            ->map(function($group) {
                return $group->sum(function($master) {
                    return $master->details->sum(function($detail) {
                        $rate = $detail->material ? ($detail->material->purchase_price ?? 0) : 0;
                        return $detail->quantity * $rate;
                    });
                });
            });

        return [
            'total_consumption_today' => $totalConsumptionToday,
            'top_consumed_materials' => $topConsumedMaterials,
            'monthly_consumption' => $monthlyConsumption,
        ];
    }

    /**
     * Get Material Transfer metrics
     */
    protected function getTransferMetrics(): array
    {
        // Materials transferred OUT from this project
        $transferredOut = MaterialTransfer::where('from_site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->count();

        // Materials transferred IN to this project
        $transferredIn = MaterialTransfer::where('to_site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->count();

        return [
            'materials_transferred_out' => $transferredOut,
            'materials_transferred_in' => $transferredIn,
        ];
    }

    /**
     * Get recent data (limit 10 each)
     */
    protected function getRecentData(): array
    {
        // Recent Invoices
        $recentInvoices = PurchaseInvoice::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->with('supplier')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => $invoice->grand_total,
                    'payment_status' => $invoice->payment_status,
                    'supplier_name' => optional($invoice->supplier)->name,
                    'created_at' => $invoice->created_at,
                ];
            });

        // Recent Activities
        $recentActivities = Activity::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'status' => $activity->status,
                    'due_date' => $activity->due_date,
                    'created_at' => $activity->created_at,
                ];
            });

        // Recent Manpower
        $recentManpower = ManPowerMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->with('supplier')
            ->orderBy('work_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function($master) {
                return [
                    'id' => $master->id,
                    'work_date' => $master->work_date,
                    'total_count' => $master->total_count,
                    'supplier_name' => optional($master->supplier)->name,
                    'created_at' => $master->created_at,
                ];
            });

        // Recent Consumption
        $recentConsumption = DailyConsumptionMaster::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->with('details.material')
            ->orderBy('consumption_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function($master) {
                $totalAmount = $master->details->sum(function($detail) {
                    $rate = $detail->material ? ($detail->material->price ?? 0) : 0;
                    return $detail->quantity * $rate;
                });
                return [
                    'id' => $master->id,
                    'consumption_number' => $master->consumption_number,
                    'consumption_date' => $master->consumption_date,
                    'total_amount' => $totalAmount,
                    'status' => $master->status,
                    'created_at' => $master->created_at,
                ];
            });

        // Recent Transfers
        $recentTransfers = MaterialTransfer::where(function($query) {
                $query->where('from_site_id', $this->project->id)
                      ->orWhere('to_site_id', $this->project->id);
            })
            ->where('workspace_id', $this->workspaceId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($transfer) {
                $direction = $transfer->from_site_id == $this->project->id ? 'out' : 'in';
                return [
                    'id' => $transfer->id,
                    'record_number' => $transfer->record_number,
                    'record_date' => $transfer->record_date,
                    'direction' => $direction,
                    'status' => $transfer->status,
                    'created_at' => $transfer->created_at,
                ];
            });

        return [
            'recent_invoices' => $recentInvoices,
            'recent_activities' => $recentActivities,
            'recent_manpower' => $recentManpower,
            'recent_consumption' => $recentConsumption,
            'recent_transfers' => $recentTransfers,
        ];
    }

    /**
     * Get chart data for dashboard visualizations
     */
    protected function getChartData(): array
    {
        // Monthly spending (invoices) for last 6 months
        $monthlySpending = PurchaseInvoice::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('invoice_date', '>=', Carbon::now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(invoice_date, "%Y-%m") as month'),
                DB::raw('SUM(grand_total) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Budget vs Actual (monthly)
        $budget = (float) $this->project->budget;
        $monthlyBudget = $budget / 6; // Assuming 6 months project

        // Procurement pipeline data
        $pipelineData = [
            'indent' => $this->getIndentMetrics()['total_indent'],
            'po' => $this->getPurchaseOrderMetrics()['total_po'],
            'grn' => $this->getGrnMetrics()['total_grn'],
            'invoice' => $this->getInvoiceMetrics()['total_invoice'],
        ];

        // Payment status distribution
        $invoiceMetrics = $this->getInvoiceMetrics();
        $paymentDistribution = [
            'paid' => $invoiceMetrics['paid_invoice'],
            'unpaid' => $invoiceMetrics['unpaid_invoice'],
            'partially_paid' => $invoiceMetrics['partially_paid_invoice'],
        ];

        return [
            'monthly_spending' => $monthlySpending,
            'monthly_budget' => $monthlyBudget,
            'pipeline_data' => $pipelineData,
            'payment_distribution' => $paymentDistribution,
        ];
    }

    /**
     * Get alerts/warnings for the project
     */
    public function getAlerts(): array
    {
        $alerts = [];

        // Overdue invoices
        $overdueInvoices = PurchaseInvoice::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('payment_status', 'unpaid')
            ->where('invoice_date', '<', Carbon::now()->subDays(30))
            ->count();

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'ti-alert-circle',
                'title' => 'Overdue Invoices',
                'message' => "You have {$overdueInvoices} overdue invoice(s)",
            ];
        }

        // Pending GRN
        $pendingGrn = Grn::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('status', 'Pending')
            ->count();

        if ($pendingGrn > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'ti-package',
                'title' => 'Pending GRN',
                'message' => "You have {$pendingGrn} pending GRN(s)",
            ];
        }

        // Delayed activities
        $delayedActivities = Activity::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', Carbon::now())
            ->count();

        if ($delayedActivities > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'ti-activity',
                'title' => 'Delayed Activities',
                'message' => "You have {$delayedActivities} delayed activity(s)",
            ];
        }

        // Pending POs
        $pendingPos = PurchaseOrder::where('site_id', $this->project->id)
            ->where('workspace_id', $this->workspaceId)
            ->where('status', 'Pending')
            ->count();

        if ($pendingPos > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'ti-shopping-cart',
                'title' => 'Pending Purchase Orders',
                'message' => "You have {$pendingPos} pending PO(s) awaiting approval",
            ];
        }

        return $alerts;
    }
}
