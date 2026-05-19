<?php
namespace Workdo\Hrm\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Hrm\DataTables\EmpLeaveDataTable;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\Leave;
use Workdo\Hrm\Entities\LeaveType;
use Workdo\Hrm\Events\CreateLeave;
use Workdo\Hrm\Events\DestroyLeave;
use Workdo\Hrm\Events\LeaveStatus;
use Workdo\Hrm\Events\UpdateLeave;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

use Workdo\Hrm\Entities\Attendance;
use Workdo\Hrm\Entities\LeaveRequestDate;


class LeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(EmpLeaveDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('leave manage')) {

            return $dataTable->render('hrm::leave.index');
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
        if (Auth::user()->isAbleTo('leave create')) {
            // DEBUG: Log user info for diagnostics
            $currentUser = Auth::user();
            $currentUserType = $currentUser->type;
            $notEmpType = $currentUser->not_emp_type ?? [];
            $isNotEmp = in_array($currentUserType, $notEmpType);
            $activeWorkspace = getActiveWorkSpace();
            
            
            if (!$isNotEmp) {
                $employees = Employee::where('user_id', '=', $currentUser->id)->where('workspace', $activeWorkspace)->first();
                
                // DEBUG: Log employee query results
                
                
                if (is_null($employees)) {
                    // DEBUG: Log detailed info when employee is null
                    
                }
            } else {
                $employees = Employee::where('workspace', $activeWorkspace)->where('created_by', '=', creatorId())->get()->pluck('name', 'id');
                
                // DEBUG: For manager/admin, log collection type
                
            }

            // DEBUG: Log the final state before foreach loop
            

            $leavetypes = LeaveType::all();

    
            // Only calculate leave balance for regular employees with valid Employee records
            // For managers/admins, they select employee from dropdown so balance is calculated differently
            if (!$isNotEmp && is_object($employees)) {
                foreach ($leavetypes as $lt) {
                    // DEBUG: Log before accessing employee_id
                    
                    
                    $employeeIdForQuery = $employees->employee_id;
                    
                    // Updated: Calculate from approved dates instead of approved_days field
                    $usedDays = LeaveRequestDate::join('leaves', 'leave_request_dates.leave_request_id', '=', 'leaves.id')
                        ->where('leaves.employee_id', $employeeIdForQuery)
                        ->where('leaves.leave_type_id', $lt->id)
                        ->where('leave_request_dates.status', 'approved')
                        ->count();

                    $sundaysWorked = Attendance::where('employee_id',  $employeeIdForQuery)
                        ->whereRaw('DAYOFWEEK(date) = 1') // MySQL: 1 = Sunday
                        ->count();

                    $lt->used = $usedDays;
                    $lt->remaining_days = $lt->days - $usedDays + $sundaysWorked;
                    $lt->sundays_worked = $sundaysWorked;
                }
            } else {
                // Initialize leave balance fields for managers or when employee record not found
                foreach ($leavetypes as $lt) {
                    $lt->used = 0;
                    $lt->remaining_days = $lt->days;
                    $lt->sundays_worked = 0;
                }
            }


            
//            dd($leavetypes);

            return view('hrm::leave.create', compact('employees', 'leavetypes'));
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
        if (Auth::user()->isAbleTo('leave create')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'leave_type_id' => 'required',
                    'start_date' => 'required|after:yesterday',
                    'end_date' => 'required',
                    'leave_reason' => 'required',
                    'remark' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $leave_type = LeaveType::find($request->leave_type_id);
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $endDate->add(new \DateInterval('P1D'));

            $leave    = new Leave();
            if (in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
                $employee = Employee::where('id', '=', $request->employee_id)->first();
                $leave->employee_id = $request->employee_id;
                $leave->user_id = $employee->user_id;
            } else {
                $employee = Employee::where('user_id', '=', Auth::user()->id)->first();
                if (!empty($employee)) {
                    $leave->user_id = Auth::user()->id;
                    $leave->employee_id = $employee->id;
                } else {
                    return redirect()->back()->with('error', __('Apologies, the employee data is currently unavailable. Please provide the necessary employee details.'));
                }
            }

            $date = AnnualLeaveCycle();

            // Leave day
            $leaves_used   = Leave::where('employee_id', '=', $leave->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

            $leaves_pending  = Leave::where('employee_id', '=', $leave->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

            $total_leave_days = !empty($startDate->diff($endDate)) ? $startDate->diff($endDate)->days : 0;

            $return = $leave_type->days - $leaves_used;
            if ($total_leave_days > $return) {
                return redirect()->back()->with('error', __('You are not eligible for leave.'));
            }
            if (!empty($leaves_pending) && $leaves_pending + $total_leave_days > $return) {
                return redirect()->back()->with('error', __('Multiple leave entry is pending.'));
            }

            if ($leave_type->days >= $total_leave_days) {

                $leave->leave_type_id    = $request->leave_type_id;
                $leave->applied_on       = date('Y-m-d');
                $leave->start_date       = $request->start_date;
                $leave->end_date         = $request->end_date;
                $leave->total_leave_days = $total_leave_days;
                $leave->leave_reason     = $request->leave_reason;
                $leave->remark           = $request->remark;
                $leave->status           = 'Pending';
                $leave->workspace        = getActiveWorkSpace();
                $leave->site_id          = getActiveProject();
                $leave->created_by       = creatorId();
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

                event(new CreateLeave($request, $leave));

                $company_settings = getCompanyAllSetting();
                if (!empty($company_settings['Employee Leave Received']) && $company_settings['Employee Leave Received']  == true && Auth::user()->type == 'staff') {
                    $User     = User::where('id', $leave->user_id)->where('workspace_id', '=',  getActiveWorkSpace())->first();
                    $company = User::where('id', $User->created_by)->first();
                    $uArr = [
                        'employee_name' => $User->name,
                        'company_name' => $company->name,
                        'leave_start_date' => $leave->start_date,
                        'leave_end_date' => $leave->end_date,
                    ];
                    try {
                        $resp = EmailTemplate::sendEmailTemplate('Employee Leave Received', [$company->email], $uArr);
                    } catch (\Exception $e) {
                        $resp['error'] = $e->getMessage();
                    }
                    return redirect()->route('leave.index')->with('success', __('The Leave status details are updated successfully.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
                }

                return redirect()->route('leave.index')->with('success', __('The leave has been created successfully.'));
            } else {
                return redirect()->back()->with('error', __('Leave type ' . $leave_type->name . ' is provide maximum ' . $leave_type->days . "  days please make sure your selected days is under " . $leave_type->days . ' days.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        // Determine if user is admin/company (not a regular employee)
        $currentUser = Auth::user();
        $currentUserType = $currentUser->type;
        $notEmpType = $currentUser->not_emp_type ?? [];
        $isAdminOrCompany = in_array($currentUserType, $notEmpType);

        $leave = Leave::with(['leaveType', 'EmployeeName'])
            ->leftJoin('work_spaces', 'work_spaces.id', '=', 'leaves.workspace')
            ->leftJoin('projects', 'projects.id', '=', 'leaves.site_id')
            ->select('leaves.*', 'work_spaces.name as workspace_name', 'projects.name as site_name');
        
        if (!$isAdminOrCompany) {
            $leave->where('leaves.workspace', getActiveWorkSpace())
                ->where('leaves.site_id', getActiveProject());
        }
        
        $leave = $leave->findOrFail($id);

    $employee  = $leave->EmployeeName;
    $leavetype = $leave->leaveType;

    return view('hrm::leave.show', compact('leave', 'employee', 'leavetype'));
}



    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit(Leave $leave)
    {
        if (Auth::user()->isAbleTo('leave edit')) {
            if ($leave->workspace  == getActiveWorkSpace()) {
                if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
                    $employees = Employee::where('user_id', '=', Auth::user()->id)->where('workspace', getActiveWorkSpace())->first();
                } else {
                    $employees = Employee::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get()->pluck('name', 'id');
                }
//                $leavetypes      = LeaveType::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get();
                
                $leavetypes      = LeaveType::all();

                return view('hrm::leave.edit', compact('leave', 'employees', 'leavetypes'));
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
    public function update(Request $request, Leave $leave)
    {
        if (Auth::user()->isAbleTo('leave edit')) {
            if ($leave->workspace  == getActiveWorkSpace()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'leave_type_id' => 'required',
                        'start_date' => 'required|date',
                        'end_date' => 'required',
                        'leave_reason' => 'required',
                        'remark' => 'required',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }
                $leave_type = LeaveType::find($request->leave_type_id);
                $startDate = new \DateTime($request->start_date);
                $endDate = new \DateTime($request->end_date);
                $endDate->add(new \DateInterval('P1D'));

                $date = AnnualLeaveCycle();

                // Leave day
                $leaves_used   = Leave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $leave->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Approved')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                $leaves_pending  = Leave::whereNotIn('id', [$leave->id])->where('employee_id', '=', $leave->employee_id)->where('leave_type_id', $leave_type->id)->where('status', 'Pending')->whereBetween('created_at', [$date['start_date'], $date['end_date']])->sum('total_leave_days');

                $total_leave_days = !empty($startDate->diff($endDate)) ? $startDate->diff($endDate)->days : 0;

                $return = $leave_type->days - $leaves_used;
                if ($total_leave_days > $return) {
                    return redirect()->back()->with('error', __('You are not eligible add more leave days.'));
                }
                if (!empty($leaves_pending) && $leaves_pending + $total_leave_days > $return) {
                    return redirect()->back()->with('error', __('Multiple leave entry is pending.'));
                }

                if ($leave_type->days >= $total_leave_days) {
                    if (in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
                        $employee = Employee::where('id', '=', $request->employee_id)->first();
                        $leave->employee_id = $request->employee_id;
                        $leave->user_id = $employee->user_id;
                    } else {
                        $employee = Employee::where('user_id', '=', creatorId())->first();
                        $leave->user_id = Auth::user()->id;
                        $leave->employee_id = $employee->id;
                    }
                    if (!empty($request->status)) {
                        $leave->status    = $request->status;
                    }
                    $leave->start_date       = $request->start_date;
                    $leave->end_date         = $request->end_date;
                    $leave->total_leave_days = $total_leave_days;
                    $leave->leave_reason     = $request->leave_reason;
                    $leave->remark           = $request->remark;

                    $leave->save();
                    event(new UpdateLeave($request, $leave));
                    return redirect()->route('leave.index')->with('success', __('The leave details are updated successfully.'));
                } else {
                    return redirect()->back()->with('error', __('Leave type ' . $leave_type->name . ' is provide maximum ' . $leave_type->days . "  days please make sure your selected days is under " . $leave_type->days . ' days.'));
                }
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy(Leave $leave)
    {
        if (Auth::user()->isAbleTo('leave delete')) {
            if ($leave->created_by == creatorId() &&  $leave->workspace  == getActiveWorkSpace() && $leave->status == 'Pending') {
                event(new DestroyLeave($leave));
                $leave->delete();

                return redirect()->route('leave.index')->with('success', __('The Leave has been deleted.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function jsoncount(Request $request)
    {
        $date = AnnualLeaveCycle();

        $leave_counts = LeaveType::select(DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave, leave_types.title, leave_types.days,leave_types.id'))->leftjoin(
            'leaves',
            function ($join) use ($request, $date) {
                $join->on('leaves.leave_type_id', '=', 'leave_types.id');
                $join->where('leaves.employee_id', '=', $request->employee_id);
                $join->where('leaves.status', '=', 'Approved');
                $join->whereBetween('leaves.created_at', [$date['start_date'], $date['end_date']]);
            }
        )->groupBy('leave_types.id')->get();
        return $leave_counts;
    }

public function action($id)
{
    

    if (Auth::user()->isAbleTo('leave approver manage')) {
        $leave     = Leave::find($id);
        $employee  = User::find($leave->user_id);
        $leavetype = LeaveType::find($leave->leave_type_id);

        // Check if leave type exists to avoid "Attempt to read property 'days' on null"
        if (is_null($leavetype)) {
            return redirect()->back()->with('error', __('Leave type not found.'));
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
            ->whereRaw('DAYOFWEEK(date) = 1') // MySQL: 1 = Sunday
            ->count();

        // Check if any previous leave for this employee in the same type has a 'Reject' status
        $hasRejectedLeave = Leave::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('id', '!=', $leave->id)
            ->where('status', 'Reject')
            ->exists();

        $allow_partial = !$hasRejectedLeave;

        // Attach extra properties to $leave
        // Load existing date-wise approvals so the modal can pre-fill when re-opened
        $existingDates = LeaveRequestDate::where('leave_request_id', $leave->id)
            ->pluck('status', 'leave_date');

        $leave->used = $usedDays;
        $leave->remaining_days = $leavetype->days - $usedDays + $sundaysWorked;
        $leave->sundays_worked = $sundaysWorked;
        $leave->days = $leavetype->days; // entitlement

       

        return view('hrm::leave.action', compact('employee', 'leavetype', 'leave', 'allow_partial', 'existingDates'));
    } else {
        return redirect()->back()->with('error', __('Permission denied.'));
    }
}




public function changeaction(Request $request)
{
    if (!Auth::user()->isAbleTo('leave approver manage')) {
        return redirect()->back()->with('error', __('Permission denied.'));
    }

    $leave = Leave::find($request->leave_id);

    $totalDays = \Carbon\Carbon::parse($leave->start_date)
        ->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1;

    DB::beginTransaction();
    try {
        if ($request->status === 'Approved') {
            // Approve all dates
            LeaveRequestDate::where('leave_request_id', $leave->id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
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
                    'approved_by' => Auth::id(),
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
                return redirect()->back()->with('error', __('Partially Approved is not allowed because a previous leave for this employee in the same leave type has been rejected.'));
            }
            // Check if new date-level approval payload exists
            if ($request->has('approved_dates') && is_array($request->approved_dates)) {
                // New date-level approval
                foreach ($request->approved_dates as $date => $data) {
                    LeaveRequestDate::where('leave_request_id', $leave->id)
                        ->where('leave_date', $date)
                        ->update([
                            'status' => $data['status'],
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                            'remarks' => $data['remarks'] ?? null,
                        ]);
                }
                $leave->recalculateDays();
                $leave->status = 'Partially Approved';
            } else {
                // Legacy approval with count only
                $request->validate([
                    'approved_days' => 'required|integer|min:1|max:' . $totalDays,
                ]);

                // Approve first N days (legacy behavior)
                $dateRecords = LeaveRequestDate::where('leave_request_id', $leave->id)
                    ->orderBy('leave_date')
                    ->get();

                foreach ($dateRecords as $index => $dateRecord) {
                    if ($index < $request->approved_days) {
                        $dateRecord->status = 'approved';
                        $dateRecord->approved_by = Auth::id();
                        $dateRecord->approved_at = now();
                    } else {
                        $dateRecord->status = 'rejected';
                        $dateRecord->approved_by = Auth::id();
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
    } catch (ValidationException $e) {
        DB::rollBack();
        return redirect()->back()->withErrors($e->errors())->withInput();
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->back()->with('error', __('Error updating leave status: ') . $e->getMessage());
    }

    // Fire event
    event(new LeaveStatus($leave));

    // Email notification logic...
    $company_settings = getCompanyAllSetting();
    if (!empty($company_settings['Leave Status']) && $company_settings['Leave Status'] === true) {
        $User = User::where('id', $leave->user_id)
            ->where('workspace_id', getActiveWorkSpace())
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
            $resp = EmailTemplate::sendEmailTemplate('Leave Status', [$User->email], $uArr);
        } catch (\Exception $e) {
            $resp['error'] = $e->getMessage();
        }

        return redirect()->route('leave.index')->with(
            'success',
        );
    }

    return redirect()->back()->with('success', __('The Leave status details are updated successfully.'));
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
                    'created_by' => creatorId(),
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
    
    public function status_reason($id)
    {
        $leaves = Leave::find($id);
        return view('hrm::leave.status_reason', compact('leaves'));
    }
}
