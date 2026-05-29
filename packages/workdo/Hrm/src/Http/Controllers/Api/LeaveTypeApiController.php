<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workdo\Hrm\Entities\LeaveType;
use Workdo\Hrm\Entities\Leave;

/**
 * @group HRM Leave Types
 * Endpoints for leave type management
 */
class LeaveTypeApiController extends Controller
{
    /**
     * List all leave types for a workspace
     *
     * Returns leave types with used days and remaining eligibility for the given user.
     *
     * @authenticated
     * @group HRM Leave Types
     *
     * @queryParam user_id integer required User ID to calculate used leaves. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": [
     *    {
     *      "id": 1,
     *      "title": "Sick Leave",
     *      "days": 12,
     *      "used": 2,
     *      "is_disable": 0
     *    }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            $leavetypes = LeaveType::query()
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($leaveType) use ($request) {
                    $totalLeaves = Leave::where('leave_type_id', $leaveType->id)
                        ->where('user_id', $request->user_id)
                        ->where('status', 'Approved')
                        ->sum('total_leave_days');

                    $is_disable = $totalLeaves < $leaveType->days ? 0 : 1;

                    return [
                        "id"         => $leaveType->id,
                        "title"      => $leaveType->title,
                        "days"       => $leaveType->days,
                        "used"       => $totalLeaves,
                        "is_disable" => $is_disable,
                    ];
                });

            return response()->json(['status' => 1, 'message' => '', 'data' => $leavetypes]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!']);
        }
    }

    /**
     * Show a single leave type
     *
     * @group HRM Leave Types
     *
     * @urlParam id integer required The ID of the leave type. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *    "id": 1,
     *    "title": "Sick Leave",
     *    "days": 12
     *  }
     * }
     * @response 404 {
     *  "status": 0,
     *  "message": "Leave type not found"
     * }
     */
    public function show($id)
    {
        try {
            $leaveType = LeaveType::find($id);

            if (!$leaveType) {
                return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
            }

            return response()->json(['status' => 1, 'data' => $leaveType], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!'], 500);
        }
    }

    /**
     * Create a new leave type
     *
     * @bodyParam title string required Leave type title. Example: Sick Leave
     * @bodyParam days integer required Number of days allowed. Example: 12
     * @response {"status": 1, "data": {...}, "message": "Leave type successfully created."}
     */
    public function store(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'title'      => 'required|string|max:255',
                'days'       => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $leaveType = new LeaveType();
            $leaveType->title     = $request->title;
            $leaveType->days      = $request->days;
            $leaveType->save();

            return response()->json(['status' => 1, 'data' => $leaveType, 'message' => 'Leave type successfully created.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!'], 500);
        }
    }

    /**
     * Update an existing leave type
     *
     * @group HRM Leave Types
     *
     * @urlParam id integer required The ID of the leave type. Example: 1
     *
     * @bodyParam title string optional Leave type title. Example: Sick Leave
     * @bodyParam days integer optional Number of days allowed. Example: 15
     *
     * @response {
     *  "status": 1,
     *  "data": {...},
     *  "message": "Leave type successfully updated."
     * }
     */
    public function update(Request $request, $id)
    {
        try {
            $leaveType = LeaveType::find($id);

            if (!$leaveType) {
                return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
            }

            $validator = \Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'days'  => 'sometimes|required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $leaveType->title = $request->title ?? $leaveType->title;
            $leaveType->days  = $request->days ?? $leaveType->days;
            $leaveType->save();

            return response()->json(['status' => 1, 'data' => $leaveType, 'message' => 'Leave type successfully updated.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!'], 500);
        }
    }

    /**
     * Delete a leave type
     *
     * @group HRM Leave Types
     *
     * @urlParam id integer required The ID of the leave type. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "message": "Leave type successfully deleted."
     * }
     */
    public function destroy($id)
    {
        try {
            $leaveType = LeaveType::find($id);

            if (!$leaveType) {
                return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
            }

            $leaveType->delete();

            return response()->json(['status' => 1, 'message' => 'Leave type successfully deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!'], 500);
        }
    }
}
