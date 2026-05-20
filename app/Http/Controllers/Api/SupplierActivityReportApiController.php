<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierTransaction;
use App\Models\Grn;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

/**
 * @group Supplier Activity Report
 * Returns a unified activity timeline per supplier across Purchase Orders, GRNs, Invoices and Payments — exactly matching the web view.
 */
class SupplierActivityReportApiController extends Controller
{
    /**
     * List supplier activity timeline.
     *
     * Builds a UNION ALL query across `purchase_orders`, `grns`, `purchase_invoices` and `payments_module`
     * so that one endpoint returns the full PO → GRN → Invoice → Payment flow per supplier.
     * A running balance is calculated ( Invoice debits increase; Payment/Advance credits decrease balance ).
     *
     * @authenticated
     *
     * @queryParam supplier_id int optional Filter by supplier ID. Use `all` for all suppliers. Default: all. Example: 5
     * @queryParam site_id int optional Filter by site/project ID. Use `all` for all sites. Default: all. Example: 2
     * @queryParam from_date date optional Filter start date (YYYY-MM-DD). Defaults to first day of current month. Example: 2025-06-01
     * @queryParam to_date date optional Filter end date (YYYY-MM-DD). Defaults to today. Example: 2025-06-30
     * @queryParam per_page int optional Number of records per page. Default: 50. Example: 50
     *
     * @responseField success bool Whether the request succeeded. Example: true
     * @responseField message string Response message. Example: Supplier activity report fetched successfully.
     * @responseField summary object Financial summary totals.
     * @responseField summary.total_po string Sum of all PO amounts in range. Example: "100000.00"
     * @responseField summary.total_grn string Sum of all GRN amounts in range. Example: "95000.00"
     * @responseField summary.total_invoice string Sum of all invoice amounts in range. Example: "85000.00"
     * @responseField summary.total_payments string Sum of all completed payment + advance amounts in range. Example: "60000.00"
     * @responseField summary.final_balance string Running balance (only when activity data present). Example: "-25000.00"
     * @responseField summary.currency string Currency code. Example: INR
     * @responseField transactions array List of activity records (latest first).
     * @responseField transactions.*.id int Composite row number. Example: 1
     * @responseField transactions.*.type string Activity type — `PO`, `GRN`, `Invoice`, `Payment`, `Advance`. Example: Invoice
     * @responseField transactions.*.reference string Document reference number. Example: INV-0012
     * @responseField transactions.*.reference_id int Primary key of the source record. Example: 12
     * @responseField transactions.*.supplier_name string Supplier name. Example: ABC Materials Ltd
     * @responseField transactions.*.site_name string Site/project name. Example: Main Site
     * @responseField transactions.*.reference_amount string Amount on source document. Example: "25000.00"
     * @responseField transactions.*.debit string Debit amount (non-zero for Invoice). Example: "25000.00"
     * @responseField transactions.*.credit string Credit amount (non-zero for Payment / Advance). Example: "0.00"
     * @responseField transactions.*.balance string|null Running balance after this event. Example: "45000.00"
     * @responseField transactions.*.date_time string Event timestamp (YYYY-MM-DD HH:mm:ss). Example: 2025-06-15T10:30:00
     * @responseField transactions.*.description string Human-readable description. Example: Invoice Generated
     *
     * @throws 403 Unauthorized when the user lacks `supplier-ledger report` permission.
     * @throws 422 Missing or invalid query parameters.
     *
     * @response status=200 scenario="Success — with activity data"
     * {
     *   "success": true,
     *   "message": "Supplier activity report fetched successfully.",
     *   "summary": {
     *     "total_po": "100000.00",
     *     "total_grn": "95000.00",
     *     "total_invoice": "85000.00",
     *     "total_payments": "60000.00",
     *     "final_balance": "-25000.00",
     *     "currency": "INR"
     *   },
     *   "transactions": [
     *     {
     *       "id": 1,
     *       "type": "Invoice",
     *       "reference": "INV-0012",
     *       "reference_id": 12,
     *       "supplier_name": "ABC Materials Ltd",
     *       "site_name": "Main Site",
     *       "reference_amount": "25000.00",
     *       "debit": "25000.00",
     *       "credit": "0.00",
     *       "balance": "25000.00",
     *       "date_time": "2025-06-15 10:30:00",
     *       "description": "Invoice Generated"
     *     }
     *   ]
     * }
     * @response status=200 scenario="No activity data (only suppliers/sites returned)"
     * {
     *   "success": true,
     *   "message": "Supplier activity report fetched successfully.",
     *   "summary": {
     *     "total_po": "0.00",
     *     "total_grn": "0.00",
     *     "total_invoice": "0.00",
     *     "total_payments": "0.00",
     *     "final_balance": "0.00",
     *     "currency": "INR"
     *   },
     *   "transactions": []
     * }
     * @response status=403 scenario="Permission denied"
     * {
     *   "success": false,
     *   "message": "Unauthorized action."
     * }
     */
    public function index(Request $request)
    {
        if (! Auth::user()->isAbleTo('supplier-ledger report')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $workspaceId = getActiveWorkSpace();
        $perPage = (int) $request->get('per_page', 50);

        // Collect filter values for validation
        $supplierId = $request->get('supplier_id', 'all');
        $siteId = $request->get('site_id', 'all');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // Default date range to current month if not provided
        if (! $fromDate) {
            $fromDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if (! $toDate) {
            $toDate = Carbon::now()->toDateString();
        }

        $summary = [
            'total_po' => '0.00',
            'total_grn' => '0.00',
            'total_invoice' => '0.00',
            'total_payments' => '0.00',
            'final_balance' => '0.00',
            'currency' => strtoupper(config('app.currency', 'INR')),
        ];

        $activities = [];
        $lastPage = 1;
        $total = 0;

        if ($request->filled('supplier_id') && $request->filled('from_date')) {
            $request->validate([
                'supplier_id' => ['required', 'string'],
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $query = $this->buildActivityQuery($supplierId, $siteId, $fromDate, $toDate, $workspaceId);

            // Fetch all results ordered ascending for running balance
            $rawActivities = $query->orderBy('date_time', 'asc')->get();

            // Calculate running balance
            $runningBalance = 0;
            foreach ($rawActivities as &$activity) {
                if ($activity->type === 'Invoice') {
                    $debit = (float) ($activity->debit ?? 0);
                    $runningBalance += $debit;
                    $activity->balance = $runningBalance;
                } elseif ($activity->type === 'Payment' || $activity->type === 'Advance') {
                    $credit = (float) ($activity->credit ?? 0);
                    $runningBalance -= $credit;
                    $activity->balance = $runningBalance;
                } else {
                    $activity->balance = null;
                }
            }
            unset($activity);

            // Reverse for display (latest first)
            $rawActivities = $rawActivities->reverse()->values();

            $summary = [
                'total_po' => (string) round($rawActivities->where('type', 'PO')->sum('reference_amount'), 2),
                'total_grn' => (string) round($rawActivities->where('type', 'GRN')->sum('reference_amount'), 2),
                'total_invoice' => (string) round($rawActivities->where('type', 'Invoice')->sum('debit'), 2),
                'total_payments' => (string) round($rawActivities->whereIn('type', ['Payment', 'Advance'])->sum('credit'), 2),
                'final_balance' => (string) round($runningBalance, 2),
                'currency' => strtoupper(config('app.currency', 'INR')),
            ];

            // Pagination: simple offset/limit on the reversed collection
            $page = (int) $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $total = $rawActivities->count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            $pageActivities = $rawActivities->slice($offset, $perPage)->values();

            $activities = $pageActivities->map(function ($a) {
                return [
                    'id' => ($a->date_time ?? '').'-'.($a->type ?? '').'-'.($a->reference_id ?? ''),
                    'type' => $a->type,
                    'reference' => $a->reference,
                    'reference_id' => (int) $a->reference_id,
                    'supplier_name' => $a->supplier_name,
                    'site_name' => $a->site_name,
                    'reference_amount' => (string) $a->reference_amount,
                    'debit' => (string) $a->debit,
                    'credit' => (string) $a->credit,
                    'balance' => $a->balance !== null ? (string) round($a->balance, 2) : null,
                    'date_time' => $a->date_time,
                    'description' => $a->description,
                ];
            })->toArray();
        }

        $meta = [
            'filters' => [
                'supplier_id' => $supplierId,
                'site_id' => $siteId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'pagination' => [
                'current_page' => (int) $request->get('page', 1),
                'per_page' => $perPage,
                'total' => count($activities) > 0 ? $total : 0,
                'last_page' => $lastPage ?? 1,
                'has_next' => ($request->get('page', 1) ?? 1) < ($lastPage ?? 1),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Supplier activity report fetched successfully.',
            'summary' => $summary,
            'meta' => $meta,
            'transactions' => $activities,
        ], 200);
    }

    /**
     * Build UNION ALL query across Purchase Orders, GRNs, Purchase Invoices and Payments.
     */
    private function buildActivityQuery($supplierId, $siteId, $fromDate, $toDate, $workspaceId)
    {
        // ─── Purchase Orders ───────────────────────────────────────────────
        $poQuery = DB::table('purchase_orders as po')
            ->select([
                'po.id',
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

        // ─── GRNs ──────────────────────────────────────────────────────────
        $grnQuery = DB::table('grns as grn')
            ->select([
                'grn.id',
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

        // ─── Purchase Invoices ─────────────────────────────────────────────
        $invoiceQuery = DB::table('purchase_invoices as inv')
            ->select([
                'inv.id',
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

        // ─── Payments ──────────────────────────────────────────────────────
        $paymentQuery = DB::table('payments_module as pm')
            ->select([
                'pm.id',
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

        // UNION ALL returns a query builder — callers add `->get()` / `->orderBy()` on it
        return $poQuery->unionAll($grnQuery)->unionAll($invoiceQuery)->unionAll($paymentQuery);
    }

    /**
     * Get distinct suppliers with activity for the filter period.
     *
     * @authenticated
     *
     * @queryParam from_date date optional Start date (YYYY-MM-DD). Defaults to start of current month. Example: 2025-06-01
     * @queryParam to_date date optional End date (YYYY-MM-DD). Defaults to today. Example: 2025-06-30
     *
     * @responseField success bool Example: true
     * @responseField data array List of suppliers with activity counts.
     * @responseField data.*.id int Supplier ID. Example: 5
     * @responseField data.*.name string Supplier name. Example: ABC Materials Ltd
     * @responseField data.*.total_transactions int Number of transactions. Example: 15
     * @responseField data.*.total_amount string Sum of transaction amounts. Example: "85000.00"
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Suppliers with activity fetched successfully.",
     *   "data": [
     *     {
     *       "id": 5,
     *       "name": "ABC Materials Ltd",
     *       "total_transactions": 15,
     *       "total_amount": "85000.00"
     *     }
     *   ]
     * }
     */
    public function suppliers(Request $request)
    {
        if (! Auth::user()->isAbleTo('supplier-ledger report')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $fromDate = $request->get('from_date', Carbon::now()->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', Carbon::now()->toDateString());
        $workspaceId = getActiveWorkSpace();

        try {
            // Collect all supplier IDs referenced in the four source tables
            $poSuppliers = DB::table('purchase_orders')
                ->select('supplier_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(grand_total) as amt'))
                ->where('workspace_id', $workspaceId)
                ->whereBetween('po_date', [$fromDate, $toDate])
                ->groupBy('supplier_id');

            $grnSuppliers = DB::table('grns')
                ->select('supplier_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_amount) as amt'))
                ->where('workspace_id', $workspaceId)
                ->whereBetween('grn_date', [$fromDate, $toDate])
                ->groupBy('supplier_id');

            $invoiceSuppliers = DB::table('purchase_invoices')
                ->select('supplier_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(grand_total) as amt'))
                ->where('workspace_id', $workspaceId)
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->groupBy('supplier_id');

            $paymentSuppliers = DB::table('payments_module')
                ->select('supplier_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(amount) as amt'))
                ->where('workspace_id', $workspaceId)
                ->where('status', 'completed')
                ->whereBetween('payment_date', [$fromDate, $toDate])
                ->groupBy('supplier_id');

            $unionedSuppliersQuery = $poSuppliers->unionAll($grnSuppliers)->unionAll($invoiceSuppliers)->unionAll($paymentSuppliers);
            $unionedSuppliers = DB::select(
                "SELECT supplier_id FROM (
                    {$unionedSuppliersQuery->toSql()}
                ) as u GROUP BY supplier_id
            ",
                $unionedSuppliersQuery->getBindings()
            );

            $suppliersWithActivity = [];
            foreach ($unionedSuppliers as $row) {
                $supplier = Supplier::find($row->supplier_id);
                if (! $supplier) {
                    continue;
                }

                $totalTx = SupplierTransaction::where('supplier_id', $row->supplier_id)
                    ->where('workspace_id', $workspaceId)
                    ->whereDate('transaction_date', '>=', $fromDate)
                    ->whereDate('transaction_date', '<=', $toDate)
                    ->count();

                $totalAmt = SupplierTransaction::where('supplier_id', $row->supplier_id)
                    ->where('workspace_id', $workspaceId)
                    ->whereDate('transaction_date', '>=', $fromDate)
                    ->whereDate('transaction_date', '<=', $toDate)
                    ->sum('reference_amount');

                $suppliersWithActivity[] = [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'total_transactions' => (int) $totalTx,
                    'total_amount' => (string) round($totalAmt, 2),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Suppliers with activity fetched successfully.',
                'data' => $suppliersWithActivity,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Supplier Activity /api/suppliers Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch supplier activity data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
