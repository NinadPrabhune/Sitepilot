<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;

/**
 * @group Activities
 * Endpoints for activity management including creation, completion tracking, and progress monitoring
 */
class ActivityApiController extends Controller {

    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('activity manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $workspaceId = $request->workspace_id;
            $siteId = $request->site_id;

            $activities = Activity::with([
                        'workspace:id,name',
                        'site:id,name',
                        'completeds:id,activity_id,completed_quantity,completed_date,completed_reference_file,created_by',
                        'completeds.creator:id,name',
                        'completeds.manpowers.details.type:id,name',
                        'completeds.manpowers.supplier:id,name',
                        'completeds.dailyProgressReports.machinery:id,name',
                        'completeds.dailyConsumptions.machinery:id,name',
                        'completeds.dailyConsumptions.details.material:id,name',
                        'completeds.dailyConsumptions.site:id,name',
                    ])
                    ->when($workspaceId && $workspaceId != 0, function ($q) use ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    })
                    ->when($siteId && $siteId != 0, function ($q) use ($siteId) {
                        $q->where('site_id', $siteId);
                    })
                    ->get();

            $formatted = $activities->map(function ($activity) {

                $totalQty = (int) $activity->quantity;
                $completedQty = (int) $activity->completeds->sum('completed_quantity');

                // ✅ Safe percentage calculation
                $percentage = $totalQty > 0 ? round(($completedQty / $totalQty) * 100, 2) : 0;

                // Only include manpowers, dailyProgressReports, dailyConsumptions if completeds exist
                $manpowers = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->manpowers : [];
                $dailyProgress = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->dailyProgressReports : [];
                $consumptions = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->dailyConsumptions : [];

                // Get assign_to users
                $assignToUsers = $activity->assign_to
                    ? \App\Models\User::whereIn('id', explode(',', $activity->assign_to))
                        ->select('id', 'name')
                        ->get()
                    : [];

                return [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'scope' => $activity->scope,
                    'quantity' => $totalQty,
                    'unit' => $activity->unit,
                    'priority' => $activity->priority,
                    'status' => $activity->status,
                    'completed_qty' => $completedQty,
                    'completion_percentage' => $percentage,
                    'is_completed' => ($percentage >= 100),
                    'workspace' => $activity->workspace,
                    'site' => $activity->site,
                    'completeds' => $activity->completeds,
                    'manpowers' => $manpowers,
                    'daily_progress' => $dailyProgress,
                    'consumptions' => $consumptions,
                    'reference_file' => $activity->reference_file,
                    'assign_to' => $assignToUsers,
                ];
            });

