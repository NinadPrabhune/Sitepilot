<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Branch;
use Workdo\Hrm\Entities\Department;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\Event;
use Workdo\Hrm\Entities\EventEmployee;
use Workdo\Hrm\Events\CreateEvent;
use Workdo\Hrm\Events\DestroyEvent;
use Workdo\Hrm\Events\UpdateEvent;

/**
 * @group HRM Events
 * Endpoints for event management
 */
class EventApiController extends Controller
{
    /**
     * List all events
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('event manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

//        $events = Event::where('workspace', getActiveWorkSpace())->get();
        
        // Get filters from request
        $workspaceId = $request->input('workspace_id');
        
        $siteId      = $request->input('site_id');

        // Build query with eager loading
        
        $query = Event::query();

        // Apply filters if provided
        if (!empty($workspaceId) && $workspaceId != 0) {
            $query->where('workspace', $workspaceId);
        }

        if (!empty($siteId) && $siteId != 0) {
            $query->where('site_id', $siteId);
        }

        // Finally execute query
        $events = $query->get();


        return response()->json([
            'status' => 1,
            'data'   => $events
        ]);
    }

    /**
     * Store a new event
     *
     * @bodyParam branch_id integer required Branch ID. Example: 0
     * @bodyParam department_id array required Department IDs. Example: [0]
     * @bodyParam employee_id array required Employee IDs. Example: [0]
     * @bodyParam title string required Event title. Example: Team Meeting
     * @bodyParam start_date date required Start date (must be after yesterday). Example: 2024-01-15
     * @bodyParam end_date date required End date (must be after or equal to start_date). Example: 2024-01-15
     * @bodyParam color string required Event color. Example: #FF0000
     * @bodyParam description string optional Description. Example: Quarterly team meeting
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @response {"status": 1, "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('event create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $request->merge([
            'branch_id'     => 0,
            'department_id' => [0],
            'employee_id'   => [0],
        ]);

        $validator = \Validator::make($request->all(), [
            'branch_id'     => 'required',
            'department_id' => 'required',
            'employee_id'   => 'required',
            'title'         => 'required',
            'start_date'    => 'required|after:yesterday',
            'end_date'      => 'required|after_or_equal:start_date',
            'color'         => 'required',
            'workspace_id'     => 'required',
            'site_id' => 'required',
            'created_by'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $event = new Event();
        $event->branch_id     = $request->branch_id;
        $event->department_id = json_encode($request->department_id);
        $event->employee_id   = json_encode($request->employee_id);
        $event->title         = $request->title;
        $event->start_date    = $request->start_date;
        $event->end_date      = $request->end_date;
        $event->color         = $request->color;
        $event->description   = $request->description;
        $event->workspace     = $request->workspace_id;
        $event->site_id       = $request->site_id;
        $event->created_by    = $request->created_by;
        $event->save();

        // Assign employees
        if (in_array('0', (array) $request->employee_id)) {
            $departmentEmployee = Employee::whereIn('department_id', (array) $request->department_id)
                                          ->pluck('id');
        } else {
            $departmentEmployee = (array) $request->employee_id;
        }
        
        $departmentEmployee = Employee::where('is_active', 1)->get()->pluck('id');

        foreach ($departmentEmployee as $employee) {
            EventEmployee::create([
                'event_id'    => $event->id,
                'employee_id' => $employee,
                'created_by'  => creatorId(),
            ]);
        }

        // Fire FCM event
        event(new CreateEvent($request, $event));

        return response()->json(['status' => 1, 'message' => 'Event created successfully', 'data' => $event]);
    }

    /**
     * Show event details
     */
    public function show($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['status' => 0, 'message' => 'Event not found'], 404);
        }

        return response()->json(['status' => 1, 'data' => $event]);
    }

    /**
     * Update event
     */
    public function update(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['status' => 0, 'message' => 'Event not found'], 404);
        }

        if (!Auth::user()->isAbleTo('event edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'title'      => 'required',
            'start_date' => 'required|date',
            'end_date'   => 'required|after_or_equal:start_date',
            'color'      => 'required',
            'workspace_id'     => 'required',
            'site_id' => 'required',
            'created_by'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $event->update($request->only(['title', 'start_date', 'end_date', 'color', 'description']));

        event(new UpdateEvent($request, $event));

        return response()->json(['status' => 1, 'message' => 'Event updated successfully', 'data' => $event]);
    }

    /**
     * Delete event
     */
    public function destroy($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['status' => 0, 'message' => 'Event not found'], 404);
        }

        if (!Auth::user()->isAbleTo('event delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        event(new DestroyEvent($event));
        $event->delete();

        return response()->json(['status' => 1, 'message' => 'Event deleted successfully']);
    }
}
