<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Isolated Test: Data Flow Analysis ===\n";

try {
    // Test 1: Direct database query for Ninad
    echo "🔍 Test 1: Direct database query\n";
    $ninadEmployeeId = 43;
    $dates = ['01', '02', '05', '06', '07'];
    
    $directAttendanceData = [];
    foreach ($dates as $date) {
        $dateFormat = '2026-01-' . $date;
        $attendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $ninadEmployeeId)
            ->where('date', $dateFormat)
            ->first();
        
        $directAttendanceData[$date] = (!empty($attendance) && $attendance->status == 'Present') ? 'P' : '';
    }
    
    $directPresentCount = 0;
    foreach ($directAttendanceData as $status) {
        if ($status == 'P') {
            $directPresentCount++;
        }
    }
    
    echo "   Direct query present days: " . $directPresentCount . "\n";
    
    // Test 2: Controller method execution
    echo "\n🔍 Test 2: Controller method execution\n";
    $controller = new \Workdo\Hrm\Http\Controllers\ReportController();
    $request = new \Illuminate\Http\Request([
        'month' => '2026-01',
        'employee_id' => null
    ]);
    
    auth()->loginUsingId(2);
    
    $response = $controller->monthlyAttendance($request);
    
    if ($response instanceof \Illuminate\View\View) {
        $viewData = $response->getData();
        
        // Find Ninad in controller's attendanceData
        $controllerNinadData = null;
        $controllerNinadKey = null;
        
        foreach ($viewData['attendanceData'] as $key => $employeeData) {
            if (strpos($employeeData['name'], 'Ninad') !== false) {
                $controllerNinadData = $employeeData;
                $controllerNinadKey = $key;
                break;
            }
        }
        
        if ($controllerNinadData && isset($controllerNinadData['attendance'])) {
            $controllerPresentCount = 0;
            foreach ($controllerNinadData['attendance'] as $status) {
                if ($status == 'P') {
                    $controllerPresentCount++;
                }
            }
            
            echo "   Controller present days: " . $controllerPresentCount . "\n";
            
            // Compare direct vs controller
            if ($directPresentCount == $controllerPresentCount) {
                echo "   ✅ MATCH: Direct query and controller data match\n";
            } else {
                echo "   ❌ MISMATCH: Direct query (" . $directPresentCount . ") vs Controller data (" . $controllerPresentCount . ")\n";
                
                // Find differences
                foreach ($dates as $date) {
                    $directStatus = $directAttendanceData[$date] ?? 'EMPTY';
                    $controllerStatus = $controllerNinadData['attendance'][$date] ?? 'EMPTY';
                    
                    if ($directStatus != $controllerStatus) {
                        echo "   - " . $date . ": Direct=" . $directStatus . " vs Controller=" . $controllerStatus . " ❌\n";
                    }
                }
            }
        }
        
    } else {
        echo "   ❌ Ninad not found in controller data\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
