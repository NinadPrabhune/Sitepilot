<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\SupplierLedger;
use App\Models\Machinery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    public function index()
    {
        if (!Auth::user()->isAbleTo('reports manage')) {
            abort(403, 'Unauthorized action.');
        }

        return view('reports.index');
    }

    /**
     * Machinery Ledger Summary Report
     */
    public function machineryLedgerSummary(Request $request)
    {
        if (!Auth::user()->isAbleTo('reports view')) {
            abort(403, 'Unauthorized action.');
        }

        $machineryId = $request->input('machinery_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = MachineryLedger::where('is_reversal', false);

        if ($machineryId) {
            $query->where('machinery_id', $machineryId);
        }

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $entries = $query->with('machinery')
            ->orderBy('date')
            ->orderBy('id')
            ->paginate(50);

        // Calculate summary
        $totalCredit = $entries->where('entry_direction', 'credit')->sum('amount');
        $totalDebit = $entries->where('entry_direction', 'debit')->sum('amount');
        $finalBalance = $totalCredit - $totalDebit;

        $machineries = Machinery::all();

        return view('reports.machinery_ledger_summary', compact(
            'entries',
            'totalCredit',
            'totalDebit',
            'finalBalance',
            'machineries',
            'machineryId',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Supplier Outstanding Report
     */
    public function supplierOutstanding(Request $request)
    {
        if (!Auth::user()->isAbleTo('reports view')) {
            abort(403, 'Unauthorized action.');
        }

        $supplierId = $request->input('supplier_id');

        $query = SupplierLedger::where('is_reversal', false);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $entries = $query->with('supplier')
            ->orderBy('date')
            ->orderBy('id')
            ->paginate(50);

        // Group by supplier and calculate outstanding
        $supplierBalances = $entries->groupBy('supplier_id')->map(function ($entries) {
            $totalCredit = $entries->where('entry_direction', 'credit')->sum('amount');
            $totalDebit = $entries->where('entry_direction', 'debit')->sum('amount');
            $balance = $totalCredit - $totalDebit;
            
            return [
                'supplier' => $entries->first()->supplier,
                'total_credit' => $totalCredit,
                'total_debit' => $totalDebit,
                'balance' => $balance,
                'entries' => $entries,
            ];
        });

        $suppliers = Supplier::all();

        return view('reports.supplier_outstanding', compact(
            'supplierBalances',
            'suppliers',
            'supplierId'
        ));
    }

    /**
     * Monthly Cost Report
     */
    public function monthlyCostReport(Request $request)
    {
        if (!Auth::user()->isAbleTo('reports view')) {
            abort(403, 'Unauthorized action.');
        }

        $machineryId = $request->input('machinery_id');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month');

        $query = MachineryLedger::where('is_reversal', false)
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        if ($machineryId) {
            $query->where('machinery_id', $machineryId);
        }

        $entries = $query->with('machinery')
            ->orderBy('date')
            ->orderBy('id')
            ->paginate(50);

        // Group by entry type and calculate totals
        $costByType = $entries->where('entry_direction', 'debit')
            ->groupBy('entry_type')
            ->map(function ($entries) {
                return [
                    'type' => $entries->first()->entry_type,
                    'total' => $entries->sum('amount'),
                    'count' => $entries->count(),
                ];
            });

        $totalDebit = $entries->where('entry_direction', 'debit')->sum('amount');
        $totalCredit = $entries->where('entry_direction', 'credit')->sum('amount');

        $machineries = Machinery::all();

        return view('reports.monthly_cost', compact(
            'entries',
            'costByType',
            'totalDebit',
            'totalCredit',
            'machineries',
            'machineryId',
            'year',
            'month'
        ));
    }
}
