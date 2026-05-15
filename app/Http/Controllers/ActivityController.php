<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityCompleted;
use App\Models\ManPowerType;
use App\Models\DailyProgressReport;
use Illuminate\Http\Request;
use App\DataTables\ActivityDataTable;
use App\Models\WorkSpace;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class ActivityController extends Controller {

    public function index(ActivityDataTable $dataTable) {
        if (!Auth::user()->isAbleTo('activity manage')) {
            abort(403, 'Permission denied.');
        }

        try {
            return $dataTable->render('activities.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load activities: ' . $e->getMessage()]);
        }
    }

    public function create() {
        if (!Auth::user()->isAbleTo('activity create')) {
            abort(403, 'Permission denied.');
        }

        try {
            $workspaces = WorkSpace::all();
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                            ->projectonly()->get()->pluck('name', 'id');

//            if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
//                $employees = Employee::where('user_id', '=', Auth::user()->id)->where('workspace', getActiveWorkSpace())->first();
//            } else {
//                $employees = Employee::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get()->pluck('name', 'id');
//            }



            $users = getActiveProjectEmployees();

            return view('activities.create', compact('workspaces', 'sites', 'users'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: ' . $e->getMessage()]);
        }
    }

    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('activity create')) {
            abort(403, 'Permission denied.');
        }

        try {
            $request->validate([
                'assign_to' => 'required',
                'start_date' => 'required',
                'due_date' => 'required',
                'title' => 'required|string|max:255',
                'scope' => 'required|string',
                'quantity' => 'required|integer|min:0',
                'unit' => 'required|string',
                'priority' => 'required|in:low,medium,high',
                'completed_quantity' => 'array',
                'completed_quantity.*' => 'nullable|integer|min:0',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx|max:20480',
            ]);

            $totalCompleted = collect($request->input('completed_quantity', []))->sum();

            if ($totalCompleted > $request->input('quantity')) {
                return back()->withErrors(['completed_quantity' => 'Total completed quantity cannot exceed the main quantity.'])->withInput();
            }

            $activityData = [
                'assign_to' => implode(",", $request->assign_to),
                'title' => $request->input('title'),
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'scope' => $request->input('scope'),
                'quantity' => $request->input('quantity'),
                'unit' => $request->input('unit'),
                'priority' => $request->input('priority'),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'workspace_id' => getActiveWorkSpace(),
                'site_id' => getActiveProject(),
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                $fileName = time() . '_activity_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'activities');
                if ($upload['flag'] == 1) {
                    $activityData['reference_file'] = $upload['url'];
                }
            }

            $activity = Activity::create($activityData);

            foreach ($request->input('completed_quantity', []) as $qty) {
                if ($qty > 0) {
                    ActivityCompleted::create([
                        'activity_id' => $activity->id,
                        'completed_quantity' => $qty,
                        'completed_date' => now()->toDateString(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $assignTo = $request->assign_to ?? [];
            if (!empty($assignTo) && !empty($assignTo[0])) {
                try {
                    $projectName = \Workdo\Taskly\Entities\Project::find(getActiveProject())?->name ?? 'Unknown Project';
                    $createdByName = auth()->user()->name;

                    $htmlMessage = "<p>A new activity has been created for project {$projectName}.</p>";
                    $htmlMessage .= "<p><strong>Title:</strong> {$request->input('title')}</p>";
                    $htmlMessage .= "<p><strong>Scope:</strong> {$request->input('scope')}</p>";
                    $htmlMessage .= "<p><strong>Quantity:</strong> {$request->input('quantity')} {$request->input('unit')}</p>";
                    $htmlMessage .= "<p><strong>Priority:</strong> {$request->input('priority')}</p>";
                    $htmlMessage .= "<p><strong>Created By:</strong> {$createdByName}</p>";

                    $notificationService = app(NotificationService::class);
                    $notificationService->create(
                        type: 'activity_created',
                        title: "New Activity Created – {$projectName}",
                        message: $htmlMessage,
                        messageArr: [
                            'activity_id' => $activity->id,
                            'activity_title' => $request->input('title'),
                            'project_id' => getActiveProject(),
                            'project_name' => $projectName,
                            'scope' => $request->input('scope'),
                            'quantity' => $request->input('quantity'),
                            'unit' => $request->input('unit'),
                            'priority' => $request->input('priority'),
                            'created_by' => auth()->id(),
                            'created_by_name' => $createdByName,
                        ],
                        userIds: $assignTo,
                        workspaceId: getActiveWorkSpace(),
                        projectId: getActiveProject(),
                        iconType: 'info',
                        relatedId: $activity->id,
                        relatedType: 'Activity',
                        actionUrl: route('activities.show', $activity->id)
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to create activity notification', [
                        'activity_id' => $activity->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return redirect()->back()->with('success', __('Activity created successfully.'));

            // return redirect()->route('activities.index')->with('success', 'Activity created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create activity: ' . $e->getMessage()]);
        }
    }

    public function edit(Activity $activity) {
        
        
        
        
        if (!Auth::user()->isAbleTo('activity edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            $activity->load([
                'completeds',
                'completeds.manpowers.details.type',
                'completeds.manpowers.supplier',
                'completeds.dailyProgressReports.machinery',
                'completeds.allConsumptions.machinery',
                'completeds.allConsumptions.details.material',
                'completeds.allConsumptions.site',
            ]);
            
            
            $workspaces = WorkSpace::all();
           
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                            ->projectonly()->get()->pluck('name', 'id');

//            if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
//                $employees = Employee::where('user_id', '=', Auth::user()->id)->where('workspace', getActiveWorkSpace())->first();
//            } else {
//                $employees = Employee::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get()->pluck('name', 'id');
//            }


            $users = getActiveProjectEmployees();

            $manpowerTypes = ManPowerType::pluck('name', 'id');
            $manpowerSuppliers = \App\Models\Supplier::where('category_id', 1)->pluck('name', 'id');
 
            // Get DPR (Daily Progress Report) linked to this activity through completeds
            $dprList = \App\Models\DailyProgressReport::whereHas('activityCompleted', function ($query) use ($activity) {
                    $query->where('activity_id', $activity->id);
                })
                ->with('machinery')
                ->orderBy('date', 'desc')
                ->get();

            // Get Consumption linked to this activity through completeds
            $consumptionList = \App\Models\DailyConsumptionMaster::whereHas('activityCompleted', function ($query) use ($activity) {
                    $query->where('activity_id', $activity->id);
                })
                ->with('machinery', 'details.material', 'site')
                ->orderBy('consumption_date', 'desc')
                ->get();
            
            // Get machinery list for DPR
            $machineryList = \App\Models\Machinery::where('workspace_id', getActiveWorkSpace())
                ->where('site_id', getActiveProject())
                ->get()
                ->mapWithKeys(function ($m) {
                    return [
                        $m->id => [
                            'id' => $m->id,
                            'name' => $m->name,
                            'owned_by' => $m->owned_by,
                            'site_id' => $m->site_id,
                            'site_name' => $m->site?->name ?? 'N/A'
                        ]
                    ];
                });

            // Get materials for DPR
            $materials = \App\Models\Material::with('category','unit')
                ->where('category_id',2)->get()
                ->mapWithKeys(fn($m)=>[strval($m->id)=>[
                    'name'=>$m->name,'unit'=>$m->unit,
                    'category_id'=>$m->category_id,
                    'category_name'=>$m->category?->name,
                ]]);

            
           
            
            // Get all materials for consumption form
            $materials_all = \App\Models\Material::with('category','unit')
                ->where('category_id','!=',2)->get()
                ->mapWithKeys(fn($m)=>[$m->id=>[
                    'name'=>$m->name,'unit'=>$m->unit,
                    'category_id'=>$m->category_id,
                    'category_name'=>$m->category?->name,
                ]]);

            // Get machinery options for consumption form
            $machineryOptions = \App\Models\Machinery::all()
                ->mapWithKeys(fn($i)=>[$i->id=>$i->name.'('.$i->vehicle_number.')'])
                ->toArray();

            // Get next consumption number
            $maxId = \App\Models\DailyConsumptionMaster::max('id');
            $nextConsumptionNumber = 'DCM-'.str_pad(($maxId ? $maxId + 1 : 1),4,'0',STR_PAD_LEFT);
            
           
            
            return view('activities.edit', compact('activity', 'workspaces', 'sites', 'users', 'manpowerTypes', 'manpowerSuppliers', 'dprList', 'machineryList', 'materials', 'consumptionList', 'materials_all', 'machineryOptions', 'nextConsumptionNumber'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Activity $activity) {
        if (!Auth::user() || !Auth::user()->isAbleTo('activity edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            $request->validate([
                'assign_to' => 'required',
                'start_date' => 'required',
                'due_date' => 'required',
                'title' => 'required|string|max:255',
                'scope' => 'required|string',
                'quantity' => 'required|integer|min:0',
                'unit' => 'required|string',
                'priority' => 'required|in:low,medium,high',
                'completed_quantity' => 'array',                
                'completed_date' => 'array',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx|max:20480',
                
            ]);
            
            // Validate that each completed_quantity doesn't exceed main quantity
            $mainQuantity = $request->input('quantity', 0);
            $activitiesCompleted = $request->input('activities_completed', []);
            $totalCompleted = 0;
            
            foreach ($activitiesCompleted as $key => $completed) {
                $completedQuantity = $completed['completed_quantity'] ?? 0;
                $totalCompleted += $completedQuantity;
                
                if ($completedQuantity > $mainQuantity) {
                    return back()->withErrors([
                        'activities_completed.' . $key . '.completed_quantity' => 'Completed quantity cannot exceed the main quantity (' . $mainQuantity . ').'
                    ])->withInput();
                }
            }
            
            // Validate that total completed_quantity doesn't exceed main quantity
            if ($totalCompleted > $mainQuantity) {
                return back()->withErrors([
                    'activities_completed' => 'Total completed quantity (' . $totalCompleted . ') cannot exceed the main quantity (' . $mainQuantity . ').'
                ])->withInput();
            }

            $activityData = [
                'assign_to' => implode(",", $request->assign_to),
                'title' => $request->input('title'),
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'scope' => $request->input('scope'),
                'quantity' => $request->input('quantity'),
                'unit' => $request->input('unit'),
                'priority' => $request->input('priority'),
                'status' => $request->input('status', $activity->status),
                'created_by' => $activity->created_by,
                'workspace_id' => getActiveWorkSpace(),
                'site_id' => getActiveProject(),
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                // Delete old file if exists
                if ($activity->reference_file) {
                    $filePath = public_path($activity->reference_file);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                $fileName = time() . '_activity_' . $activity->id . '_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'activities');
                if ($upload['flag'] == 1) {
                    $activityData['reference_file'] = $upload['url'];
                }
            }

            $activity->update($activityData);

            // Handle activities_completed - update existing or create new
            $activitiesCompleted = $request->input('activities_completed', []);
            
            if (!empty($activitiesCompleted)) {
                // Get existing completed IDs for this activity
                $existingIds = $activity->completeds()->pluck('id')->toArray();
                $submittedIds = [];
                
                foreach ($activitiesCompleted as $key => $completed) {
                    $completedId = $completed['id'] ?? null;
                    $completedQuantity = $completed['completed_quantity'] ?? 0;
                    $completedDate = $completed['completed_date'] ?? null;
                    $completedReferenceFile = $completed['completed_reference_file'] ?? null;
                    
                    // Skip empty entries
                    if ($completedQuantity <= 0 || !$completedDate) {
                        continue;
                    }
                    
                    // Handle file upload for completed reference file
                    $referenceFilePath = null;
                    $fileKey = 'activities_completed.' . $key . '.completed_reference_file';
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        $fileName = time() . '_completed_' . $activity->id . '_' . $file->getClientOriginalName();
                        $upload = upload_file($request, $fileKey, $fileName, 'activity_completed_files');
                        if ($upload['flag'] == 1) {
                            $referenceFilePath = $upload['url'];
                        }
                    }
                    
                    // If ID is provided and exists in database, update it
                    if ($completedId && is_numeric($completedId) && in_array($completedId, $existingIds)) {
                        // Update existing record
                        $activityCompleted = ActivityCompleted::find($completedId);
                        $updateData = [
                            'completed_quantity' => $completedQuantity,
                            'completed_date' => $completedDate,
                            'created_by' => auth()->id(),
                        ];
                        // Update file only if new file is uploaded
                        if ($referenceFilePath) {
                            // Delete old file if exists
                            if ($activityCompleted->completed_reference_file) {
                                $oldFilePath = public_path($activityCompleted->completed_reference_file);
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }
                            $updateData['completed_reference_file'] = $referenceFilePath;
                        }
                        $activityCompleted->update($updateData);
                        $submittedIds[] = $completedId;
                    } else {
                        // Create new record (new_ prefix or empty ID)
                        $createData = [
                            'completed_quantity' => $completedQuantity,
                            'completed_date' => $completedDate,
                            'created_by' => auth()->id(),
                        ];
                        if ($referenceFilePath) {
                            $createData['completed_reference_file'] = $referenceFilePath;
                        }
                        $newCompleted = $activity->completeds()->create($createData);
                        $submittedIds[] = $newCompleted->id;
                    }
                }
                
                // Optionally: Delete completions that were removed in the form
                // Uncomment if you want to delete removed completions
                // $activity->completeds()->whereNotIn('id', $submittedIds)->delete();
            } else {
                // Backward compatibility: Handle old format with completed_quantity[] and completed_date[] arrays
                $completedQuantities = $request->input('completed_quantity', []);
                $completedDates = $request->input('completed_date', []);

                foreach ($completedQuantities as $index => $qty) {
                    $date = $completedDates[$index] ?? null;

                    if ($qty > 0 && $date) {
                        $activity->completeds()->create([
                            'completed_quantity' => $qty,
                            'completed_date' => $date,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            return redirect()->back()->with([
                'success' => __('Activity updated successfully.'),
                'highlight_last_completion' => true,
            ]);

            // return redirect()->route('activities.index')->with('success', 'Activity updated successfully.');
        } catch (\Exception $e) {
            // Log the error with context
            Log::error('Activity update failed', [
                'activity_id' => $activity->id,
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update activity. Please check logs for details.']);
        }
    }

    public function show($id) {
        if (!Auth::user()->isAbleTo('activity show')) {
            abort(403, 'Permission denied.');
        }

        try {
            $activity = Activity::with(['creator', 'workspace', 'site', 'completeds'])->findOrFail($id);
            return view('activities.show', compact('activity'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to show activity: ' . $e->getMessage()]);
        }
    }

    public function destroy(Activity $activity) {
        if (!Auth::user()->isAbleTo('activity delete')) {
            abort(403, 'Permission denied.');
        }

        try {
            // Don't delete completeds - they are linked to ManPower, DPR, and Consumption
            // Just delete the activity itself if needed, or keep it for historical records
            $activity->delete();

            return redirect()->route('activities.index')
                            ->with('success', 'Activity deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete activity: ' . $e->getMessage()]);
        }
    }
}
