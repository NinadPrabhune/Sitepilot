<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Attendance;
use App\Models\WorkSpace;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Log;
use Workdo\Hrm\Events\CreateMarkAttendance;
use Workdo\Hrm\Events\DestroyMarkAttendance;
use Workdo\Hrm\Events\UpdateBulkAttendance;
use Workdo\Hrm\Events\UpdateMarkAttendance;



class AttendanceApiController extends Controller {

    public function createData(Request $request)
    {
        if (Auth::user()->isAbleTo('attendance create')) {

            $validator = \Validator::make($request->all(), [

                    'user_id' => 'required',
                    'site_id' => 'required',
                    'workspace_id' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                                'status' => 0,
                                'message' => $validator->getMessageBag()->first()
                                    ], 403);
                }



            $currentWorkspace = $request->workspace_id;

            // Fetch employees for the current workspace
            $employees = User::where('workspace_id', $currentWorkspace)
                ->where('created_by', creatorId())
                ->emp()
                ->get(['id', 'name']); // return id and name

            return response()->json([
                'success'   => true,
                'employees' => $employees,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'error'   => __('Permission denied.'),
            ], 401);
        }
    }

    
    
    
    public function AdminAttendenceInsert(Request $request)
{
    try {
        // ✅ Permission check
        if (!Auth::user()->isAbleTo('attendance create')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // ✅ Validation
        $validator = \Validator::make(
            $request->all(),
            [
                'employee_id' => 'required|integer',
                'user_id' => 'required|integer',
                'date'        => 'required|date',
                'clock_in'    => 'required|date_format:H:i',
                'clock_out'   => 'required|date_format:H:i',
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',
                'created_by' => 'required|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $company_settings = getCompanyAllSetting();
        $startTime  = $company_settings['company_start_time'] ?? '09:00';
        $endTime    = $company_settings['company_end_time'] ?? '18:00';

        // ✅ Check duplicate attendance
        $attendance = Attendance::where('employee_id', $request->employee_id)
            ->where('workspace', $request->workspace_id)
            ->where('site_id', $request->site_id)
            ->where('date', $request->date)
            ->where('clock_out', '00:00:00')
            ->exists();

        if ($attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee Attendance Already Created.'
            ], 409);
        }

        $date = date("Y-m-d");

        // ✅ Late calculation
        $totalLateSeconds = strtotime($request->clock_in) - strtotime($date . $startTime);
        $late = $totalLateSeconds > 0
            ? sprintf('%02d:%02d:%02d', floor($totalLateSeconds / 3600), floor($totalLateSeconds / 60 % 60), floor($totalLateSeconds % 60))
            : '00:00:00';

        // ✅ Early Leaving calculation
        $totalEarlyLeavingSeconds = strtotime($date . $endTime) - strtotime($request->clock_out);
        $earlyLeaving = $totalEarlyLeavingSeconds > 0
            ? sprintf('%02d:%02d:%02d', floor($totalEarlyLeavingSeconds / 3600), floor($totalEarlyLeavingSeconds / 60 % 60), floor($totalEarlyLeavingSeconds % 60))
            : '00:00:00';

        // ✅ Overtime calculation
        $overtime = strtotime($request->clock_out) > strtotime($date . $endTime)
            ? sprintf('%02d:%02d:%02d',
                floor((strtotime($request->clock_out) - strtotime($date . $endTime)) / 3600),
                floor((strtotime($request->clock_out) - strtotime($date . $endTime)) / 60 % 60),
                floor((strtotime($request->clock_out) - strtotime($date . $endTime)) % 60)
            )
            : '00:00:00';

        // ✅ Save attendance
        $employeeAttendance = new Attendance();
        $employeeAttendance->employee_id   = $request->employee_id;
        $employeeAttendance->date          = $request->date;
        $employeeAttendance->status        = 'Present';
        $employeeAttendance->clock_in      = $request->clock_in . ':00';
        $employeeAttendance->clock_out     = $request->clock_out . ':00';
        $employeeAttendance->late          = $late;
        $employeeAttendance->early_leaving = $earlyLeaving;
        $employeeAttendance->overtime      = $overtime;
        $employeeAttendance->total_rest    = '00:00:00';
        $employeeAttendance->workspace     = $request->workspace_id;
        $employeeAttendance->site_id       = $request->site_id;
        $employeeAttendance->created_by    = $request->created_by;
        $employeeAttendance->save();

        event(new CreateMarkAttendance($request, $employeeAttendance));

        return response()->json([
            'status' => 'success',
            'message' => 'The employee attendance has been created successfully.',
            'data' => $employeeAttendance
        ], 201);

    } catch (\Exception $e) {
        // ✅ Catch unexpected errors
        return response()->json([
            'status' => 'error',
            'message' => 'Something went wrong while creating attendance.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function show($id)
{
    try {
        // ✅ Permission check
        if (!Auth::user()->isAbleTo('attendance edit')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // ✅ Find attendance by ID
        $attendance = Attendance::where('id', $id)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $attendance
        ], 200);

    } catch (\Exception $e) {
        // ✅ Catch unexpected errors
        return response()->json([
            'status' => 'error',
            'message' => 'Something went wrong while fetching attendance.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function clockInOut(Request $request)
{
    $rules = [
        'type' => 'required|in:clockin,clockout',
        'latitude' => 'required',
        'longitude' => 'required',
        'user_id' => 'required|integer|min:1',
        'employee_id' => 'required|integer|min:1',
        'site_id' => 'required|integer|min:1',
        'workspace_id' => 'required|integer|min:1',
    ];

    if ($request->type === 'clockin') {
        $rules['clock_in_image'] = 'required|image|mimes:jpg,jpeg,png';
    } else {
        $rules['attendence_id'] = 'required|integer';
        $rules['clock_out_image'] = 'required|image|mimes:jpg,jpeg,png';
    }

    $validator = \Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json([
            'status' => 0,
            'message' => $validator->errors()->first()
        ], 422);
    }

    try {

        \DB::beginTransaction();

        $userId = $request->user_id;
        $employeeId = (int)$request->employee_id;
        $workspaceId = $request->workspace_id;

        $settings = getCompanyAllSetting($userId, $workspaceId);

        $startTime = $settings['company_start_time'] ?? '09:00';
        $endTime = $settings['company_end_time'] ?? '18:00';

        if (!empty($settings['defult_timezone'])) {
            date_default_timezone_set($settings['defult_timezone']);
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');

        /*
        =================================
        CLOCK IN
        =================================
        */
        if ($request->type === 'clockin') {

            $site = \Workdo\Taskly\Entities\Project::where('id', $request->site_id)
                ->projectonly()
                ->first(['latitude', 'longitude']);

            if (!$site) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Site not found'
                ], 404);
            }

            if (!isWithinSiteRadius($site->latitude, $site->longitude, $request->latitude, $request->longitude)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Employee is outside site 1 Km radius'
                ], 422);
            }

            $openAttendance = Attendance::where('employee_id', $employeeId)
                ->where('clock_out', '00:00:00')
                ->latest()
                ->first();

            if ($openAttendance) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Please Employee First Clock Out.',
                    'data' => [
                        'is_clockin' => 1,
                        'attendence_id' => $openAttendance->id,
                        'clock_in' => $openAttendance->clock_in,
                        'clock_out' => $openAttendance->clock_out,
                    ]
                ]);
            }

            $lastClockOut = Attendance::where('employee_id', $employeeId)
                ->where('clock_out', '!=', '00:00:00')
                ->where('date', $date)
                ->latest()
                ->first();

            if ($lastClockOut) {
                $lateSeconds = strtotime("$date $time") - strtotime("$date {$lastClockOut->clock_out}");
            } else {
                $lateSeconds = strtotime("$date $time") - strtotime("$date $startTime");
            }

            $late = gmdate("H:i:s", max($lateSeconds, 0));

            $clockInImage = null;

            if ($request->hasFile('clock_in_image')) {

                $file = $request->file('clock_in_image');
                $filename = time() . '_' . $file->getClientOriginalName();

                $path = upload_file($request, 'clock_in_image', $filename, 'employee-clock-in-image');

                if ($path['flag'] == 0) {
                    return response()->json([
                        'status' => 0,
                        'message' => $path['msg']
                    ], 500);
                }

                $clockInImage = $path['url'] ?? null;
            }

            $attendance = Attendance::create([
                'employee_id' => $employeeId,
                'date' => $date,
                'status' => 'Present',
                'clock_in' => $time,
                'clock_out' => '00:00:00',
                'late' => $late,
                'early_leaving' => '00:00:00',
                'overtime' => '00:00:00',
                'total_rest' => '00:00:00',
                'clock_in_latitude' => $request->latitude,
                'clock_in_longitude' => $request->longitude,
                'clock_in_image' => $clockInImage,
                'created_by' => $request->user_id,
                'workspace' => $workspaceId,
                'site_id' => $request->site_id
            ]);

            $clockInFormatted = \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A');

        }

        /*
        =================================
        CLOCK OUT
        =================================
        */
        else {

            $attendance = Attendance::findOrFail($request->attendence_id);

            $earlyLeavingSeconds = strtotime("$date $endTime") - time();
            $earlyLeaving = $earlyLeavingSeconds > 0
                ? gmdate("H:i:s", $earlyLeavingSeconds)
                : "00:00:00";

            $overtime = time() > strtotime("$date $endTime")
                ? gmdate("H:i:s", time() - strtotime("$date $endTime"))
                : "00:00:00";

            $clockOutImage = null;

            if ($request->hasFile('clock_out_image')) {

                $file = $request->file('clock_out_image');
                $filename = time() . '_' . $file->getClientOriginalName();

                $path = upload_file($request, 'clock_out_image', $filename, 'employee-clock-out-image');

                if ($path['flag'] == 0) {
                    return response()->json([
                        'status' => 0,
                        'message' => $path['msg']
                    ], 500);
                }

                $clockOutImage = $path['url'] ?? null;
            }

            $attendance->update([
                'clock_out' => $time,
                'early_leaving' => $earlyLeaving,
                'overtime' => $overtime,
                'clock_out_latitude' => $request->latitude,
                'clock_out_longitude' => $request->longitude,
                'clock_out_image' => $clockOutImage
            ]);

            $clockInFormatted = \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A');
            $clockOutFormatted = \Carbon\Carbon::parse($attendance->clock_out)->format('h:i A');
        }

        /*
        =================================
        TOTAL HOURS
        =================================
        */

        $totalMinutes = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->where('status', 'Present')
            ->get()
            ->sum(function ($entry) {

                $clockIn = \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_in);

                $clockOut = $entry->clock_out == '00:00:00'
                    ? now()
                    : \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_out);

                return $clockOut->diffInMinutes($clockIn);
            });

        $hours = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;

        $totalHours = sprintf("%02d:%02d hours", $hours, $mins);

        \DB::commit();

        if ($request->type === 'clockin') {

            return response()->json([
                'status' => 1,
                'message' => 'Employee Successfully Clock In.',
                'data' => [
                    'is_clockin' => 1,
                    'attendence_id' => $attendance->id,
                    'clock_in' => $clockInFormatted,
                    'total_hours' => $totalHours
                ]
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Employee Successfully Clock Out.',
            'data' => [
                'is_clockin' => 0,
                'attendence_id' => $attendance->id,
                'clock_in' => $clockInFormatted,
                'clock_out' => $clockOutFormatted,
                'total_hours' => $totalHours
            ]
        ]);

    } catch (\Exception $e) {

        \DB::rollBack();

        return response()->json([
            'status' => 0,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ]);
    }
}




