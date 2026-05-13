<?php

namespace App\Http\Controllers;

use App\Models\MachineryCategory;
use Illuminate\Http\Request;
use App\DataTables\MachineryCategoryDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\MachineryCategoryProduct;
use App\Models\Purchase;
use App\Events\CreateMachineryCategory;
use App\Events\DestroyMachineryCategory;
use App\Events\UpdateMachineryCategory;

class MachineryCategoryController extends Controller {

    /**
     * Display a listing of the resource.
     */
    public function index(MachineryCategoryDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('machinery-category manage')) {
            return $dataTable->render('machinery-categories.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create() {
        if (\Auth::user()->isAbleTo('machinery-category create')) {

            $customFields = null;

            return view('machinery-categories.create', compact('customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        if (\Auth::user()->isAbleTo('machinery-category create')) {
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

            $MachineryCategory = new MachineryCategory();
            $MachineryCategory->name = $request->name;
            $MachineryCategory->workspace_id = getActiveWorkSpace();
            $MachineryCategory->created_by = creatorId();
            $MachineryCategory->save();

            return redirect()->route('machinery-categories.index')->with('success', __('The Machinery Category has been created successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MachineryCategory $MachineryCategory) {
        if (\Auth::user()->isAbleTo('machinery-category show')) {
          
            
            return view('machinery-categories.show', compact('MachineryCategory'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MachineryCategory $MachineryCategory) {
        if (\Auth::user()->isAbleTo('machinery-category edit')) {
            // If you're using Blade views:
            // return view('machinery-categories.edit', compact('machinery-categories'));
//        dd($MachineryCategory);
            $customFields = null;

            return view('machinery-categories.edit', compact('MachineryCategory', 'customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MachineryCategory $MachineryCategory) {
        if (\Auth::user()->isAbleTo('machinery-category edit')) {
            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required|string|max:255|unique:machinery_categories,name,' . $MachineryCategory->id,
                    ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $MachineryCategory->name = $request->name;
            $MachineryCategory->workspace_id = getActiveWorkSpace();
            $MachineryCategory->created_by = creatorId();
            $MachineryCategory->save();

            return redirect()->route('machinery-categories.index')->with('success', __('The MachineryCategory details are updated successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MachineryCategory $MachineryCategory) {
        if (\Auth::user()->isAbleTo('machinery-category delete')) {
            $MachineryCategory->delete();

            return redirect()->route('machinery-categories.index')->with('success', __('The MachineryCategory has been deleted'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }
}
