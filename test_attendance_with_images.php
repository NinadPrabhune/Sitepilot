<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Workdo\Hrm\Entities\Attendance;
use Workdo\Hrm\Entities\Employee;
use Carbon\Carbon;

echo "Creating test attendance record with images...\n\n";

try {
    // Get or create a test employee
    $employee = Employee::first();
    
    if (!$employee) {
        echo "No employee found. Please ensure you have employees in the system.\n";
        exit(1);
    }
    
    echo "Using employee: " . $employee->name . " (ID: " . $employee->id . ")\n";
    
    // Create test attendance record with images
    $attendanceData = [
        'employee_id' => $employee->id,
        'date' => Carbon::today()->format('Y-m-d'),
        'clock_in' => '09:00:00',
        'clock_out' => '17:30:00',
        'status' => 'Present',
        'clock_in_latitude' => '40.7128',
        'clock_in_longitude' => '-74.0060',
        'clock_out_latitude' => '40.7128',
        'clock_out_longitude' => '-74.0060',
        'clock_in_image' => 'uploads/attendance/clock_in_' . time() . '.jpg',
        'clock_out_image' => 'uploads/attendance/clock_out_' . time() . '.jpg',
        'workspace_id' => $employee->workspace_id,
        'site_id' => $employee->site_id ?? 1,
        'created_by' => 1,
    ];
    
    // Check if attendance already exists for today
    $existingAttendance = Attendance::where('employee_id', $employee->id)
                                   ->where('date', $attendanceData['date'])
                                   ->first();
    
    if ($existingAttendance) {
        echo "Attendance record already exists for today. Updating it...\n";
        $existingAttendance->update($attendanceData);
        $attendance = $existingAttendance;
    } else {
        echo "Creating new attendance record...\n";
        $attendance = Attendance::create($attendanceData);
    }
    
    echo "✅ Attendance record created/updated successfully!\n";
    echo "Record ID: " . $attendance->id . "\n";
    echo "Date: " . $attendance->date . "\n";
    echo "Clock In Image: " . $attendance->clock_in_image . "\n";
    echo "Clock Out Image: " . $attendance->clock_out_image . "\n\n";
    
    // Use existing test images or create simple placeholders
    $clockInPath = public_path($attendance->clock_in_image);
    $clockOutPath = public_path($attendance->clock_out_image);
    
    // Create directory if it doesn't exist
    $dir = dirname($clockInPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Create simple placeholder images
    if (!file_exists($clockInPath)) {
        // Create a simple 300x200 placeholder image
        $img = imagecreatetruecolor(300, 200);
        $bgColor = imagecolorallocate($img, 100, 150, 200);
        $textColor = imagecolorallocate($img, 255, 255, 255);
        
        imagefill($img, 0, 0, $bgColor);
        
        // Add simple text using built-in font
        imagestring($img, 5, 100, 80, 'Clock In', $textColor);
        imagestring($img, 2, 80, 120, date('Y-m-d H:i:s'), $textColor);
        
        imagejpeg($img, $clockInPath);
        imagedestroy($img);
        echo "✅ Clock In placeholder image created: " . $clockInPath . "\n";
    }
    
    if (!file_exists($clockOutPath)) {
        // Create a simple 300x200 placeholder image
        $img = imagecreatetruecolor(300, 200);
        $bgColor = imagecolorallocate($img, 200, 100, 150);
        $textColor = imagecolorallocate($img, 255, 255, 255);
        
        imagefill($img, 0, 0, $bgColor);
        
        // Add simple text using built-in font
        imagestring($img, 5, 100, 80, 'Clock Out', $textColor);
        imagestring($img, 2, 80, 120, date('Y-m-d H:i:s'), $textColor);
        
        imagejpeg($img, $clockOutPath);
        imagedestroy($img);
        echo "✅ Clock Out placeholder image created: " . $clockOutPath . "\n";
    }
    
    echo "\n🎯 Test Data Ready!\n";
    echo "You can now test the image preview functionality:\n";
    echo "1. Go to the monthly attendance report page\n";
    echo "2. Find the attendance for today (" . $attendance->date . ")\n";
    echo "3. Click on the 'P' badge to open attendance details\n";
    echo "4. Click on the Clock In/Out images or Preview buttons\n\n";
    
    echo "📋 Quick Test URLs:\n";
    echo "Monthly Report: " . url('/attendance/monthly-report-new?month=' . date('Y-m') . '&employee_id=' . $employee->employee_id) . "\n";
    echo "Attendance Details API: " . url('/attendance/details?employee_id=' . $employee->employee_id . '&date=' . $attendance->date) . "\n\n";
    
    echo "🔍 Testing the image preview modal directly:\n";
    echo "You can test the modal by visiting: " . url('/test-simple') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
