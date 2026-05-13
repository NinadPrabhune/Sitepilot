<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Units
 * Endpoints for material unit management
 */
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UnitApiController extends Controller {

    public function index() {
        if (!Auth::user()->isAbleTo('material-unit manage')) {
            return response()->json(['status' => false, 'message' => 'Permission denied'], 403);
        }
        $units = Unit::all();
        return response()->json(['status' => 1, 'data' => $units]);
    }

    /**
     * Store a newly created unit.
     *
     * @bodyParam name string required Unit name. Example: Kilogram
     * @bodyParam symbol string required Unit symbol. Example: kg
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @response {"status": 1, "data": {...}, "message": "Unit created successfully"}
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('material-unit create')) {
            return response()->json(['status' => false, 'message' => 'Permission denied'], 403);
        }
        try {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:units,name',
            'symbol' => 'required|string|max:50',
            'site_id' => 'required|integer',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $unit = Unit::create([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'site_id' => $request->site_id,
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
        ]);

        return response()->json(['status' => 1, 'data' => $unit, 'message' => 'Unit created successfully']);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id) {
        if (!Auth::user()->isAbleTo('material-unit show')) {
            return response()->json(['status' => false, 'message' => 'Permission denied'], 403);
        }
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['status' => 0, 'message' => 'Unit not found'], 404);
        }
        return response()->json(['status' => 1, 'data' => $unit]);
    }

    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('material-unit edit')) {
            return response()->json(['status' => false, 'message' => 'Permission denied'], 403);
        }
        try {
            
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['status' => 0, 'message' => 'Unit not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:units,name,' . $id,
            'symbol' => 'required|string|max:50',
            'site_id' => 'required|integer',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $unit->update([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'site_id' => $request->site_id,
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
        ]);

        return response()->json(['status' => 1, 'data' => $unit, 'message' => 'Unit updated successfully']);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id) {
        if (!Auth::user()->isAbleTo('material-unit delete')) {
            return response()->json(['status' => false, 'message' => 'Permission denied'], 403);
        }
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['status' => 0, 'message' => 'Unit not found'], 404);
        }
        
        // Check if material is used in materials
            $existsInMaterials = DB::table('materials')
                ->where('unit_id', $unit->id)
                ->exists();
          

            if ($existsInMaterials) {
                
                return response()->json([
                    'status' => 0,
                    'message' => 'Unit cannot be deleted as it is used in the Material Master'
                ], 400);
                
                
            }

        $unit->delete();

        return response()->json(['status' => 1, 'message' => 'Unit deleted successfully']);
    }
}
