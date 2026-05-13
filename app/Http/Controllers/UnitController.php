<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\DataTables\UnitDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\UnitProduct;
use App\Models\Purchase;
use App\Events\CreateUnit;
use App\Events\DestroyUnit;
use App\Events\UpdateUnit;

class UnitController extends Controller {

    /**
     * Display a listing of the resource.
     */
    public function index(UnitDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('material-unit manage')) {
            return $dataTable->render('units.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        if (\Auth::user()->isAbleTo('material-unit create')) {

            $customFields = null;

            return view('units.create', compact('customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {

        if (\Auth::user()->isAbleTo('material-unit create')) {
//        dd($request->all());


            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required|string|max:255',
                'symbol' => 'required|string|max:255',
                'site_id' => 'nullable|integer',
                'created_by' => 'integer',
                'workspace_id' => 'integer',
                    ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $unit = new unit();
            $unit->name = $request->name;
            $unit->symbol = $request->symbol;
            $unit->workspace_id = getActiveWorkSpace();
            $unit->created_by = creatorId();
            $unit->save();

            return redirect()->route('units.index')->with('success', __('The Unit has been created successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit) {
        if (\Auth::user()->isAbleTo('material-unit show')) {
            return response()->json($unit);
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Unit $unit) {

        if (\Auth::user()->isAbleTo('material-unit edit')) {
            // If you're using Blade views:
            // return view('units.edit', compact('unit'));


            $customFields = null;

            return view('units.edit', compact('unit', 'customFields'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Unit $unit) {
        if (\Auth::user()->isAbleTo('material-unit edit')) {
            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required|string|max:255|unique:units,name,' . $unit->id,
                'symbol' => 'required|string|max:255',
                    ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $unit->name = $request->name;
            $unit->symbol = $request->symbol;
            $unit->workspace_id = getActiveWorkSpace();
            $unit->created_by = creatorId();
            $unit->save();

            return redirect()->route('units.index')->with('success', __('The unit details are updated successfully'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit) {
        if (\Auth::user()->isAbleTo('material-unit delete')) {
            
            $existsInMaterials = \DB::table('materials')
                ->where('unit_id', $unit->id)
                ->exists();
          

            if ($existsInMaterials) {
                return redirect()->back()->with('error', 'Unit cannot be deleted as it is used in the Material Master');
            }
            
            
            $unit->delete();

            return redirect()->route('units.index')->with('success', __('The unit has been deleted'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }
}
