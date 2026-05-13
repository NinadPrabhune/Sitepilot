<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @group Material Issue
 * Endpoints for material issue management to users or suppliers
 */
class MaterialIssueApiController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the material issues.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
{
    // Permission check
    if (!Auth::user()->isAbleTo('material-issue manage')) {
        return response()->json([
            'status' => false,
            'message' => 'Permission denied'
        ], 403);
    }

    try {
        $issueToType = $request->input('issue_to_type'); // Optional filter: 'user' or 'supplier'
        $workspaceId = $request->input('workspace_id') ?? (function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null);
        $siteId = $request->input('site_id') ?? (function_exists('getActiveProject') ? getActiveProject() : null);

        // Base query with relationships
        $query = MaterialIssue::with([
            'site:id,name',
            'creator:id,name',
            'items.material:id,name',
            'user:id,name',
            'supplier:id,name'
        ]);

        // Apply filters
        if (!empty($workspaceId)) {
            $query->where('workspace_id', $workspaceId);
        }
        if (!empty($siteId)) {
            $query->where('site_id', $siteId);
        }
        if (!empty($issueToType) && in_array($issueToType, ['user', 'supplier'])) {
            $query->where('issue_to_type', $issueToType);
        }

        $issues = $query->orderBy('created_at', 'desc')->get();

        // Transform user/supplier for output: only id and name
        $issues->transform(function ($issue) {
            $issue->user = $issue->issue_to_type === 'user' && $issue->user
                ? ['id' => $issue->user->id, 'name' => $issue->user->name]
                : null;

            $issue->supplier = $issue->issue_to_type === 'supplier' && $issue->supplier
                ? ['id' => $issue->supplier->id, 'name' => $issue->supplier->name]
                : null;

            return $issue;
        });

        return response()->json([
            'status' => true,
            'message' => 'Material Issues fetched successfully',
            'data' => $issues
        ], 200);

    } catch (\Exception $e) {
        Log::error('Material Issue Index API ERROR', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Internal Server Error'
        ], 500);
    }
}

    /**
     * Get data for creating a new material issue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('material-issue create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $workspaceId = $request->input('workspace_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            $materials = Material::with('unit')->get();
            $users = getActiveProjectEmployees();
            $suppliers = Supplier::where('category_id', 1)->orderBy('name')->get();

            return response()->json([
                'status' => true,
                'message' => 'Material Issue create data fetched successfully',
                'data' => [
                    'materials' => $materials,
                    'users' => $users,
                    'suppliers' => $suppliers,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Issue CreateData API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Store a newly created material issue.
     *
     * @bodyParam issue_to_type string required Issue to type (user or supplier). Example: user
     * @bodyParam issue_to_id integer required Issue to ID (user ID or supplier ID). Example: 1
     * @bodyParam issue_date date required Issue date. Example: 2024-01-15
     * @bodyParam remarks string optional Remarks. Example: Urgent requirement
     * @bodyParam items array required Array of material items.
     * @bodyParam items.*.material_id integer required Material ID. Example: 10
     * @bodyParam items.*.quantity number required Quantity. Example: 100
     * @bodyParam items.*.rate number optional Rate per unit. Example: 500.00
     * @bodyParam items.*.remarks string optional Item remarks. Example: High quality
     * @response {"status": true, "message": "Material Issue created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('material-issue create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'issue_to_type' => 'required|in:user,supplier',
            'issue_to_id' => 'required|integer',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Create material issue
            $materialIssue = MaterialIssue::create([
                'issue_number' => MaterialIssue::generateIssueNumber(),
                'site_id' => $siteId,
                'issue_to_type' => $request->issue_to_type,
                'issue_to_id' => $request->issue_to_id,
                'issue_date' => $request->issue_date,
                'status' => MaterialIssue::STATUS_COMPLETED,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
            ]);

            // Process items
            foreach ($request->items as $itemData) {
                // Validate stock availability
                $availableStock = $this->stockService->getCurrentStock($siteId, $itemData['material_id']);
                if ($availableStock < $itemData['quantity']) {
                    throw new \Exception('Insufficient stock for material ID: ' . $itemData['material_id'] . '. Available: ' . $availableStock . ', Requested: ' . $itemData['quantity']);
                }

                // Create issue item
                $issueItem = MaterialIssueItem::create([
                    'issue_id' => $materialIssue->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'rate' => $itemData['rate'] ?? null,
                    'amount' => $itemData['rate'] ? $itemData['rate'] * $itemData['quantity'] : null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Deduct stock
                $this->stockService->issueMaterial(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Issue: ' . $materialIssue->issue_number,
                    'material_issue',
                    $materialIssue->id
                );
            }

            DB::commit();

            $materialIssue->load(['site', 'creator', 'items.material']);

            return response()->json([
                'status' => true,
                'message' => 'Material Issue created successfully',
                'data' => $materialIssue
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue Store API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Issue creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified material issue.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('material-issue show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $materialIssue = MaterialIssue::with(['site', 'creator', 'items.material.unit'])
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Material Issue fetched successfully',
                'data' => $materialIssue
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Issue Show API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Issue not found'
            ], 404);
        }
    }

    /**
     * Update the specified material issue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('material-issue edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $materialIssue = MaterialIssue::with(['items'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'issue_to_type' => 'required|in:user,supplier',
            'issue_to_id' => 'required|integer',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $siteId = $request->input('site_id');

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Reverse previous stock impact
            foreach ($materialIssue->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    $item->quantity,
                    'Material Issue Reversal (Edit): ' . $materialIssue->issue_number
                );
            }

            // Update material issue
            $materialIssue->update([
                'issue_to_type' => $request->issue_to_type,
                'issue_to_id' => $request->issue_to_id,
                'issue_date' => $request->issue_date,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id(),
            ]);

            // Delete old items
            $materialIssue->items()->delete();

            // Process new items
            foreach ($request->items as $itemData) {
                // Validate stock availability
                $availableStock = $this->stockService->getCurrentStock($siteId, $itemData['material_id']);
                if ($availableStock < $itemData['quantity']) {
                    throw new \Exception('Insufficient stock for material ID: ' . $itemData['material_id'] . '. Available: ' . $availableStock . ', Requested: ' . $itemData['quantity']);
                }

                // Create issue item
                $issueItem = MaterialIssueItem::create([
                    'issue_id' => $materialIssue->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'rate' => $itemData['rate'] ?? null,
                    'amount' => $itemData['rate'] ? $itemData['rate'] * $itemData['quantity'] : null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Deduct stock
                $this->stockService->issueMaterial(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Issue: ' . $materialIssue->issue_number,
                    'material_issue',
                    $materialIssue->id
                );
            }

            DB::commit();

            $materialIssue->load(['site', 'creator', 'items.material']);

            return response()->json([
                'status' => true,
                'message' => 'Material Issue updated successfully',
                'data' => $materialIssue
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue Update API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Issue update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified material issue.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('material-issue delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $materialIssue = MaterialIssue::with(['items'])->findOrFail($id);

        try {
            DB::beginTransaction();

            $siteId = $materialIssue->site_id;

            // Reverse stock impact
            foreach ($materialIssue->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    $item->quantity,
                    'Material Issue Reversal (Delete): ' . $materialIssue->issue_number
                );
            }

            // Delete items
            $materialIssue->items()->delete();

            // Delete main record
            $materialIssue->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Material Issue deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue Delete API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Issue deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get issue_to users or suppliers based on type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIssueTo(Request $request)
    {
        $issueToType = $request->input('issue_to_type');

        if (!in_array($issueToType, ['user', 'supplier'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid issue_to_type. Must be user or supplier'
            ], 422);
        }

        try {
            if ($issueToType === 'user') {
                $data = getActiveProjectEmployees()->pluck('name', 'id')->map(function ($name, $id) {
                    return ['id' => $id, 'name' => $name];
                })->values();
            } else {
                $data = Supplier::where('category_id', 1)->orderBy('name')->get(['id', 'name']);
            }

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Issue GetIssueTo API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get available stock for a material at the current site.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|exists:materials,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $siteId = $request->input('site_id');

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            $availableStock = $this->stockService->getCurrentStock($siteId, $request->material_id);

            return response()->json([
                'status' => true,
                'message' => 'Available stock fetched successfully',
                'data' => [
                    'available_stock' => $availableStock,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Issue Get Available Stock API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
} 
