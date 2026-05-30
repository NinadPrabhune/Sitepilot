<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Site Stock
 * Endpoints for site-wise stock reporting and export
 */
use App\Services\StockService;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class SiteStockApiController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get site-wise stock report
     *
     * Returns stock report grouped by site/project with optional material filter.
     *
     * @authenticated
     *
     * @bodyParam project_id integer optional Filter by project ID. Example: 1
     * @bodyParam material_id integer optional Filter by material ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * { "success": true, "message": "Site stock report fetched successfully", "data": {"stock_report": [...], "filters": {...}} }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "Validation error", "errors": {...} }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|integer|exists:projects,id',
                'material_id' => 'nullable|integer|exists:materials,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get stock report from service
            $stockReport = $this->stockService->getStockReport(
                $request->project_id,
                $request->material_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Site stock report fetched successfully',
                'data' => [
                    'stock_report' => $stockReport,
                    'filters' => [
                        'project_id' => $request->project_id,
                        'material_id' => $request->material_id,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Export site stock report to Excel
     *
     * Downloads stock report as an Excel file with optional filtering.
     *
     * @authenticated
     *
     * @bodyParam project_id integer optional Filter by project ID. Example: 1
     * @bodyParam material_id integer optional Filter by material ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * (binary file download)
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "Validation error", "errors": {...} }
     */
    public function export(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|integer|exists:projects,id',
                'material_id' => 'nullable|integer|exists:materials,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get stock report from service
            $stockReport = $this->stockService->getStockReport(
                $request->project_id,
                $request->material_id
            );

            // Generate filename with timestamp
            $fileName = 'site_stock_report_' . date('Ymd_His') . '.xlsx';

            // Export to Excel
            return Excel::download(
                new \App\Exports\SiteStockExport($stockReport),
                $fileName
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get site stock form data
     *
     * Returns projects and materials for filter dropdowns.
     *
     * @authenticated
     * @requiredPermission site-stock manage
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"projects": {...}, "materials": {...}} }
     * @response status=403 scenario="Permission denied"
     * { "success": false, "message": "Permission denied." }
     */
    public function createData(Request $request): JsonResponse
    {
        try {
            // Check permission
            if (!Auth::user()->isAbleTo('site-stock manage')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.'
                ], 403);
            }

            // Get active projects
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->get()
                ->pluck('name', 'id');

            // Get active materials
            $materials = Material::where('status', 'active')
                ->pluck('name', 'id');

            return response()->json([
                'success' => true,
                'message' => 'Form data fetched successfully',
                'data' => [
                    'projects' => $projects,
                    'materials' => $materials,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }
}
