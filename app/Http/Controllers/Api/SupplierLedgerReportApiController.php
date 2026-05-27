<?php

namespace App\Http\Controllers\Api;

use App\Exports\SupplierLedgerExport;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @group Supplier Ledger Report
 * Endpoints for supplier ledger reconciliation — returns every PO, invoice, advance and payment transaction per supplier within a date range with running balance.
 */
class SupplierLedgerReportApiController extends Controller
{
    /**
     * List supplier ledger transactions.
     *
     * Returns transactions ordered by date (latest first) with a financial summary.
     * Transactions include: Purchase Orders (`po`), Invoices (`invoice`), Payments (`payment`) and Advances (`advance`).
     *
     * @authenticated
     *
     * @queryParam supplier_id int optional Filter by supplier ID. Use `all` for all suppliers. Default: all. Example: 5
     * @queryParam site_id int optional Filter by site/project ID. Use `all` for all sites. Default: all. Example: 2
     * @queryParam from_date date optional Start date (YYYY-MM-DD). Defaults to first day of current month. Example: 2025-01-01
     * @queryParam to_date date optional End date (YYYY-MM-DD). Defaults to today. Example: 2025-12-31
     * @queryParam per_page int optional Number of records per page. Default: 50. Example: 50
     *
     * @responseField success bool Whether the request succeeded. Example: true
     * @responseField message string Response message. Example: Supplier ledger fetched successfully.
     * @responseField summary object Financial summary for the filter range.
     * @responseField summary.total_po string Total purchase order amount. Example: "100000.00"
     * @responseField summary.total_invoice string Total invoice (debit) amount. Example: "85000.00"
     * @responseField summary.total_payments string Total payments and advances credited. Example: "60000.00"
     * @responseField summary.total_advances string Total advance amount (subset of total_payments). Example: "10000.00"
     * @responseField summary.current_balance string Outstanding balance across suppliers. Example: "25000.00"
     * @responseField summary.currency string Currency code used. Example: INR
     * @responseField transactions array List of transaction records.
     * @responseField transactions.*.id int Transaction ID. Example: 1
     * @responseField transactions.*.supplier_id int Supplier ID. Example: 5
     * @responseField transactions.*.supplier object Nested supplier details.
     * @responseField transactions.*.site object Nested site/project details.
     * @responseField transactions.*.reference_type string One of: `po`, `invoice`, `payment`, `advance`. Example: invoice
     * @responseField transactions.*.reference_id int ID of the related record. Example: 12
     * @responseField transactions.*.debit string Debit amount. Example: "25000.00"
     * @responseField transactions.*.credit string Credit amount. Example: "0.00"
     * @responseField transactions.*.balance string Running balance. Example: "45000.00"
     * @responseField pagination array Laravel standard pagination meta.
     *
     * @throws 403 Unauthorized when the user lacks `supplier-ledger report` permission.
     * @throws 422 Validation error from date range / parameter format.
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Supplier ledger fetched successfully.",
     *   "summary": {
     *     "total_po": "100000.00",
     *     "total_invoice": "85000.00",
     *     "total_payments": "60000.00",
     *     "total_advances": "10000.00",
     *     "current_balance": "25000.00",
     *     "currency": "INR"
     *   },
     *   "transactions": [
     *     {
     *       "id": 15,
     *       "supplier": {"id": 5, "name": "ABC Materials Ltd"},
     *       "site": {"id": 2, "name": "Main Site"},
     *       "reference_type": "invoice",
     *       "reference_id": 12,
     *       "debit": "25000.00",
     *       "credit": "0.00",
     *       "balance": "25000.00",
     *       "transaction_date": "2025-06-15"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 50,
     *     "total": 85
     *   }
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

        $request->validate([
            'supplier_id' => 'nullable',
            'site_id' => 'nullable',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $workspaceId = getActiveWorkSpace();
        $perPage = (int) $request->get('per_page', 50);

        $query = SupplierTransaction::with(['supplier:id,name', 'site:id,name'])
            ->where('workspace_id', $workspaceId);

        if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('site_id') && $request->site_id !== 'all') {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        } else {
            $query->whereDate('transaction_date', '>=', Carbon::now()->startOfMonth()->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        } else {
            $query->whereDate('transaction_date', '<=', Carbon::now()->toDateString());
        }

        try {
            $transactions = $query->orderedByDate()->get();

            $totalPO = $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount');
            $totalPayments = $transactions->whereIn('reference_type', [SupplierTransaction::TYPE_PAYMENT, SupplierTransaction::TYPE_ADVANCE])->sum('credit');
            $totalInvoiceAmount = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
            $totalAdvances = $transactions->where('reference_type', SupplierTransaction::TYPE_ADVANCE)->sum('credit');

if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                 $currentBalance = SupplierTransaction::getCurrentBalance(
                     (int) $request->supplier_id,
                     null,
                     $request->to_date,
                     $workspaceId
                 );
             } else {
                 $supplierIds = SupplierTransaction::where('workspace_id', $workspaceId)->distinct()->pluck('supplier_id');
                 $currentBalance = 0;
                 foreach ($supplierIds as $sid) {
                     $currentBalance += SupplierTransaction::getCurrentBalance(
                         $sid,
                         null,
                         $request->to_date,
                         $workspaceId
                     );
                 }
             }

            $summary = [
                'total_po' => (string) round($totalPO, 2),
                'total_invoice' => (string) round($totalInvoiceAmount, 2),
                'total_payments' => (string) round($totalPayments, 2),
                'total_advances' => (string) round($totalAdvances, 2),
                'current_balance' => (string) round($currentBalance, 2),
                'currency' => strtoupper(config('app.currency', 'INR')),
            ];

            $paginator = $transactions->sortByDesc(function ($t) {
                return $t->transaction_date.' '.$t->id;
            })->values();

            $pageTransactions = $paginator->forPage(
                (int) ($request->get('page', 1)),
                $perPage
            )->values();

            $formattedTransactions = $pageTransactions->map(function (SupplierTransaction $t) {
                return [
                    'id' => $t->id,
                    'supplier_id' => $t->supplier_id,
                    'site_id' => $t->site_id,
                    'reference_type' => $t->reference_type,
                    'reference_id' => $t->reference_id,
                    'reference_amount' => (string) $t->reference_amount,
                    'debit' => (string) $t->debit,
                    'credit' => (string) $t->credit,
                    'balance' => (string) $t->balance,
                    'transaction_date' => $t->transaction_date ? $t->transaction_date->format('Y-m-d') : null,
                    'description' => $t->description,
                    'meta' => $t->meta,
                    'supplier' => $t->supplier ? [
                        'id' => $t->supplier->id,
                        'name' => $t->supplier->name,
                    ] : null,
                    'site' => $t->site ? [
                        'id' => $t->site->id,
                        'name' => $t->site->name,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Supplier ledger fetched successfully.',
                'summary' => $summary,
                'data' => $formattedTransactions,
                'pagination' => [
                    'current_page' => (int) $request->get('page', 1),
                    'per_page' => $perPage,
                    'total' => count($paginator),
                    'last_page' => (int) ceil(count($paginator) / $perPage),
                    'has_next' => $request->get('page', 1) < ceil(count($paginator) / $perPage),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Supplier Ledger Report API Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load supplier ledger report: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the current balance for a specific supplier.
     *
     * @authenticated
     *
     * @urlParam supplier_id int required The supplier ID. Example: 5
     *
     * @queryParam site_id int optional Filter by site/project ID. Defaults to `null` (all sites). Example: 2
     *
     * @response status=200 scenario="Success"
      * {
      *   "success": true,
      *   "message": "Supplier balance fetched successfully.",
      *   "data": {
      *     "supplier_id": 5,
      *     "supplier_name": "ABC Materials Ltd",
      *     "current_balance": "25000.00",
      *     "total_debit": "85000.00",
      *     "total_credit": "60000.00",
      *     "currency": "INR",
      *     "transactions": [
      *       {
      *         "date": "2025-06-15",
      *         "type": "invoice",
      *         "reference": "INV-001",
      *         "ref_amount": "25000.00",
      *         "description": "Advance received for site development project."
      *       }
      *     ]
      *   }
      * }
      * @response status=404 scenario="Supplier not found"
      */
public function balance($supplierId)
      {
          if (! Auth::user()->isAbleTo('supplier-ledger report')) {
              return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
          }

          try {
              $workspaceId = getActiveWorkSpace();
              $supplier = Supplier::findOrFail($supplierId);

              $totalCredits = SupplierTransaction::where('supplier_id', $supplierId)
                  ->where('workspace_id', $workspaceId)
                  ->whereIn('reference_type', [SupplierTransaction::TYPE_PAYMENT, SupplierTransaction::TYPE_ADVANCE])
                  ->sum('credit');

              $totalDebits = SupplierTransaction::where('supplier_id', $supplierId)
                  ->where('workspace_id', $workspaceId)
                  ->sum('debit');

              $currentBalance = SupplierTransaction::getCurrentBalance($supplierId, null, null, $workspaceId);

$transactions = SupplierTransaction::with(['supplier:id,name', 'site:id,name'])
                  ->where('supplier_id', $supplierId)
                  ->where('workspace_id', $workspaceId)
                  ->orderedByDate()
                  ->get()
                 ->map(function (SupplierTransaction $t) {
                     return [
                         'date' => $t->transaction_date ? $t->transaction_date->format('Y-m-d') : null,
                         'type' => $t->reference_type,
                         'reference' => $t->reference_number,
                         'ref_amount' => (string) $t->reference_amount,
                         'description' => $t->description,
                     ];
                 })
                 ->values()
                 ->all();

             return response()->json([
                 'success' => true,
                 'message' => 'Supplier balance fetched successfully.',
                 'data' => [
                     'supplier_id' => $supplier->id,
                     'supplier_name' => $supplier->name,
                     'current_balance' => (string) round($currentBalance, 2),
                     'total_debit' => (string) round($totalDebits, 2),
                     'total_credit' => (string) round($totalCredits, 2),
                     'currency' => strtoupper(config('app.currency', 'INR')),
                     'transactions' => $transactions,
                 ],
             ], 200);
         } catch (ModelNotFoundException $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Supplier not found.',
             ], 404);
         } catch (\Exception $e) {
             Log::error('Supplier Balance API Error: '.$e->getMessage());

             return response()->json([
                 'success' => false,
                 'message' => 'Unable to fetch supplier balance.',
                 'error' => $e->getMessage(),
             ], 500);
         }
     }

    /**
     * Export supplier ledger report to Excel.
     *
     * @authenticated
     *
     * @queryParam supplier_id int optional Filter by supplier ID. Use `all` for all suppliers. Example: 5
     * @queryParam site_id int optional Filter by site/project ID. Use `all` for all sites. Example: 2
     * @queryParam from_date date optional Start date (YYYY-MM-DD). Defaults to first day of current month. Example: 2025-01-01
     * @queryParam to_date date optional End date (YYYY-MM-DD). Defaults to today. Example: 2025-12-31
     *
     * @response {"status": 1, "message": "Export started", "filename": "supplier-ledger-report-2025-06-19.xlsx"}
     *
     * @throws 403 Unauthorized.
     * @throws 500 Export error.
     */
    public function exportExcel(Request $request)
    {
        if (! Auth::user()->isAbleTo('supplier-ledger report')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'supplier_id' => 'nullable',
            'site_id' => 'nullable',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $workspaceId = getActiveWorkSpace();

        $query = SupplierTransaction::with(['supplier', 'site'])
            ->where('workspace_id', $workspaceId);

        if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('site_id') && $request->site_id !== 'all') {
            $query->where('site_id', $request->site_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        } else {
            $query->whereDate('transaction_date', '>=', Carbon::now()->startOfMonth()->toDateString());
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        } else {
            $query->whereDate('transaction_date', '<=', Carbon::now()->toDateString());
        }

        try {
            $transactions = $query->orderedByDate()->get();
            $filename = 'supplier-ledger-report-'.date('Y-m-d').'.xlsx';

            return Excel::download(new SupplierLedgerExport($transactions), $filename);
        } catch (\Exception $e) {
            Log::error('Supplier Ledger Excel Export Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to export Excel: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export supplier ledger report to PDF.
     *
     * @authenticated
     *
     * @queryParam supplier_id int optional Filter by supplier ID. Use `all` for all suppliers. Example: 5
     * @queryParam site_id int optional Filter by site/project ID. Use `all` for all sites. Example: 2
     * @queryParam from_date date optional Start date (YYYY-MM-DD). Defaults to first day of current month. Example: 2025-01-01
     * @queryParam to_date date optional End date (YYYY-MM-DD). Defaults to today. Example: 2025-12-31
     *
     * @response Returns the PDF as a file download.
     *
     * @throws 403 Unauthorized.
     * @throws 500 Export error.
     */
    public function exportPdf(Request $request)
    {
        if (! Auth::user()->isAbleTo('supplier-ledger report')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'supplier_id' => 'nullable',
            'site_id' => 'nullable',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $workspaceId = getActiveWorkSpace();

        $query = SupplierTransaction::with(['supplier', 'site'])
            ->where('workspace_id', $workspaceId);

        if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('site_id') && $request->site_id !== 'all') {
            $query->where('site_id', $request->site_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        } else {
            $query->whereDate('transaction_date', '>=', Carbon::now()->startOfMonth()->toDateString());
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        } else {
            $query->whereDate('transaction_date', '<=', Carbon::now()->toDateString());
        }

        try {
            $transactions = $query->orderedByDate()->get();

            $totalPO = $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount');
            $totalPayments = $transactions->whereIn('reference_type', [SupplierTransaction::TYPE_PAYMENT, SupplierTransaction::TYPE_ADVANCE])->sum('credit');
            $totalInvoiceAmount = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
            $totalAdvances = $transactions->where('reference_type', SupplierTransaction::TYPE_ADVANCE)->sum('credit');

if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                 $currentBalance = SupplierTransaction::getCurrentBalance(
                     (int) $request->supplier_id,
                     null,
                     $request->to_date,
                     $workspaceId
                 );
             } else {
                 $supplierIds = SupplierTransaction::where('workspace_id', $workspaceId)->distinct()->pluck('supplier_id');
                 $currentBalance = 0;
                 foreach ($supplierIds as $sid) {
                     $currentBalance += SupplierTransaction::getCurrentBalance(
                         $sid,
                         null,
                         $request->to_date,
                         $workspaceId
                     );
                 }
             }

            $summary = [
                'total_po' => (string) round($totalPO, 2),
                'total_invoice' => (string) round($totalInvoiceAmount, 2),
                'total_payments' => (string) round($totalPayments, 2),
                'total_advances' => (string) round($totalAdvances, 2),
                'current_balance' => (string) round($currentBalance, 2),
                'currency' => strtoupper(config('app.currency', 'INR')),
            ];

            $filters = [
                'supplier_id' => $request->supplier_id ?? 'all',
                'site_id' => $request->site_id ?? 'all',
                'from_date' => $request->from_date ?? Carbon::now()->startOfMonth()->toDateString(),
                'to_date' => $request->to_date ?? Carbon::now()->toDateString(),
            ];

            // Generate PDF using Dompdf directly instead of non-existent PDF facade
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);
            $dompdf = new Dompdf($options);

            $viewName = (!$request->filled('supplier_id') || $request->supplier_id === 'all') ? 'reports.supplier-ledger.pdf_all' : 'reports.supplier-ledger.pdf';
            $html = view($viewName, compact('transactions', 'summary', 'filters'))->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="supplier-ledger-report-'.date('Y-m-d').'.pdf"');
        } catch (\Exception $e) {
            Log::error('Supplier Ledger PDF Export Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to export PDF: '.$e->getMessage(),
            ], 500);
        }
    }
}
