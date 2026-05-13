<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * @group Materials
 * Endpoints for material management including CRUD operations and category-based filtering
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

    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('material manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $perPage = (int) $request->get('per_page', 10);

        $query = Material::query(); // ✅ Use query builder

        if ($q = $request->get('q')) {
            $query->where(function ($r) use ($q) {
                $r->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        if ($category = $request->get('category_id')) {
            $query->where('category_id', $category);
        }

        // Sort by latest created_at
        $query->orderBy('id', 'desc');

        // $materials = $query->with(['unit', 'category'])->paginate($perPage); // 🔒 Pagination temporarily disabled
        $materials = $query->with(['unit', 'category', 'gstMaster'])->get(); // ✅ Fetch all results without pagination

        return response()->json(['status' => 1, 'data' => $materials]);
    }

    /**
     * Create Material
     *
     * Create a new material in the system
     *
     * @bodyParam name string required Material name. Example: Cement
     * @bodyParam hsn_sac string optional HSN/SAC code. Example: 2523
     * @bodyParam gst_master_id integer optional GST master ID. Example: 1
     * @bodyParam category_id integer required Material category ID. Example: 5
     * @bodyParam unit_id integer required Unit ID. Example: 3
     * @bodyParam description string optional Material description. Example: Portland cement
     * @bodyParam price number required Material price. Example: 450.00
     * @bodyParam reorder_level integer required Reorder level. Example: 100
     * @bodyParam status string required Status (active/inactive). Example: active
     * @bodyParam image file optional Material image (max 2MB).
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @response {"status": 1, "data": {...}, "message": "Material created successfully"}
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

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/material', 'public');
                $data['image'] = $path;
            }

            $material = Material::create($data);

            event(new \App\Events\CreateMaterial($request, $material));

            return response()->json(['status' => 1, 'data' => $material->toArray(), 'message' => 'Material created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id) {
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

            if ($request->hasFile('image')) {
                if (!empty($material->image) && Storage::disk('public')->exists($material->image)) {
                    Storage::disk('public')->delete($material->image);
                }
                $path = $request->file('image')->store('images/material', 'public');
                $material->image = $path;
            }

            $material->save();

            event(new \App\Events\UpdateMaterial($request, $material));

            return response()->json(['status' => 1, 'data' => $material->toArray(), 'message' => 'Material updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id) {
        if (!Auth::user()->isAbleTo('material delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $material = Material::find($id);

            if (!$material) {
                return response()->json(['status' => 0, 'message' => 'Material not found'], 404);
            }

            // Check if material is linked in purchase_invoice_items
            $existsInPurchase = \DB::table('purchase_invoice_items')
                ->where('material_id', $material->id)
                ->exists();

            // Check if material is linked in assets_tools_and_equipment
            $existsInAssets = \DB::table('assets_tools_and_equipment')
                ->where('material_id', $material->id)
                ->exists();

            // Check if material is used in daily_consumption_details
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
            

            // Delete image if exists
            if (!empty($material->image) && Storage::disk('public')->exists($material->image)) {
                Storage::disk('public')->delete($material->image);
            }

            $material->delete();

            event(new \App\Events\DestroyMaterial($material));

            return response()->json(['status' => 1, 'message' => 'Material deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }


    public function getUnit($id) {

        try {

            $material = Material::with('unit')->find($id);
            return response()->json(['status' => 1, 'unit' => optional($material->unit)->name]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

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
