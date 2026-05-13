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

