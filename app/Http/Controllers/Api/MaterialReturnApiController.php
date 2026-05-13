<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialReturn;
use App\Models\MaterialReturnItem;
use App\Models\MaterialIssue;
use App\Models\Material;
use App\Services\StockService;
use App\Http\Requests\StoreMaterialReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @group Material Return
 * Endpoints for material return management
 */
class MaterialReturnApiController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the material returns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('material-return manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            $query = MaterialReturn::with([
                'site' => function ($q) { $q->select('id', 'name'); },
                'creator' => function ($q) { $q->select('id', 'name'); },
                'issue' => function ($q) { $q->select('id', 'issue_number', 'issue_to_type', 'issue_to_id'); },
                'items.material' => function ($q) { $q->select('id', 'name'); }
            ]);

            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId)) {
                $query->where('site_id', $siteId);
            }

            $returns = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Material Returns fetched successfully',
                'data' => $returns
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Return Index API ERROR', [
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
     * Get data for creating a new material return.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        if (!Auth::user()->isAbleTo('material-return create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            $materials = Material::with('unit')->get();
            $issues = MaterialIssue::with(['items.material'])
                ->forWorkspace($workspaceId)
                ->forSite($siteId)
                ->latestFirst()
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Material Return create data fetched successfully',
                'data' => [
                    'materials' => $materials,
                    'issues' => $issues,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Return Create API ERROR', [
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
     * Store a newly created material return.
     *
     * @bodyParam issue_id integer required Material Issue ID. Example: 1
     * @bodyParam return_date date required Return date. Example: 2024-01-15
     * @bodyParam remarks string optional Remarks. Example: Returning unused materials
     * @bodyParam items array required Array of return items.
     * @bodyParam items.*.issue_item_id integer required Issue Item ID. Example: 5
     * @bodyParam items.*.material_id integer required Material ID. Example: 10
     * @bodyParam items.*.quantity number required Return quantity. Example: 50
     * @bodyParam items.*.remarks string optional Item remarks. Example: Good condition
     * @response {"status": true, "message": "Material Return created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('material-return create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'issue_id' => 'required|exists:material_issues,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.issue_item_id' => 'required|exists:material_issue_items,id',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom validation for quantity limits
        $issueId = $request->input('issue_id');
        $items = $request->input('items', []);

        $issue = MaterialIssue::with('items')->find($issueId);
        if (!$issue) {
            return response()->json([
                'status' => false,
                'message' => 'Material Issue not found'
            ], 404);
        }

        // Group return items by issue_item_id
        $returnItemsByIssueItem = [];
        foreach ($items as $index => $item) {
            if (isset($item['issue_item_id'])) {
                $issueItemId = $item['issue_item_id'];
                if (!isset($returnItemsByIssueItem[$issueItemId])) {
                    $returnItemsByIssueItem[$issueItemId] = 0;
                }
                $returnItemsByIssueItem[$issueItemId] += floatval($item['quantity']);
            }
        }

        // Validate each issue item
        $errors = [];
        foreach ($returnItemsByIssueItem as $issueItemId => $returnQty) {
            $issueItem = $issue->items->firstWhere('id', $issueItemId);
            
            if (!$issueItem) {
                $errors["items.{$issueItemId}.issue_item_id"] = ['Invalid issue item selected.'];
                continue;
            }

            // Calculate already returned quantity for this issue item
            $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItemId)
                ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                ->where('material_returns.issue_id', $issueId)
                ->sum('material_return_items.quantity');

            // Calculate remaining quantity
            $issuedQty = floatval($issueItem->quantity);
            $remainingQty = $issuedQty - $alreadyReturnedQty;

            // Validate return quantity doesn't exceed remaining
            if ($returnQty > $remainingQty) {
                $errors["items.{$issueItemId}.quantity"] = [
                    __('Return quantity cannot exceed remaining issued quantity. Issued: :issued, Already Returned: :returned, Remaining: :remaining', [
                        'issued' => $issuedQty,
                        'returned' => $alreadyReturnedQty,
                        'remaining' => $remainingQty,
                    ])
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $errors
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

            // Create material return
            $materialReturn = MaterialReturn::create([
                'return_number' => MaterialReturn::generateReturnNumber(),
                'issue_id' => $request->issue_id,
                'site_id' => $siteId,
                'return_date' => $request->return_date,
                'status' => MaterialReturn::STATUS_COMPLETED,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
            ]);

            // Process items
            foreach ($request->items as $itemData) {
                // Create return item
                $returnItem = MaterialReturnItem::create([
                    'return_id' => $materialReturn->id,
                    'issue_item_id' => $itemData['issue_item_id'],
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Add stock back
                $this->stockService->adjustStock(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Return: ' . $materialReturn->return_number
                );
            }

            DB::commit();

            $materialReturn->load(['site', 'creator', 'issue', 'items.material']);

            return response()->json([
                'status' => true,
                'message' => 'Material Return created successfully',
                'data' => $materialReturn
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return Store API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Return creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified material return.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('material-return show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $materialReturn = MaterialReturn::with(['site', 'creator', 'issue', 'items.material.unit'])
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Material Return fetched successfully',
                'data' => $materialReturn
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Return Show API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Return not found'
            ], 404);
        }
    }

    /**
     * Update the specified material return.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('material-return edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $materialReturn = MaterialReturn::with(['items', 'issue'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'issue_id' => 'required|exists:material_issues,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.issue_item_id' => 'required|exists:material_issue_items,id',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom validation for quantity limits
        $issueId = $request->input('issue_id');
        $items = $request->input('items', []);

        $issue = MaterialIssue::with('items')->find($issueId);
        if (!$issue) {
            return response()->json([
                'status' => false,
                'message' => 'Material Issue not found'
            ], 404);
        }

        // Group return items by issue_item_id
        $returnItemsByIssueItem = [];
        foreach ($items as $index => $item) {
            if (isset($item['issue_item_id'])) {
                $issueItemId = $item['issue_item_id'];
                if (!isset($returnItemsByIssueItem[$issueItemId])) {
                    $returnItemsByIssueItem[$issueItemId] = 0;
                }
                $returnItemsByIssueItem[$issueItemId] += floatval($item['quantity']);
            }
        }

        // Validate each issue item
        $errors = [];
        foreach ($returnItemsByIssueItem as $issueItemId => $returnQty) {
            $issueItem = $issue->items->firstWhere('id', $issueItemId);
            
            if (!$issueItem) {
                $errors["items.{$issueItemId}.issue_item_id"] = ['Invalid issue item selected.'];
                continue;
            }

            // Calculate already returned quantity for this issue item (excluding current return)
            $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItemId)
                ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                ->where('material_returns.issue_id', $issueId)
                ->where('material_returns.id', '!=', $materialReturn->id)
                ->sum('material_return_items.quantity');

            // Calculate remaining quantity
            $issuedQty = floatval($issueItem->quantity);
            $remainingQty = $issuedQty - $alreadyReturnedQty;

            // Validate return quantity doesn't exceed remaining
            if ($returnQty > $remainingQty) {
                $errors["items.{$issueItemId}.quantity"] = [
                    __('Return quantity cannot exceed remaining issued quantity. Issued: :issued, Already Returned: :returned, Remaining: :remaining', [
                        'issued' => $issuedQty,
                        'returned' => $alreadyReturnedQty,
                        'remaining' => $remainingQty,
                    ])
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $errors
            ], 422);
        }

        try {
            DB::beginTransaction();

            $siteId = $request->input('site_id');

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Reverse previous stock impact
            foreach ($materialReturn->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    -$item->quantity,
                    'Material Return Reversal (Edit): ' . $materialReturn->return_number
                );
            }

            // Update material return
            $materialReturn->update([
                'issue_id' => $request->issue_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id(),
            ]);

            // Delete old items
            $materialReturn->items()->delete();

            // Process new items
            foreach ($request->items as $itemData) {
                // Create return item
                $returnItem = MaterialReturnItem::create([
                    'return_id' => $materialReturn->id,
                    'issue_item_id' => $itemData['issue_item_id'],
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Add stock back
                $this->stockService->adjustStock(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Return: ' . $materialReturn->return_number
                );
            }

            DB::commit();

            $materialReturn->load(['site', 'creator', 'issue', 'items.material']);

            return response()->json([
                'status' => true,
                'message' => 'Material Return updated successfully',
                'data' => $materialReturn
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return Update API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Return update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified material return.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('material-return delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $materialReturn = MaterialReturn::with(['items'])->findOrFail($id);

        try {
            DB::beginTransaction();

            $siteId = $materialReturn->site_id;

            // Reverse stock impact
            foreach ($materialReturn->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    -$item->quantity,
                    'Material Return Reversal (Delete): ' . $materialReturn->return_number
                );
            }

            // Delete items
            $materialReturn->items()->delete();

            // Delete main record
            $materialReturn->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Material Return deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return Delete API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Material Return deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get issue details for return form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIssueDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'issue_id' => 'required|exists:material_issues,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $issue = MaterialIssue::with(['items.material.unit'])->findOrFail($request->issue_id);

            // Calculate already returned quantities for each issue item
            $issueItemsWithReturns = $issue->items->map(function ($issueItem) use ($issue) {
                $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItem->id)
                    ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                    ->where('material_returns.issue_id', $issue->id)
                    ->sum('material_return_items.quantity');

                $issueItem->already_returned_qty = $alreadyReturnedQty;
                $issueItem->remaining_qty = $issueItem->quantity - $alreadyReturnedQty;

                return $issueItem;
            });

            $issue->items = $issueItemsWithReturns;

            return response()->json([
                'status' => true,
                'message' => 'Issue details fetched successfully',
                'data' => $issue
            ], 200);

        } catch (\Exception $e) {
            Log::error('Material Return Get Issue Details API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Issue not found'
            ], 404);
        }
    }
}
