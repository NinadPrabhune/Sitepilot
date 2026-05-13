<?php

namespace Workdo\Hrm\Http\Controllers;

use App\Models\User;
use DateTime;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Attendance;
use Workdo\Hrm\Entities\Branch;
use Workdo\Hrm\Entities\Department;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\Leave;
use Workdo\Hrm\Entities\LeaveType;
use Workdo\Hrm\Entities\PaySlip;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function monthlyAttendance(Request $request)
    {
        // Minimal logging for performance tracking
        \Log::info('Attendance report accessed', [
            'user_id' => Auth::id(),
            'month' => $request->month ?? date('Y-m')
        ]);
        
        if (Auth::user()->isAbleTo('attendance monthly-report')) {
            // Permission check passed - no logging needed for each successful check
            
            $branch = Branch::get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::get()->pluck('name', 'id');
            $department->prepend('All', '');

            if ($request->branch_id == null) {
                $data['branch']     = __('All');
            } else {
                $data['branch']     = $branch->get($request->branch_id);
            }
            if ($request->branch_id == null) {
                $data['department'] = __('All');
            } else {
                $data['department'] = $department->get($request->department_id);
            }

            $employeeQuery = \App\Models\User::leftjoin('employees', 'users.id', '=', 'employees.user_id')
                ->select('users.id', 'users.name', 'employees.id as employee_id');

            // Set employeeId for view
            $employeeId = $request->employee_id ?? '';

            // Employee filter processing - no debug logging needed

            if (!empty($request->branch)) {
                $employeeQuery->where('branch_id', $request->branch);
            }

            if (!empty($request->department)) {
                $employeeQuery->where('department_id', $request->department);
            }
            if (!empty($request->employee_id)) {
                if (is_array($request->employee_id)) {
                    if (!in_array('0', $request->employee_id)) {
                        $employeeQuery->whereIn('employees.id', $request->employee_id);
                    }
                } else {
                    $employeeQuery->where('employees.id', $request->employee_id);
                }
            }

            $employees = $employeeQuery->get()
                ->map(function($user) {
                    return [
                        'employee_id' => $user->employee_id,
                        'name' => $user->name
                    ];
                });

            if ($request->has('week') && $request->type == 'weekly') {
                $week = $request->input('week');
                $year = substr($week, 0, 4);
                $week_number = substr($week, -2);

                $start_date = date("Y-m-d", strtotime($year . "W" . $week_number));
                $week_dates = [];

                $date = new DateTime($start_date);
                for ($i = 0; $i < 7; $i++) {
                    $week_dates[] = $date->format('d-m-Y');
                    $dates[] = date('d', strtotime($date->format('Y-m-d')));
                    $date->modify('+1 day');
                }
                $start_date = reset($week_dates);
                $end_date = end($week_dates);
                $curMonth    = $start_date . __(' To ') . $end_date;
            } elseif ($request->has('month') && $request->type == 'monthly') {
                $currentdate = strtotime($request->month);
                $month       = date('m', $currentdate);
                $year        = date('Y', $currentdate);

                $curMonth    = date('M-Y', strtotime($request->month));

                $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
                for ($i = 1; $i <= $num_of_days; $i++) {
                    $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
                }
            } else {
                // Check if month parameter is provided (even without type=monthly)
                if ($request->has('month')) {
                    $currentdate = strtotime($request->month);
                    $month       = date('m', $currentdate);
                    $year        = date('Y', $currentdate);
                    $curMonth    = date('M-Y', strtotime($request->month));
                } else {
                    $month    = date('m');
                    $year     = date('Y');
                    $curMonth = date('M-Y', strtotime($year . '-' . $month));
                }

                $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
                for ($i = 1; $i <= $num_of_days; $i++) {
                    $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
                }
            }

            $employeesAttendance = [];
            $totalPresent        = $totalLeave = $totalEarlyLeave = 0;
            $ovetimeHours        = $overtimeMins = $earlyleaveHours = $earlyleaveMins = $lateHours = $lateMins = 0;
            // Batch fetch all attendance records for all employees at once
            $monthStart = $year . '-' . $month . '-01';
            $monthEnd = $year . '-' . $month . '-' . $num_of_days;
            
            $employeeIds = $employees->pluck('employee_id')->filter();
            
            // Get all attendance records for all employees in one query
            $allAttendances = Attendance::whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('employee_id');
            
            // Get all approved leaves for all employees in one query
            $allLeaves = Leave::whereIn('employee_id', $employeeIds)
                ->whereIn('status', ['Approved', 'Partially Approved'])
                ->where(function($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function($q) use ($monthStart, $monthEnd) {
                            $q->where('start_date', '<', $monthStart)
                                ->where('end_date', '>', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('employee_id');
            
            \Log::info('Batch data fetched', [
                'total_employees' => count($employeeIds),
                'attendance_records' => $allAttendances->count(),
                'leave_records' => $allLeaves->count(),
                'month_range' => [$monthStart, $monthEnd]
            ]);
            
            foreach ($employees as $employee) {
                $employeeId = $employee['employee_id'];
                
                $attendances['name'] = $employee['name'];
                $attendanceStatus = []; // Reset attendance status for each employee
                
                if ($request->type == 'weekly') {
                    foreach ($week_dates as $date) {
                        $employeeAttendance = Attendance::where('employee_id', $employeeId)
                            ->whereDate('date', '=', date('Y-m-d', strtotime($date)))
                            ->first();

                        if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                            $attendanceStatus[$date] = 'P';
                            $totalPresent            += 1;

                            if ($employeeAttendance->overtime > 0) {
                                $ovetimeHours += date('h', strtotime($employeeAttendance->overtime));
                                $overtimeMins += date('i', strtotime($employeeAttendance->overtime));
                            }

                            if ($employeeAttendance->early_leaving > 0) {
                                $earlyleaveHours += date('h', strtotime($employeeAttendance->early_leaving));
                                $earlyleaveMins  += date('i', strtotime($employeeAttendance->early_leaving));
                            }

                            if ($employeeAttendance->late > 0) {
                                $lateHours += date('h', strtotime($employeeAttendance->late));
                                $lateMins  += date('i', strtotime($employeeAttendance->late));
                            }
                        } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                            $attendanceStatus[$date] = 'L';
                            $totalLeave              += 1;
                        } else {
                            $attendanceStatus[$date] = '';
                        }
                    }
                } else {
                    // Use pre-fetched data instead of individual queries
                    $employeeAttendances = $allAttendances->get($employeeId, collect());
                    $approvedLeaves = $allLeaves->get($employeeId, collect());
                    
                    // Key attendance records by day for faster lookup
                    $employeeAttendances = $employeeAttendances->keyBy(function($attendance) {
                        return date('d', strtotime($attendance->date));
                    });
                    
                    foreach ($dates as $date) {
                        $dateFormat = $year . '-' . $month . '-' . $date;
                        
                        // Check if this date is within any approved leave period
                        $isOnLeave = false;
                        foreach ($approvedLeaves as $leave) {
                            if ($dateFormat >= $leave->start_date && $dateFormat <= $leave->end_date) {
                                $isOnLeave = true;
                                break;
                            }
                        }
                        
                        if ($isOnLeave) {
                            // Show 'L' for approved leave dates (both past and future)
                            $attendanceStatus[$date] = 'L';
                            $totalLeave              += 1;
                        } elseif ($dateFormat <= date('Y-m-d')) {
                            // Only check attendance for past dates
                            $employeeAttendance = $employeeAttendances->get($date);
                            
                            if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                                $attendanceStatus[$date] = 'P';
                                $totalPresent            += 1;

                                if ($employeeAttendance->overtime > 0) {
                                    $ovetimeHours += date('h', strtotime($employeeAttendance->overtime));
                                    $overtimeMins += date('i', strtotime($employeeAttendance->overtime));
                                }

                                if ($employeeAttendance->early_leaving > 0) {
                                    $earlyleaveHours += date('h', strtotime($employeeAttendance->early_leaving));
                                    $earlyleaveMins  += date('i', strtotime($employeeAttendance->early_leaving));
                                }

                                if ($employeeAttendance->late > 0) {
                                    $lateHours += date('h', strtotime($employeeAttendance->late));
                                    $lateMins  += date('i', strtotime($employeeAttendance->late));
                                }
                            } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                                $attendanceStatus[$date] = 'L';
                                $totalLeave              += 1;
                            } else {
                                $attendanceStatus[$date] = 'A';
                            }
                        } else {
                            // Future dates without approved leave show blank
                            $attendanceStatus[$date] = '';
                        }
                    }
                }

                // Calculate present days for this employee
                $presentDays = count(array_filter($attendanceStatus, function($status) {
                    return $status == 'P';
                }));
                                
                                
                $attendances['attendance'] = $attendanceStatus;
                $attendances['present_days'] = $presentDays;
                $employeesAttendance[$employeeId] = $attendances;
            }

            $totalOverTime   = $ovetimeHours + ($overtimeMins / 60);
            $totalEarlyleave = $earlyleaveHours + ($earlyleaveMins / 60);
            $totalLate       = $lateHours + ($lateMins / 60);

            $data['totalOvertime']   = $totalOverTime;
            $data['totalEarlyLeave'] = $totalEarlyleave;
            $data['totalLate']       = $totalLate;
            $data['totalPresent']    = $totalPresent;
            $data['totalLeave']      = $totalLeave;
            $data['curMonth']        = $curMonth;

            \Log::info('Attendance report completed', [
                'user_id' => Auth::id(),
                'employees_processed' => count($employeesAttendance ?? []),
                'total_present' => $data['totalPresent'] ?? 0,
                'total_leave' => $data['totalLeave'] ?? 0,
                'query_optimization' => 'batch_queries_used'
            ]);
            
            // Prepare data for view with expected variable names
            $attendanceData = $employeesAttendance;
            
            $employees = $employees->toArray(); // Convert Collection to array for view compatibility
            
            $workingDays = count($dates ?? []);
            $averageAttendance = $workingDays > 0 ? round(($data['totalPresent'] / ($workingDays * count($employeesAttendance))) * 100, 2) : 0;
            $month = $request->month ?? date('Y-m');
            
            // Remove debug logging to reduce log size
            // \Log::info('DEBUG - All arrays being passed to view:', [
            //     'attendanceData' => $attendanceData,
            //     'employees' => $employees,
            //     'branch' => $branch,
            //     'department' => $department,
            //     'dates' => $dates,
            //     'data' => $data,
            //     'workingDays' => $workingDays,
            //     'averageAttendance' => $averageAttendance,
            //     'month' => $month
            // ]);
            
            return view('hrm::report.monthlyAttendance', compact('attendanceData', 'employees', 'branch', 'department', 'dates', 'data', 'workingDays', 'averageAttendance', 'month', 'year', 'employeeId'));
        } else {
            \Log::warning('Permission denied for attendance monthly-report', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()->email ?? 'unknown',
                'required_permission' => 'attendance monthly-report'
            ]);
            
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function leave(Request $request)
    {
        if (Auth::user()->isAbleTo('leave report manage')) {

            $branch = Branch::get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::get()->pluck('name', 'id');
            $department->prepend('All', '');

            $filterYear['branch']        = __('All');
            $filterYear['department']    = __('All');
            $filterYear['type']          = __('Monthly');
            $filterYear['dateYearRange'] = date('M-Y');

            $employees = User::where('workspace_id', getActiveWorkSpace())
                ->leftjoin('employees', 'users.id', '=', 'employees.user_id')
                ->where('users.created_by', creatorId())->emp()
                ->select('users.id', 'users.name', 'employees.employee_id');

            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }
            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }


            $employees = $employees->get();

            $leaves        = [];
            $totalApproved = $totalReject = $totalPending = 0;
            foreach ($employees as $employee) {
                $employeeLeave['id']          = $employee->id;
                $employeeLeave['employee_id'] = $employee->employee_id;
                $employeeLeave['employee']    = $employee->name;

                $approved = Leave::where('user_id', $employee->id)->where('status', 'Approved');
                $reject   = Leave::where('user_id', $employee->id)->where('status', 'Reject');
                $pending  = Leave::where('user_id', $employee->id)->where('status', 'Pending');

                if ($request->type == 'monthly' && !empty($request->month)) {
                    $month = date('m', strtotime($request->month));
                    $year  = date('Y', strtotime($request->month));

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($request->month));
                    $filterYear['type']          = __('Monthly');
                } elseif (!isset($request->type)) {
                    $month     = date('m');
                    $year      = date('Y');
                    $monthYear = date('Y-m');

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($monthYear));
                    $filterYear['type']          = __('Monthly');
                }

                if ($request->type == 'yearly' && !empty($request->year)) {
                    $approved->whereYear('applied_on', $request->year);
                    $reject->whereYear('applied_on', $request->year);
                    $pending->whereYear('applied_on', $request->year);


                    $filterYear['dateYearRange'] = $request->year;
                    $filterYear['type']          = __('Yearly');
                }

                $approved = $approved->count();
                $reject   = $reject->count();
                $pending  = $pending->count();

                $totalApproved += $approved;
                $totalReject   += $reject;
                $totalPending  += $pending;

                $employeeLeave['approved'] = $approved;
                $employeeLeave['reject']   = $reject;
                $employeeLeave['pending']  = $pending;


                $leaves[] = $employeeLeave;
            }

            $starting_year = date('Y', strtotime('-5 year'));
            $ending_year   = date('Y', strtotime('+5 year'));

            $filterYear['starting_year'] = $starting_year;
            $filterYear['ending_year']   = $ending_year;

            $filter['totalApproved'] = $totalApproved;
            $filter['totalReject']   = $totalReject;
            $filter['totalPending']  = $totalPending;


            return view('hrm::report.leave', compact('department', 'branch', 'leaves', 'filterYear', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function employeeLeave(Request $request, $employee_id, $status, $type, $month, $year)
    {
        if (Auth::user()->isAbleTo('leave report manage')) {
            $leaveTypes = LeaveType::where('workspace', getActiveWorkSpace())->get();

            $leaves     = [];
            foreach ($leaveTypes as $leaveType) {
                $leave        = new Leave();
                $leave->title = $leaveType->title;
                $totalLeave   = Leave::where('user_id', '=', $employee_id)->where('status', $status)->where('workspace', getActiveWorkSpace())->where('leave_type_id', $leaveType->id);
                if ($type == 'yearly') {
                    $totalLeave->whereYear('applied_on', $year);
                } else {
                    $m = date('m', strtotime($month));
                    $y = date('Y', strtotime($month));

                    $totalLeave->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
                }
                $totalLeave = $totalLeave->get()->count();
                $leave->total = $totalLeave;
                $leaves[]     = $leave;
            }
            $leaveData = Leave::where('user_id', '=', $employee_id)->where('status', $status)->where('workspace', getActiveWorkSpace());
            if ($type == 'yearly') {
                $leaveData->whereYear('applied_on', $year);
            } else {
                $m = date('m', strtotime($month));
                $y = date('Y', strtotime($month));

                $leaveData->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
            }
            $leaveData = $leaveData->get();

            return view('hrm::report.leaveShow', compact('leaves', 'leaveData'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function Payroll(Request $request)
    {
        if (Auth::user()->isAbleTo('payroll report manage')) {
            $count_key = 0;
            $data = [];
            if (!empty($request->all()) && !empty($request->start_month) && !empty($request->end_month) && !empty($request->report_type) && !empty($request->employees)) {
                $selected_month = [];

                $start    = new \DateTime($request->start_month);
                $start->modify('first day of this month');
                $end      = new \DateTime($request->end_month);
                $end->modify('first day of next month');
                $interval = \DateInterval::createFromDateString('1 month');
                $period   = new \DatePeriod($start, $interval, $end);

                // Selected Months Get and set header
                $report_type = !empty($request->report_type) ? $request->report_type : 'allowance';
                $header_args = [];
                $header_args[] = 'Name';

                foreach ($period as $dt) {
                    $selected_month[] =  $dt->format("Y-m");
                    $header_args[] =  $dt->format("M-Y");
                }
                $header_args[] = 'Total';

                // Get  selected Employees
                $employees = Employee::where('workspace', getActiveWorkSpace());
                if (isset($request->employees) && !in_array('0', $request->employees)) {
                    $employees = $employees->whereIn('id', $request->employees);
                }
                $employees = $employees->get();

                // calculation
                foreach ($employees as $index => $employee) {
                    $temp_data = [];
                    $temp_data[] = $employee->name;

                    $month_calculation = Employee::PayrollCalculation($employee->id, $selected_month, $report_type);

                    $temp_data =  array_merge($temp_data, $month_calculation);

                    array_push($data, $temp_data);

                    $count_key = count($month_calculation);
                }
            }

            if (empty($request->all()) || $request->is_export == 'no' || !empty($request->all())) {
                $employees_box = [];
                $report_type = [
                    '' => 'Please Select',
                    'allowance' => 'Allowance',
                    'commission' => 'Commission',
                    'loan' => 'Loan',
                    'saturation_deduction' => 'Saturation Deduction',
                    'other_payment' => 'Other Payment',
                    'overtime' => 'Overtime',
                ];

                if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
                    $employees = Employee::where('user_id', Auth::user()->id)->where('workspace', getActiveWorkSpace())->get();
                } else {
                    if (!empty($request->all())) {
                        $employees = Employee::select('employees.*', 'employees.name')
                            ->leftJoin('pay_slips', 'employees.id', '=', 'pay_slips.employee_id')
                            ->where('pay_slips.created_by', creatorId())
                            ->where('pay_slips.salary_month', '>=', $request->start_month)
                            ->where('pay_slips.salary_month', '<=', $request->end_month);
                    } else {
                        $employees = Employee::where('workspace', getActiveWorkSpace());
                    }
                    $employees_box = $employees->pluck('name', 'employees.id');

                    if (isset($request->employees) && !in_array('0', $request->employees)) {
                        $employees = $employees->whereIn('employees.id', $request->employees);
                    }
                    $employees = $employees->get();
                }

                return view('hrm::report.payroll', compact('employees', 'employees_box', 'report_type', 'data'));
            }
            if (!empty($request->all()) && $request->is_export == 'yes') {
                // For Final Total
                $final_total = [];
                $final_total[] = 'Total';
                for ($i = 1; $i <= $count_key; $i++) {
                    $final_total[] = array_sum(array_map(fn($item) => $item[$i], $data));
                }
                array_push($data, $final_total);

                $filename = $report_type . "-" . date('Ymd') . ".csv";
                header('Content-Type: text/csv; charset=utf-8');
                header("Content-Disposition: attachment; filename=\"$filename\"");


                $output = fopen('php://output', 'w');
                ob_end_clean();
                fputcsv($output, $header_args);
                foreach ($data as $data_item) {
                    fputcsv($output, $data_item);
                }
                exit;
                return redirect()->route('report.payroll');
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function getdepartment(Request $request)
    {
        // Global master data - filter by branch_id only if provided
        if ($request->branch_id == 0 || empty($request->branch_id)) {
            $departments = Department::all()->pluck('name', 'id')->toArray();
        } else {
            $departments = Department::where('branch_id', $request->branch_id)->pluck('name', 'id')->toArray();
        }

        return response()->json($departments);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function getemployee(Request $request)
    {
        $employees = [];
        if (isset($request->department_id)) {

            if (!$request->department_id) {
                $employees = Employee::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
            } else {

                $employees = Employee::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->where('department_id', $request->department_id)->get()->pluck('name', 'id')->toArray();
            }
        }

        return response()->json($employees);
    }

    /**
     * Get attendance details for a specific employee and date
     */
    public function getAttendanceDetails(Request $request)
    {
        $employeeId = $request->employee_id;
        $date = $request->date;

        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $date)
            ->with(['employees', 'site', 'workspaceRelation'])
            ->first();

          

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ]);
        }

        
        $html = view('hrm::report.attendance-details-modal', compact('attendance'))->render();

        return response()->json([
            'success' => true,
            'html' => $html
        ]);
    }

    /**
     * Get leave details for a specific employee and date
     */
    public function getLeaveDetails(Request $request)
    {
        $employeeId = $request->employee_id;
        $date = $request->date;

        $leave = Leave::where('employee_id', $employeeId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->with(['leaveType', 'EmployeeName'])
            ->first();

        if (!$leave) {
            return response()->json([
                'success' => false,
                'message' => 'Leave record not found'
            ]);
        }

        $html = view('hrm::report.leave-details-modal', compact('leave'))->render();

        return response()->json([
            'success' => true,
            'html' => $html
        ]);
    }
}
