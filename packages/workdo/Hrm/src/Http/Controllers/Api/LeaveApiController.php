<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\Leave;
use Workdo\Hrm\Entities\LeaveType;
use Workdo\Hrm\Entities\Attendance;
use Workdo\Hrm\Entities\LeaveRequestDate;
use App\Models\User;
use Workdo\Hrm\Events\LeaveStatus;
use App\Models\EmailTemplate;

/**
 * @group HRM Leaves
 * Endpoints for leave management including requests, approvals, and tracking
 */
class LeaveApiController extends Controller
{

    /**
     * Check if user is admin/company (not a regular employee)
     */
    private function isAdminOrCompany($user)
    {
        $notEmpType = $user->not_emp_type ?? [];
        return in_array($user->type, $notEmpType);
    }

    /**
     * Check if user can access the leave record
     * - Admin/company can access all
     * - Regular users can only access their own leaves
     */
    private function canAccessLeave($user, $leave)
    {
        // Admin/company can access all
        if ($this->isAdminOrCompany($user)) {
            return true;
        }
        // Regular users can only access their own leaves
        return $leave->user_id == $user->id;
    }

    /**
     * Check if user can manage leave (approver)
     */
    private function canManageLeave($user)
    {
        // Check if user has approver permission or is admin/company
        return $this->isAdminOrCompany($user) || $user->isAbleTo('leave manage');
    }

    /**
     * Get all leave records
     *
     * List all leave requests for the authenticated user. If the user is an admin or company, all leave records are returned.
     *
     * @authenticated
     * @response {
     *  "status": 1,
     *  "message": "",
     *  "data": [
     *    {
     *      "id": 1,
     *      "employee_id": 5,
     *      "user_id": 2,
     *      "leave_type_id": 1,
     *      "leave_type": {"id": 1, "title": "Casual Leave", "days": 10},
     *      "applied_on": "2024-01-10",
     *      "start_date": "2024-01-15",
     *      "end_date": "2024-01-16",
     *      "total_leave_days": 2,
     *      "approved_days": 0,
     *      "leave_reason": "Family function",
     *      "remark": "Urgent",
     *      "status": "Pending",
     *      "status_reason": null,
     *      "workspace": 1,
     *      "site_id": 1,
     *      "created_by": 1
     *    }
     *  ]
     * }
     * @response 500 {
     *  "status": 0,
     *  "message": "Something went wrong: [Error Message]"
     * }
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            // If admin/company, get all leaves; otherwise get user's own leaves
            if ($this->isAdminOrCompany($user)) {
                $leaves = Leave::with('leaveType')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->map(function ($leave) {
                        return $this->formatLeaveRecord($leave);
                    });
            } else {
                $leaves = Leave::with('leaveType')
                    ->where('user_id', '=', $user->id)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->map(function ($leave) {
                        return $this->formatLeaveRecord($leave);
                    });
            }

            return response()->json(['status' => 1, 'message' => '', 'data' => $leaves]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Format leave record for API response
     */
    private function formatLeaveRecord($leave)
    {
        return [
            "id" => $leave->id,
            "employee_id" => $leave->employee_id,
            "user_id" => $leave->user_id,
            "leave_type_id" => $leave->leave_type_id,
            "leave_type" => $leave->leaveType,
            "applied_on" => $leave->applied_on,
            "start_date" => $leave->start_date,
            "end_date" => $leave->end_date,
            "total_leave_days" => $leave->total_leave_days,
            "approved_days" => $leave->approved_days,
            "leave_reason" => $leave->leave_reason,
            "remark" => $leave->remark,
            "status" => $leave->status,
            "status_reason" => $leave->status_reason,
            "workspace" => $leave->workspace,
            "site_id" => $leave->site_id,
            "created_by" => $leave->created_by
        ];
    }

