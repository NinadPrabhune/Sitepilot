<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Announcement;
use Workdo\Hrm\Entities\AnnouncementEmployee;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Events\CreateAnnouncement;
use Workdo\Hrm\Events\UpdateAnnouncement;
use Workdo\Hrm\Events\DestroyAnnouncement;

class AnnouncementApiController extends Controller {

    public function index(Request $request) {
        try {
            if (!Auth::user()->isAbleTo('announcement manage')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            $query = Announcement::query();

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $announcements = $query->get();

            return response()->json(['status' => 1, 'data' => $announcements]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createData(Request $request)
    {
        try {
            // Check permission
            if (!Auth::user()->isAbleTo('announcement create')) {
                return response()->json([
                    'error' => __('Permission denied.')
                ], 403);
            }

            // Build project query
            $projectsQuery = \Workdo\Taskly\Entities\Project::query();

            $workspaceId = $request->input('workspace_id'); // safely get from request
            if (!empty($workspaceId) && $workspaceId != 0) {
                $projectsQuery->where('workspace', $workspaceId);
            }

            $projects = $projectsQuery->projectonly()->get()->pluck('name', 'id');

            // Return JSON response instead of view for API
            return response()->json([
                'success' => true,
                'projects' => $projects
            ], 200);

        } catch (\Exception $e) {
            // Catch unexpected errors
            return response()->json([
                'error' => __('Something went wrong.'),
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request) {
        try {
            if (!Auth::user()->isAbleTo('announcement create')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $request->merge([
                'branch_id' => 0,
                'department_id' => [0],
                'employee_id' => [0],
            ]);

            $validator = \Validator::make($request->all(), [
                'title' => 'required',
                'branch_id' => 'required',
                'department_id' => 'required|array',
                'employee_id' => 'required|array',
                'start_date' => 'required|after:yesterday',
                'end_date' => 'required|after_or_equal:start_date',
                'description' => 'required',
                'workspace_id' => 'required',
                'site_id' => 'required',
                'created_by' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $announcement = new Announcement();
            $announcement->title = $request->title;
            $announcement->start_date = $request->start_date;
            $announcement->end_date = $request->end_date;
            $announcement->branch_id = $request->branch_id;
            $announcement->department_id = implode(",", $request->department_id);
            $announcement->employee_id = implode(",", $request->employee_id);
            $announcement->description = $request->description;
            $announcement->workspace = $request->input('workspace_id', getActiveWorkSpace());
            $announcement->site_id = $request->input('site_id', getActiveProject());
            $announcement->created_by = $request->input('created_by', creatorId());
            $announcement->save();

            event(new CreateAnnouncement($request, $announcement));

            $departmentEmployee = Employee::where('is_active', 1)->pluck('id');
            foreach ($departmentEmployee as $employee) {
                AnnouncementEmployee::create([
                    'announcement_id' => $announcement->id,
                    'employee_id' => $employee,
                    'workspace' => $announcement->workspace,
                    'created_by' => Auth::user()->id,
                ]);
            }

            return response()->json(['status' => 1, 'message' => 'Announcement created successfully', 'data' => $announcement]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id) {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json(['status' => 0, 'message' => 'Announcement not found'], 404);
            }

            return response()->json(['status' => 1, 'data' => $announcement]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json(['status' => 0, 'message' => 'Announcement not found'], 404);
            }

            if (!Auth::user()->isAbleTo('announcement edit')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $request->merge([
                'branch_id' => 0,
                'department_id' => [0],
                'employee_id' => [0],
            ]);

            $validator = \Validator::make($request->all(), [
                'title' => 'required',
                'branch_id' => 'required',
                'department_id' => 'required|array',
                'start_date' => 'required|after:yesterday',
                'end_date' => 'required|after_or_equal:start_date',
                'description' => 'required',
                'workspace_id' => 'required',
                'site_id' => 'required',
                'created_by' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $announcement->update([
                'title' => $request->title,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'branch_id' => $request->branch_id,
                'department_id' => implode(",", $request->department_id),
                'description' => $request->description,
            ]);

            event(new UpdateAnnouncement($request, $announcement));

            return response()->json(['status' => 1, 'message' => 'Announcement updated successfully', 'data' => $announcement]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id) {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json(['status' => 0, 'message' => 'Announcement not found'], 404);
            }

            if (!Auth::user()->isAbleTo('announcement delete')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            event(new DestroyAnnouncement($announcement));
            AnnouncementEmployee::where('announcement_id', $announcement->id)->delete();
            $announcement->delete();

            return response()->json(['status' => 1, 'message' => 'Announcement deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
}
