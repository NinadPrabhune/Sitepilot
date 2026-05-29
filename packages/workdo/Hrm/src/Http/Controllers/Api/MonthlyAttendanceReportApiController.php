<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Workdo\Hrm\Entities\Attendance;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\Branch;
use Workdo\Hrm\Entities\Department;
use Workdo\Hrm\Entities\LeaveRequestDate;
use Workdo\Hrm\Http\Resources\EmployeeMonthlyAttendanceResource;
use Workdo\Hrm\Http\Resources\MonthlyAttendanceSummaryResource;

/**
 * @group HRM Attendance Monthly Report
 * Endpoints for monthly attendance report including employee-wise reports,
 * date/month filters, attendance summary, and export support
 */
class MonthlyAttendanceReportApiController extends Controller
{
    /**
     * Check if user is admin or company (not a regular employee)
     */
    private function isAdminOrCompany($user)
    {
        $notEmpType = $user->not_emp_type ?? ['super admin', 'company', 'admin', 'Admin', 'client', 'vendor', 'driver', 'salesagent', 'hr', 'gym member', 'gym trainer', 'advocate', 'case initiator', 'parent', 'Site / Project Manager', 'Account Manager'];
        return in_array($user->type, $notEmpType);
    }

    /**
     * Get monthly attendance report
     *
     * Generate a monthly attendance report with employee-wise attendance data.
     * Includes present/absent/leave counts, late entry, and overtime information.
     *
     * @authenticated
     *
     * @queryParam month string optional The month to filter (YYYY-MM format). Defaults to current month. Example: 2024-05
     * @queryParam employee_id integer optional Filter by specific employee ID. Use 'all' for all employees. Example: 5
     * @queryParam branch_id integer optional Filter by branch ID. Example: 1
     * @queryParam department_id integer optional Filter by department ID. Example: 2
     * @queryParam type string optional Report type: 'monthly' or 'weekly'. Defaults to 'monthly'. Example: monthly
     * @queryParam week string optional Week to filter (YYYY-WW format) when type is 'weekly'. Example: 2024-02
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "summary": {
     *       "month": "05",
     *       "year": "2024",
     *       "month_display": "May-2024",
     *       "working_days": 31,
     *       "total_present": 22,
     *       "total_leave": 3,
     *       "total_absent": 6,
     *       "leave_dates": ["03", "05", "15"],
     *       "absent_dates": ["04", "07", "12"],
     *       "total_late_count": 2,
     *       "total_late_hours": 1.5,
     *       "total_early_leaving_count": 1,
     *       "total_early_leaving_hours": 0.5,
     *       "total_overtime_hours": 3.0,
     *       "average_attendance": 91.67,
     *       "dates": ["01", "02", "03", "...", "31"]
     *     },
     *     "employees": [
     *       {
     *         "employee_id": 5,
     *         "employee_name": "John Doe",
     *         "present_days": 22,
     *         "leave_days": 3,
     *         "absent_days": 5,
     *         "late_count": 2,
     *         "late_hours": 1.5,
     *         "early_leaving_count": 1,
     *         "early_leaving_hours": 0.5,
     *         "overtime_hours": 3.0,
     *         "attendance": {
     *           "01": "P",
     *           "02": "P",
     *           "03": "L",
     *           "04": "A"
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 {
     *   "status": 0,
     *   "message": "Permission denied."
     * }
     */
public function index(Request $request)
      {
        //   Log::info('MonthlyAttendanceReportApiController:index started', ['request' => $request->all()]);
         try {
             if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());
            $user = Auth::user();

            // Determine month and year
            if ($request->has('week') && $request->type == 'weekly') {
                $week = $request->input('week');
                $year = substr($week, 0, 4);
                $week_number = substr($week, -2);

                $startDate = date("Y-m-d", strtotime($year . "W" . $week_number));
                $weekDates = [];
                $dates = [];

                $date = new \DateTime($startDate);
                for ($i = 0; $i < 7; $i++) {
                    $weekDates[] = $date->format('d-m-Y');
                    $dates[] = date('d', strtotime($date->format('Y-m-d')));
                    $date->modify('+1 day');
                }
                $month = date('m', strtotime($startDate));
                $curMonth = date('M-Y', strtotime($startDate));
            } elseif ($request->has('month') && $request->type == 'monthly') {
                $currentdate = strtotime($request->month);
                $month = date('m', $currentdate);
                $year = date('Y', $currentdate);
                $curMonth = date('M-Y', strtotime($request->month));

                $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
                for ($i = 1; $i <= $num_of_days; $i++) {
                    $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
                }
            } else {
                if ($request->has('month')) {
                    $currentdate = strtotime($request->month);
                    $month = date('m', $currentdate);
                    $year = date('Y', $currentdate);
                    $curMonth = date('M-Y', strtotime($request->month));
                } else {
                    $month = date('m');
                    $year = date('Y');
                    $curMonth = date('M-Y', strtotime($year . '-' . $month));
                }

                $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
                for ($i = 1; $i <= $num_of_days; $i++) {
                    $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
                }
             }

            //  Log::info('Month and year determined', ['month' => $month, 'year' => $year, 'curMonth' => $curMonth, 'dates_count' => count($dates)]);

              // Build employee query
              $employeeQuery = \App\Models\User::leftjoin('employees', 'users.id', '=', 'employees.user_id')
                  ->select('users.id', 'users.name', 'employees.id as employee_id');
            //  Log::info('Employee query built');



              // Apply filters
             if (!empty($request->branch_id) && $request->branch_id !== 'all') {
                 $employeeQuery->where('employees.branch_id', $request->branch_id);
             }

             if (!empty($request->department_id) && $request->department_id !== 'all') {
                 $employeeQuery->where('employees.department_id', $request->department_id);
             }

             if (!empty($request->employee_id) && $request->employee_id !== 'all') {
                 $employeeQuery->where('employees.id', $request->employee_id);
             }

            //  Log::info('Filters applied', ['branch_id' => $request->branch_id, 'department_id' => $request->department_id, 'employee_id' => $request->employee_id]);

              // Role-based filtering
               if (!$this->isAdminOrCompany($user) && empty($request->employee_id)) {
                  $employee = Employee::where('user_id', $user->id)->first();
                  if ($employee) {
                      $employeeQuery->where('employees.id', $employee->id);
                  }
              }

            //    Log::info('Role-based filtering applied', ['isAdminOrCompany' => $this->isAdminOrCompany($user)]);

              $employees = $employeeQuery->get()
                  ->map(function ($user) {
                      return [
                          'employee_id' => $user->employee_id,
                          'name' => $user->name
                      ];
                  })
                  ->reject(function ($employee) {
                      return is_null($employee['employee_id']);
                  })
                  ->values();

            //   Log::info('Employees retrieved', ['count' => $employees->count()]);
              if ($employees->count() > 0) {
                  Log::info('First employee sample', ['sample' => $employees->first()]);
              }

              // Month date range
             $monthStart = $year . '-' . $month . '-01';
             $monthEnd = $year . '-' . $month . '-' . ($num_of_days ?? count($dates));

            //  Log::info('Month date range set', ['monthStart' => $monthStart, 'monthEnd' => $monthEnd]);

             $employeeIds = $employees->pluck('employee_id')->filter();

             // Batch fetch all attendance records
              $allAttendances = Attendance::whereIn('employee_id', $employeeIds)
                  ->whereBetween('date', [$monthStart, $monthEnd])
                  ->get()
                  ->groupBy('employee_id');

            //  Log::info('Attendance records fetched', ['count' => $allAttendances->count()]);

              // Batch fetch all approved leave dates
             $allApprovedLeaveDates = LeaveRequestDate::select('leave_request_dates.*', 'leaves.employee_id')
                 ->join('leaves', 'leave_request_dates.leave_request_id', '=', 'leaves.id')
                 ->whereIn('leaves.employee_id', $employeeIds)
                 ->where('leave_request_dates.status', 'approved')
                 ->where(function ($query) use ($monthStart, $monthEnd) {
                     $query->where('leave_request_dates.leave_date', '>=', $monthStart)
                         ->where('leave_request_dates.leave_date', '<=', $monthEnd);
                 })
                 ->get()
                 ->groupBy('employee_id');

            //  Log::info('Approved leave dates fetched', ['count' => $allApprovedLeaveDates->count()]);

             $employeesAttendance = [];
            $totalPresent = 0;
            $totalLeave = 0;
            $totalAbsent = 0;
            $totalLateHours = $totalLateMins = 0;
            $totalEarlyLeaveHours = $totalEarlyLeaveMins = 0;
            $totalOvertimeHours = $totalOvertimeMins = 0;
            $totalLateCount = 0;
            $totalEarlyLeaveCount = 0;
            $totalLeaveDates = [];
            $totalAbsentDates = [];

foreach ($employees as $employee) {
                $employeeId = $employee['employee_id'];

                $attendanceStatus = [];
                $employeeLateCount = 0;
                $employeeEarlyLeaveCount = 0;

                if ($request->type == 'weekly') {
                    foreach ($weekDates as $date) {
                        $employeeAttendance = Attendance::where('employee_id', $employeeId)
                            ->where('workspace', $workspaceId)
                            ->whereDate('date', '=', date('Y-m-d', strtotime($date)))
                            ->first();

                        if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                            $attendanceStatus[$date] = 'P';
                            $totalPresent += 1;

                            if ($employeeAttendance->overtime > 0) {
                                $totalOvertimeHours += (int) date('H', strtotime($employeeAttendance->overtime));
                                $totalOvertimeMins += (int) date('i', strtotime($employeeAttendance->overtime));
                            }

                            if ($employeeAttendance->early_leaving > 0) {
                                $totalEarlyLeaveHours += (int) date('H', strtotime($employeeAttendance->early_leaving));
                                $totalEarlyLeaveMins += (int) date('i', strtotime($employeeAttendance->early_leaving));
                                $employeeEarlyLeaveCount += 1;
                            }

                            if ($employeeAttendance->late > 0) {
                                $totalLateHours += (int) date('H', strtotime($employeeAttendance->late));
                                $totalLateMins += (int) date('i', strtotime($employeeAttendance->late));
                                $employeeLateCount += 1;
                            }
                        } elseif (!empty($employeeAttendance) && strtolower($employeeAttendance->status) == 'leave') {
                            $attendanceStatus[$date] = 'L';
                            $totalLeave += 1;
                        } else {
                            $attendanceStatus[$date] = '';
                        }
                    }

                    $presentDays = count(array_filter($attendanceStatus, function ($status) {
                        return $status == 'P';
                    }));
                } else {
                    $employeeAttendances = $allAttendances->get($employeeId, collect());
                    $approvedLeaveDates = $allApprovedLeaveDates->get($employeeId, collect());

                    // Key attendance records by day for faster lookup
                    $employeeAttendances = $employeeAttendances->keyBy(function ($attendance) {
                        return date('d', strtotime($attendance->date));
                    });

                    // Key approved leave dates by day for faster lookup
                    $approvedLeaveDatesByDay = $approvedLeaveDates->keyBy(function ($leaveDate) {
                        return date('d', strtotime($leaveDate->leave_date));
                    });

                    foreach ($dates as $date) {
                        $dateFormat = $year . '-' . $month . '-' . $date;

                        $isOnApprovedLeave = $approvedLeaveDatesByDay->has($date);

                        if ($isOnApprovedLeave) {
                            $attendanceStatus[$date] = 'L';
                            $totalLeave += 1;
} elseif ($dateFormat <= date('Y-m-d')) {
                            $employeeAttendance = $employeeAttendances->get($date);

                            if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                                $attendanceStatus[$date] = 'P';
                                $totalPresent += 1;

                                if ($employeeAttendance->overtime > 0) {
                                    $totalOvertimeHours += (int) date('H', strtotime($employeeAttendance->overtime));
                                    $totalOvertimeMins += (int) date('i', strtotime($employeeAttendance->overtime));
                                }

                                if ($employeeAttendance->early_leaving > 0) {
                                    $totalEarlyLeaveHours += (int) date('H', strtotime($employeeAttendance->early_leaving));
                                    $totalEarlyLeaveMins += (int) date('i', strtotime($employeeAttendance->early_leaving));
                                    $employeeEarlyLeaveCount += 1;
                                    $totalEarlyLeaveCount += 1;
                                }

                                if ($employeeAttendance->late > 0) {
                                    $totalLateHours += (int) date('H', strtotime($employeeAttendance->late));
                                    $totalLateMins += (int) date('i', strtotime($employeeAttendance->late));
                                    $employeeLateCount += 1;
                                    $totalLateCount += 1;
                                }
                            } elseif (!empty($employeeAttendance) && strtolower($employeeAttendance->status) == 'leave') {
                                $attendanceStatus[$date] = 'L';
                                $totalLeave += 1;
                            } else {
                                $attendanceStatus[$date] = 'A';
                                $totalAbsent += 1;
                            }
                        } else {
                            $attendanceStatus[$date] = '';
                        }
                    }

                    $presentDays = count(array_filter($attendanceStatus, function ($status) {
                        return $status == 'P';
                    }));
                }