    /**
     * Create a new leave record
     *
     * Submit a new leave request. Eligibility is checked against remaining leave balance.
     *
     * @authenticated
     * @bodyParam leave_type_id integer required The ID of the leave type. Example: 1
     * @bodyParam start_date date required The start date of the leave (YYYY-MM-DD). Must be today or later. Example: 2024-01-15
     * @bodyParam end_date date required The end date of the leave (YYYY-MM-DD). Example: 2024-01-20
     * @bodyParam leave_reason string required The reason for requesting leave. Example: Medical appointment
     * @bodyParam remark string required Additional remarks for the leave. Example: Urgent
     * @bodyParam employee_id integer optional The ID of the employee (required if an admin/company is creating leave for someone else). Example: 5
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "id": 1,
     *      "employee_id": 5,
     *      "user_id": 2,
     *      "leave_type_id": 1,
     *      "applied_on": "2024-01-10",
     *      "start_date": "2024-01-15",
     *      "end_date": "2024-01-20",
     *      "total_leave_days": 6,
     *      "status": "Pending"
     *  },
     *  "message": "Leave successfully created."
     * }
     * @response 403 {
     *  "status": 0,
     *  "message": "You are not eligible for this leave. Maximum days remaining: 2"
     * }
     * @response 404 {
     *  "status": 0,
     *  "message": "Leave type not found"
     * }
     */
    public function store(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'leave_type_id' => 'required',
                'start_date' => 'required|after:yesterday',
                'end_date' => 'required',
                'leave_reason' => 'required',
                'remark' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $user = Auth::user();
            $leave_type = LeaveType::find($request->leave_type_id);

            if (!$leave_type) {
                return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
            }

            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $endDate->add(new \DateInterval('P1D'));

            $leave = new Leave();

            // If admin/company creating leave for another employee
            if ($this->isAdminOrCompany($user) && $request->has('employee_id')) {
                $employee = Employee::where('id', '=', $request->employee_id)->first();
                if (!$employee) {
                    return response()->json(['status' => 0, 'message' => 'Employee not found'], 404);
                }
                $leave->employee_id = $request->employee_id;
                $leave->user_id = $employee->user_id;
            } else {
                // Regular employee creating their own leave
                $employee = Employee::where('user_id', '=', $user->id)->first();
                if (!empty($employee)) {
                    $leave->user_id = $user->id;
                    $leave->employee_id = $employee->id;
                } else {
                    return response()->json(['status' => 0, 'message' => 'Employee data not found. Please contact administrator.'], 403);
                }
            }

            $date = AnnualLeaveCycle();

            // Leave days calculation
            $leaves_used = Leave::where('employee_id', '=', $leave->employee_id)
                ->where('leave_type_id', '=', $leave_type->id)
                ->where('status', 'Approved')
                ->whereBetween('created_at', [$date['start_date'], $date['end_date']])
                ->sum('total_leave_days');

            $leaves_pending = Leave::where('employee_id', '=', $leave->employee_id)
                ->where('leave_type_id', '=', $leave_type->id)
                ->where('status', 'Pending')
                ->whereBetween('created_at', [$date['start_date'], $date['end_date']])
                ->sum('total_leave_days');

            $total_leave_days = !empty($startDate->diff($endDate)) ? $startDate->diff($endDate)->days : 0;

            $return = $leave_type->days - $leaves_used;
            if ($total_leave_days > $return) {
                return response()->json(['status' => 0, 'message' => 'You are not eligible for this leave. Maximum days remaining: ' . $return], 403);
            }
            if (!empty($leaves_pending) && $leaves_pending + $total_leave_days > $return) {
                return response()->json(['status' => 0, 'message' => 'Multiple leave entries are pending. Please wait for approval.'], 403);
            }

            if ($leave_type->days >= $total_leave_days) {
                $leave->leave_type_id = $request->leave_type_id;
                $leave->applied_on = date('Y-m-d');
                $leave->start_date = $request->start_date;
                $leave->end_date = $request->end_date;
                $leave->total_leave_days = $total_leave_days;
                $leave->approved_days = 0;
                $leave->leave_reason = $request->leave_reason;
                $leave->remark = $request->remark;
                $leave->status = 'Pending';
                $leave->workspace = $user->active_workspace ?? getActiveWorkSpace();
                $leave->site_id = getActiveProject();
                $leave->created_by = creatorId();
                $leave->save();

                // Create leave request date records
                $currentDate = new \DateTime($request->start_date);
                $endDate = new \DateTime($request->end_date);

                while ($currentDate <= $endDate) {
                    LeaveRequestDate::create([
                        'leave_request_id' => $leave->id,
                        'leave_date' => $currentDate->format('Y-m-d'),
                        'status' => 'pending',
                    ]);
                    $currentDate->add(new \DateInterval('P1D'));
                }

                return response()->json([
                    'status' => 1,
                    'data' => $this->formatLeaveRecord($leave),
                    'message' => 'Leave successfully created.'
                ], 200);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => "Leave type '$leave_type->name' provides a maximum of $leave_type->days days. Please select $leave_type->days days or less."
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a single leave record
     *
     * Retrieve details of a specific leave request.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "id": 1,
     *      "employee_id": 5,
     *      "user_id": 2,
     *      "leave_type_id": 1,
     *      "leave_type": {"id": 1, "title": "Casual Leave", "days": 10},
     *      "applied_on": "2024-01-10",
     *      "start_date": "2024-01-15",
     *      "end_date": "2024-01-16",
     *      "total_leave_days": 2,
     *      "status": "Pending"
     *  }
     * }
     * @response 403 {
     *  "status": 0,
     *  "message": "Permission denied"
     * }
     * @response 404 {
     *  "status": 0,
     *  "message": "Leave not found"
     * }
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $leave = Leave::with('leaveType')->find($id);

            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Check if user can access this leave
            if (!$this->canAccessLeave($user, $leave)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            return response()->json(['status' => 1, 'data' => $this->formatLeaveRecord($leave)], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing leave record
     *
     * Update an existing leave request. Only requests with 'Pending' status can be modified.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record to update. Example: 1
     * @bodyParam leave_type_id integer required The ID of the leave type. Example: 1
     * @bodyParam start_date date required The start date of the leave (YYYY-MM-DD). Example: 2024-01-15
     * @bodyParam end_date date required The end date of the leave (YYYY-MM-DD). Example: 2024-01-20
     * @bodyParam leave_reason string required The reason for requesting leave. Example: Medical appointment
     * @bodyParam remark string required Additional remarks for the leave. Example: Updated remarks
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "id": 1,
     *      "status": "Pending",
     *      "message": "Leave successfully updated."
     *  }
     * }
     * @response 403 {
     *  "status": 0,
     *  "message": "Only pending leave requests can be updated"
     * }
     * @response 404 {
     *  "status": 0,
     *  "message": "Leave not found"
     * }
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'leave_type_id' => 'required',
                'start_date' => 'required|after:yesterday',
                'end_date' => 'required',
                'leave_reason' => 'required',
                'remark' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $user = Auth::user();
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Only pending leaves can be updated
            if ($leave->status !== 'Pending') {
                return response()->json(['status' => 0, 'message' => 'Only pending leave requests can be updated'], 403);
            }

