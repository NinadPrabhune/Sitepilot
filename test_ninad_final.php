<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Final Test: Ninad Attendance Display ===\n";

try {
    // Test the updated controller
    $controller = new \Workdo\Hrm\Http\Controllers\ReportController();
    
    // Create request for January 2026
    $request = new \Illuminate\Http\Request([
        'month' => '2026-01',
        'employee_id' => null
    ]);
    
    // Mock authentication
    auth()->loginUsingId(2);
    
    echo "✅ Testing updated controller and view\n";
    
    // Get the response
    $response = $controller->monthlyAttendance($request);
    
    if ($response instanceof \Illuminate\View\View) {
        $viewData = $response->getData();
        
        echo "✅ View data generated:\n";
        echo "   - attendanceData: " . count($viewData['attendanceData']) . " employees\n";
        
        // Find Ninad in the data
        $ninadData = null;
        foreach ($viewData['attendanceData'] as $employeeData) {
            if (strpos($employeeData['name'], 'Ninad') !== false) {
                $ninadData = $employeeData;
                break;
            }
        }
        
        if ($ninadData) {
            echo "✅ Found Ninad in attendance data\n";
            echo "   - Name: " . $ninadData['name'] . "\n";
            
            if (isset($ninadData['attendance'])) {
                echo "   - Attendance data structure: CORRECT ✅\n";
                
                // Check first 10 days
                $checkDates = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10'];
                echo "   - First 10 days attendance:\n";
                
                foreach ($checkDates as $date) {
                    $status = $ninadData['attendance'][$date] ?? 'NOT_FOUND';
                    $icon = ($status == 'P') ? '✅' : (($status == 'A') ? '❌' : '⚪');
                    echo "     Jan " . $date . ": " . $status . " " . $icon . "\n";
                }
                
                // Count present days
                $presentCount = 0;
                $absentCount = 0;
                $emptyCount = 0;
                
                foreach ($ninadData['attendance'] as $status) {
                    if ($status == 'P') {
                        $presentCount++;
                    } elseif ($status == 'A') {
                        $absentCount++;
                    } else {
                        $emptyCount++;
                    }
                }
                
                echo "\n   📊 Ninad's January 2026 Summary:\n";
                echo "     - Present days: " . $presentCount . "\n";
                echo "     - Absent days: " . $absentCount . "\n";
                echo "     - Empty/No record: " . $emptyCount . "\n";
                echo "     - Total days: " . ($presentCount + $absentCount + $emptyCount) . "\n";
                echo "     - Present rate: " . round(($presentCount / ($presentCount + $absentCount + $emptyCount)) * 100, 2) . "%\n";
                
                if ($presentCount > 15) {
                    echo "     ✅ RESULT: Ninad shows MAXIMUM days (present > 15)\n";
                } else {
                    echo "     ⚠️  RESULT: Ninad does NOT show maximum days\n";
                }
                
            } else {
                echo "   ❌ Attendance data structure: MISSING 'attendance' key\n";
            }
        } else {
            echo "   ❌ Ninad not found in attendance data\n";
        }
        
    } else {
        echo "❌ Controller did not return view\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Browser Test Ready ===\n";
echo "Access: http://sitepilot/attendance/monthly-report-new?month=2026-01\n";

?>
