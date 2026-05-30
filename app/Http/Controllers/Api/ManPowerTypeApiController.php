<?php
namespace App\Http\Controllers\Api;

use App\Models\ManPowerType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * @group Manpower Type
 * Endpoints for manpower type management
 */
class ManPowerTypeApiController extends Controller
{
    /**
     * List Manpower Types
     *
     * Returns all active manpower types.
     *
     * @authenticated
     * @requiredPermission man-power-type manage
     *
     * @response status=200 scenario="Success"
     * [{"id": 1, "name": "Skilled", "site_id": 1, ...}]
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function index(): JsonResponse
    {
        if (!Auth::user()->isAbleTo('man-power-type manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $types = ManPowerType::all();
            return response()->json($types, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    /**
     * Create Manpower Type
     *
     * @authenticated
     * @requiredPermission man-power-type create
     *
     * @bodyParam name string required Manpower type name. Example: Skilled
     * @bodyParam site_id integer required Site ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     *
     * @response status=201 scenario="Created"
     * {"id": 1, "name": "Skilled", ...}
     * @response status=422 scenario="Validation error"
     * { "error": "The name field is required.", "message": "..." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::user()->isAbleTo('man-power-type create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',                
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',
                'created_by' => 'required|integer',
            ]);

            $type = ManPowerType::create($validated);
            return response()->json($type, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create record', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Manpower Type
     *
     * @authenticated
     * @requiredPermission man-power-type show
     *
     * @urlParam id integer required Manpower type ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * {"id": 1, "name": "Skilled", ...}
     * @response status=404 scenario="Not found"
     * { "error": "Record not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show($id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('man-power-type show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $type = ManPowerType::findOrFail($id);
            return response()->json($type, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve record'], 500);
        }
    }

    /**
     * Update Manpower Type
     *
     * @authenticated
     * @requiredPermission man-power-type edit
     *
     * @urlParam id integer required Manpower type ID. Example: 1
     * @bodyParam name string required Manpower type name. Example: Semi-Skilled
     * @bodyParam site_id integer required Site ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     *
     * @response status=200 scenario="Updated"
     * {"id": 1, "name": "Semi-Skilled", ...}
     * @response status=404 scenario="Not found"
     * { "error": "Record not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('man-power-type edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $type = ManPowerType::findOrFail($id);
            $type->update($request->all());
            return response()->json($type, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update record'], 500);
        }
    }

    /**
     * Delete Manpower Type
     *
     * @authenticated
     * @requiredPermission man-power-type delete
     *
     * @urlParam id integer required Manpower type ID. Example: 1
     *
     * @response status=204 scenario="Deleted"
     * { "message": "Deleted successfully" }
     * @response status=409 scenario="Has related records"
     * { "error": "Cannot delete: related manpower details exist." }
     * @response status=404 scenario="Not found"
     * { "error": "Record not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function destroy($id): JsonResponse
    {
        if (!Auth::user()->isAbleTo('man-power-type delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $type = ManPowerType::findOrFail($id);

            // Check if any related man_power_details exist
            $hasDetails = \App\Models\ManPowerDetail::where('man_power_type_id', $type->id)->exists();

            if ($hasDetails) {
                return response()->json([
                    'error' => 'Cannot delete: related manpower details exist.'
                ], 409); // 409 Conflict
            }

            $type->delete();

            return response()->json(['message' => 'Deleted successfully'], 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete record'], 500);
        }
    }

}

