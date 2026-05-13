<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Helpers\LedgerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Taskly\Entities\Project;

class SupplierLedgerReportController extends Controller
{
    /**
     * Display the supplier ledger report.
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

            // Build query
            $query = SupplierTransaction::with(['supplier', 'site'])
                ->where('workspace_id', $workspaceId);

            // Apply filters
            if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('site_id') && $request->site_id !== 'all') {
                $query->where('site_id', $request->site_id);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('transaction_date', '>=', $request->from_date);
            } else {
                $query->whereDate('transaction_date', '>=', \Carbon\Carbon::now()->startOfMonth()->toDateString());
            }

            if ($request->filled('to_date')) {
                $query->whereDate('transaction_date', '<=', $request->to_date);
            } else {
                $query->whereDate('transaction_date', '<=', \Carbon\Carbon::now()->toDateString());
            }

            // Get transactions ordered by date
            $query = SupplierTransaction::with(['supplier', 'site'])
                ->where('workspace_id', $workspaceId);

            // Apply filters
            if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('site_id') && $request->site_id !== 'all') {
                $query->where('site_id', $request->site_id);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('transaction_date', '>=', $request->from_date);
            } else {
                $query->whereDate('transaction_date', '>=', \Carbon\Carbon::now()->startOfMonth()->toDateString());
            }

            if ($request->filled('to_date')) {
                $query->whereDate('transaction_date', '<=', $request->to_date);
            } else {
                $query->whereDate('transaction_date', '<=', \Carbon\Carbon::now()->toDateString());
            }

            // Get transactions ordered by date
            $transactions = $query->orderedByDate()->get();

            // Calculate summary
            $totalPO = $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount');
            $totalPayments = $transactions->whereIn('reference_type', [SupplierTransaction::TYPE_PAYMENT, SupplierTransaction::TYPE_ADVANCE])->sum('credit');
            
            // Invoice total uses debit column (financial impact)
            $totalInvoiceAmount = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
            
            // Calculate total advances
            $totalAdvances = $transactions->where('reference_type', SupplierTransaction::TYPE_ADVANCE)->sum('credit');
            
            // Get current balance for the selected supplier or all
            if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                $currentBalance = SupplierTransaction::getCurrentBalance($request->supplier_id);
            } else {
                // For all suppliers, calculate sum of all current balances
                $supplierIds = SupplierTransaction::where('workspace_id', $workspaceId)
                    ->distinct()
                    ->pluck('supplier_id');
                $currentBalance = 0;
                foreach ($supplierIds as $supplierId) {
                    $currentBalance += SupplierTransaction::getCurrentBalance($supplierId);
                }
            }

            $summary = [
                'total_po' => $totalPO,
                'total_invoice' => $totalInvoiceAmount,
                'total_payments' => $totalPayments,
                'total_advances' => $totalAdvances,
                'current_balance' => $currentBalance,
            ];

            // Pass filters to view
            $filters = [
                'supplier_id' => $request->supplier_id ?? 'all',
                'site_id' => $request->site_id ?? 'all',
                'from_date' => $request->from_date ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                'to_date' => $request->to_date ?? \Carbon\Carbon::now()->toDateString(),
            ];

            return view('reports.supplier-ledger.index', compact(
                'transactions',
                'suppliers',
                'sites',
                'summary',
                'filters'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load supplier ledger report: ' . $e->getMessage()]);
        }
    }

    /**
     * Export supplier ledger report to PDF.
     */
    public function exportPdf(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier-ledger report')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $workspaceId = getActiveWorkSpace();
            
            // Build query similar to index
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
                $query->whereDate('transaction_date', '>=', \Carbon\Carbon::now()->startOfMonth()->toDateString());
            }

            if ($request->filled('to_date')) {
                $query->whereDate('transaction_date', '<=', $request->to_date);
            } else {
                $query->whereDate('transaction_date', '<=', \Carbon\Carbon::now()->toDateString());
            }

            $transactions = $query->orderedByDate()->get();

            $totalPO = $transactions->where('reference_type', SupplierTransaction::TYPE_PO)->sum('reference_amount');
            $totalPayments = $transactions->whereIn('reference_type', [SupplierTransaction::TYPE_PAYMENT, SupplierTransaction::TYPE_ADVANCE])->sum('credit');
            
            // Invoice total uses debit column (financial impact)
            $totalInvoiceAmount = $transactions->where('reference_type', SupplierTransaction::TYPE_INVOICE)->sum('debit');
            
            $currentBalance = $transactions->last()->balance ?? 0;

            $summary = [
                'total_po' => $totalPO,
                'total_invoice' => $totalInvoiceAmount,
                'total_payments' => $totalPayments,
                'current_balance' => $currentBalance,
            ];

            $filters = [
                'supplier_id' => $request->supplier_id ?? 'all',
                'site_id' => $request->site_id ?? 'all',
                'from_date' => $request->from_date ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                'to_date' => $request->to_date ?? \Carbon\Carbon::now()->toDateString(),
            ];

            $pdf = \PDF::loadView('reports.supplier-ledger.pdf', compact('transactions', 'summary', 'filters'));
            
            return $pdf->download('supplier-ledger-report.pdf');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to export PDF: ' . $e->getMessage()]);
        }
    }

    /**
     * Export supplier ledger report to Excel.
     */
    public function exportExcel(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier-ledger report')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $workspaceId = getActiveWorkSpace();
            
            // Build query similar to index
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
                $query->whereDate('transaction_date', '>=', \Carbon\Carbon::now()->startOfMonth()->toDateString());
            }

            if ($request->filled('to_date')) {
                $query->whereDate('transaction_date', '<=', $request->to_date);
            } else {
                $query->whereDate('transaction_date', '<=', \Carbon\Carbon::now()->toDateString());
            }

            $transactions = $query->orderedByDate()->get();

            $filename = 'supplier-ledger-report-' . date('Y-m-d') . '.xlsx';
            
            return \Excel::download(new \App\Exports\SupplierLedgerExport($transactions), $filename);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to export Excel: ' . $e->getMessage()]);
        }
    }

    /**
     * Get supplier balance for AJAX request.
     */
    public function getSupplierBalance(Request $request)
    {
        try {
            $supplierId = $request->supplier_id;
            
            if (!$supplierId) {
                return response()->json(['balance' => 0]);
            }

            $balance = LedgerHelper::getSupplierSummary($supplierId);
            
            return response()->json($balance);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
