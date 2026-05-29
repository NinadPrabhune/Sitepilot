<?php

namespace App\Http\Controllers;

use App\DataTables\ManPowerTypeDataTable;
use App\Models\ManPowerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Exception;

class ManPowerTypeController extends Controller {

//    public function __construct()
//    {
//        $this->middleware('permission:manpower-type manage')->only('index');
//        $this->middleware('permission:manpower-type create')->only(['create', 'store']);
//        $this->middleware('permission:manpower-type edit')->only(['edit', 'update']);
//        $this->middleware('permission:manpower-type delete')->only('destroy');
//    }

    /**
     * List manpower types
     *
     * @authenticated
     * @response view="manpower-type.index"
     */
    public function index(ManPowerTypeDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('man-power-type manage')) {

            return $dataTable->render('manpower-type.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show create manpower type form
     *
     * @authenticated
     * @response view="manpower-type.create"
     */
    public function create() {
        if (\Auth::user()->isAbleTo('man-power-type create')) {
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');

            return view('manpower-type.create', compact('sites'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a new manpower type
     *
     * @authenticated
     * @bodyParam name string required Manpower type name. Max 255 chars. Example: Skilled Labor
     * @response redirect to manpower-type.index
     */
    public function store(Request $request) {
        if (\Auth::user()->isAbleTo('man-power-type create')) {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            ManPowerType::create([
                'name' => $request->name,
                'status' => 1,
                'created_by' => creatorId(),
            ]);

            return redirect()->route('manpower-type.index')->with('success', __('Manpower type created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show edit manpower type form
     *
     * @authenticated
     * @urlParam manpower_type int required The manpower type ID.
     * @response view="manpower-type.edit"
     */
    public function edit(ManPowerType $manpower_type) {
        if (\Auth::user()->isAbleTo('man-power-type edit')) {

            return view('manpower-type.edit', compact('manpower_type'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update manpower type
     *
     * @authenticated
     * @urlParam manpower_type int required The manpower type ID.
     * @bodyParam name string required Manpower type name. Max 255 chars.
     * @response redirect to manpower-type.index
     */
    public function update(Request $request, ManPowerType $manpower_type) {
        if (\Auth::user()->isAbleTo('man-power-type edit')) {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            $manpower_type->update([
                'name' => $request->name,
                'created_by' => creatorId(),
            ]);

            return redirect()->route('manpower-type.index')->with('success', __('Manpower type updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Delete manpower type
     *
     * @authenticated
     * @urlParam manpower_type int required The manpower type ID.
     * @response redirect to manpower-type.index
     */
    public function destroy(ManPowerType $manpower_type): RedirectResponse {
        if (\Auth::user()->isAbleTo('man-power-type delete')) {
            try {
                // Check if related details exist
                $hasDetails = \App\Models\ManPowerDetail::where('man_power_type_id', $manpower_type->id)->exists();

                if ($hasDetails) {
                    return redirect()
                                    ->route('manpower-type.index')
                                    ->with('error', __('Cannot delete: related manpower details exist.'));
                }

                $manpower_type->delete();

                return redirect()
                                ->route('manpower-type.index')
                                ->with('success', __('Manpower type deleted successfully.'));
            } catch (ModelNotFoundException $e) {
                return redirect()
                                ->route('manpower-type.index')
                                ->with('error', __('Manpower type not found.'));
            } catch (Exception $e) {
                Log::error('Failed to delete manpower type: ' . $e->getMessage());

                return redirect()
                                ->route('manpower-type.index')
                                ->with('error', __('Failed to delete manpower type. Please try again.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show manpower type details
     *
     * @authenticated
     * @urlParam manpower_type int required The manpower type ID.
     * @response view="manpower-type.show"
     */
    public function show(ManPowerType $manpower_type) {
        if (\Auth::user()->isAbleTo('man-power-type show')) {
            return view('manpower-type.show', compact('manpower_type'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