//    public function clockInOut(Request $request) {
//     
//    // Base validation
//    $rules = [
//        'type' => 'required|in:clockin,clockout',
//        'latitude' => 'required',
//        'longitude' => 'required',
//        'user_id' => 'required',
//        'employee_id' => 'required',
//        'site_id' => 'required',
//        'workspace_id' => 'required',
//    ];
//
//    // Add conditional validation
//    if ($request->type === 'clockin') {
//        $rules['clock_in_image'] = 'required|file|mimes:jpg,jpeg,png';
//    } elseif ($request->type === 'clockout') {
//        $rules['attendence_id'] = 'required';
//        $rules['clock_out_image'] = 'required|file|mimes:jpg,jpeg,png';
//    }
//
//    $validator = \Validator::make($request->all(), $rules);
//
//    if ($validator->fails()) {
//        return response()->json([
//            'status' => 0,
//            'message' => $validator->getMessageBag()->first()
//        ], 403);
//    }
//
//
//
//        // CLOCK IN
//        if ($request->type === 'clockin') {
//            try {
//
//
//                $site = \Workdo\Taskly\Entities\Project::where('id', $request->site_id)
//                        ->projectonly()
//                        ->first(['latitude', 'longitude']);
//
//                if (!$site) {
//                    return response()->json(['error' => 'Site not found'], 404);
//                }
//
//                $siteLat = $site->latitude;
//                $siteLon = $site->longitude;
//
//                $empLat = $request->latitude;
//                $empLon = $request->longitude;
//
//                if (isWithinSiteRadius($siteLat, $siteLon, $empLat, $empLon)) {
//
//                } else {
//                    return response()->json(['status' => 'error', 'message' => 'Employee is outside site 1 Km radius'], 422); // Unprocessable Entity
//                }
//
//
//
//
//
//                $userId = $request->user_id;
//                $activeWorkspace = $request->workspace_id;
//                $companySettings = getCompanyAllSetting($userId, $activeWorkspace);
//                
//                 $employeeId = $request->employee_id;
//                
//                $startTime = $companySettings['company_start_time'] ?? '09:00';
//                $endTime = $companySettings['company_end_time'] ?? '18:00';
//
//                if (!empty($companySettings['defult_timezone'])) {
//                    date_default_timezone_set($companySettings['defult_timezone']);
//                }
//
//                $date = date("Y-m-d");
//                $time = date("H:i:s");
//
//                // Check if employee already has an open attendance
//                $openAttendance = Attendance::where('employee_id', $employeeId)
//                        ->where('clock_out', '00:00:00')
//                        ->orderBy('id', 'desc')
//                        ->first();
//
//                if ($openAttendance) {
//                    return response()->json([
//                                'status' => 0,
//                                'message' => 'Please Employee First Clock Out.',
//                                'data' => [
//                                    'is_clockin' => 1,
//                                    'attendence_id' => $openAttendance->id,
//                                    'clock_in' => $openAttendance->clock_in,
//                                    'clock_out' => $openAttendance->clock_out,
//                                ]
//                    ]);
//                }
//
//                // Calculate late time
//                $lastClockOut = Attendance::where('employee_id', $employeeId)
//                        ->where('clock_out', '!=', '00:00:00')
//                        ->where('date', $date)
//                        ->orderBy('id', 'desc')
//                        ->first();
//
//                if ($lastClockOut) {
//                    $lateSeconds = strtotime("$date $time") - strtotime("$date {$lastClockOut->clock_out}");
//                } else {
//                    $lateSeconds = strtotime("$date $time") - strtotime("$date $startTime");
//                }
//
//                $lateSeconds = max($lateSeconds, 0);
//                $late = gmdate("H:i:s", $lateSeconds);
//
//                // clock_in_image upload
//                $clock_in_imagePath = null;
//                if ($request->hasFile('clock_in_image')) {
//                    $filenameWithExt = $request->file('clock_in_image')->getClientOriginalName();
//                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
//                    $extension = $request->file('clock_in_image')->getClientOriginalExtension();
//                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;
//
//                    $path = upload_file($request, 'clock_in_image', $fileNameToStore, 'employee-clock-in-image');
//                    if ($path['flag'] == 0) {
//                        return response()->json(['status' => 0, 'message' => $path['msg']], 500);
//                    }
//                    if (!empty($path['url'])) {
//                        $clock_in_imagePath = $path['url'];
//                    }
//                }
//
//                // Create attendance
//                $attendance = Attendance::create([
//                    'employee_id' => $employeeId,
//                    'date' => $date,
//                    'status' => 'Present',
//                    'clock_in' => $time,
//                    'clock_out' => '00:00:00',
//                    'late' => $late,
//                    'early_leaving' => '00:00:00',
//                    'overtime' => '00:00:00',
//                    'total_rest' => '00:00:00',
//                    'clock_in_latitude' => $request->latitude,
//                    'clock_in_longitude' => $request->longitude,
//                    'clock_in_image' => $clock_in_imagePath,
//                    'created_by' => $request->user_id,
//                    'workspace' => $request->workspace_id,
//                    'site_id' => $request->site_id,
//                ]);
//
//                // Format clock-in time
//                $clockInFormatted = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->clock_in)
//                        ->format('h:i A');
//
//                // Calculate total hours today
//                $currentDate = \Carbon\Carbon::today();
//                $totalMinutes = Attendance::where('employee_id', $employeeId)
//                        ->whereDate('date', $currentDate)
//                        ->whereNotNull('clock_in')
//                        ->where('status', 'Present')
//                        ->get()
//                        ->sum(function ($entry) {
//                            $clockIn = \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_in);
//                            $clockOut = $entry->clock_out === '00:00:00' ? \Carbon\Carbon::now() : \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_out);
//
//                            return $clockOut->diffInMinutes($clockIn);
//                        });
//
//                $totalHours = floor($totalMinutes / 60);
//                $totalMins = $totalMinutes % 60;
//                $totalTimeString = sprintf("%02d:%02d hours", $totalHours, $totalMins);
//
//                return response()->json([
//                            'status' => 1,
//                            'message' => 'Employee Successfully Clock In.',
//                            'data' => [
//                                'is_clockin' => 1,
//                                'attendence_id' => $attendance->id,
//                                'clock_in' => $clockInFormatted,
//                                'total_hours' => $totalTimeString,
//                            ]
//                ]);
//            } catch (\Exception $e) {
//                return response()->json([
//                            'status' => 0,
//                            'message' => 'Something went wrong!',
//                            'error' => $e->getMessage()
//                ]);
//            }
//        }
//
//        // CLOCK OUT
//        else {
//            // Validate attendance ID
//            $validator = \Validator::make($request->all(), [
//                'attendence_id' => 'required',
//            ]);
//
//            if ($validator->fails()) {
//                return response()->json([
//                            'status' => 0,
//                            'message' => $validator->getMessageBag()->first()
//                                ], 403);
//            }
//
//            try {
//                
//                 $userId = $request->user_id;
//               
//                $activeWorkspace = $request->workspace_id;
//                $companySettings = getCompanyAllSetting($userId, $activeWorkspace);
//
//                $startTime = $companySettings['company_start_time'] ?? '09:00';
//                $endTime = $companySettings['company_end_time'] ?? '18:00';
//
//                if (!empty($companySettings['defult_timezone'])) {
//                    date_default_timezone_set($companySettings['defult_timezone']);
//                }
//                
//                 $employeeId = $request->employee_id;
//
//                $date = date("Y-m-d");
//                $time = date("H:i:s");
//
//                // Early leaving
//                $earlyLeavingSeconds = strtotime("$date $endTime") - time();
//                $earlyLeaving = $earlyLeavingSeconds > 0 ? gmdate("H:i:s", $earlyLeavingSeconds) : "00:00:00";
//
//                // Overtime
//                $overtime = time() > strtotime("$date $endTime") ? gmdate("H:i:s", time() - strtotime("$date $endTime")) : "00:00:00";
//
//                // clock_in_image upload
//                $clock_out_imagePath = null;
//                if ($request->hasFile('clock_out_image')) {
//                    $filenameWithExt = $request->file('clock_out_image')->getClientOriginalName();
//                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
//                    $extension = $request->file('clock_out_image')->getClientOriginalExtension();
//                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;
//
//                    $path = upload_file($request, 'clock_out_image', $fileNameToStore, 'employee-clock-out-image');
//                    if ($path['flag'] == 0) {
//                        return response()->json(['status' => 0, 'message' => $path['msg']], 500);
//                    }
//                    if (!empty($path['url'])) {
//                        $clock_out_imagePath = $path['url'];
//                    }
//                }
//
//
//
//                // Update attendance
//                $attendance = Attendance::find($request->attendence_id);
//                $attendance->clock_out = $time;
//                $attendance->early_leaving = $earlyLeaving;
//                $attendance->overtime = $overtime;
//                $attendance->clock_out_latitude = $request->latitude;
//                $attendance->clock_out_longitude = $request->longitude;
//                $attendance->clock_out_image = $clock_out_imagePath;
//                $attendance->save();
//
//                // Format times
//                $clockInFormatted = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->clock_in)->format('h:i A');
//                $clockOutFormatted = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->clock_out)->format('h:i A');
//
//                // Total hours today
//                $currentDate = \Carbon\Carbon::today();
//                $totalMinutes = Attendance::where('employee_id', $employeeId)
//                        ->whereDate('date', $currentDate)
//                        ->whereNotNull('clock_in')
//                        ->whereNotNull('clock_out')
//                        ->where('status', 'Present')
//                        ->get()
//                        ->sum(function ($entry) {
//                            $clockIn = \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_in);
//                            $clockOut = \Carbon\Carbon::parse($entry->date . ' ' . $entry->clock_out);
//                            return $clockOut->diffInMinutes($clockIn);
//                        });
//
//                $totalHours = floor($totalMinutes / 60);
//                $totalMins = $totalMinutes % 60;
//                $totalTimeString = sprintf("%02d:%02d hours", $totalHours, $totalMins);
//
//                return response()->json([
//                            'status' => 1,
//                            'message' => 'Employee Successfully Clock Out.',
//                            'data' => [
//                                'is_clockin' => 0,
//                                'attendence_id' => $attendance->id,
//                                'clock_in' => $clockInFormatted,
//                                'clock_out' => $clockOutFormatted,
//                                'total_hours' => $totalTimeString,
//                            ]
//                ]);
//            } catch (\Exception $e) {
//                return response()->json([
//                            'status' => 0,
//                            'message' => 'Something went wrong!',
//                            'error' => $e->getMessage()
//                ]);
//            }
//        }
//    }

    public function attendenceHistory(Request $request)
{
    try {
        $workspaceId = $request->input('workspace_id');
        $siteId      = $request->input('site_id');
        $employee_id = $request->input('employee_id');

        $attendances = Attendance::with(['site','employees'])
            ->select('date', 'id', 'status', 'clock_in', 'clock_out','site_id','employee_id');

        // Apply filters
        if (!empty($workspaceId) && $workspaceId != 0) {
            $attendances->where('workspace', $workspaceId);
        }
        if (!empty($siteId) && $siteId != 0) {
            $attendances->where('site_id', $siteId);
        }
        if (!empty($employee_id) && $employee_id != 0) {
            $attendances->where('employee_id', $employee_id);
        }

        // Date range filter
        if ($request->type === 'monthly' && !empty($request->month)) {
            $month = $request->month;
            $year  = $request->year ?? date('Y');
            $start_date = Carbon::create($year, $month, 1)->startOfMonth();
            $end_date   = Carbon::create($year, $month, 1)->endOfMonth();
        } else {
            $month = date('m');
            $year  = date('Y');
            $start_date = Carbon::create($year, $month, 1)->startOfMonth();
            $end_date   = Carbon::create($year, $month, 1)->endOfMonth();
        }

        $attendances->whereBetween('date', [$start_date, $end_date]);

        $company_settings = getCompanyAllSetting($request->user_id, $request->workspace_id);
        $formattedData = [];

        foreach ($attendances->get() as $attendance) {
            $date = $attendance->date;

            if (!empty($company_settings['defult_timezone'])) {
                date_default_timezone_set($company_settings['defult_timezone']);
            }

            $clockIn = new DateTime($attendance->clock_in);
            $clockOut = ($attendance->clock_out == '00:00:00')
                ? new DateTime()
                : new DateTime($attendance->clock_out);

            $interval = $clockIn->diff($clockOut);
            $totalTimeString = $interval->format('%H:%I hours');

            $attendanceDetail = [
                'id'            => $attendance->id,
                'status'        => $attendance->status,
                'clock_in'      => $attendance->clock_in,
                'clock_out'     => $attendance->clock_out,
                'total'         => $totalTimeString,

                // ✅ Added employee info
                'employee_id'   => $attendance->employee_id,
                'employee_name' => optional($attendance->employees)->name,

                // ✅ Added site info
                'site_id'       => $attendance->site_id,
                'site_name'     => optional($attendance->site)->name,
            ];

            if (!isset($formattedData[$date])) {
                $formattedData[$date] = [
                    'total_time' => '00:00 hours',
                    'date'       => $date,
                    'history'    => [],
                ];
            }

            $formattedData[$date]['history'][] = $attendanceDetail;
        }

        // Calculate daily totals
        foreach ($formattedData as $key => $data) {
            $totalTime = Attendance::where('employee_id', $request->user_id)
                ->whereDate('date', $data['date'])
                ->whereNotNull('clock_in')
                ->where('status', 'Present')
                ->get()
                ->sum(function ($entry) {
                    $clockOut = ($entry->clock_out == '00:00:00')
                        ? Carbon::now()
                        : Carbon::parse($entry->date . ' ' . $entry->clock_out);

                    $clockIn = Carbon::parse($entry->date . ' ' . $entry->clock_in);
                    return $clockOut->diffInMinutes($clockIn);
                });

            $totalHours = floor($totalTime / 60);
            $totalMinutes = $totalTime % 60;
            $formattedData[$key]['total_time'] = sprintf("%02d:%02d", $totalHours, $totalMinutes);
        }

        // Reindex data sequentially
        $newData = array_values($formattedData);

        return response()->json(['status' => 1, 'data' => $newData]);
    } catch (\Exception $e) {
        // Log the full error message and stack trace
        Log::error('AuthApiController error', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status'  => 0,
            'message' => 'Something went wrong!!!'
        ], 500);
    }
}

    
    
