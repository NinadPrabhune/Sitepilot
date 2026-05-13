<?php

namespace App\Http\Controllers;

use App\DataTables\MaterialDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Events\CreateMaterial;
use App\Events\DestroyMaterial;
use App\Events\UpdateMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller {

    /**
     * Generate auto SKU for materials
     */
    private function generateSku()
    {
        $lastMaterial = Material::latest('id')->first();
        $nextNumber = $lastMaterial ? $lastMaterial->id + 1 : 1;
        return 'MAT-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    public function index(MaterialDataTable $dataTable) {


        if (\Auth::user()->isAbleTo('material manage')) {
            return $dataTable->render('materials.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create() {
        if (\Auth::user()->isAbleTo('material create')) {
            $categories = \App\Models\MaterialCategory::pluck('name', 'id');
            $units = \App\Models\Unit::pluck('name', 'id');
            $gstMasters = \App\Models\GstMaster::where('is_active', true)->pluck('name', 'id');

            return view('materials.create', compact('categories', 'units', 'gstMasters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function store(Request $request) {

        if (\Auth::user()->isAbleTo('material create')) {

            $validator = Validator::make(
            $request->all(), [
                'name' => 'required',
                'hsn_sac' => 'nullable|string|max:20',
                'gst_master_id' => 'nullable|exists:gst_masters,id',
                'category_id' => 'required|exists:material_categories,id',
                'unit_id' => 'required|exists:units,id',
                'description' => 'nullable',
                'price' => 'required|numeric',
                'reorder_level' => 'required|integer',
                'status' => 'required',
                'image' => 'nullable|image|max:2048',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $material = new Material();
            $material->name = $request->name;
            $material->sku = $this->generateSku();
            $material->hsn_sac = $request->hsn_sac;
            $material->gst_master_id = $request->gst_master_id;
            $material->category_id = $request->category_id;
            $material->unit_id = $request->unit_id;
            $material->description = $request->description;
            $material->price = $request->price;
            $material->reorder_level = $request->reorder_level;
            $material->status = $request->status;
            $material->created_by = creatorId();

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . preg_replace('/\s+/', '_', $image->getClientOriginalName());
                $imagePath = public_path('images/material');

                // Ensure the directory exists
                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0755, true);
                }

                $image->move($imagePath, $imageName);
                $material->image = 'images/material/' . $imageName;
            }

            $material->save();

            event(new CreateMaterial($request, $material));

            return redirect()->route('material.index')->with('success', __('The Material has been created successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(Material $material) {

        $categories = \App\Models\MaterialCategory::pluck('name', 'id');
        $units = \App\Models\Unit::pluck('name', 'id');
        $gstMasters = \App\Models\GstMaster::where('is_active', true)->pluck('name', 'id');

        return view('materials.edit', compact('material', 'categories', 'units', 'gstMasters'));
    }

    public function update(Request $request, Material $material) {


        if (\Auth::user()->isAbleTo('material edit')) {

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
                'image' => 'nullable|image|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }


            $material->name = $request->name;
            $material->hsn_sac = $request->hsn_sac;
            $material->gst_master_id = $request->gst_master_id;
            $material->category_id = $request->category_id;
            $material->unit_id = $request->unit_id;
            $material->description = $request->description;
            $material->price = $request->price;
            $material->reorder_level = $request->reorder_level;
            $material->status = $request->status;
            $material->created_by = creatorId();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if (!empty($material->image)) {
                    $imagePath = public_path($material->image);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }


                $image = $request->file('image');
                $imageName = time() . '_' . preg_replace('/\s+/', '_', $image->getClientOriginalName());
                $imagePath = public_path('images/material');

                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0755, true);
                }

                $image->move($imagePath, $imageName);
                $material->image = 'images/material/' . $imageName;
            }

            $material->save();

            event(new UpdateMaterial($request, $material));
            return redirect()->route('material.index')->with('success', __('The Material details are updated successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(Material $material) {

        if (\Auth::user()->isAbleTo('material delete')) {
            
            
            // Check if material is used in purchase_invoice_items
            $existsInPurchase = \DB::table('purchase_invoice_items')
                ->where('material_id', $material->id)
                ->exists();

            // Check if material is used in assets_tools_and_equipment
            $existsInAssets = \DB::table('assets_tools_and_equipment')
                ->where('material_id', $material->id)
                ->exists();
            
            // Check if material is used in daily_consumption_details
            $existsInDailyConsumption = \DB::table('daily_consumption_details')
                ->where('material_id', $material->id)
                ->exists();

            
            if ($existsInPurchase) {
                
                return redirect()->back()->with('error', 'Material cannot be deleted because it is used in Purchase Invoices.');
            } 
            
            if ($existsInAssets) {
               
                return redirect()->back()->with('error', 'Material cannot be deleted because it is used in Tools & Equipment records.');
            }
            
            if ($existsInDailyConsumption) {
               
                return redirect()->back()->with('error', 'Material cannot be deleted because it is used in Consumption Log records.');
            }
            
            
         
            
            $material->delete();

            event(new DestroyMaterial($material));
            return redirect()->route('material.index')->with('success', 'Material deleted');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Material $material) {
        if (\Auth::user()->isAbleTo('material show')) {
            $material->load('gstMaster');
            return view('materials.show', compact('material'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getUnit($id) {
        $material = \App\Models\Material::with('unit')->find($id);
        return response()->json(['unit' => optional($material->unit)->name]);
    }

    /**
     * AJAX endpoint for materials with category filtering
     */
    public function getMaterialsAjax(Request $request) {
        $query = \App\Models\Material::with('category', 'unit');
        
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        
        if ($request->has('q') && $request->q) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }
        
        $materials = $query->get(['id', 'name', 'category_id', 'unit_id', 'price']);
        
        return response()->json([
            'status' => 1,
            'data' => $materials
        ]);
    }

    /**
     * AJAX endpoint to get single material details with price
     */
    public function getMaterialDetails($id) {
        $material = \App\Models\Material::with(['unit', 'category', 'gstMaster'])
            ->find($id);
        
        if (!$material) {
            return response()->json([
                'status' => 0,
                'message' => 'Material not found'
            ], 404);
        }
        
        return response()->json([
            'status' => 1,
            'data' => $material
        ]);
    }
}
