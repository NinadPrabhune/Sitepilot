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
            if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
                $employees = Employee::where('user_id', '=', Auth::user()->id)->where('workspace', getActiveWorkSpace())->first();
            } else {
                $employees = Employee::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get()->pluck('name', 'id');
            }
//            $leavetypes      = LeaveType::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->get();
            
//             dd(getActiveWorkSpace());
            
            
//            $leavetypes      = LeaveType::where('workspace', getActiveWorkSpace())->get();
            
           

            $leavetypes = LeaveType::where('workspace', getActiveWorkSpace())->get();

    
            foreach ($leavetypes as $lt) {
                $usedDays = Leave::where('employee_id', $employees->employee_id)
                    ->where('leave_type_id', $lt->id)
                    ->whereIn('status', ['Approved', 'Partially Approved'])
                    ->sum('approved_days');

                $sundaysWorked = Attendance::where('employee_id',  $employees->employee_id)
                    ->whereRaw('DAYOFWEEK(date) = 1') // MySQL: 1 = Sunday
                    ->count();

                $lt->used = $usedDays;
                $lt->remaining_days = $lt->days - $usedDays + $sundaysWorked;
                $lt->sundays_worked = $sundaysWorked;
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
    $leave = Leave::with(['leaveType', 'EmployeeName'])
        ->leftJoin('work_spaces', 'work_spaces.id', '=', 'leaves.workspace')
        ->leftJoin('projects', 'projects.id', '=', 'leaves.site_id')
        ->select('leaves.*', 'work_spaces.name as workspace_name', 'projects.name as site_name')
        ->findOrFail($id);

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
                
                $leavetypes      = LeaveType::where('workspace', getActiveWorkSpace())->get();

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
        )->where('leave_types.workspace','=',getActiveWorkSpace())->where('leave_types.created_by', '=', creatorId())->groupBy('leave_types.id')->get();
        return $leave_counts;
    }

    public function action($id)
{
    if (Auth::user()->isAbleTo('leave approver manage')) {
        $leave     = Leave::find($id);
        $employee  = User::find($leave->user_id);
        $leavetype = LeaveType::find($leave->leave_type_id);

        // Calculate used days for this employee and leave type
        $usedDays = Leave::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->sum('approved_days');

        // Count Sundays worked from attendance
        $sundaysWorked = Attendance::where('employee_id', $leave->employee_id)
            ->whereRaw('DAYOFWEEK(date) = 1') // MySQL: 1 = Sunday
            ->count();

        // Attach extra properties to $leave
        $leave->used = $usedDays;
        $leave->remaining_days = $leavetype->days - $usedDays + $sundaysWorked;
        $leave->sundays_worked = $sundaysWorked;
        $leave->days = $leavetype->days; // entitlement

        return view('hrm::leave.action', compact('employee', 'leavetype', 'leave'));
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

    try {
        if ($request->status === 'Approved') {
            $leave->status = 'Approved';
            $leave->total_leave_days = $totalDays;
            $leave->approved_days = $totalDays;
        } elseif ($request->status === 'Reject') {
            $leave->status = 'Reject';
            $leave->approved_days = 0;
        } elseif ($request->status === 'Partially Approved') {
            // ✅ use $request->validate
            $request->validate([
                'approved_days' => 'required|integer|min:1|max:' . $totalDays,
            ]);

            $leave->status = 'Partially Approved';
            $leave->approved_days = $request->approved_days;
            $leave->total_leave_days = $totalDays;
        }
          $leave->status_reason = $request->status_reason;
        $leave->save();
    } catch (ValidationException $e) {
        // Redirect back instantly with validation error
        return redirect()->back()->withErrors($e->errors())->withInput();
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
            __('Leave status successfully updated.') .
            ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error']))
                ? '<br> <span class="text-danger">' . $resp['error'] . '</span>'
                : '')
        );
    }

    return redirect()->back()->with('success', __('The Leave status details are updated successfully.'));
}




    public function description($id)
    {
        $leaves = Leave::find($id);
        return view('hrm::leave.description', compact('leaves'));
    }
    
    public function status_reason($id)
    {
        $leaves = Leave::find($id);
        return view('hrm::leave.status_reason', compact('leaves'));
    }
}