                $leaveDays = count(array_filter($attendanceStatus, function ($status) {
                    return $status == 'L';
                }));

                $absentDays = count(array_filter($attendanceStatus, function ($status) {
                    return $status == 'A';
                }));

                $lateHours = 0;
                $earlyLeaveHours = 0;
                $overtimeHours = 0;

                $employeeAttendances = $allAttendances->get($employeeId, collect())->keyBy(function ($attendance) {
                    return date('d', strtotime($attendance->date));
                });

                foreach ($dates as $date) {
                    $attendance = $employeeAttendances->get($date);
                    if (!empty($attendance)) {
                        if ($attendance->late > 0) {
                            $lateHours += date('H', strtotime($attendance->late)) + (date('i', strtotime($attendance->late)) / 60);
                        }
                        if ($attendance->early_leaving > 0) {
                            $earlyLeaveHours += date('H', strtotime($attendance->early_leaving)) + (date('i', strtotime($attendance->early_leaving)) / 60);
                        }
                        if ($attendance->overtime > 0) {
                            $overtimeHours += date('H', strtotime($attendance->overtime)) + (date('i', strtotime($attendance->overtime)) / 60);
                        }
                    }
                }

                $employeesAttendance[] = [
                    'employee_id' => $employeeId,
                    'name' => $employee['name'],
                    'present_days' => $presentDays,
                    'leave_days' => $leaveDays,
                    'absent_days' => $absentDays,
                    'late_count' => $employeeLateCount > 0 ? $employeeLateCount : ($lateHours > 0 ? 1 : 0),
                    'late_hours' => $lateHours,
                    'early_leaving_count' => $employeeEarlyLeaveCount > 0 ? $employeeEarlyLeaveCount : ($earlyLeaveHours > 0 ? 1 : 0),
                    'early_leaving_hours' => $earlyLeaveHours,
                    'overtime_hours' => $overtimeHours,
                    'attendance' => $attendanceStatus
                ];
            }

            $totalOverTime = $totalOvertimeHours + ($totalOvertimeMins / 60);
            $totalEarlyLeave = $totalEarlyLeaveHours + ($totalEarlyLeaveMins / 60);
            $totalLate = $totalLateHours + ($totalLateMins / 60);

            $workingDays = count($dates ?? []);
            $employeeCount = count($employeesAttendance);

            // Calculate total leave and absent date-wise (for date-wise counts)
            foreach ($employeesAttendance as $emp) {
                foreach ($emp['attendance'] as $day => $status) {
                    $dayPadded = str_pad($day, 2, '0', STR_PAD_LEFT);
                    if ($status === 'L') {
                        $totalLeaveDates[$dayPadded] = ($totalLeaveDates[$dayPadded] ?? 0) + 1;
                    } elseif ($status === 'A') {
                        $totalAbsentDates[$dayPadded] = ($totalAbsentDates[$dayPadded] ?? 0) + 1;
                    }
                }
            }

            $averageAttendance = $workingDays > 0 && $employeeCount > 0
                ? round(($totalPresent / ($workingDays * $employeeCount)) * 100, 2)
                : 0;

            $summary = [
                'month' => $month,
                'year' => $year,
                'month_display' => $curMonth,
                'working_days' => $workingDays,
                'total_present' => $totalPresent,
                'total_leave' => $totalLeave,
                'total_absent' => $totalAbsent,
                'leave_dates' => array_values(array_map(function($d) { return str_pad($d, 2, '0', STR_PAD_LEFT); }, array_keys($totalLeaveDates ?? []))),
                'absent_dates' => array_values(array_map(function($d) { return str_pad($d, 2, '0', STR_PAD_LEFT); }, array_keys($totalAbsentDates ?? []))),
                'total_late_count' => $totalLateCount,
                'total_late_hours' => round($totalLate, 2),
                'total_early_leaving_count' => $totalEarlyLeaveCount,
                'total_early_leaving_hours' => round($totalEarlyLeave, 2),
                'total_overtime_hours' => round($totalOverTime, 2),
                'average_attendance' => $averageAttendance,
                'dates' => $dates ?? []
            ];

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => [
                    'summary' => $summary,
                    'employees' => EmployeeMonthlyAttendanceResource::collection($employeesAttendance)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('MonthlyAttendanceReportApiController index error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            $fileShort = basename($e->getFile());
            return response()->json([
                'status' => 0,
                'message' => 'Error (' . $fileShort . ':' . $e->getLine() . '): ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee departments for filter
     *
     * Retrieve department list for employee filtering in attendance reports.
     *
     * @authenticated
     *
     * @queryParam branch_id integer optional Filter departments by branch ID. Example: 1
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "1": "Engineering",
     *     "2": "Marketing",
     *     "3": "Sales"
     *   }
     * }
     */
    public function getDepartments(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());

            if ($request->branch_id == 0 || empty($request->branch_id)) {
                $departments = Department::where('created_by', creatorId())
                    ->where('workspace', $workspaceId)
                    ->pluck('name', 'id')
                    ->prepend('All', 'all')
                    ->toArray();
            } else {
                $departments = Department::where('branch_id', $request->branch_id)
                    ->where('created_by', creatorId())
                    ->where('workspace', $workspaceId)
                    ->pluck('name', 'id')
                    ->prepend('All', 'all')
                    ->toArray();
            }

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => $departments
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees for filter
     *
     * Retrieve employee list for filtering in attendance reports.
     *
     * @authenticated
     *
     * @queryParam department_id integer optional Filter employees by department ID. Example: 1
     * @queryParam branch_id integer optional Filter employees by branch ID. Example: 1
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "1": "John Doe",
     *     "2": "Jane Smith",
     *     "3": "Bob Johnson"
     *   }
     * }
     */
    public function getEmployees(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());
            $user = Auth::user();

            $employeeQuery = Employee::where('workspace', $workspaceId)
                ->where('created_by', creatorId());

            if (!empty($request->branch_id) && $request->branch_id !== 'all') {
                $employeeQuery->where('branch_id', $request->branch_id);
            }

            if (!empty($request->department_id) && $request->department_id !== 'all') {
                $employeeQuery->where('department_id', $request->department_id);
            }

            // Role-based filtering
            if (!$this->isAdminOrCompany($user)) {
                $employee = Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    $employeeQuery->where('id', $employee->id);
                }
            }

            $employees = $employeeQuery->pluck('name', 'id')
                ->prepend('All', 'all')
                ->toArray();

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => $employees
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee branches for filter
     *
     * Retrieve branch list for employee filtering in attendance reports.
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "1": "Head Office",
     *     "2": "Branch Office",
     *     "3": "Regional Office"
     *   }
     * }
     */
    public function getBranches(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());

            $branches = Branch::where('created_by', creatorId())
                ->where('workspace', $workspaceId)
                ->pluck('name', 'id')
                ->prepend('All', 'all')
                ->toArray();

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => $branches
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance details for a specific date
     *
     * Retrieve detailed attendance information for an employee on a specific date.
     *
     * @authenticated
     *
     * @queryParam employee_id integer required The employee ID. Example: 5
     * @queryParam date date required The date (YYYY-MM-DD format). Example: 2024-05-15
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "id": 1,
     *     "employee_id": 5,
     *     "date": "2024-05-15",
     *     "status": "Present",
     *     "clock_in": "09:00:00",
     *     "clock_out": "18:00:00",
     *     "late": "00:00:00",
     *     "early_leaving": "00:00:00",
     *     "overtime": "01:00:00",
     *     "total_rest": "00:00:00",
     *     "workspace": 1,
     *     "site_id": null,
     *     "created_by": 1,
     *     "employee": {
     *       "id": 5,
     *       "name": "John Doe",
     *       "employee_id": "EMP-00001"
     *     },
     *     "site": null
     *   }
     * }
     *
     * @response 404 {
     *   "status": 0,
     *   "message": "Attendance record not found"
     * }
     */
    public function getAttendanceDetails(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $validator = \Validator::make($request->all(), [
                'employee_id' => 'required|integer',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());

            $attendance = Attendance::where('employee_id', $request->employee_id)
                ->where('date', $request->date)
                ->where('workspace', $workspaceId)
                ->with(['employees', 'site'])
                ->first();

            if (!$attendance) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Attendance record not found'
                ], 404);
            }

            $data = [
                'id' => $attendance->id,
                'employee_id' => $attendance->employee_id,
                'date' => $attendance->date,
                'status' => $attendance->status,
                'clock_in' => $attendance->clock_in,
                'clock_out' => $attendance->clock_out,
                'late' => $attendance->late,
                'early_leaving' => $attendance->early_leaving,
                'overtime' => $attendance->overtime,
                'total_rest' => $attendance->total_rest,
                'workspace' => $attendance->workspace,
                'site_id' => $attendance->site_id,
                'created_by' => $attendance->created_by,
                'employee' => $attendance->employees ? [
                    'id' => $attendance->employees->id,
                    'name' => $attendance->employees->name,
                    'employee_id' => $attendance->employees->employee_id,
                ] : null,
                'site' => $attendance->site ? [
                    'id' => $attendance->site->id,
                    'name' => $attendance->site->name,
                ] : null,
            ];

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leave details for a specific date
     *
     * Retrieve detailed leave information for an employee on a specific date.
     *
     * @authenticated
     *
     * @queryParam employee_id integer required The employee ID. Example: 5
     * @queryParam date date required The date (YYYY-MM-DD format). Example: 2024-05-15
     *
     * @response 200 {
     *   "status": 1,
     *   "message": "",
     *   "data": {
     *     "id": 1,
     *     "employee_id": 5,
     *     "user_id": 2,
     *     "leave_type_id": 1,
     *     "start_date": "2024-05-15",
     *     "end_date": "2024-05-16",
     *     "total_leave_days": 2,
     *     "status": "Approved",
     *     "leave_reason": "Medical appointment",
     *     "leave_type": {
     *       "id": 1,
     *       "title": "Casual Leave",
     *       "days": 10
     *     },
     *     "employee": {
     *       "id": 2,
     *       "name": "John Doe"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": 0,
     *   "message": "Leave record not found"
     * }
     */
    public function getLeaveDetails(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('attendance monthly-report')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Permission denied.'
                ], 403);
            }

            $validator = \Validator::make($request->all(), [
                'employee_id' => 'required|integer',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());

            $leave = \Workdo\Hrm\Entities\Leave::where('employee_id', $request->employee_id)
                ->where('start_date', '<=', $request->date)
                ->where('end_date', '>=', $request->date)
                ->whereIn('status', ['Approved', 'Partially Approved'])
                ->with(['leaveType', 'EmployeeName', 'leaveDates'])
                ->where('workspace', $workspaceId)
                ->first();

            if (!$leave) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Leave record not found'
                ], 404);
            }

            $data = [
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
                'leave_type' => $leave->leaveType ? [
                    'id' => $leave->leaveType->id,
                    'title' => $leave->leaveType->title,
                    'days' => $leave->leaveType->days,
                ] : null,
                'employee' => $leave->EmployeeName ? [
                    'id' => $leave->EmployeeName->id,
                    'name' => $leave->EmployeeName->name,
                ] : null,
            ];

            return response()->json([
                'status' => 1,
                'message' => '',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }
}