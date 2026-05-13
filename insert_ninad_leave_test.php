<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Inserting Test Leave Data for Ninad ===\n\n";

// Ninad's employee_id
$ninadEmployeeId = 43;

// Get Ninad's user_id
$employee = \Workdo\Hrm\Entities\Employee::find($ninadEmployeeId);
if (!$employee) {
    echo "❌ Employee not found with ID: $ninadEmployeeId\n";
    exit(1);
}

echo "✅ Found Employee: " . $employee->name . " (ID: $ninadEmployeeId, User ID: " . $employee->user_id . ")\n\n";

// Get a leave type (use first available)
$leaveType = \Workdo\Hrm\Entities\LeaveType::first();
if (!$leaveType) {
    echo "❌ No leave type found. Please create a leave type first.\n";
    exit(1);
}
echo "✅ Using Leave Type: " . $leaveType->title . " (ID: " . $leaveType->id . ")\n\n";

// Define test leave dates
// Past dates: May 1-3, 2026 (already passed)
$pastLeaveStart = '2026-05-01';
$pastLeaveEnd = '2026-05-03';

// Future dates: May 20-22, 2026 (future dates)
$futureLeaveStart = '2026-05-20';
$futureLeaveEnd = '2026-05-22';

echo "📅 Past Leave Period: $pastLeaveStart to $pastLeaveEnd\n";
echo "📅 Future Leave Period: $futureLeaveStart to $futureLeaveEnd\n\n";

// Delete existing attendance records for these dates
$datesToDelete = [$pastLeaveStart, $pastLeaveEnd, $futureLeaveStart, $futureLeaveEnd];
$deletedCount = 0;

foreach ($datesToDelete as $date) {
    $deleted = \Workdo\Hrm\Entities\Attendance::where('employee_id', $ninadEmployeeId)
        ->where('date', $date)
        ->delete();
    if ($deleted > 0) {
        echo "🗑️  Deleted attendance record for date: $date\n";
        $deletedCount += $deleted;
    }
}
echo "\n✅ Total attendance records deleted: $deletedCount\n\n";

// Delete existing leave records for these periods to avoid duplicates
$deletedLeaves = \Workdo\Hrm\Entities\Leave::where('employee_id', $ninadEmployeeId)
    ->where(function($query) use ($pastLeaveStart, $pastLeaveEnd, $futureLeaveStart, $futureLeaveEnd) {
        $query->whereBetween('start_date', [$pastLeaveStart, $pastLeaveEnd])
            ->orWhereBetween('end_date', [$pastLeaveStart, $pastLeaveEnd])
            ->orWhereBetween('start_date', [$futureLeaveStart, $futureLeaveEnd])
            ->orWhereBetween('end_date', [$futureLeaveStart, $futureLeaveEnd]);
    })
    ->delete();

if ($deletedLeaves > 0) {
    echo "🗑️  Deleted $deletedLeaves existing leave records for these periods\n\n";
}

// Create past leave record (Approved)
$pastLeave = new \Workdo\Hrm\Entities\Leave();
$pastLeave->employee_id = $ninadEmployeeId;
$pastLeave->user_id = $employee->user_id;
$pastLeave->leave_type_id = $leaveType->id;
$pastLeave->applied_on = date('Y-m-d');
$pastLeave->start_date = $pastLeaveStart;
$pastLeave->end_date = $pastLeaveEnd;
$pastLeave->total_leave_days = 3;
$pastLeave->approved_days = 3;
$pastLeave->leave_reason = 'Test leave - past dates';
$pastLeave->remark = 'Test data for leave integration';
$pastLeave->status = 'Approved';
$pastLeave->workspace = 1; // Default workspace
$pastLeave->site_id = null;
$pastLeave->created_by = 1;
$pastLeave->save();

echo "✅ Created PAST leave record: $pastLeaveStart to $pastLeaveEnd (ID: " . $pastLeave->id . ")\n";

// Create future leave record (Approved)
$futureLeave = new \Workdo\Hrm\Entities\Leave();
$futureLeave->employee_id = $ninadEmployeeId;
$futureLeave->user_id = $employee->user_id;
$futureLeave->leave_type_id = $leaveType->id;
$futureLeave->applied_on = date('Y-m-d');
$futureLeave->start_date = $futureLeaveStart;
$futureLeave->end_date = $futureLeaveEnd;
$futureLeave->total_leave_days = 3;
$futureLeave->approved_days = 3;
$futureLeave->leave_reason = 'Test leave - future dates';
$futureLeave->remark = 'Test data for leave integration';
$futureLeave->status = 'Approved';
$futureLeave->workspace = 1; // Default workspace
$futureLeave->site_id = null;
$futureLeave->created_by = 1;
$futureLeave->save();

echo "✅ Created FUTURE leave record: $futureLeaveStart to $futureLeaveEnd (ID: " . $futureLeave->id . ")\n\n";

echo "=== Test Data Insertion Complete ===\n";
echo "📊 Summary:\n";
echo "   - Past Leave (May 1-3): Should show as 'L' in report\n";
echo "   - Future Leave (May 20-22): Should show as blank in report (future dates)\n";
echo "   - Attendance records for these dates have been deleted\n";
echo "\n🔗 Test URL: http://sitepilot/attendance/monthly-report-new?month=2026-05&employee_id=$ninadEmployeeId\n";
