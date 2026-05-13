<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use Illuminate\Http\Request;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockLedgerExport;
use Illuminate\Support\Facades\Log;

class StockLedgerController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display stock ledger with filters.
     */
    public function index(Request $request)
    {
        try {
            if (!\Auth::user()->isAbleTo('stock-ledger manage')) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            // Validate request
            $validated = $request->validate([
                'project_id' => 'nullable|exists:projects,id',
                'material_id' => 'nullable|exists:materials,id',
                'type' => 'nullable|in:opening,grn,issue,transfer_in,transfer_out,adjustment',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Fetch dropdown data
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->pluck('name', 'id');

            $materials = Material::where('status', 'active')
                ->pluck('name', 'id');

            // Filters
            $filters = [
                'project_id' => $validated['project_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'type' => $validated['type'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ];

            // Get transactions
            $transactions = $this->stockService->getLedgerTransactions($filters);

            return view('stock-ledger.index', compact(
                'projects',
                'materials',
                'transactions',
                'filters'
            ));

        } catch (\Exception $e) {
            Log::error('Stock Ledger Error: ' . $e->getMessage());

            return redirect()->back()->with('error', __('Something went wrong.'));
        }
    }

    /**
     * Export stock ledger to Excel.
     */
    public function export(Request $request)
    {
        try {
            if (!\Auth::user()->isAbleTo('stock-ledger manage')) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            // Validate request
            $validated = $request->validate([
                'project_id' => 'nullable|exists:projects,id',
                'material_id' => 'nullable|exists:materials,id',
                'type' => 'nullable|in:opening,grn,issue,transfer_in,transfer_out,adjustment',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $filters = [
                'project_id' => $validated['project_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'type' => $validated['type'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ];

            $transactions = $this->stockService->getLedgerTransactions($filters);

            $fileName = 'stock_ledger_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(
                new StockLedgerExport($transactions),
                $fileName
            );

        } catch (\Exception $e) {
            Log::error('Stock Ledger Export Error: ' . $e->getMessage());

            return redirect()->back()->with('error', __('Export failed.'));
        }
    }
}