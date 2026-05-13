<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Stock Ledger
 * Endpoints for stock ledger operations with filtering and export capabilities
 */
use App\Http\Requests\StockLedgerRequest;
use App\Services\StockService;
use App\Exports\StockLedgerExport;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Stock Ledger API Controller
 * 
 * Handles stock ledger operations via API endpoints.
 * All endpoints require Bearer Token authentication (auth:sanctum).
 * 
 * Base URL: /api/stock-ledger
 */
class StockLedgerApiController extends Controller
{
    protected $stockService;

    /**
     * StockLedgerApiController constructor.
     *
     * @param StockService $stockService
     */
    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get stock ledger list with optional filters.
     * 
     * Returns a list of stock ledger transactions based on the provided filters.
     * 
     * @param StockLedgerRequest $request
     * @return JsonResponse
     * 
     * @queryParam project_id integer optional The project ID to filter by. Must exist in projects table.
     * @queryParam material_id integer optional The material ID to filter by. Must exist in materials table.
     * @queryParam type string optional Transaction type filter. Allowed values: opening, grn, issue, transfer_in, transfer_out, adjustment.
     * @queryParam start_date string optional Start date filter (format: Y-m-d).
     * @queryParam end_date string optional End date filter (format: Y-m-d). Must be >= start_date.
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Stock ledger fetched successfully",
     *   "data": {
     *     "transactions": [],
     *     "filters": {}
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": {}
     * }
     * 
     * @response 500 {
     *   "success": false,
     *   "message": "Something went wrong"
     * }
     */
    public function index(StockLedgerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $filters = [
                'project_id' => $validated['project_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'type' => $validated['type'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ];

            $transactions = $this->stockService->getLedgerTransactions($filters);

            return response()->json([
                'success' => true,
                'message' => 'Stock ledger fetched successfully',
                'data' => [
                    'transactions' => $transactions,
                    'filters' => $filters,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stock Ledger API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get dropdown data for stock ledger form.
     * 
     * Returns projects, materials, and transaction types for populating filter dropdowns.
     * 
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Dropdown data fetched successfully",
     *   "data": {
     *     "projects": {},
     *     "materials": {},
     *     "types": {}
     *   }
     * }
     * 
     * @response 500 {
     *   "success": false,
     *   "message": "Something went wrong"
     * }
     */
    public function createData(): JsonResponse
    {
        try {
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->pluck('name', 'id');

            $materials = Material::where('status', 'active')
                ->pluck('name', 'id');

            $types = [
                'opening' => __('Opening'),
                'grn' => __('GRN'),
                'issue' => __('Issue'),
                'transfer_in' => __('Transfer In'),
                'transfer_out' => __('Transfer Out'),
                'adjustment' => __('Adjustment'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dropdown data fetched successfully',
                'data' => [
                    'projects' => $projects,
                    'materials' => $materials,
                    'types' => $types,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stock Ledger CreateData API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Export stock ledger to Excel file.
     * 
     * Downloads an Excel file containing stock ledger transactions based on the provided filters.
     * 
     * @param StockLedgerRequest $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     * 
     * @queryParam project_id integer optional The project ID to filter by. Must exist in projects table.
     * @queryParam material_id integer optional The material ID to filter by. Must exist in materials table.
     * @queryParam type string optional Transaction type filter. Allowed values: opening, grn, issue, transfer_in, transfer_out, adjustment.
     * @queryParam start_date string optional Start date filter (format: Y-m-d).
     * @queryParam end_date string optional End date filter (format: Y-m-d). Must be >= start_date.
     * 
     * @response 200 Excel file download
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": {}
     * }
     * 
     * @response 500 {
     *   "success": false,
     *   "message": "Export failed"
     * }
     */
    public function export(StockLedgerRequest $request)
    {
        try {
            $validated = $request->validated();

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
            Log::error('Stock Ledger Export API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }
}
