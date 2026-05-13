<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machinery;
use App\Http\Resources\MachineryResourceLegacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @group Machinery (Legacy)
 * Legacy endpoints for backward compatibility with existing API consumers
 * @deprecated Use new MachineryApiController for updated functionality
 */
class MachineryApiControllerLegacy extends Controller
{
    /**
     * Display a listing of the machinery (legacy format).
     * 
     * @deprecated Use /api/machineries instead
     * @queryParam site_id integer optional Filter by site ID. Example: 1
     * @queryParam workspace_id integer optional Filter by workspace ID. Example: 1
     * @response {"status": 1, "data": [{"id": 1, "name": "Excavator JCB", "category_id": 1, ...}]}
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $query = Machinery::query();

            // Apply filters
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            if (!empty($siteId)) {
                $query->where('site_id', $siteId);
            }

            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            $machineries = $query->get();

            return response()->json([
                'status' => 1,
                'data'   => MachineryResourceLegacy::collection($machineries),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching machineries (legacy): ' . $e->getMessage());

            return response()->json([
                'status'  => 0,
                'message' => 'Failed to fetch machineries.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified machinery (legacy format).
     * 
     * @deprecated Use /api/machineries/{id} instead
     * @urlParam id integer required Machinery ID. Example: 1
     * @response {"status": 1, "data": {"id": 1, "name": "Excavator JCB", "category_id": 1, ...}}
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('machinery show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        
        $machinery = Machinery::find($id);
        if (!$machinery) {
            return response()->json(['status' => 0, 'message' => 'Machinery not found'], 404);
        }

        return response()->json(['status' => 1, 'data' => new MachineryResourceLegacy($machinery)]);
    }
}
