<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Grn;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\Supplier;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierActivityReportController extends Controller
{
    /**
     * Display the supplier activity report.
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier-ledger report')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $workspaceId = getActiveWorkSpace();
            
            // Get suppliers for filter
            $suppliers = Supplier::orderBy('name')
                ->pluck('name', 'id');
            $suppliers->prepend('All Suppliers', 'all');

            // Get sites for filter
            $sites = Project::where('workspace', $workspaceId)
                ->projectonly()
                ->orderBy('name')
                ->pluck('name', 'id');
            $sites->prepend('All Sites', 'all');

            // Validate required parameters only when filtering
            if ($request->filled('supplier_id') || $request->filled('from_date')) {
                $request->validate([
                    'supplier_id' => 'required',
                    'from_date' => 'required|date',
                    'to_date' => 'required|date|after_or_equal:from_date',
                ]);
            }

            // Build UNION ALL query for all data sources (only if filters are applied)
            $activities = collect();
            $summary = [
                'total_po' => 0,
                'total_grn' => 0,
                'total_invoice' => 0,
                'total_payments' => 0,
                'final_balance' => 0,
            ];

            if ($request->filled('supplier_id') && $request->filled('from_date')) {
                $query = $this->buildActivityQuery($request, $workspaceId);

                // Get raw data sorted by date ascending for balance calculation
                $activities = $query->orderBy('date_time', 'asc')->get();

                // Calculate running balance (only for Invoice and Payment/Advance)
                $runningBalance = 0;
                foreach ($activities as $activity) {
                    if ($activity->type === 'Invoice') {
                        $debit = (float)($activity->debit ?? 0);
                        $runningBalance += $debit;
                        $activity->balance = $runningBalance;
                    } elseif ($activity->type === 'Payment' || $activity->type === 'Advance') {
                        $credit = (float)($activity->credit ?? 0);
                        $runningBalance -= $credit;
                        $activity->balance = $runningBalance;
                    } else {
                        // PO and GRN don't affect balance
                        $activity->balance = null;
                    }
                }

                // Reverse for display (latest first)
                $activities = $activities->reverse();

                // Calculate summary
                $summary = [
                    'total_po' => $activities->where('type', 'PO')->sum('reference_amount'),
                    'total_grn' => $activities->where('type', 'GRN')->sum('reference_amount'),
                    'total_invoice' => $activities->where('type', 'Invoice')->sum('debit'),
                    'total_payments' => $activities->whereIn('type', ['Payment', 'Advance'])->sum('credit'),
                    'final_balance' => $runningBalance,
                ];
            }

            // Pass filters to view
            $filters = [
                'supplier_id' => $request->supplier_id ?? 'all',
                'site_id' => $request->site_id ?? 'all',
                'from_date' => $request->from_date ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                'to_date' => $request->to_date ?? \Carbon\Carbon::now()->toDateString(),
            ];

            return view('reports.supplier-activity.index', compact(
                'activities',
                'suppliers',
                'sites',
                'summary',
                'filters'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load supplier activity report: ' . $e->getMessage()]);
        }
    }

    /**
     * Build UNION ALL query for PO, GRN, Invoice, and Payment data.
     */
    private function buildActivityQuery(Request $request, $workspaceId)
    {
        $supplierId = $request->supplier_id;
        $siteId = $request->site_id;
        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        // PO Query
        $poQuery = DB::table('purchase_orders as po')
            ->select([
                DB::raw("DATE_FORMAT(CONCAT(po.po_date, ' ', TIME(po.created_at)), '%Y-%m-%d %H:%i:%s') as date_time"),
                DB::raw("'PO' as type"),
                'po.po_number as reference',
                's.name as supplier_name',
                'po.grand_total as reference_amount',
                'p.name as site_name',
                DB::raw('CAST(0 AS DECIMAL(10,2)) as debit'),
                DB::raw('CAST(0 AS DECIMAL(10,2)) as credit'),
                DB::raw('NULL as balance'),
                DB::raw("'PO Created' as description"),
                'po.id as reference_id',
            ])
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('projects as p', 'po.site_id', '=', 'p.id')
            ->where('po.workspace_id', $workspaceId)
            ->whereBetween('po.po_date', [$fromDate, $toDate]);

        if ($supplierId !== 'all') {
            $poQuery->where('po.supplier_id', $supplierId);
        }
        if ($siteId !== 'all') {
            $poQuery->where('po.site_id', $siteId);
        }

        // GRN Query
        $grnQuery = DB::table('grns as grn')
            ->select([
                DB::raw("DATE_FORMAT(CONCAT(grn.grn_date, ' ', TIME(grn.created_at)), '%Y-%m-%d %H:%i:%s') as date_time"),
                DB::raw("'GRN' as type"),
                'grn.grn_number as reference',
                's.name as supplier_name',
                'grn.total_amount as reference_amount',
                'p.name as site_name',
                DB::raw('CAST(0 AS DECIMAL(10,2)) as debit'),
                DB::raw('CAST(0 AS DECIMAL(10,2)) as credit'),
                DB::raw('NULL as balance'),
                DB::raw("'GRN Received' as description"),
                'grn.id as reference_id',
            ])
            ->leftJoin('suppliers as s', 'grn.supplier_id', '=', 's.id')
            ->leftJoin('projects as p', 'grn.site_id', '=', 'p.id')
            ->where('grn.workspace_id', $workspaceId)
            ->whereBetween('grn.grn_date', [$fromDate, $toDate]);

        if ($supplierId !== 'all') {
            $grnQuery->where('grn.supplier_id', $supplierId);
        }
        if ($siteId !== 'all') {
            $grnQuery->where('grn.site_id', $siteId);
        }

        // Invoice Query
        $invoiceQuery = DB::table('purchase_invoices as inv')
            ->select([
                DB::raw("DATE_FORMAT(CONCAT(inv.invoice_date, ' ', TIME(inv.created_at)), '%Y-%m-%d %H:%i:%s') as date_time"),
                DB::raw("'Invoice' as type"),
                'inv.invoice_number as reference',
                's.name as supplier_name',
                'inv.grand_total as reference_amount',
                'p.name as site_name',
                'inv.grand_total as debit',
                DB::raw('CAST(0 AS DECIMAL(10,2)) as credit'),
                DB::raw('NULL as balance'),
                DB::raw("'Invoice Generated' as description"),
                'inv.id as reference_id',
            ])
            ->leftJoin('suppliers as s', 'inv.supplier_id', '=', 's.id')
            ->leftJoin('projects as p', 'inv.site_id', '=', 'p.id')
            ->where('inv.workspace_id', $workspaceId)
            ->whereBetween('inv.invoice_date', [$fromDate, $toDate]);

        if ($supplierId !== 'all') {
            $invoiceQuery->where('inv.supplier_id', $supplierId);
        }
        if ($siteId !== 'all') {
            $invoiceQuery->where('inv.site_id', $siteId);
        }

        // Payment Query
        $paymentQuery = DB::table('payments_module as pm')
            ->select([
                DB::raw("DATE_FORMAT(CONCAT(pm.payment_date, ' ', TIME(pm.created_at)), '%Y-%m-%d %H:%i:%s') as date_time"),
                DB::raw("CASE WHEN pm.payment_type = 'advance_against_po' THEN 'Advance' ELSE 'Payment' END as type"),
                'pm.payment_number as reference',
                's.name as supplier_name',
                'pm.amount as reference_amount',
                'p.name as site_name',
                DB::raw('CAST(0 AS DECIMAL(10,2)) as debit'),
                'pm.amount as credit',
                DB::raw('NULL as balance'),
                DB::raw("CASE WHEN pm.payment_type = 'advance_against_po' THEN CONCAT(pm.payment_number, ' / ', pm.mode, ' / Advance Against PO') ELSE CONCAT(pm.payment_number, ' / ', pm.mode, ' / Against Invoice') END as description"),
                'pm.id as reference_id',
            ])
            ->leftJoin('suppliers as s', 'pm.supplier_id', '=', 's.id')
            ->leftJoin('projects as p', 'pm.site_id', '=', 'p.id')
            ->where('pm.workspace_id', $workspaceId)
            ->where('pm.status', 'completed')
            ->whereBetween('pm.payment_date', [$fromDate, $toDate]);

        if ($supplierId !== 'all') {
            $paymentQuery->where('pm.supplier_id', $supplierId);
        }
        if ($siteId !== 'all') {
            $paymentQuery->where('pm.site_id', $siteId);
        }

        // Combine all queries with UNION ALL
        return $poQuery->unionAll($grnQuery)->unionAll($invoiceQuery)->unionAll($paymentQuery);
    }
}
