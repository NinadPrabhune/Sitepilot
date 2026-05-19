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
     * @bodyParam leave_type_id integer required Leave type ID. Example: 1
     * @bodyParam start_date date required Start date (must be after yesterday). Example: 2024-01-15
     * @bodyParam end_date date required End date. Example: 2024-01-20
     * @bodyParam leave_reason string required Reason for leave. Example: Medical appointment
     * @bodyParam remark string required Remarks. Example: Urgent
     * @bodyParam employee_id integer optional Employee ID (for admin/company only). Example: 5
     * @response {"status": 1, "data": {...}, "message": "Leave request created successfully"}
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
     * Get leave details with employee, leave type, used days, remaining days, sundays worked
     * Equivalent to Web controller's action() method
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
            ];

            return response()->json(['status' => 1, 'data' => $data, 'message' => ''], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Change leave status (Approve/Reject/Partially Approve)
     * Equivalent to Web controller's changeaction() method
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
                    // Check if new date-level approval payload exists
                    if ($request->has('approved_dates') && is_array($request->approved_dates)) {
                        // New date-level approval
                        foreach ($request->approved_dates as $date => $data) {
                            LeaveRequestDate::where('leave_request_id', $leave->id)
                                ->where('leave_date', $date)
                                ->update([
                                    'status' => $data['status'],
                                    'approved_by' => $user->id,
                                    'approved_at' => now(),
                                    'remarks' => $data['remarks'] ?? null,
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
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['status' => 0, 'message' => 'Error updating leave status: ' . $e->getMessage()], 500);
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
     * Get leave summary for an employee grouped by leave type
     * Equivalent to Web controller's jsoncount() method
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
     * Get leave description (leave_reason + remark)
     * Equivalent to Web controller's description() method
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
     * Get leave status and status_reason
     * Equivalent to Web controller's status_reason() method
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