            return response()->json([
                        'status' => true,
                        'data' => [
                            'pending' => $formatted->where('is_completed', false)->values(),
                            'completed' => $formatted->where('is_completed', true)->values(),
                        ]
                            ], 200);
        } catch (\Throwable $e) {

            \Log::error('Activity Index Error: ' . $e->getMessage());

            return response()->json([
                        'status' => false,
                        'message' => 'Failed to fetch activities.'
                            ], 500);
        }
    }

    public function createData(Request $request) {
        if (!Auth::user()->isAbleTo('activity create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $created_by = $request->input('created_by');

            // Fetch workspaces and sites (projects) for dropdowns
            $workspaces = WorkSpace::all(['id', 'name']);
            $sites = Project::where('workspace', $workspaceId)
                    ->projectonly()
                    ->get(['id', 'name']);

            // Define static options (like priority)
            $priorities = ['low', 'medium', 'high'];
            $users = getActiveProjectEmployees();

            return response()->json([
                        'status' => true,
                        'message' => 'Form metadata fetched successfully',
                        'workspaces' => $workspaces,
                        'sites' => $sites,
                        'priorities' => $priorities,
                        'users' => $users,
                            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => false,
                        'message' => 'Failed to fetch form metadata',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    /**
     * Create Activity
     *
     * Create a new activity in the system
     *
     * @bodyParam assign_to array required Array of user IDs to assign. Example: [1, 2, 3]
     * @bodyParam title string required Activity title. Example: Foundation work
     * @bodyParam start_date date required Start date. Example: 2024-01-01
     * @bodyParam due_date date required Due date. Example: 2024-01-31
     * @bodyParam scope string required Activity scope/description. Example: Building foundation for block A
     * @bodyParam quantity integer required Total quantity. Example: 100
     * @bodyParam unit string required Unit of measurement. Example: sqft
     * @bodyParam priority string required Priority level (low, medium, high). Example: high
     * @bodyParam completed_quantity integer optional Initial completed quantity. Example: 0
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam site_id integer required Site/Project ID. Example: 5
     * @bodyParam reference_file file optional Reference document (max 20MB).
     * @response {"status": true, "message": "Activity created successfully", "data": {...}}
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('activity create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $request->validate([
                'assign_to' => 'required|array|min:1',
                'title' => 'required|string|max:255',
                'start_date' => 'required',
                'due_date' => 'required',
                'scope' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'unit' => 'required|string',
                'priority' => 'required|in:low,medium,high',
                'completed_quantity' => 'nullable|integer|min:0',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'site_id' => 'required|exists:projects,id',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx|max:20480',
            ]);

            $totalCompleted = $request->input('completed_quantity');

            if ($totalCompleted > $request->input('quantity')) {
                return response()->json([
                            'status' => false,
                            'message' => 'Total completed quantity cannot exceed the main quantity.',
                                ], 422);
            }

            // ===== DEBUG LOG =====
            \Log::info('Activity Store Request Data', [
                'request_data' => $request->all()
            ]);

            // Convert assign_to array to comma-separated string
            $assignToString = is_array($request->assign_to) ? implode(',', $request->assign_to) : $request->assign_to;

            \Log::info('Assign_to processed value', [
                'assign_to_string' => $assignToString
            ]);

            $activityData = [
                'assign_to' => $assignToString,
                'title' => $request->title,
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'scope' => $request->scope,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
                'priority' => $request->priority,
                'status' => 'pending',
                'created_by' => $request->created_by,
                'workspace_id' => $request->workspace_id,
                'site_id' => $request->site_id,
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                $fileName = time() . '_activity_api_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'activities');
                if ($upload['flag'] == 1) {
                    $activityData['reference_file'] = $upload['url'];
                }
            }

            $activity = Activity::create($activityData);

            \Log::info('Activity created successfully', [
                'activity_id' => $activity->id,
                'assign_to' => $activity->assign_to
            ]);

            if ($totalCompleted > 0) {
                $completed = ActivityCompleted::create([
                    'activity_id' => $activity->id,
                    'completed_quantity' => $totalCompleted,
                    'completed_date' => now()->toDateString(),
                    'created_by' => $request->created_by,
                ]);

                \Log::info('ActivityCompleted created', [
                    'completed_id' => $completed->id,
                    'completed_quantity' => $completed->completed_quantity
                ]);
            }

            return response()->json([
                        'status' => true,
                        'message' => 'Activity created successfully',
                        'data' => $activity->load(['completeds', 'completeds.creator']),
                            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                        'status' => false,
                        'message' => 'Failed to create activity',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    public function createProgress(Request $request) {
        try {
            // Validation
            $request->validate([
                'site_id' => 'required|exists:projects,id', // adjust table name if needed
                'workspace_id' => 'required|integer',
                'activity_id' => 'required|integer',
            ]);

            // Get filters from request
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $activityId = $request->input('activity_id');

            // Fetch workspaces and sites (projects) for dropdowns
            $workspaces = WorkSpace::all(['id', 'name']);
            $sites = Project::where('workspace', $workspaceId)
                    ->projectonly()
                    ->get(['id', 'name']);

            // Define static options (like priority)
            $priorities = ['low', 'medium', 'high'];

            // Build query with eager loading
            $query = Activity::with(['completeds', 'site']);

            // Apply filters if provided
            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            if (!empty($activityId) && $activityId != 0) {
                $query->where('id', $activityId);
            }

            // Fetch activities
            $activities = $query->get();

            // Correct way to sum completed quantities across all activities
            $alreadyCompleted = $activities->pluck('completeds')
                    ->flatten()
                    ->sum('completed_quantity');

            return response()->json([
                        'status' => true,
                        'message' => 'Progress data fetched successfully',
                        'workspaces' => $workspaces,
                        'sites' => $sites,
                        'priorities' => $priorities,
                        'activities' => $activities,
                        'alreadyCompleted' => $alreadyCompleted,
                            ], 200);
        } catch (\Exception $e) {
            \Log::error('createProgress error: ' . $e->getMessage());

            return response()->json([
                        'status' => false,
                        'message' => 'Failed to fetch progress data',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    public function storeProgress(Request $request) {
        if (!Auth::user()->isAbleTo('activity manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $request->validate([
                'workspace_id' => 'required|integer',
                'site_id' => 'required|integer',
                'completed_quantity' => 'required|integer|min:1',
                'date' => 'required|date',
                'activity_id' => 'required|integer',
                'completed_reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx|max:20480',
            ]);

            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $activityId = $request->input('activity_id');
            $totalCompleted = $request->input('completed_quantity');
            $date = $request->input('date');

            // Fetch the specific activity with relations
            $activity = Activity::with(['completeds', 'site'])
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->findOrFail($activityId);

            // Calculate already completed + new completed
            $alreadyCompleted = $activity->completeds->sum('completed_quantity');
            $newTotal = $alreadyCompleted + $totalCompleted;

            if ($newTotal > $activity->quantity) {
                return response()->json([
                            'status' => false,
                            'message' => 'Total completed quantity cannot exceed the main quantity.',
                                ], 422);
            }

            // Prepare completed data
            $completedData = [
                'activity_id' => $activityId,
                'completed_quantity' => $totalCompleted,
                'completed_date' => $date,
                'created_by' => $request->input('created_by', auth()->id()),
            ];

            // Handle completed reference file upload
            if ($request->hasFile('completed_reference_file')) {
                $fileName = time() . '_activity_completed_' . $request->file('completed_reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'completed_reference_file', $fileName, 'activities/completed');
                if ($upload['flag'] == 1) {
                    $completedData['completed_reference_file'] = $upload['url'];
                }
            }

            // Insert new progress
            ActivityCompleted::create($completedData);

            // Refresh activity with updated completeds
            $activity->load(['completeds', 'completeds.creator']);

            return response()->json([
                        'status' => true,
                        'message' => 'Progress stored successfully',
                        'data' => $activity,
                            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => false,
                        'message' => 'Failed to store progress',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    public function show(Activity $activity) {
        if (!Auth::user()->isAbleTo('activity show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $activity->load([
                'workspace:id,name',
                'site:id,name',
                'completeds:id,activity_id,completed_quantity,completed_date,completed_reference_file,created_by',
                'completeds.creator:id,name',
                'completeds.manpowers.details.type:id,name',
                'completeds.manpowers.supplier:id,name',
                'completeds.dailyProgressReports.machinery:id,name',
                'completeds.dailyConsumptions.machinery:id,name',
                'completeds.dailyConsumptions.details.material:id,name',
                'completeds.dailyConsumptions.site:id,name',
            ]);

            $totalQty = (int) $activity->quantity;
            $completedQty = (int) $activity->completeds->sum('completed_quantity');

            // ✅ Calculate percentage safely
            $percentage = $totalQty > 0 ? round(($completedQty / $totalQty) * 100, 2) : 0;

            // Only include manpowers, dailyProgressReports, dailyConsumptions if completeds exist
            $manpowers = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->manpowers : [];
            $dailyProgress = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->dailyProgressReports : [];
            $consumptions = $activity->completeds->count() > 0 ? $activity->completeds->flatMap->dailyConsumptions : [];

            // Get assign_to users
            $assignToUsers = $activity->assign_to
                ? \App\Models\User::whereIn('id', explode(',', $activity->assign_to))
                    ->select('id', 'name')
                    ->get()
                : [];

            return response()->json([
                        'status' => true,
                        'data' => [
                            'id' => $activity->id,
                            'title' => $activity->title,
                            'scope' => $activity->scope,
                            'quantity' => $totalQty,
                            'unit' => $activity->unit,
                            'priority' => $activity->priority,
                            'status' => $activity->status,
                            'completed_qty' => $completedQty,
                            'completion_percentage' => $percentage,
                            'is_completed' => ($percentage >= 100),
                            'workspace' => $activity->workspace,
                            'site' => $activity->site,
                            'completeds' => $activity->completeds,
                            'manpowers' => $manpowers,
                            'daily_progress' => $dailyProgress,
                            'consumptions' => $consumptions,
                            'reference_file' => $activity->reference_file,
                            'assign_to' => $assignToUsers,
                        ]
                            ], 200);
        } catch (\Throwable $e) {

            \Log::error('Activity Show Error: ' . $e->getMessage());

            return response()->json([
                        'status' => false,
                        'message' => 'Failed to fetch activity.'
                            ], 500);
        }
    }

    public function update(Request $request, Activity $activity) {
        if (!Auth::user()->isAbleTo('activity edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
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
                'completed_quantity' => 'nullable|integer|min:0',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'site_id' => 'required|integer',
                'activity_id' => 'required|integer',
                'activities_completed' => 'array',
                'activities_completed.*.id' => 'nullable|integer|exists:activity_completeds,id',
                'activities_completed.*.completed_quantity' => 'required|integer|min:0',
                'activities_completed.*.completed_date' => 'required|date',
                'activities_completed.*.completed_reference_file' => 'nullable|file|max:5120',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx|max:20480',
            ]);

            $totalCompleted = $request->input('completed_quantity', 0);
            $date = $request->input('date');
            $activitiesCompleted = $request->input('activities_completed', []);

            // Only validate quantity if using old format (not array)
            if (empty($activitiesCompleted) && $totalCompleted > $request->input('quantity')) {
                return response()->json([
                            'status' => false,
                            'message' => 'Total completed quantity cannot exceed the main quantity.',
                                ], 422);
            }

            $activityData = [
                'assign_to' => is_array($request->assign_to) ? implode(',', $request->assign_to) : $request->assign_to,
                'title' => $request->title,
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'scope' => $request->scope,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
                'priority' => $request->priority,
                'status' => $request->input('status', $activity->status),
                'workspace_id' => $request->workspace_id,
                'site_id' => $request->site_id,
                'created_by' => $request->created_by,
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
                
                $fileName = time() . '_activity_api_' . $activity->id . '_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'activities');
                if ($upload['flag'] == 1) {
                    $activityData['reference_file'] = $upload['url'];
                }
            }

            $activity->update($activityData);

            // Handle activities_completed - update existing or create new
            $activitiesCompleted = $request->input('activities_completed', []);

            if (!empty($activitiesCompleted)) {

                $existingIds = $activity->completeds()->pluck('id')->toArray();
                $submittedIds = [];

                foreach ($activitiesCompleted as $key => $completed) {

                    $completedId = $completed['id'] ?? null;
                    $completedQuantity = $completed['completed_quantity'] ?? 0;
                    $completedDate = $completed['completed_date'] ?? null;

                    if ($completedQuantity <= 0 || !$completedDate) {
                        continue;
                    }

                    $referenceFilePath = null;

                    // File upload handling
                    if ($request->hasFile("activities_completed.$key.completed_reference_file")) {

                        $file = $request->file("activities_completed.$key.completed_reference_file");

                        $fileName = time().'_completed_api_'.$activity->id.'_'.$file->getClientOriginalName();

                        $upload = upload_file(
                            $request,
                            "activities_completed.$key.completed_reference_file",
                            $fileName,
                            'activity_completed_files'
                        );

                        if ($upload['flag'] == 1) {
                            $referenceFilePath = $upload['url'];
                        }
                    }

                    // Update existing completion
                    if ($completedId && in_array($completedId, $existingIds)) {

                        $activityCompleted = ActivityCompleted::find($completedId);

                        $updateData = [
                            'completed_quantity' => $completedQuantity,
                            'completed_date' => $completedDate,
                        ];

                        if ($referenceFilePath) {

                            if ($activityCompleted->completed_reference_file) {

                                $oldFile = public_path($activityCompleted->completed_reference_file);

                                if (file_exists($oldFile)) {
                                    unlink($oldFile);
                                }
                            }

                            $updateData['completed_reference_file'] = $referenceFilePath;
                        }

                        $activityCompleted->update($updateData);

                        $submittedIds[] = $completedId;

                    } else {

                        // Create new completion
                        $createData = [
                            'completed_quantity' => $completedQuantity,
                            'completed_date' => $completedDate,
                            'created_by' => $request->input('created_by', $request->created_by),
                        ];

                        if ($referenceFilePath) {
                            $createData['completed_reference_file'] = $referenceFilePath;
                        }

                        $newCompleted = $activity->completeds()->create($createData);

                        $submittedIds[] = $newCompleted->id;
                    }
                }

                // Optional: delete removed records
                // $activity->completeds()->whereNotIn('id', $submittedIds)->delete();
            }

            return response()->json([
                        'status' => true,
                        'message' => 'Activity updated successfully',
                        'data' => $activity->load(['completeds', 'completeds.creator']),
                            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => false,
                        'message' => 'Failed to update activity',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    public function destroy(Activity $activity) {
        if (!Auth::user()->isAbleTo('activity delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Don't delete completeds - they are linked to ManPower, DPR, and Consumption
            // Just delete the activity itself if needed, or keep it for historical records
            $activity->delete();

            return response()->json([
                        'status' => true,
                        'message' => 'Activity deleted successfully',
                            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => false,
                        'message' => 'Failed to delete activity',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }
}
