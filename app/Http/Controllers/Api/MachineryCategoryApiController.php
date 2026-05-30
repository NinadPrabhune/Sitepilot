<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MachineryCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @group Machinery Category
 * Endpoints for machinery category management
 */
class MachineryCategoryApiController extends Controller {

    /**
     * List Machinery Categories
     *
     * Returns all active machinery categories.
     *
     * @authenticated
     * @requiredPermission machinery-category manage
     *
     * @response status=200 scenario="Success"
     * { "status": 1, "data": [{"id": 1, "name": "Excavators", "description": "...", ...}] }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function index() {
        if (!Auth::user()->isAbleTo('machinery-category manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
        $categories = MachineryCategory::where('status', 0)->get();
//        dd($categories);
        return response()->json(['status' => 1, 'data' => $categories]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Machinery Category
     *
     * @authenticated
     * @requiredPermission machinery-category create
     *
     * @bodyParam name string required Category name. Example: Cranes
     * @bodyParam site_id integer required Site ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     *
     * @response status=201 scenario="Created"
     * { "status": 1, "data": {"id": 1, "name": "Cranes", ...}, "message": "Machinery Category created successfully" }
     * @response status=422 scenario="Validation error"
     * { "status": 0, "message": "The name field is required." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('machinery-category create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'site_id' => 'nullable|integer',
                'description' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $category = new MachineryCategory();
            $category->name = $request->name;
            $category->site_id = $request->site_id;
            $category->description = $request->description;
            $category->site_id = $request->site_id;
            $category->created_by = $request->created_by;
            $category->workspace_id = $request->workspace_id;
            $category->save();

            return response()->json(['status' => 1, 'data' => $category, 'message' => 'Machinery category created successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Machinery Category
     *
     * @authenticated
     * @requiredPermission machinery-category show
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "status": 1, "data": {"id": 1, "name": "Excavators", ...} }
     * @response status=404 scenario="Not found"
     * { "status": 0, "message": "Machinery Category not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show($id) {
        if (!Auth::user()->isAbleTo('machinery-category show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
        $category = MachineryCategory::find($id);
        
//        dd($category);
        if (!$category) {
            return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
        }

        return response()->json(['status' => 1, 'data' => $category]);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Machinery Category
     *
     * @authenticated
     * @requiredPermission machinery-category edit
     *
     * @urlParam id integer required Category ID. Example: 1
     * @bodyParam name string required Category name. Example: Tower Cranes
     * @bodyParam description string optional Description. Example: Heavy lifting equipment
     * @bodyParam site_id integer required Site ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     *
     * @response status=200 scenario="Updated"
     * { "status": 1, "data": {"id": 1, ...}, "message": "Machinery category updated successfully" }
     * @response status=422 scenario="Validation error"
     * { "status": 0, "message": "The name field is required." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('machinery-category edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $category = MachineryCategory::find($id);
            if (!$category) {
                return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:machinery_categories,name,' . $id,
                'description' => 'nullable|string',                
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $category->name = $request->name;            
            $category->description = $request->description;           
            $category->site_id = $request->site_id;
            $category->created_by = $request->created_by;
            $category->workspace_id = $request->workspace_id;
            $category->save();

            return response()->json(['status' => 1, 'data' => $category, 'message' => 'Machinery category updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Machinery Category
     *
     * @authenticated
     * @requiredPermission machinery-category delete
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @response status=200 scenario="Deleted"
     * { "status": 1, "message": "Machinery category deleted successfully" }
     * @response status=404 scenario="Not found"
     * { "status": 0, "message": "Category not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function destroy($id) {
        if (!Auth::user()->isAbleTo('machinery-category delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $category = MachineryCategory::find($id);
        if (!$category) {
            return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
        }

        $category->delete();
        return response()->json(['status' => 1, 'message' => 'Machinery category deleted successfully']);
    }
}
