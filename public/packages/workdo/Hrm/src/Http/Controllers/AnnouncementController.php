<?php

namespace Workdo\Hrm\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\DataTables\EmpAnnouncementDataTable;
use Workdo\Hrm\Entities\Announcement;
use Workdo\Hrm\Entities\AnnouncementEmployee;
use Workdo\Hrm\Entities\Branch;
use Workdo\Hrm\Entities\Department;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Events\CreateAnnouncement;
use Workdo\Hrm\Events\DestroyAnnouncement;
use Workdo\Hrm\Events\UpdateAnnouncement;
use App\Services\NotificationService; // adjust namespace

class AnnouncementController extends Controller
{
    
    protected $notificationService; public function __construct(
            NotificationService $notificationService) { 
        
                $this->notificationService = $notificationService; 
        
            }
    
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(EmpAnnouncementDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('announcement manage')) {

            return $dataTable->render('hrm::announcement.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('announcement create')) {
            $branch    = Branch::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id');
            $branch->prepend('All', 0);
            $departments  = Department::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get();
            
            $projectsQuery = \Workdo\Taskly\Entities\Project::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $projectsQuery->where('workspace', $workspaceId);
            }
            $projects = $projectsQuery->projectonly()->get()->pluck('name', 'id');

            return view('hrm::announcement.create', compact('branch', 'departments','projects'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    
    public function store(Request $request)
{
    if (Auth::user()->isAbleTo('announcement create')) {

        // Default values for branch/department/employee/workspace
        $request->merge([
            'branch_id'     => 0,
            'department_id' => [0],
            'employee_id'   => [0],
           
        ]);

        // Validation
        $validator = \Validator::make(
            $request->all(),
            [
                'title'        => 'required',
                'branch_id'    => 'required',
                'department_id'=> 'required',
                'employee_id'  => 'required',
                'site_id'      => 'required|integer', // single project ID required
                'start_date'   => 'required|after:yesterday',
                'end_date'     => 'required|after_or_equal:start_date',
                'description'  => 'required',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }
        
        $workspaceId    = getWorkspaceIDFromSiteID($request->site_id);

        // ✅ Create announcement
        $announcement                = new Announcement();
        $announcement->title         = $request->title;
        $announcement->start_date    = $request->start_date;
        $announcement->end_date      = $request->end_date;
        $announcement->branch_id     = $request->branch_id;
        $announcement->department_id = implode(",", $request->department_id);
        $announcement->employee_id   = implode(",", $request->employee_id);
        $announcement->description   = $request->description;
        $announcement->workspace     = $workspaceId;
        $announcement->site_id       = $request->site_id; // single project ID
        $announcement->created_by    = creatorId();
        $announcement->save();

        event(new CreateAnnouncement($request, $announcement));

        // ✅ Get all user IDs assigned to the selected project
        $projectUsers = \DB::table('user_projects')
            ->where('project_id', $request->site_id)
            ->pluck('user_id')
            ->toArray();

        // ✅ Create announcement-employee records for each active employee
        foreach ($projectUsers as $userId) {
            $employee = Employee::where('user_id', $userId)
                                ->where('is_active', 1)
                                ->first();

            if ($employee) {
                $announcementEmployee                  = new AnnouncementEmployee();
                $announcementEmployee->announcement_id = $announcement->id;
                $announcementEmployee->employee_id     = $employee->id;
                $announcementEmployee->workspace       = $workspaceId;
                $announcementEmployee->site_id       =  $request->site_id;
                $announcementEmployee->created_by      = Auth::user()->id;
                $announcementEmployee->save();
            }
        }

        // ✅ Call notification service for the single project
        $this->notificationService->createAnnouncementNotificationInstant(
            $announcement->id,
            (int) $request->site_id,
            $announcement->title,
            $announcement->description,
            Auth::user()->name
        );

        return redirect()->route('announcement.index')
                         ->with('success', __('The announcement has been created successfully.'));
    } else {
        return redirect()->back()->with('error', __('Permission denied.'));
    }
}




    
    
    
//    public function store(Request $request)
//    {
//        if (Auth::user()->isAbleTo('announcement create')) {
//            
//            
//            $request->merge([
//                'branch_id' => 0,
//                'department_id' => [0],
//                'employee_id' => [0],
//                'workspace' => [0],
//            ]);
//            
//            
//            $validator = \Validator::make(
//                $request->all(),
//                [
//                    'title' => 'required',
//                    'branch_id' => 'required',
//                    'department_id' => 'required',
//                    'employee_id' => 'required',
//                    'start_date' => 'required|after:yesterday',
//                    'end_date' => 'required|after_or_equal:start_date',
//                    'description' => 'required',
//                ]
//            );
//            if ($validator->fails()) {
//                $messages = $validator->getMessageBag();
//
//                return redirect()->back()->with('error', $messages->first());
//            }
//
//            $announcement                = new Announcement();
//            $announcement->title         = $request->title;
//            $announcement->start_date    = $request->start_date;
//            $announcement->end_date      = $request->end_date;
//            $announcement->branch_id     = $request->branch_id;
//            $announcement->department_id = implode(",", $request->department_id);
//            $announcement->employee_id   = implode(",", $request->employee_id);
//            $announcement->description   = $request->description;
//            $announcement->workspace     = getActiveWorkSpace();
//            $announcement->site_id       = getActiveProject();
//            $announcement->created_by    = creatorId();
//            $announcement->save();
//
//            event(new CreateAnnouncement($request, $announcement));
//
////            if (in_array('0', $request->employee_id)) {
////                $departmentEmployee = Employee::whereIn('department_id', $request->department_id)->where('workspace', getActiveWorkSpace())->get()->pluck('id');
////                $departmentEmployee = $departmentEmployee;
////            } else {
////                $departmentEmployee = $request->employee_id;
////            }
//
//            $departmentEmployee = Employee::where('is_active', 1)->get()->pluck('id');
//            foreach ($departmentEmployee as $employee) {
//                $announcementEmployee                  = new AnnouncementEmployee();
//                $announcementEmployee->announcement_id = $announcement->id;
//                $announcementEmployee->employee_id     = $employee;
//                $announcementEmployee->workspace       = getActiveWorkSpace();
//                $announcementEmployee->created_by      = Auth::user()->id;
//                $announcementEmployee->save();
//            }
//
//            return redirect()->route('announcement.index')->with('success', __('The announcement has been created successfully.'));
//        } else {
//            return redirect()->back()->with('error', __('Permission denied.'));
//        }
//    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return redirect()->back();
        return view('hrm::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        if (Auth::user()->isAbleTo('announcement edit')) {
            $announcement = Announcement::find($id);
            if ($announcement->created_by == creatorId() && $announcement->workspace == getActiveWorkSpace()) {
                $branch    = Branch::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id');
                $branch->prepend('All', 0);
                $departments  = Department::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id');
                $departments->prepend('All', 0);
                
                
                   $projectsQuery = \Workdo\Taskly\Entities\Project::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $projectsQuery->where('workspace', $workspaceId);
            }
            $projects = $projectsQuery->projectonly()->get()->pluck('name', 'id');


                return view('hrm::announcement.edit', compact('announcement', 'branch', 'departments','projects'));
            } else {
                return response()->json(['error' => __('Permission denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    
    
    public function update(Request $request, Announcement $announcement)
{
    if (Auth::user()->isAbleTo('announcement edit')) {

        $request->merge([
            'branch_id'     => 0,
            'department_id' => [0],
            'employee_id'   => [0],
        ]);

        if ($announcement->created_by == creatorId() && $announcement->workspace == getActiveWorkSpace()) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title'        => 'required',
                    'branch_id'    => 'required',
                    'department_id'=> 'required',
                    'employee_id'  => 'required',
                    'site_id'      => 'required|integer', // single project ID required
                    'start_date'   => 'required|after:yesterday',
                    'end_date'     => 'required|after_or_equal:start_date',
                    'description'  => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            // ✅ Resolve workspace from site_id
            $workspaceId = getWorkspaceIDFromSiteID($request->site_id);

            // ✅ Update announcement
            $announcement->title         = $request->title;
            $announcement->start_date    = $request->start_date;
            $announcement->end_date      = $request->end_date;
            $announcement->branch_id     = $request->branch_id;
            $announcement->department_id = implode(",", $request->department_id);
            $announcement->employee_id   = implode(",", $request->employee_id);
            $announcement->description   = $request->description;
            $announcement->workspace     = $workspaceId;
            $announcement->site_id       = $request->site_id; // single project ID
            $announcement->save();

            event(new UpdateAnnouncement($request, $announcement));

            // ✅ Refresh announcement-employee mappings
            AnnouncementEmployee::where('announcement_id', $announcement->id)->delete();

            $projectUsers = \DB::table('user_projects')
                ->where('project_id', $request->site_id)
                ->pluck('user_id')
                ->toArray();

            foreach ($projectUsers as $userId) {
                $employee = Employee::where('user_id', $userId)
                                    ->where('is_active', 1)
                                    ->first();

                if ($employee) {
                    $announcementEmployee                  = new AnnouncementEmployee();
                    $announcementEmployee->announcement_id = $announcement->id;
                    $announcementEmployee->employee_id     = $employee->id;
                    $announcementEmployee->workspace       = $workspaceId;
                    $announcementEmployee->site_id         = $request->site_id;
                    $announcementEmployee->created_by      = Auth::user()->id;
                    $announcementEmployee->save();
                }
            }

            // ✅ Optionally trigger notification on update
            $this->notificationService->createAnnouncementNotificationInstant(
                $announcement->id,
                (int) $request->site_id,
                $announcement->title,
                $announcement->description,
                Auth::user()->name
            );

            return redirect()->route('announcement.index')
                             ->with('success', __('The announcement details are updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    } else {
        return redirect()->back()->with('error', __('Permission denied.'));
    }
}

    
    
//    public function update(Request $request, Announcement $announcement)
//    {
//        if (Auth::user()->isAbleTo('announcement edit')) {
//            
//            $request->merge([
//                'branch_id' => 0,
//                'department_id' => [0],
//                'employee_id' => [0],
//            ]);
//            
//            
//            if ($announcement->created_by == creatorId() && $announcement->workspace == getActiveWorkSpace()) {
//                $validator = \Validator::make(
//                    $request->all(),
//                    [
//                        'title' => 'required',
//                        'branch_id' => 'required',
//                        'department_id' => 'required',
//                        'start_date' => 'required|after:yesterday',
//                        'end_date' => 'required|after_or_equal:start_date',
//                        'description' => 'required',
//                    ]
//                );
//                if ($validator->fails()) {
//                    $messages = $validator->getMessageBag();
//
//                    return redirect()->back()->with('error', $messages->first());
//                }
//
//                $announcement->title         = $request->title;
//                $announcement->start_date    = $request->start_date;
//                $announcement->end_date      = $request->end_date;
//                $announcement->branch_id     = $request->branch_id;
//                $announcement->department_id = implode(",", $request->department_id);
//                $announcement->description   = $request->description;
//
//                $announcement->save();
//                event(new UpdateAnnouncement($request, $announcement));
//
//                return redirect()->route('announcement.index')->with('success', __('The announcement details are updated successfully.'));
//            } else {
//                return redirect()->back()->with('error', __('Permission denied.'));
//            }
//        } else {
//            return redirect()->back()->with('error', __('Permission denied.'));
//        }
//    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy(Announcement $announcement)
    {
        if (Auth::user()->isAbleTo('announcement delete')) {
            if ($announcement->created_by == creatorId() && $announcement->workspace == getActiveWorkSpace()) {
                event(new DestroyAnnouncement($announcement));
                $announcementemployee = AnnouncementEmployee::where('announcement_id', $announcement->id)->delete();
                $announcement->delete();

                return redirect()->route('announcement.index')->with('success', __('The announcement has been deleted.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getemployee(Request $request)
    {
        $employees = [];
        if (isset($request->department_id)) {
            if (in_array('0', $request->department_id)) {
                $employees = Employee::where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
            } else {

                $employees = Employee::where('workspace', getActiveWorkSpace())->whereIn('department_id', $request->department_id)->get()->pluck('name', 'id')->toArray();
            }
        }
        return response()->json($employees);
    }

    public function description($id)
    {
        $announcements = Announcement::find($id);
        return view('hrm::announcement.description', compact('announcements'));
    }
}