//    public function attendenceHistory(Request $request)
//    {
//        try {
//            $workspaceId = $request->input('workspace_id');
//            $siteId      = $request->input('site_id');
//            $employee_id      = $request->input('employee_id');
//
//            $attendances = Attendance::with(['site','employees'])
//                ->select('date', 'id', 'status', 'clock_in', 'clock_out','site_id','employee_id');
//
//            // Apply filters
//            if (!empty($workspaceId) && $workspaceId != 0) {
//                $attendances->where('workspace', $workspaceId);
//            }
//            if (!empty($siteId) && $siteId != 0) {
//                $attendances->where('site_id', $siteId);
//            }
//            if (!empty($employee_id) && $employee_id != 0) {
//                $attendances->where('employee_id', $employee_id);
//            }
//
//            // Date range filter
//            if ($request->type === 'monthly' && !empty($request->month)) {
//                $month = $request->month;
//                $year  = $request->year ?? date('Y');
//                $start_date = Carbon::create($year, $month, 1)->startOfMonth();
//                $end_date   = Carbon::create($year, $month, 1)->endOfMonth();
//            } else {
//                $month = date('m');
//                $year  = date('Y');
//                $start_date = Carbon::create($year, $month, 1)->startOfMonth();
//                $end_date   = Carbon::create($year, $month, 1)->endOfMonth();
//            }
//
//            $attendances->whereBetween('date', [$start_date, $end_date]);
//
//            $company_settings = getCompanyAllSetting($request->user_id, $request->workspace_id);
//            $formattedData = [];
//
//            foreach ($attendances->get() as $attendance) {
//                $date = $attendance->date;
//
//                if (!empty($company_settings['defult_timezone'])) {
//                    date_default_timezone_set($company_settings['defult_timezone']);
//                }
//
//                $clockIn = new DateTime($attendance->clock_in);
//                $clockOut = ($attendance->clock_out == '00:00:00')
//                    ? new DateTime()
//                    : new DateTime($attendance->clock_out);
//
//                $interval = $clockIn->diff($clockOut);
//                $totalTimeString = $interval->format('%H:%I hours');
//
//                $attendanceDetail = [
//                    'id'        => $attendance->id,
//                    'status'    => $attendance->status,
//                    'clock_in'  => $attendance->clock_in,
//                    'clock_out' => $attendance->clock_out,
//                    'total'     => $totalTimeString,
//                ];
//
//                if (!isset($formattedData[$date])) {
//                    $formattedData[$date] = [
//                        'total_time' => '00:00 hours',
//                        'date'       => $date,
//                        'history'    => [],
//                    ];
//                }
//
//                $formattedData[$date]['history'][] = $attendanceDetail;
//            }
//
//            // Calculate daily totals
//            foreach ($formattedData as $key => $data) {
//                $totalTime = Attendance::where('employee_id', $request->user_id)
//                    ->whereDate('date', $data['date'])
//                    ->whereNotNull('clock_in')
//                    ->where('status', 'Present')
//                    ->get()
//                    ->sum(function ($entry) {
//                        $clockOut = ($entry->clock_out == '00:00:00')
//                            ? Carbon::now()
//                            : Carbon::parse($entry->date . ' ' . $entry->clock_out);
//
//                        $clockIn = Carbon::parse($entry->date . ' ' . $entry->clock_in);
//                        return $clockOut->diffInMinutes($clockIn);
//                    });
//
//                $totalHours = floor($totalTime / 60);
//                $totalMinutes = $totalTime % 60;
//                $formattedData[$key]['total_time'] = sprintf("%02d:%02d", $totalHours, $totalMinutes);
//            }
//
//            // Reindex data sequentially
//            $newData = array_values($formattedData);
//
//            return response()->json(['status' => 1, 'data' => $newData]);
//        } catch (\Exception $e) {
//            // Log the full error message and stack trace
//            Log::error('AuthApiController error', [
//                'message' => $e->getMessage(),
//                'file'    => $e->getFile(),
//                'line'    => $e->getLine(),
//                'trace'   => $e->getTraceAsString(),
//            ]);
//
//            return response()->json([
//                'status'  => 0,
//                'message' => 'Something went wrong!!!'
//            ], 500);
//        }
//    }





    // public function ___attendenceHistory(Request $request)
    // {
    //     try{
    //         $attendances = Attendance::where('employee_id', $request->user_id)->where('workspace', $request->workspace_id);
    //         if ($request->type == 'monthly' && !empty($request->month)) {
    //             $month = $request->month;
    //             $year = !empty($request->year) ? $request->year : date('Y');
    //             $start_date = date("$year-$month-01");
    //             $end_date   = date("$year-$month-t");
    //             $attendances->whereBetween(
    //                 'date',
    //                 [
    //                     $start_date,
    //                     $end_date,
    //                 ]
    //             );
    //         }
    // 		//elseif ($request->type == 'daily' && !empty($request->date)) {
    //          //   $attendances->where('date', $request->date);
    //         //}
    // 		else {
    //             $month      = date('m');
    //             $year       = date('Y');
    //             $start_date = date($year . '-' . $month . '-01');
    //             $end_date   = date($year . '-' . $month . '-t');
    //             $attendances->whereBetween(
    //                 'date',
    //                 [
    //                     $start_date,
    //                     $end_date,
    //                 ]
    //             );
    //         }
    //         $attendances = $attendances->limit(10)->offset((($request->page??1)-1)*10)->get()
    //                         ->map(function($attendance){
    //                             return [
    //                                 'id'            => $attendance->id,
    //                                 'employee_id'   => $attendance->employee_id,
    //                                 'date'          => $attendance->date,
    //                                 'status'        => $attendance->status,
    //                                 'clock_in'      => $attendance->clock_in,
    //                                 'clock_out'     => $attendance->clock_out,
    //                                 'late'          => $attendance->late,
    //                                 'early_leaving' => $attendance->early_leaving,
    //                                 'overtime'      => $attendance->overtime,
    //                                 'total_rest'    => $attendance->total_rest,
    //                                 'workspace'     => $attendance->workspace,
    //                                 'created_by'    => $attendance->created_by,
    //                             ];
    //                         });
    //         return response()->json(['status'=>1,'message'=>'','data'=>$attendances]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status'=>0,'message'=>'something went wrong!!!']);
    //     }
    // }
    
    
    
    public function AdminAttendenceUpdate(Request $request, $id)
    {
        try {
            
            // ✅ Permission check
        if (!Auth::user()->isAbleTo('attendance edit')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // ✅ Validation
        $validator = \Validator::make(
            $request->all(),
            [
                'employee_id' => 'required|integer',
                'user_id' => 'required|integer',
                'date'        => 'required|date',
                'clock_in'    => 'required|date_format:H:i',
                'clock_out'   => 'required|date_format:H:i',
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',
                'created_by' => 'required|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
            
            
            
            // ✅ Determine employee ID
            $employeeId = !empty($request->employee_id) ? $request->employee_id : Auth::user()->id;

            $company_settings = getCompanyAllSetting();
            $startTime  = $company_settings['company_start_time'] ?? '09:00';
            $endTime    = $company_settings['company_end_time'] ?? '18:00';

            // ✅ Find attendance record
            $attendance = Attendance::find($id);
            if (!$attendance) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Attendance record not found.'
                ], 404);
            }

//            dd($request->clock_in);
            
//            // ✅ Branch based on employee type
//            if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
//                // Employee clock-out flow
//                if (!empty($company_settings['defult_timezone'])) {
//                    date_default_timezone_set($company_settings['defult_timezone']);
//                }
//
//                $date = date("Y-m-d");
//                $time = date("H:i");
//
//                // Early Leaving
//                $totalEarlyLeavingSeconds = strtotime($date . $endTime) - time();
//                $earlyLeaving = $totalEarlyLeavingSeconds > 0
//                    ? sprintf('%02d:%02d:%02d', floor($totalEarlyLeavingSeconds / 3600), floor($totalEarlyLeavingSeconds / 60 % 60), floor($totalEarlyLeavingSeconds % 60))
//                    : '00:00:00';
//
//                // Overtime
//                $overtime = time() > strtotime($date . $endTime)
//                    ? sprintf('%02d:%02d:%02d',
//                        floor((time() - strtotime($date . $endTime)) / 3600),
//                        floor((time() - strtotime($date . $endTime)) / 60 % 60),
//                        floor((time() - strtotime($date . $endTime)) % 60)
//                    )
//                    : '00:00:00';
//
//                $attendance->clock_out     = $time;
//                $attendance->early_leaving = $earlyLeaving;
//                $attendance->overtime      = $overtime;
//                $attendance->save();
//
//                event(new UpdateMarkAttendance($request, $attendance));
//
//                return response()->json([
//                    'status' => 'success',
//                    'message' => 'Employee successfully clocked out.',
//                    'data' => $attendance
//                ], 200);
//
//            } else {
                
                
                // Admin/manual update flow
                $date = date("Y-m-d");

                // Late
                $totalLateSeconds = strtotime($request->clock_in) - strtotime($date . $startTime);
                $late = $totalLateSeconds > 0
                    ? sprintf('%02d:%02d:%02d', floor($totalLateSeconds / 3600), floor($totalLateSeconds / 60 % 60), floor($totalLateSeconds % 60))
                    : '00:00:00';

                // Early Leaving
                $totalEarlyLeavingSeconds = strtotime($date . $endTime) - strtotime($request->clock_out);
                $earlyLeaving = $totalEarlyLeavingSeconds > 0
                    ? sprintf('%02d:%02d:%02d', floor($totalEarlyLeavingSeconds / 3600), floor($totalEarlyLeavingSeconds / 60 % 60), floor($totalEarlyLeavingSeconds % 60))
                    : '00:00:00';

                // Overtime
                $overtime = strtotime($request->clock_out) > strtotime($date . $endTime)
                    ? sprintf('%02d:%02d:%02d',
                        floor((strtotime($request->clock_out) - strtotime($date . $endTime)) / 3600),
                        floor((strtotime($request->clock_out) - strtotime($date . $endTime)) / 60 % 60),
                        floor((strtotime($request->clock_out) - strtotime($date . $endTime)) % 60)
                    )
                    : '00:00:00';

                
                
                $attendance->employee_id   = $request->employee_id;
                $attendance->date          = $request->date;
                $attendance->clock_in      = $request->clock_in. ':00';
                $attendance->clock_out     = $request->clock_out. ':00';
                $attendance->late          = $late;
                $attendance->early_leaving = $earlyLeaving;
                $attendance->overtime      = $overtime;
                $attendance->total_rest    = '00:00:00';
                $attendance->created_by = $request->user_id;
                $attendance->workspace = $request->workspace_id;
                $attendance->site_id = $request->site_id;
                
                $attendance->save();

                event(new UpdateMarkAttendance($request, $attendance));

                return response()->json([
                    'status' => 'success',
                    'message' => 'The employee attendance details have been updated successfully.',
                    'data' => $attendance
                ], 200);
//            }

        } catch (\Exception $e) {
            // ✅ Catch unexpected errors
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while updating attendance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    
    
    
public function AdminAttendenceDelete($id)
{
    $attendance = Attendance::find($id);

    if (!$attendance) {
        return response()->json(['message' => 'Attendance record not found'], 404);
    }

    $attendance->delete();

    return response()->json(['message' => 'Attendance record deleted successfully']);
}

    
    
    
    
    

}
