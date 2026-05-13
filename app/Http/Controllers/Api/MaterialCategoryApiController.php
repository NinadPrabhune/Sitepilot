<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * @group Material Category
 * Endpoints for material category management
 */
class MaterialCategoryApiController extends Controller {

    public function index() {
        if (!Auth::user()->isAbleTo('material-category manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $categories = MaterialCategory::all();
        return response()->json(['data' => $categories], 200);
    }

    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('material-category create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $category = MaterialCategory::create([
                'name' => $request->name,
                'site_id' => $request->site_id,
                'created_by' => $request->created_by,
                'workspace_id' => $request->workspace_id,
            ]);

            return response()->json(['status' => 1, 'data' => $category, 'message' => 'Material Category created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id) {
        if (!Auth::user()->isAbleTo('material-category show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $MaterialCategory = MaterialCategory::find($id);
            if (!$MaterialCategory) {
                return response()->json(['status' => 0, 'message' => 'Material Category not found'], 404);
            }
            return response()->json(['status' => 1, 'data' => $MaterialCategory]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, MaterialCategory $materialCategory) {
        if (!Auth::user()->isAbleTo('material-category edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        // Prevent updating of hardcoded categories (ID 2: Fuels, ID 3: Tools & Equipment)
        if (in_array($materialCategory->id, [2, 3])) {
            return response()->json(['status' => 0, 'message' => 'This category cannot be updated as it is a system category used by the application.'], 403);
        }
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:material_categories,name,' . $materialCategory->id,
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $materialCategory->update([
                'name' => $request->name,
                'site_id' => $request->site_id,
                'created_by' => $request->created_by,
                'workspace_id' => $request->workspace_id,
            ]);

            return response()->json(['status' => 1, 'data' => $materialCategory, 'message' => 'Material Category Updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id) {
        if (!Auth::user()->isAbleTo('material-category delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        // Prevent deletion of hardcoded categories (ID 2: Fuels, ID 3: Tools & Equipment)
        if (in_array($id, [2, 3])) {
            return response()->json(['status' => 0, 'message' => 'This category cannot be deleted as it is a system category used by the application.'], 403);
        }
        try {
            $MaterialCategory = MaterialCategory::find($id);
            if (!$MaterialCategory) {
                return response()->json(['status' => 0, 'message' => 'Material Category not found'], 404);
            }


             // Check if material is used in materials
            $existsInMaterials = \DB::table('materials')
                ->where('category_id', $MaterialCategory->id)
                ->exists();


            if ($existsInMaterials) {

                return response()->json([
                    'status' => 0,
                    'message' => 'Material Category cannot be deleted as it is used in the Material Master'
                ], 400);


            }

            $MaterialCategory->delete();

            return response()->json(['status' => 1, 'message' => 'Material Category deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}
