<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use App\Models\Material;
use App\Models\StockTransaction;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Opening Stock
 * Endpoints for opening stock management
 */
class OpeningStockApiController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Standard API response format
     */
    private function apiResponse(bool $success, string $message, $data = null, int $status = 200)
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * List opening stock transactions
     *
     * Returns paginated list of opening stock entries with optional filters.
     *
     * @authenticated
     * @requiredPermission opening-stock manage
     *
     * @bodyParam project_id integer optional Filter by project ID. Example: 1
     * @bodyParam material_id integer optional Filter by material ID. Example: 5
     * @bodyParam start_date string optional Start date (Y-m-d). Example: 2024-01-01
     * @bodyParam end_date string optional End date (Y-m-d). Example: 2024-12-31
     *
     * @response status=200 scenario="Success"
     * { "success": true, "message": "Opening stock transactions fetched successfully.", "data": [...] }
     * @response status=403 scenario="Permission denied"
     * { "success": false, "message": "Permission denied.", "data": null }
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('opening-stock manage')) {
            return $this->apiResponse(false, 'Permission denied.', null, 403);
        }

        try {
            $filters = [
                'project_id' => $request->input('project_id'),
                'material_id' => $request->input('material_id'),
                'type' => StockTransaction::TYPE_OPENING,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ];

            $transactions = $this->stockService->getLedgerTransactions($filters);

            $data = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'project_id' => $transaction->project_id,
                    'project_name' => $transaction->project->name ?? null,
                    'material_id' => $transaction->material_id,
                    'material_name' => $transaction->material->name ?? null,
                    'type' => $transaction->type,
                    'quantity' => $transaction->quantity,
                    'rate' => $transaction->rate,
                    'remarks' => $transaction->remarks,
                    'created_by' => $transaction->created_by,
                    'creator_name' => $transaction->creator->name ?? null,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            });

            return $this->apiResponse(true, 'Opening stock transactions fetched successfully.', $data);
        } catch (\Exception $e) {
            Log::error('Error fetching opening stock transactions: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to fetch opening stock transactions.', null, 500);
        }
    }

    /**
     * Add opening stock
     *
     * Creates a new opening stock transaction for a material at a project.
     *
     * @authenticated
     * @requiredPermission opening-stock create
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam material_id integer required Material ID. Example: 5
     * @bodyParam quantity numeric required Opening quantity. Example: 100.50
     * @bodyParam rate numeric optional Rate per unit. Example: 250.00
     * @bodyParam remarks string optional Remarks. Example: Initial stock
     *
     * @response status=201 scenario="Created"
     * { "success": true, "message": "Opening stock added successfully.", "data": {...} }
     * @response status=400 scenario="Already exists"
     * { "success": false, "message": "Opening stock already exists for this material in selected project.", "data": null }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "Validation failed.", "data": {...} }
     * @response status=403 scenario="Permission denied"
     * { "success": false, "message": "Permission denied.", "data": null }
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('opening-stock create')) {
            return $this->apiResponse(false, 'Permission denied.', null, 403);
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|integer|exists:projects,id',
                'material_id' => 'required|integer|exists:materials,id',
                'quantity' => 'required|numeric|min:0.0001',
                'rate' => 'nullable|numeric|min:0',
                'remarks' => 'nullable|string',
            ]);

            // Prevent duplicate opening stock
            $existingStock = $this->stockService->getCurrentStock(
                $validated['project_id'],
                $validated['material_id']
            );

            if ($existingStock > 0) {
                return $this->apiResponse(false, 'Opening stock already exists for this material in selected project.', null, 400);
            }

            $rate = $validated['rate'] ?? 0;

            $transaction = $this->stockService->addOpeningStock(
                $validated['project_id'],
                $validated['material_id'],
                $validated['quantity'],
                $rate,
                $validated['remarks'] ?? null
            );

            $data = [
                'id' => $transaction->id,
                'project_id' => $transaction->project_id,
                'material_id' => $transaction->material_id,
                'type' => $transaction->type,
                'quantity' => $transaction->quantity,
                'rate' => $transaction->rate,
                'remarks' => $transaction->remarks,
                'created_by' => $transaction->created_by,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ];

            return $this->apiResponse(true, 'Opening stock added successfully.', $data, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->apiResponse(false, 'Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error adding opening stock: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to add opening stock.', null, 500);
        }
    }

    /**
     * Get current stock
     *
     * Returns the current stock quantity for a specific project and material.
     *
     * @authenticated
     * @requiredPermission opening-stock manage
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam material_id integer required Material ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * { "success": true, "message": "Current stock fetched successfully.", "data": {"project_id": 1, "material_id": 5, "current_stock": 100.50} }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "Validation failed.", "data": {...} }
     * @response status=403 scenario="Permission denied"
     * { "success": false, "message": "Permission denied.", "data": null }
     */
    public function getStock(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('opening-stock manage')) {
            return $this->apiResponse(false, 'Permission denied.', null, 403);
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|integer|exists:projects,id',
                'material_id' => 'required|integer|exists:materials,id',
            ]);

            $stock = $this->stockService->getCurrentStock(
                $validated['project_id'],
                $validated['material_id']
            );

            $data = [
                'project_id' => $validated['project_id'],
                'material_id' => $validated['material_id'],
                'current_stock' => $stock,
            ];

            return $this->apiResponse(true, 'Current stock fetched successfully.', $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->apiResponse(false, 'Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error fetching current stock: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to fetch current stock.', null, 500);
        }
    }
}
