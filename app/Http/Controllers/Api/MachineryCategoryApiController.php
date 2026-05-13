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