            // Check if user can access this leave
            if (!$this->canAccessLeave($user, $leave)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $leave->leave_type_id = $request->leave_type_id;
            $leave->start_date = $request->start_date;
            $leave->end_date = $request->end_date;
            $leave->leave_reason = $request->leave_reason;
            $leave->remark = $request->remark;
            $leave->save();

            return response()->json([
                'status' => 1,
                'data' => $this->formatLeaveRecord($leave),
                'message' => 'Leave successfully updated.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a leave record
     *
     * Remove a leave request from the system. Only requests with 'Pending' status can be deleted.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record to delete. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "message": "Leave successfully deleted."
     * }
     * @response 403 {
     *  "status": 0,
     *  "message": "Only pending leave requests can be deleted"
     * }
     * @response 404 {
     *  "status": 0,
     *  "message": "Leave not found"
     * }
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Check if user can access this leave
            if (!$this->canAccessLeave($user, $leave)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            // Only pending leaves can be deleted
            if ($leave->status !== 'Pending') {
                return response()->json(['status' => 0, 'message' => 'Only pending leave requests can be deleted'], 403);
            }

            $leave->delete();

            return response()->json(['status' => 1, 'message' => 'Leave successfully deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get leave details for approval
     *
     * Retrieve detailed information about a leave request for approval purposes. Includes used/remaining days and date-wise status.
     * Equivalent to Web controller's action() method.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "leave": {
     *          "id": 1,
     *          "start_date": "2024-01-15",
     *          "end_date": "2024-01-16",
     *          "status": "Pending"
     *      },
     *      "employee": {"id": 2, "name": "John Doe"},
     *      "leave_type": {"id": 1, "title": "Casual Leave", "days": 10},
     *      "used_days": 2,
     *      "remaining_days": 8,
     *      "sundays_worked": 0,
     *      "allow_partial": true,
     *      "existing_dates": {"2024-01-15": "pending", "2024-01-16": "pending"}
     *  },
     *  "message": ""
     * }
     * @response 403 {
     *  "status": 0,
     *  "message": "Permission denied. Approver access required."
     * }
     */
    public function action($id)
    {
        try {
            $user = Auth::user();

            // Only approvers can access this
            if (!$this->canManageLeave($user)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied. Approver access required.'], 403);
            }

            $leave = Leave::find($id);
            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            $employee = User::find($leave->user_id);
            $leavetype = LeaveType::find($leave->leave_type_id);

            if (!$leavetype) {
                return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
            }

            // Calculate used days for this employee and leave type
            // Updated: Calculate from approved dates instead of approved_days field
            $usedDays = LeaveRequestDate::join('leaves', 'leave_request_dates.leave_request_id', '=', 'leaves.id')
                ->where('leaves.employee_id', $leave->employee_id)
                ->where('leaves.leave_type_id', $leave->leave_type_id)
                ->where('leave_request_dates.status', 'approved')
                ->count();

            // Count Sundays worked from attendance
            $sundaysWorked = Attendance::where('employee_id', $leave->employee_id)
                ->whereRaw('DAYOFWEEK(date) = 1')
                ->count();

            // Check if any previous leave for this employee in the same type has a 'Reject' status
            $hasRejectedLeave = Leave::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('id', '!=', $leave->id)
                ->where('status', 'Reject')
                ->exists();

            $allow_partial = !$hasRejectedLeave;

            // Load existing date-wise approvals
            $existingDates = LeaveRequestDate::where('leave_request_id', $leave->id)
                ->pluck('status', 'leave_date');

            $remainingDays = $leavetype->days - $usedDays + $sundaysWorked;

            $data = [
                'leave' => [
                    'id' => $leave->id,
                    'employee_id' => $leave->employee_id,
                    'user_id' => $leave->user_id,
                    'leave_type_id' => $leave->leave_type_id,
                    'start_date' => $leave->start_date,
                    'end_date' => $leave->end_date,
                    'total_leave_days' => $leave->total_leave_days,
                    'approved_days' => $leave->approved_days,
                    'leave_reason' => $leave->leave_reason,
                    'remark' => $leave->remark,
                    'status' => $leave->status,
                    'status_reason' => $leave->status_reason,
                ],
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                ] : null,
                'leave_type' => [
                    'id' => $leavetype->id,
                    'title' => $leavetype->title,
                    'days' => $leavetype->days,
                ],
                'used_days' => $usedDays,
                'remaining_days' => $remainingDays,
                'sundays_worked' => $sundaysWorked,
                'allow_partial' => $allow_partial,
                'existing_dates' => $existingDates,
            ];

            return response()->json(['status' => 1, 'data' => $data, 'message' => ''], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
    * Change leave status
    *
    * Approve, Reject, or Partially Approve a leave request. Supports date-wise approval.
    * Equivalent to the Web controller's changeaction() method.
    *
    * @authenticated
    *
    * @bodyParam leave_id integer required The ID of the leave record. Example: 1
    * @bodyParam status string required The new status. Allowed values: Approved, Reject, Partially Approved. Example: Approved
    * @bodyParam status_reason string optional Reason for the status change. Example: Approved for urgent work.
    * @bodyParam approved_dates object optional Map of date-wise statuses for 'Partially Approved'. Example: {"2024-01-15": {"status": "approved", "remarks": "OK"}, "2024-01-16": {"status": "rejected", "remarks": "Busy"}}
    * @bodyParam approved_days integer optional Number of days to approve (legacy support for 'Partially Approved'). Example: 1
    *
    * @response 200 {
    *   "status": 1,
    *   "data": {
    *     "id": 1,
    *     "status": "Approved",
    *     "approved_days": 3,
    *     "rejected_days": 0,
    *     "pending_days": 0
    *   },
    *   "message": "Leave status successfully updated."
    * }
    *
    * @response 403 {
    *   "status": 0,
    *   "message": "Partially Approved is not allowed because a previous leave for this employee in the same leave type has been rejected."
    * }
    *
    * @response 404 {
    *   "status": 0,
    *   "message": "Leave not found"
    * }
    */
    public function changeStatus(Request $request)
    {
        try {
            $user = Auth::user();

            // Only approvers can change status
            if (!$this->canManageLeave($user)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied. Approver access required.'], 403);
            }

            $validator = \Validator::make($request->all(), [
                'leave_id' => 'required',
                'status' => 'required|in:Approved,Reject,Partially Approved',
                'status_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $leave = Leave::find($request->leave_id);
            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Calculate total days
            $totalDays = \Carbon\Carbon::parse($leave->start_date)
                ->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1;

            DB::beginTransaction();
            try {
                if ($request->status === 'Approved') {
                    // Approve all dates
                    LeaveRequestDate::where('leave_request_id', $leave->id)
                        ->update([
                            'status' => 'approved',
                            'approved_by' => $user->id,
                            'approved_at' => now(),
                        ]);
                    $leave->status = 'Approved';
                    $leave->total_leave_days = $totalDays;
                    $leave->approved_days = $totalDays;
                    $leave->rejected_days = 0;
                    $leave->pending_days = 0;
                } elseif ($request->status === 'Reject') {
                    // Reject all dates
                    LeaveRequestDate::where('leave_request_id', $leave->id)
                        ->update([
                            'status' => 'rejected',
                            'approved_by' => $user->id,
                            'approved_at' => now(),
                        ]);
                    $leave->status = 'Reject';
                    $leave->approved_days = 0;
                    $leave->rejected_days = $totalDays;
                    $leave->pending_days = 0;
                } elseif ($request->status === 'Partially Approved') {
                    // Verify previous leaves are all approved for this employee+type
                    $hasRejectedLeave = Leave::where('employee_id', $leave->employee_id)
                        ->where('leave_type_id', $leave->leave_type_id)
                        ->where('id', '!=', $leave->id)
                        ->where('status', 'Reject')
                        ->exists();

                    if ($hasRejectedLeave) {
                        DB::rollBack();
                        return response()->json(['status' => 0, 'message' => 'Partially Approved is not allowed because a previous leave for this employee in the same leave type has been rejected.'], 403);
                    }

                    // Check if new date-level approval payload exists
                    if ($request->has('approved_dates') && (is_array($request->approved_dates) || is_object($request->approved_dates))) {
                        // New date-level approval
                        foreach ($request->approved_dates as $key => $data) {
                            $date = (is_array($data) && isset($data['date'])) ? $data['date'] : $key;
                            $status = is_array($data) ? ($data['status'] ?? 'approved') : $data;
                            $remarks = is_array($data) ? ($data['remarks'] ?? null) : null;

                            LeaveRequestDate::where('leave_request_id', $leave->id)
                                ->where('leave_date', $date)
                                ->update([
                                    'status' => $status,
                                    'approved_by' => $user->id,
                                    'approved_at' => now(),
                                    'remarks' => $remarks,
                                ]);
                        }
                        $leave->recalculateDays();
                        $leave->status = 'Partially Approved';
                    } else {
                        // Legacy approval with count only
                        $validator2 = \Validator::make($request->all(), [
                            'approved_days' => 'required|integer|min:1|max:' . $totalDays,
                        ]);

                        if ($validator2->fails()) {
                            DB::rollBack();
                            return response()->json(['status' => 0, 'message' => $validator2->errors()->first()], 403);
                        }

                        // Approve first N days (legacy behavior)
                        $dateRecords = LeaveRequestDate::where('leave_request_id', $leave->id)
                            ->orderBy('leave_date')
                            ->get();

                        foreach ($dateRecords as $index => $dateRecord) {
                            if ($index < $request->approved_days) {
                                $dateRecord->status = 'approved';
                                $dateRecord->approved_by = $user->id;
                                $dateRecord->approved_at = now();
                            } else {
                                $dateRecord->status = 'rejected';
                                $dateRecord->approved_by = $user->id;
                                $dateRecord->approved_at = now();
                            }
                            $dateRecord->save();
                        }
                        $leave->recalculateDays();
                        $leave->status = 'Partially Approved';
                    }
                }

                $leave->status_reason = $request->status_reason;
                $leave->save();

                // Sync attendance with approved dates
                $this->syncAttendanceWithLeave($leave->id);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['status' => 0, 'message' => 'Error updating leave status: ' . $e->getMessage()], 500);
            }

            // Fire event
            event(new LeaveStatus($leave));

            // Email notification logic
            $company_settings = getCompanyAllSetting();
            if (!empty($company_settings['Leave Status']) && $company_settings['Leave Status'] == true) {
                $User = User::where('id', $leave->user_id)
                    ->where('workspace_id', $leave->workspace)
                    ->first();

                $uArr = [
                    'leave_email'       => $User->email,
                    'leave_status_name' => $User->name,
                    'leave_status'      => $leave->status,
                    'leave_reason'      => $leave->leave_reason,
                    'leave_start_date'  => $leave->start_date,
                    'leave_end_date'    => $leave->end_date,
                    'total_leave_days'  => $leave->total_leave_days,
                    'approved_days'     => $leave->approved_days,
                ];

                try {
                    EmailTemplate::sendEmailTemplate('Leave Status', [$User->email], $uArr);
                } catch (\Exception $e) {
                    // Log error or ignore
                }
            }

            return response()->json([
                'status' => 1,
                'data' => $this->formatLeaveRecord($leave),
                'message' => 'Leave status successfully updated.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sync attendance with approved leave dates
     */
    private function syncAttendanceWithLeave($leaveId)
    {
        $leave = Leave::find($leaveId);
        if (!$leave) {
            return;
        }

        // Get approved dates
        $approvedDates = LeaveRequestDate::where('leave_request_id', $leaveId)
            ->where('status', 'approved')
            ->get();

        // Mark approved dates as leave in attendance
        foreach ($approvedDates as $dateRecord) {
            Attendance::updateOrCreate(
                [
                    'employee_id' => $leave->employee_id,
                    'date' => $dateRecord->leave_date
                ],
                [
                    'status' => 'leave',
                    'leave_request_id' => $leaveId,
                    'leave_request_date_id' => $dateRecord->id,
                    'workspace' => $leave->workspace,
                    'created_by' => $leave->created_by,
                ]
            );
        }

        // Remove attendance for rejected dates (if they were previously marked)
        $rejectedDates = LeaveRequestDate::where('leave_request_id', $leaveId)
            ->where('status', 'rejected')
            ->get();

        foreach ($rejectedDates as $dateRecord) {
            Attendance::where('employee_id', $leave->employee_id)
                ->where('date', $dateRecord->leave_date)
                ->where('leave_request_id', $leaveId)
                ->delete();
        }
    }

    /**
     * Get leave summary
     *
     * Get summary of used and remaining leave days for an employee, grouped by leave type.
     * Equivalent to Web controller's jsoncount() method.
     *
     * @authenticated
     * @bodyParam employee_id integer required The ID of the employee. Example: 5
     *
     * @response {
     *  "status": 1,
     *  "data": [
     *    {
     *      "id": 1,
     *      "title": "Casual Leave",
     *      "allowed_days": 10,
     *      "used_days": 2,
     *      "remaining_days": 8
     *    }
     *  ],
     *  "message": ""
     * }
     */
    public function leaveSummary(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'employee_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 403);
            }

            $user = Auth::user();
            $date = AnnualLeaveCycle();

            $leave_counts = LeaveType::select(
                DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave'),
                'leave_types.title',
                'leave_types.days',
                'leave_types.id'
            )
            ->leftJoin('leaves', function ($join) use ($request, $date) {
                $join->on('leaves.leave_type_id', '=', 'leave_types.id');
                $join->where('leaves.employee_id', '=', $request->employee_id);
                $join->where('leaves.status', '=', 'Approved');
                $join->whereBetween('leaves.created_at', [$date['start_date'], $date['end_date']]);
            })
            ->where('leave_types.workspace', '=', getActiveWorkSpace())
            ->where('leave_types.created_by', '=', creatorId())
            ->groupBy('leave_types.id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'allowed_days' => $item->days,
                    'used_days' => $item->total_leave,
                    'remaining_days' => $item->days - $item->total_leave,
                ];
            });

            return response()->json([
                'status' => 1,
                'data' => $leave_counts,
                'message' => ''
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get leave description
     *
     * Get the reason and remark for a specific leave request.
     * Equivalent to Web controller's description() method.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "leave_reason": "Medical appointment",
     *      "remark": "Urgent"
     *  },
     *  "message": ""
     * }
     */
    public function description($id)
    {
        try {
            $user = Auth::user();
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Check if user can access this leave
            if (!$this->canAccessLeave($user, $leave)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $data = [
                'leave_reason' => $leave->leave_reason,
                'remark' => $leave->remark,
            ];

            return response()->json(['status' => 1, 'data' => $data, 'message' => ''], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get leave status reason
     *
     * Get the current status and the reason provided for that status.
     * Equivalent to Web controller's status_reason() method.
     *
     * @authenticated
     * @urlParam id integer required The ID of the leave record. Example: 1
     *
     * @response {
     *  "status": 1,
     *  "data": {
     *      "status": "Approved",
     *      "status_reason": "Approved for urgent work."
     *  },
     *  "message": ""
     * }
     */
    public function status_reason($id)
    {
        try {
            $user = Auth::user();
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json(['status' => 0, 'message' => 'Leave not found'], 404);
            }

            // Check if user can access this leave
            if (!$this->canAccessLeave($user, $leave)) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $data = [
                'status' => $leave->status,
                'status_reason' => $leave->status_reason,
            ];

            return response()->json(['status' => 1, 'data' => $data, 'message' => ''], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }
}
