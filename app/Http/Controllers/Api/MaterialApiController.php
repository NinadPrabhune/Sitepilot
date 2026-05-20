<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @group Materials
 * Endpoints for material management including CRUD operations, reference data, and category-based filtering
 */
class MaterialApiController extends Controller {

    /**
     * Generate auto SKU for materials
     */
    private function generateSku()
    {
        $lastMaterial = Material::latest('id')->first();
        $nextNumber = $lastMaterial ? $lastMaterial->id + 1 : 1;
        return 'MAT-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }


    /**
     * Fetch reference/master data required to build the Create / Edit Material form.
     * Returns active GST masters, units, and categories scoped to the workspace.
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @queryParam workspace_id integer optional Workspace ID to scope lookups. Example: 1
     * @queryParam site_id integer optional Site / Project ID. Example: 5
     *
     * @response {"status": 1, "data": {"gst_masters": [{"id": 1, "name": "5% GST", "cgst": 2.5, "sgst": 2.5, "igst": 5, "total_gst": 5}], "units": [{"id": 1, "name": "Kilogram", "symbol": "kg"}], "categories": [{"id": 1, "name": "Raw Materials"}]}, "message": ""}
     * @response {"status": 0, "message": "Permission denied", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data object Reference data for material form
     * @responseField data.gst_masters array Active GST master records
     * @responseField data.gst_masters.id integer GST master ID
     * @responseField data.gst_masters.name string GST label
     * @responseField data.gst_masters.cgst number CGST percentage
     * @responseField data.gst_masters.sgst number SGST percentage
     * @responseField data.gst_masters.igst number IGST percentage
     * @responseField data.gst_masters.total_gst number Total GST percentage
     * @responseField data.units array Unit records scoped to active workspace
     * @responseField data.units.id integer Unit ID
     * @responseField data.units.name string Unit name
     * @responseField data.units.symbol string Unit symbol
     * @responseField data.categories array Material category records
     * @responseField data.categories.id integer Category ID
     * @responseField data.categories.name string Category name
     * @responseField message string Status message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated — missing or invalid token
     * @throws 500 Server error
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('material create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $workspaceId = $request->input('workspace_id');

            $gstQuery = \App\Models\GstMaster::query()
                            ->select('id', 'name', 'cgst', 'sgst', 'igst', 'total_gst')
                            ->where('is_active', true);

            $unitQuery = \App\Models\Unit::query()
                            ->select('id', 'name', 'symbol');
            if ($workspaceId) {
                $unitQuery->where('workspace_id', $workspaceId);
            }

            $categoryQuery = \App\Models\MaterialCategory::query()
                                ->select('id', 'name');
            if ($workspaceId) {
                $categoryQuery->where('workspace_id', $workspaceId);
            }

            return response()->json([
                'status'   => 1,
                'message'  => 'Material creation data retrieved successfully',
                'data'     => [
                    'gst_masters' => $gstQuery->orderBy('name')->get(),
                    'units'       => $unitQuery->orderBy('name')->get(),
                    'categories'  => $categoryQuery->orderBy('name')->get(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 0,
                'message' => 'Failed to retrieve material creation data',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all materials. Supports optional filtering by search keyword and category.
     *
     * @subgroup Listing
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @queryParam q string optional Search keyword — matches material name and SKU. Example: cement
     * @queryParam category_id integer optional Filter by material category ID. Example: 5
     *
     * @response {"status": 1, "data": [{"id": 1, "name": "Cement", "sku": "MAT-00001", "price": "450.00", "unit": {"id": 1, "name": "Kilogram", "symbol": "kg"}, "category": {"id": 5, "name": "Raw Materials"}, "gstMaster": null}]}
     * @response {"status": 0, "message": "Permission denied", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data array List of Material resources
     * @responseField data.id integer Material ID
     * @responseField data.name string Material name
     * @responseField data.sku string Auto-generated SKU
     * @responseField data.hsn_sac string HSN/SAC code
     * @responseField data.description string Material description
     * @responseField data.price number Unit price
     * @responseField data.reorder_level integer Reorder level threshold
     * @responseField data.status string active or inactive
     * @responseField data.image string|null Image path
     * @responseField data.created_at string Created timestamp
     * @responseField data.unit object Nested unit relation
     * @responseField data.unit.id integer Unit ID
     * @responseField data.unit.name string Unit name
     * @responseField data.unit.symbol string Unit symbol
     * @responseField data.category object Nested category relation
     * @responseField data.category.id integer Category ID
     * @responseField data.category.name string Category name
     * @responseField data.gstMaster object|null Nested GST relation
     * @responseField data.gstMaster.id integer GST master ID
     * @responseField data.gstMaster.name string GST label
     * @responseField data.gstMaster.total_gst number Total GST percentage
     * @responseField message string Status message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated
     *
     * @unauthenticated
     */
    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('material manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $perPage = (int) $request->get('per_page', 10);

        $query = Material::query();

        if ($q = $request->get('q')) {
            $query->where(function ($r) use ($q) {
                $r->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        if ($category = $request->get('category_id')) {
            $query->where('category_id', $category);
        }

        $query->orderBy('id', 'desc');

        $materials = $query->with(['unit', 'category', 'gstMaster'])->get();

        return response()->json(['status' => 1, 'data' => $materials]);
    }

    /**
     * Create a new material record.
     *
     * @subgroup Creation
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @bodyParam name string required Material name. Example: Cement
     * @bodyParam hsn_sac string optional HSN / SAC code (max 20 chars). Example: 2523
     * @bodyParam gst_master_id integer optional GST master ID — must exist in gst_masters table. Example: 1
     * @bodyParam category_id integer required Material category ID — must exist in material_categories. Example: 5
     * @bodyParam unit_id integer required Unit ID — must exist in units. Example: 3
     * @bodyParam description string optional Material description. Example: Portland cement
     * @bodyParam price number required Unit price. Example: 450.00
     * @bodyParam reorder_level integer required Minimum stock before triggering a re-order alert. Example: 100
     * @bodyParam status string required Status value: active or inactive. Example: active
     * @bodyParam image file optional Material image, max 2 MB, JPEG / PNG.
     * @bodyParam created_by integer required ID of the user creating this record. Example: 1
     *
     * @response {"status": 1, "data": {"id": 1, "name": "Cement", "sku": "MAT-00001", "hsn_sac": "2523", "gst_master_id": 1, "category_id": 5, "unit_id": 3, "description": "Portland cement", "price": "450.00", "reorder_level": 100, "status": "active", "image": null, "created_by": 1, "created_at": "2024-01-15T10:00:00.000000Z", "updated_at": "2024-01-15T10:00:00.000000Z"}, "message": "Material created successfully"}
     * @response {"status": 0, "message": "The name field is required.", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data object Created Material resource
     * @responseField data.id integer Material ID
     * @responseField data.name string Material name
     * @responseField data.sku string Auto-generated SKU
     * @responseField data.hsn_sac string|null HSN/SAC code
     * @responseField data.gst_master_id integer|null Foreign key to gst_masters
     * @responseField data.category_id integer Foreign key to material_categories
     * @responseField data.unit_id integer Foreign key to units
     * @responseField data.description string|null Material description
     * @responseField data.price number Unit price
     * @responseField data.reorder_level integer Reorder threshold
     * @responseField data.status string active or inactive
     * @responseField data.image string|null Stored image path
     * @responseField data.created_by integer Creator user ID
     * @responseField data.created_at string Created-at timestamp
     * @responseField data.updated_at string Updated-at timestamp
     * @responseField message string Status message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated
     * @throws 422 Validation error — returns first validation failure message
     * @throws 500 Server error — returns exception message
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('material create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'hsn_sac' => 'nullable|string|max:20',
                'gst_master_id' => 'nullable|exists:gst_masters,id',
                'category_id' => 'required|exists:material_categories,id',
                'unit_id' => 'required|exists:units,id',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'reorder_level' => 'required|integer',
                'status' => 'required|in:active,inactive',
                'image' => 'nullable|image|max:2048',
                'created_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $data = $request->only([
                'name', 'hsn_sac', 'gst_master_id', 'category_id', 'unit_id', 'description',
                'price', 'reorder_level', 'status', 'created_by'
            ]);
            $data['sku'] = $this->generateSku();

            // Handle image upload using upload_file helper
            if ($request->hasFile('image')) {
                $imageName = time() . '_' . preg_replace('/\s+/', '_', $request->file('image')->getClientOriginalName());
                $upload = upload_file($request, 'image', $imageName, 'materials');
                if ($upload['flag'] == 1) {
                    $data['image'] = $upload['url'];
                }
            }

            $material = Material::create($data);

            event(new \App\Events\CreateMaterial($request, $material));

            return response()->json(['status' => 1, 'data' => $material->toArray(), 'message' => 'Material created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve a single material by its ID.
     *
     * @subgroup Retrieval
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @urlParam id integer required Material ID. Example: 1
     *
     * @response {"status": 1, "data": {"id": 1, "name": "Cement", "sku": "MAT-00001", "price": "450.00", "unit": {"id": 1, "name": "Kilogram", "symbol": "kg"}, "category": {"id": 5, "name": "Raw Materials"}, "gstMaster": {"id": 1, "name": "5% GST", "cgst": 2.5, "sgst": 2.5, "igst": 5, "total_gst": 5}}}
     * @response {"status": 0, "message": "Material not found", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data object Material resource with nested relations
     * @responseField data.id integer Material ID
     * @responseField data.name string Material name
     * @responseField data.sku string Auto-generated SKU
     * @responseField data.hsn_sac string|null HSN/SAC code
     * @responseField data.gst_master_id integer|null GST master ID
     * @responseField data.category_id integer Category ID
     * @responseField data.unit_id integer Unit ID
     * @responseField data.description string|null Material description
     * @responseField data.price number Unit price
     * @responseField data.reorder_level integer Reorder threshold
     * @responseField data.status string active or inactive
     * @responseField data.image string|null Stored image path
     * @responseField data.created_by integer Creator user ID
     * @responseField data.created_at string Created-at timestamp
     * @responseField data.updated_at string Updated-at timestamp
     * @responseField data.unit object Nested unit relation
     * @responseField data.unit.id integer Unit ID
     * @responseField data.unit.name string Unit name
     * @responseField data.unit.symbol string Unit symbol
     * @responseField data.category object Nested category relation
     * @responseField data.category.id integer Category ID
     * @responseField data.category.name string Category name
     * @responseField data.gstMaster object|null Nested GST master relation
     * @responseField data.gstMaster.id integer GST master ID
     * @responseField data.gstMaster.name string GST label
     * @responseField data.gstMaster.cgst number CGST percentage
     * @responseField data.gstMaster.sgst number SGST percentage
     * @responseField data.gstMaster.igst number IGST percentage
     * @responseField data.gstMaster.total_gst number Total GST percentage
     * @responseField data.gstMaster.is_active boolean Whether the GST rule is currently active
     * @responseField message string Status message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated
     * @throws 404 Material not found
     * @throws 500 Server error
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('material show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $material = Material::with(['unit', 'category', 'gstMaster'])->find($id);
            if (!$material) {
                return response()->json(['status' => 0, 'message' => 'Material not found'], 404);
            }
            return response()->json(['status' => 1, 'data' => $material]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing material by its ID.
     *
     * @subgroup Update
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @urlParam id integer required Material ID. Example: 1
     *
     * @bodyParam name string required Material name. Example: Portland Cement
     * @bodyParam hsn_sac string optional HSN / SAC code. Example: 2523
     * @bodyParam gst_master_id integer optional GST master ID. Example: 2
     * @bodyParam category_id integer required Material category ID. Example: 5
     * @bodyParam unit_id integer required Unit ID. Example: 3
     * @bodyParam description string optional Material description.
     * @bodyParam price number required Unit price. Example: 480.00
     * @bodyParam reorder_level integer required Reorder threshold. Example: 150
     * @bodyParam status string required Status value: active or inactive. Example: active
     * @bodyParam image file optional Replacement material image, max 2 MB. Omit to keep current image.
     * @bodyParam created_by integer required ID of the user making this edit. Example: 1
     *
     * @response {"status": 1, "data": {"id": 1, "name": "Portland Cement", "sku": "MAT-00001", ...}, "message": "Material updated successfully"}
     * @response {"status": 0, "message": "Material not found", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data object Updated Material resource
     * @responseField data.id integer Material ID
     * @responseField data.name string Material name
     * @responseField data.sku string Auto-generated SKU
     * @responseField data.hsn_sac string|null HSN/SAC code
     * @responseField data.gst_master_id integer|null GST master ID
     * @responseField data.category_id integer Category ID
     * @responseField data.unit_id integer Unit ID
     * @responseField data.description string|null Material description
     * @responseField data.price number Unit price
     * @responseField data.reorder_level integer Reorder threshold
     * @responseField data.status string active or inactive
     * @responseField data.image string|null Stored image path
     * @responseField data.created_by integer Updating user ID
     * @responseField data.created_at string Created-at timestamp
     * @responseField data.updated_at string Updated-at timestamp
     * @responseField data.unit object Nested unit relation
     * @responseField data.category object Nested category relation
     * @responseField data.gstMaster object|null Nested GST master relation
     * @responseField message string Status message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated
     * @throws 404 Material not found
     * @throws 422 Validation error
     * @throws 500 Server error
     */
    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('material edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $material = Material::find($id);
            if (!$material) {
                return response()->json(['status' => 0, 'message' => 'Material not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'hsn_sac' => 'nullable|string|max:20',
                'gst_master_id' => 'nullable|exists:gst_masters,id',
                'category_id' => 'required|exists:material_categories,id',
                'unit_id' => 'required|exists:units,id',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'reorder_level' => 'required|integer',
                'status' => 'required|in:active,inactive',
                'image' => 'nullable|image|max:2048',
                'created_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $material->fill($request->only([
                        'name', 'hsn_sac', 'gst_master_id', 'category_id', 'unit_id', 'description',
                        'price', 'reorder_level', 'status', 'created_by'
            ]));
            $material->created_by = $request->created_by;

            // Handle image upload using upload_file helper
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if (!empty($material->image) && check_file($material->image)) {
                    $oldImagePath = base_path($material->image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imageName = time() . '_' . preg_replace('/\s+/', '_', $request->file('image')->getClientOriginalName());
                $upload = upload_file($request, 'image', $imageName, 'materials');
                if ($upload['flag'] == 1) {
                    $material->image = $upload['url'];
                }
            }

            $material->save();

            event(new \App\Events\UpdateMaterial($request, $material));

            return response()->json(['status' => 1, 'data' => $material->toArray(), 'message' => 'Material updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Soft / permanently delete a material by its ID.
     *
     * Deletion is blocked if the material is referenced by any existing
     * purchase invoice items, asset / tool records, or consumption details.
     *
     * @subgroup Deletion
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @urlParam id integer required Material ID to delete. Example: 1
     *
     * @response {"status": 1, "message": "Material deleted successfully"}
     * @response {"status": 0, "message": "Material not found"}
     * @response {"status": 0, "message": "Material cannot be deleted because it is used in Purchase Invoices."}
     * @response {"status": 0, "message": "Material cannot be deleted because it is used in Tools & Equipment records."}
     * @response {"status": 0, "message": "Material cannot be deleted because it is used in Consumption Log records."}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField message string Status or error message
     *
     * @throws 403 Permission denied
     * @throws 401 Unauthenticated
     * @throws 404 Material not found
     * @throws 400 Material is in use — returned when the material is referenced
     *             by purchase_invoice_items, assets_tools_and_equipment, or
     *             daily_consumption_details
     * @throws 500 Server error
     */
    public function destroy($id) {
        if (!Auth::user()->isAbleTo('material delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $material = Material::find($id);

            if (!$material) {
                return response()->json(['status' => 0, 'message' => 'Material not found'], 404);
            }

            $existsInPurchase = \DB::table('purchase_invoice_items')
                ->where('material_id', $material->id)
                ->exists();

            $existsInAssets = \DB::table('assets_tools_and_equipment')
                ->where('material_id', $material->id)
                ->exists();

            $existsInDailyConsumption = \DB::table('daily_consumption_details')
                ->where('material_id', $material->id)
                ->exists();

            if ($existsInPurchase) {
                return response()->json(['status' => 0, 'message' => 'Material cannot be deleted because it is used in Purchase Invoices.'], 400);
            }

            if ($existsInAssets) {
                return response()->json(['status' => 0, 'message' => 'Material cannot be deleted because it is used in Tools & Equipment records.'], 400);
            }

            if ($existsInDailyConsumption) {
                return response()->json(['status' => 0, 'message' => 'Material cannot be deleted because it is used in Consumption Log records.'], 400);
            }

            if (!empty($material->image)) {
                // Handle old image path from previous storage method
                $oldImagePath = null;
                if (str_starts_with($material->image, 'images/material')) {
                    // Old storage path (public/images/material)
                    $oldImagePath = public_path($material->image);
                } elseif (check_file($material->image)) {
                    // New storage path (uploads/materials)
                    $oldImagePath = base_path($material->image);
                }
                
                if ($oldImagePath && file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $material->delete();

            event(new \App\Events\DestroyMaterial($material));

            return response()->json(['status' => 1, 'message' => 'Material deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return the unit name for a given material.
     *
     * @subgroup Helpers
     *
     * @unauthenticated
     *
     * @urlParam id integer required Material ID. Example: 1
     *
     * @response {"status": 1, "unit": "Kilogram"}
     * @response {"status": 0, "message": "Material not found", "unit": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField unit string|null Unit name or null if material / unit missing
     * @responseField message string Error message (omitted on success)
     *
     * @throws 404 Material not found
     * @throws 500 Server error
     */
    public function getUnit($id)
    {
        try {
            $material = Material::with('unit')->find($id);
            return response()->json(['status' => 1, 'unit' => optional($material->unit)->name]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all materials that belong to a specific category.
     *
     * @subgroup Retrieval
     *
     * @authenticated
     * @header Authorization Bearer {access_token}
     *
     * @urlParam category_id integer required Material category ID. Example: 5
     *
     * @response {"status": 1, "data": [{"id": 1, "name": "Cement", "sku": "MAT-00001", "price": "450.00", "unit": {"id": 1, "name": "Kilogram", "symbol": "kg"}, "category": {"id": 5, "name": "Raw Materials"}, "gstMaster": null}]}
     * @response {"status": 0, "message": "No materials found for this category", "data": null}
     *
     * @responseField status integer 1 = success, 0 = failure
     * @responseField data array List of Material resources filtered by category
     * @responseField data.id integer Material ID
     * @responseField data.name string Material name
     * @responseField data.sku string Auto-generated SKU
     * @responseField data.price number Unit price
     * @responseField data.reorder_level integer Reorder threshold
     * @responseField data.status string active or inactive
     * @responseField data.image string|null Stored image path
     * @responseField data.unit object Nested unit relation
     * @responseField data.unit.id integer Unit ID
     * @responseField data.unit.name string Unit name
     * @responseField data.unit.symbol string Unit symbol
     * @responseField data.category object Nested category relation
     * @responseField data.category.id integer Category ID
     * @responseField data.category.name string Category name
     * @responseField data.gstMaster object|null Nested GST master relation
     * @responseField data.gstMaster.id integer GST master ID
     * @responseField data.gstMaster.name string GST label
     * @responseField data.gstMaster.total_gst number Total GST percentage
     * @responseField message string Status message
     *
     * @throws 404 No materials found for the given category
     * @throws 500 Server error
     */
    public function getByCategory($categoryId) {
        try {
            $materials = Material::with(['unit', 'category', 'gstMaster'])
                    ->where('category_id', $categoryId)
                    ->orderBy('id', 'desc')
                    ->get();

            if ($materials->isEmpty()) {
                return response()->json(['status' => 0, 'message' => 'No materials found for this category'], 404);
            }

            return response()->json(['status' => 1, 'data' => $materials]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}
