<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workdo\Hrm\Entities\LeaveType;
use Workdo\Hrm\Entities\Leave;

class LeaveTypeApiController extends Controller
{
    /**
     * List all leave types for a workspace
     */
    public function index(Request $request)
    {
        try {
            $leavetypes = LeaveType::where('workspace', $request->workspace_id)
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
     */
    public function store(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'title'      => 'required|string|max:255',
                'days'       => 'required|integer|min:1',
                'workspace_id'  => 'required|integer',
                'site_id'  => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $leaveType = new LeaveType();
            $leaveType->title     = $request->title;
            $leaveType->days      = $request->days;
            $leaveType->workspace = $request->workspace_id;
            $leaveType->site_id = $request->site_id;
            $leaveType->save();

            return response()->json(['status' => 1, 'data' => $leaveType, 'message' => 'Leave type successfully created.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!'], 500);
        }
    }

    /**
     * Update an existing leave type
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
