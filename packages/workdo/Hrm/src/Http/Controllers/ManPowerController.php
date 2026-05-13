<?php
namespace Workdo\Hrm\Http\Controllers;

use App\DataTables\ManPowerDataTable;
use App\Models\ManPowerMaster;
use App\Models\ManPowerDetail;
use App\Models\ManPowerType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ManPowerController extends Controller
{
    public function index(ManPowerDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('man-power manage')) {
            abort(403, 'Permission denied.');
        }

        try {
            return $dataTable->render('manpower.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load manpower records: '.$e->getMessage()]);
        }
    }

    public function create(Request $request)
    {
        if (!Auth::user()->isAbleTo('man-power create')) {
            abort(403, 'Permission denied.');
        }

        try {
            $manpowerTypes = ManPowerType::all();
            $suppliers = \App\Models\Supplier::where('category_id', 1)->pluck('name', 'id');
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                        ->projectonly()->get()->pluck('name', 'id');

            // Get activity_completed_id from request
            $activityCompletedId = $request->get('activity_completed_id');
            
            $completed = null;
            $activity = null;
            
            // If activity_completed_id is passed, get the completion
            if ($activityCompletedId) {
                $completed = \App\Models\ActivityCompleted::with('activity')->findOrFail($activityCompletedId);
                $activity = $completed->activity;
            }

            return view('manpower.create', compact('manpowerTypes', 'suppliers', 'sites', 'completed', 'activity', 'activityCompletedId'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: '.$e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('man-power create')) {
            abort(403, 'Permission denied.');
        }

        try {
            $validator = Validator::make($request->all(), [
                'work_date' => 'required|date', 
                'supplier_id' => 'required|integer',
                'site_id' => 'required|integer',
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'details' => 'required|array',
                'details.*.manpower_type_id' => 'required|exists:man_power_types,id',
                'details.*.count' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
                }
                if ($request->activity_completed_id) {
                    $completed = \App\Models\ActivityCompleted::find($request->activity_completed_id);
                    return redirect()->route('activities.edit', $completed->activity_id)
                        ->with('error', $validator->errors()->first());
                }
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            $totalCount = collect($request->details)->sum('count');

            // Find the completion and save via relationship
            $completed = \App\Models\ActivityCompleted::findOrFail($request->activity_completed_id);
            
            $masterData = [
                'work_date' => $request->work_date,
                'site_id' => $request->site_id,
                'workspace_id' => getActiveWorkSpace(),
                'supplier_id' => $request->supplier_id,
                'created_by' => creatorId(),
                'total_count' => $totalCount,
            ];

            // Handle reference file upload
            $referenceFilePath = null;
            if ($request->hasFile('reference_file')) {
                $fileName = time() . '_manpower_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'manpower');
                if ($upload['flag'] == 1) {
                    $referenceFilePath = $upload['url'];
                }
            }

            if ($referenceFilePath) {
                $masterData['reference_file'] = $referenceFilePath;
            }

            $master = $completed->manpowers()->create($masterData);

            foreach ($request->details as $detail) {
                ManPowerDetail::create([
                    'man_power_master_id' => $master->id,
                    'man_power_type_id' => $detail['manpower_type_id'],
                    'count' => $detail['count'],
                ]);
            }

            // Return JSON for AJAX, redirect for regular requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true, 
                    'message' => __('Manpower record created successfully'),
                    'redirect' => route('activities.edit', $completed->activity_id)
                ]);
            }
            
            return redirect()->back()->with('success', __('Manpower record created successfully.'));
            
//            return redirect()->route('activities.edit', $completed->activity_id)->with('success', __('Manpower record created successfully'));
            
            
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => false, 'message' => 'Failed: '.$e->getMessage()], 500);
            }
            if ($request->activity_completed_id) {
                $completed = \App\Models\ActivityCompleted::find($request->activity_completed_id);
                return redirect()->route('activities.edit', $completed->activity_id)
                    ->with('error', 'Failed to create manpower record: '.$e->getMessage());
            }
            return back()->withErrors(['error' => 'Failed to create manpower record: '.$e->getMessage()]);
        }
    }

    public function edit($id)
    {
        if (!Auth::user()->isAbleTo('man-power edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            $manpowerTypes = ManPowerType::all();
            $manPowerMaster = ManPowerMaster::with('details.type')->findOrFail($id);
            $suppliers = \App\Models\Supplier::where('category_id', 1)->pluck('name', 'id');
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())->where('id', getActiveProject())
                        ->projectonly()->get()->pluck('name', 'id');    
            $customFields = module_is_active('CustomField') ? \Workdo\CustomField\Entities\CustomField::get() : collect();

            return view('manpower.edit', compact('manpowerTypes','manPowerMaster', 'suppliers', 'sites', 'customFields'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form: '.$e->getMessage()]);
        }
    }

    public function update(Request $request, ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            $validator = Validator::make($request->all(), [
                'work_date' => 'required|date',            
                'supplier_id' => 'required|integer',
                'site_id' => 'required|integer',               
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'details' => 'required|array',
                'details.*.manpower_type_id' => 'required|exists:man_power_types,id',
                'details.*.count' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                if ($request->activity_id) {
                    return redirect()->route('activities.edit', $request->activity_id)
                        ->with('error', $validator->errors()->first());
                }
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            $totalCount = collect($request->details)->sum('count');

            $masterData = [
                'work_date' => $request->work_date,
                'site_id' => $request->site_id,                
                'activity_completed_id' => $request->activity_completed_id,
                'supplier_id' => $request->supplier_id,
                'created_by' => creatorId(),
                'total_count' => $totalCount,
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                // Delete old file if exists
                if ($manpower->reference_file) {
                    $filePath = public_path($manpower->reference_file);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                $fileName = time() . '_manpower_' . $manpower->id . '_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'manpower');
                if ($upload['flag'] == 1) {
                    $masterData['reference_file'] = $upload['url'];
                }
            }

            $manpower->update($masterData);

            $manpower->details()->delete();

            foreach ($request->details as $detail) {
                ManPowerDetail::create([
                    'man_power_master_id' => $manpower->id,
                    'man_power_type_id' => $detail['manpower_type_id'],
                    'count' => $detail['count'],
                ]);
            }

            return back()->with('success', __('Manpower record updated successfully'));
            
            
            
        } catch (\Exception $e) {
            if ($manpower->activity_id) {
                return redirect()->route('activities.edit', $manpower->activity_id)
                    ->with('error', 'Failed to update manpower record: '.$e->getMessage());
            }
            return back()->withErrors(['error' => 'Failed to update manpower record: '.$e->getMessage()]);
        }
    }

    public function destroy(ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power delete')) {
            abort(403, 'Permission denied.');
        }
        
        

        try {
            $activityId = $manpower->activity_id;
            $manpower->details()->delete();
            $manpower->delete();

            return redirect()->route('activities.edit', $activityId)->with('success', __('Manpower record deleted'));
        } catch (\Exception $e) {
            if ($manpower->activity_id) {
                return redirect()->route('activities.edit', $manpower->activity_id)
                    ->with('error', 'Failed to delete manpower record: '.$e->getMessage());
            }
            return back()->withErrors(['error' => 'Failed to delete manpower record: '.$e->getMessage()]);
        }
    }

    public function show(ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power show')) {
            abort(403, 'Permission denied.');
        }

        try {
            return view('manpower.show', compact('manpower'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to show manpower record: '.$e->getMessage()]);
        }
    }
}
