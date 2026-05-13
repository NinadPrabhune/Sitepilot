<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Supplier Categories
 * Endpoints for supplier category management
 */
use App\Models\SupplierCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class SupplierCategoryApiController extends Controller
{
    // GET /api/supplier-categories
    public function index()
    {
        if (!Auth::user()->isAbleTo('supplier-category manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $categories = SupplierCategory::where('is_active', true)->get();
        return response()->json(['status' => 1, 'data' => $categories]);
    }

    /**
     * Store a newly created supplier category.
     *
     * @bodyParam name string required Category name. Example: Material Suppliers
     * @bodyParam description string optional Description. Example: Suppliers of construction materials
     * @bodyParam site_id integer optional Site ID. Example: 5
     * @bodyParam workspace_id integer optional Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @response {"status": 1, "data": {...}, "message": "Category created successfully"}
     */
    // POST /api/supplier-categories
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier-category create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'site_id' => 'nullable|integer',
            'workspace_id' => 'nullable|integer',
            'created_by' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $category = SupplierCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'site_id' => $request->site_id,
            'is_active' => true,
            'status' => '0',
            'workspace_id' => $request->workspace_id,
            'created_by' => $request->created_by,
        ]);

        return response()->json(['status' => 1, 'data' => $category->toArray(), 'message' => 'Category created successfully']);
    }

    // GET /api/supplier-categories/{id}
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('supplier-category show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $category = SupplierCategory::find($id);

        if (!$category) {
            return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
        }

        return response()->json(['status' => 1, 'data' => $category]);
    }

    // PUT /api/supplier-categories/{id}
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('supplier-category edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        // Prevent updating of hardcoded category (ID 1: Subcontractors)
        if ($id == 1) {
            return response()->json(['status' => 0, 'message' => 'This category cannot be updated as it is a system category used by the application.'], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'site_id' => 'nullable|integer',
            'workspace_id' => 'nullable|integer',
            'created_by' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }


        $category = SupplierCategory::find($id);

        if (!$category) {
            return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:supplier_categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $category->update($request->only('name', 'description','created_by','site_id','workspace_id'));

        return response()->json(['status' => 1, 'data' => $category->toArray(), 'message' => 'Category updated successfully']);
    }

    // DELETE /api/supplier-categories/{id}
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('supplier-category delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        // Prevent deletion of hardcoded category (ID 1: Subcontractors)
        if ($id == 1) {
            return response()->json(['status' => 0, 'message' => 'This category cannot be deleted as it is a system category used by the application.'], 403);
        }
        // Check if category is used in materials
        $existsInSuppliers = \DB::table('suppliers')
            ->where('category_id', $id)
            ->exists();

        if ($existsInSuppliers) {

             return response()->json([
                'status' => 0,
                'message' => 'Supplier Category cannot be deleted because it is used in the Suppliers Master.'
            ], 400);



        }

        $category = SupplierCategory::find($id);




        if (!$category) {
            return response()->json(['status' => 0, 'message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['status' => 1, 'message' => 'Category deleted successfully']);
    }
}
