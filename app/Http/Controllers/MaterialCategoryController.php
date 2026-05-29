<?php

namespace App\Http\Controllers;

use App\Models\MaterialCategory;
use Illuminate\Http\Request;
use App\DataTables\MaterialCategoryDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\MaterialCategoryProduct;
use App\Models\Purchase;
use App\Events\CreateMaterialCategory;
use App\Events\DestroyMaterialCategory;
use App\Events\UpdateMaterialCategory;

class MaterialCategoryController extends Controller {

    /**
     * Display a listing of material categories
     *
     * @authenticated
     * @response view="material-categories.index"
     */
    public function index(MaterialCategoryDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('material-category manage')) {
            return $dataTable->render('material-categories.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */

    /**
     * Show create material category form
     *
     * @authenticated
     * @response view="material-categories.create"
     */
    public function create() {
        if (\Auth::user()->isAbleTo('material-category create')) {

            $customFields = null;

            return view('material-categories.create', compact('customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created material category
     *
     * @authenticated
     * @bodyParam name string required Category name. Max 255 chars. Example: Cement
     * @bodyParam site_id int optional Site ID.
     * @response redirect to material-categories.index
     */
    public function store(Request $request) {

        if (\Auth::user()->isAbleTo('material-category create')) {

//        dd($request->all());


            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required|string|max:255',
                'site_id' => 'nullable|integer',
                'created_by' => 'integer',
                'workspace_id' => 'integer',
                    ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $MaterialCategory = new MaterialCategory();
            $MaterialCategory->name = $request->name;
            $MaterialCategory->workspace_id = getActiveWorkSpace();
            $MaterialCategory->created_by = creatorId();
            $MaterialCategory->save();

            return redirect()->route('material-categories.index')->with('success', __('The Material Category has been created successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Display the specified material category (JSON)
     *
     * @authenticated
     * @urlParam material_category int required The material category ID.
     * @response {
     *   "id": 1,
     *   "name": "Cement"
     * }
     */
    public function show(MaterialCategory $MaterialCategory) {

        if (\Auth::user()->isAbleTo('material-category show')) {
            return response()->json($MaterialCategory);
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Show edit material category form
     *
     * @authenticated
     * @urlParam material_category int required The material category ID.
     * @response view="material-categories.edit"
     */
    public function edit(MaterialCategory $MaterialCategory) {
        if (\Auth::user()->isAbleTo('material-category edit')) {
            // Prevent editing of hardcoded categories (ID 2: Fuels, ID 3: Tools & Equipment)
            if (in_array($MaterialCategory->id, [2, 3])) {
                return redirect()->back()->with('error', __('This category cannot be edited as it is a system category used by the application.'));
            }
            $customFields = null;

            return view('material-categories.edit', compact('MaterialCategory', 'customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified material category
     *
     * @authenticated
     * @urlParam material_category int required The material category ID.
     * @bodyParam name string required Category name. Max 255 chars. Must be unique.
     * @response redirect to material-categories.index
     */
    public function update(Request $request, MaterialCategory $MaterialCategory) {

        if (\Auth::user()->isAbleTo('material-category edit')) {
            // Prevent updating of hardcoded categories (ID 2: Fuels, ID 3: Tools & Equipment)
            if (in_array($MaterialCategory->id, [2, 3])) {
                return redirect()->back()->with('error', __('This category cannot be updated as it is a system category used by the application.'));
            }
            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required|string|max:255|unique:material_categories,name,' . $MaterialCategory->id,
                    ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $MaterialCategory->name = $request->name;
            $MaterialCategory->workspace_id = getActiveWorkSpace();
            $MaterialCategory->created_by = creatorId();
            $MaterialCategory->save();

            return redirect()->route('material-categories.index')->with('success', __('The MaterialCategory details are updated successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Remove the specified material category
     *
     * @authenticated
     * @urlParam material_category int required The material category ID.
     * @response redirect to material-categories.index
     */
    public function destroy(MaterialCategory $MaterialCategory) {
        if (\Auth::user()->isAbleTo('material-category delete')) {
            // Prevent deletion of hardcoded categories (ID 2: Fuels, ID 3: Tools & Equipment)
            if (in_array($MaterialCategory->id, [2, 3])) {
                return redirect()->back()->with('error', __('This category cannot be deleted as it is a system category used by the application.'));
            }

             // Check if material is used in materials
            $existsInMaterials = \DB::table('materials')
                ->where('category_id', $MaterialCategory->id)
                ->exists();


            if ($existsInMaterials) {
                return redirect()->back()->with('error', 'Material Category cannot be deleted as it is used in the Material Master.');
            }


            $MaterialCategory->delete();

            return redirect()->route('material-categories.index')->with('success', __('The MaterialCategory has been deleted'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * AJAX endpoint for material categories (typeahead)
     *
     * @authenticated
     * @queryParam q string Search query to filter categories by name.
     * @response {
     *   "status": 1,
     *   "data": [{"id": 1, "name": "Cement"}]
     * }
     */
    public function getCategoriesAjax(Request $request) {
        $query = MaterialCategory::query();
        
        if ($request->has('q') && $request->q) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }
        
        $categories = $query->get(['id', 'name']);
        
        return response()->json([
            'status' => 1,
            'data' => $categories
        ]);
    }
}
