<?php

namespace App\Http\Controllers;

use App\Models\SupplierCategory;
use Illuminate\Http\Request;
use App\DataTables\SupplierCategoryDataTable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Events\CreateSupplierCategory;
use App\Events\DestroySupplierCategory;
use App\Events\UpdateSupplierCategory;

class SupplierCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SupplierCategoryDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('supplier-category show')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return $dataTable->render('supplier-categories.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!Auth::user()->isAbleTo('supplier-category create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $customFields = null;
        return view('supplier-categories.create', compact('customFields'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier-category create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make(
            $request->all(),
            [
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

        $SupplierCategory = new SupplierCategory();
        $SupplierCategory->name = $request->name;
        $SupplierCategory->workspace_id = getActiveWorkSpace();
        $SupplierCategory->created_by = creatorId();
        $SupplierCategory->save();

        event(new CreateSupplierCategory($SupplierCategory));

        return redirect()->route('supplier-categories.index')
            ->with('success', __('The Supplier Category has been created successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierCategory $SupplierCategory)
    {
        if (!Auth::user()->isAbleTo('supplier-category show')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return response()->json($SupplierCategory);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierCategory $SupplierCategory)
    {
        if (!Auth::user()->isAbleTo('supplier-category edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Prevent editing of hardcoded category (ID 1: Subcontractors)
        if ($SupplierCategory->id == 1) {
            return redirect()->back()->with('error', __('This category cannot be edited as it is a system category used by the application.'));
        }

        $customFields = null;
        return view('supplier-categories.edit', compact('SupplierCategory', 'customFields'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierCategory $SupplierCategory)
    {
        if (!Auth::user()->isAbleTo('supplier-category edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Prevent updating of hardcoded category (ID 1: Subcontractors)
        if ($SupplierCategory->id == 1) {
            return redirect()->back()->with('error', __('This category cannot be updated as it is a system category used by the application.'));
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255|unique:supplier_categories,name,' . $SupplierCategory->id,
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $SupplierCategory->name = $request->name;
        $SupplierCategory->workspace_id = getActiveWorkSpace();
        $SupplierCategory->created_by = creatorId();
        $SupplierCategory->save();

        event(new UpdateSupplierCategory($SupplierCategory));

        return redirect()->route('supplier-categories.index')
            ->with('success', __('The Supplier Category details are updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierCategory $SupplierCategory)
    {
        if (!Auth::user()->isAbleTo('supplier-category delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Prevent deletion of hardcoded category (ID 1: Subcontractors)
        if ($SupplierCategory->id == 1) {
            return redirect()->back()->with('error', __('This category cannot be deleted as it is a system category used by the application.'));
        }

        // Check if category is used in materials
        $existsInSuppliers = \DB::table('suppliers')
            ->where('category_id', $SupplierCategory->id)
            ->exists();

        if ($existsInSuppliers) {
            return redirect()->back()->with('error', 'Supplier Category cannot be deleted because it is used in the Suppliers Master.');
        }

        $SupplierCategory->delete();

        event(new DestroySupplierCategory($SupplierCategory));

        return redirect()->route('supplier-categories.index')
            ->with('success', __('The Supplier Category has been deleted successfully.'));
    }
}
