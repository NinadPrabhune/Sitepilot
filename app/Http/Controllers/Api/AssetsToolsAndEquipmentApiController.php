<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssetsToolsAndEquipment;
use App\Events\CreateAssetsToolsAndEquipment;
use App\Events\UpdateAssetsToolsAndEquipment;
use App\Events\DestroyAssetsToolsAndEquipment;
use Illuminate\Support\Facades\Auth;

/**
 * @group Assets Tools & Equipment
 * Endpoints for tools and equipment management including CRUD operations
 */
class AssetsToolsAndEquipmentApiController extends Controller {

    /**
     * List Tools & Equipment
     *
     * Returns all assets, tools, and equipment filtered by site and workspace.
     *
     * @authenticated
     * @requiredPermission tools-and-equipment manage
     *
     * @queryParam site_id integer optional Filter by site/project ID. Example: 1
     * @queryParam workspace_id integer optional Filter by workspace ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * [
     *   {"id": 1, "material_id": 5, "quantity": 10, "operational_status": "active", "site_id": 1, "workspace_id": 1, ...}
     * ]
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('tools-and-equipment manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            $query = AssetsToolsAndEquipment::where('status', 0);

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $tools = $query->get();

            return response()->json($tools);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Tools & Equipment Create Data
     *
     * Retrieve tools and equipment records matching specific criteria.
     *
     * @authenticated
     * @requiredPermission tools-and-equipment create
     *
     * @bodyParam material_id integer required Material ID to filter by. Example: 5
     * @bodyParam site_id integer required Site/Project ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     *
     * @response status=200 scenario="Data found"
     * { "status": 1, "data": [{"id": 1, "material_id": 5, "quantity": 10, ...}] }
     * @response status=404 scenario="No tools found"
     * { "status": 0, "message": "No tools found for given criteria", "data": [] }
     * @response status=422 scenario="Validation error"
     * { "status": 0, "message": "Validation error message" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function createData(Request $request) {
        if (!Auth::user()->isAbleTo('tools-and-equipment create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Validate input
            $validator = \Validator::make($request->all(), [
                'material_id' => 'required|exists:materials,id',
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                            'status' => 0,
                            'message' => $validator->getMessageBag()->first(),
                                ], 422);
            }

            // Extract inputs
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');
            $materialId = $request->input('material_id');

            // Build query
            $query = AssetsToolsAndEquipment::where('status', 0);

            if ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            }

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            if ($materialId) {
                $query->where('material_id', $materialId);
            }

            $tools = $query->get();

            // Return consistent JSON
            if ($tools->isEmpty()) {
                return response()->json([
                            'status' => 0,
                            'message' => 'No tools found for given criteria',
                            'data' => [],
                                ], 404);
            }

            return response()->json([
                        'status' => 1,
                        'data' => $tools,
                            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => 0,
                        'message' => $e->getMessage(),
                            ], 500);
        }
    }

    /**
     * Create Asset/Tool/Equipment
     *
     * Create a new asset, tool, or equipment record
     *
     * @bodyParam material_id integer required Material ID. Example: 5
     * @bodyParam quantity integer required Quantity. Example: 10
     * @bodyParam operational_status string required Status (active, breakdown, scrap). Example: active
     * @bodyParam site_id integer required Site/Project ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @response {"message": "Tool/Equipment created successfully.", "data": {...}}
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('tools-and-equipment create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = \Validator::make($request->all(), [
                'material_id' => 'required|exists:materials,id',
                'quantity' => 'required|integer|min:1',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->getMessageBag()->first()], 422);
            }

            // Check if record already exists for same material + site + workspace
            $tool = AssetsToolsAndEquipment::where('material_id', $request->material_id)
                    ->where('site_id', $request->site_id)
                    ->where('workspace_id', $request->workspace_id)
                    ->first();

            if ($tool) {
                // Update existing record: increment quantity
                $tool->quantity += $request->quantity;
                $tool->operational_status = $request->operational_status;
                $tool->save();

                event(new CreateAssetsToolsAndEquipment($request, $tool));

                return response()->json([
                            'message' => 'Tool/Equipment quantity updated successfully.',
                            'data' => $tool
                                ], 200);
            } else {
                // Create new record
                $tool = AssetsToolsAndEquipment::create([
                    'material_id' => $request->material_id,
                    'quantity' => $request->quantity,
                    'operational_status' => $request->operational_status,
                    'site_id' => $request->site_id,
                    'created_by' => $request->created_by,
                    'workspace_id' => $request->workspace_id,
                ]);

                event(new CreateAssetsToolsAndEquipment($request, $tool));

                return response()->json([
                            'message' => 'Tool/Equipment created successfully.',
                            'data' => $tool
                                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Tool/Equipment
     *
     * Retrieve a specific tool or equipment record by ID.
     *
     * @authenticated
     * @requiredPermission tools-and-equipment show
     *
     * @urlParam id integer required Tool/Equipment ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * {"id": 1, "material_id": 5, "quantity": 10, "operational_status": "active", ...}
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show($id) {
        if (!Auth::user()->isAbleTo('tools-and-equipment show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $tool = AssetsToolsAndEquipment::findOrFail($id);
            return response()->json($tool);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Tool/Equipment
     *
     * Update an existing tool or equipment record. If a record with the same material+site+workspace exists, quantities are merged.
     *
     * @authenticated
     * @requiredPermission tools-and-equipment edit
     *
     * @urlParam id integer required Tool/Equipment ID. Example: 1
     *
     * @bodyParam material_id integer required Material ID (must exist in materials). Example: 5
     * @bodyParam quantity integer required Quantity (minimum 1). Example: 10
     * @bodyParam operational_status string required Status. Allowed: active, breakdown, scrap. Example: active
     * @bodyParam site_id integer required Site/Project ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     *
     * @response status=200 scenario="Updated successfully"
     * { "message": "Tool/Equipment updated successfully.", "data": {...} }
     * @response status=200 scenario="Merged with existing"
     * { "message": "Tool/Equipment merged and updated successfully.", "data": {...} }
     * @response status=422 scenario="Validation error"
     * { "error": "Validation error message" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('tools-and-equipment edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = \Validator::make($request->all(), [
                'material_id' => 'required|exists:materials,id',
                'quantity' => 'required|integer|min:1',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->getMessageBag()->first()], 422);
            }

            $tool = AssetsToolsAndEquipment::findOrFail($id);

            // Check if another record already exists with same material + site + workspace
            $existingTool = AssetsToolsAndEquipment::where('material_id', $request->material_id)
                    ->where('site_id', $request->site_id)
                    ->where('workspace_id', $request->workspace_id)
                    ->where('id', '!=', $tool->id)
                    ->first();

            if ($existingTool) {
                // Merge: increment quantity on existing record
                $existingTool->quantity += $request->quantity;
                $existingTool->operational_status = $request->operational_status;
                $existingTool->save();

                // Delete current record to avoid duplicates
                $tool->delete();

                event(new UpdateAssetsToolsAndEquipment($request, $existingTool));

                return response()->json([
                            'message' => 'Tool/Equipment merged and updated successfully.',
                            'data' => $existingTool
                                ], 200);
            } else {
                // Update current record normally
                $tool->update([
                    'material_id' => $request->material_id,
                    'quantity' => $request->quantity,
                    'operational_status' => $request->operational_status,
                    'site_id' => $request->site_id,
                    'created_by' => $request->created_by,
                    'workspace_id' => $request->workspace_id,
                ]);

                event(new UpdateAssetsToolsAndEquipment($request, $tool));

                return response()->json([
                            'message' => 'Tool/Equipment updated successfully.',
                            'data' => $tool
                                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Tool/Equipment
     *
     * Permanently delete a tool or equipment record by ID.
     *
     * @authenticated
     * @requiredPermission tools-and-equipment delete
     *
     * @urlParam id integer required Tool/Equipment ID. Example: 1
     *
     * @response status=200 scenario="Deleted successfully"
     * { "message": "Tool/Equipment deleted successfully." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function destroy($id) {
        if (!Auth::user()->isAbleTo('tools-and-equipment delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $tool = AssetsToolsAndEquipment::findOrFail($id);
            $tool->delete();

            event(new DestroyAssetsToolsAndEquipment($tool));

            return response()->json(['message' => 'Tool/Equipment deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}
