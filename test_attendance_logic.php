<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Attendance Logic Deep Dive ===\n";

try {
    // Test the exact controller logic step by step
    $ninadEmployeeId = 43;
    $dates = ['01', '02', '05', '06', '07'];
    
    echo "🔍 Testing controller attendance logic for Ninad (ID: " . $ninadEmployeeId . ")\n";
    
    // Simulate the exact controller logic
    $attendanceStatus = [];
    foreach ($dates as $date) {
        $dateFormat = '2026-01-' . $date;
        
        echo "   Date: " . $date . " -> Query: " . $dateFormat . "\n";
        
        $employeeAttendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $ninadEmployeeId)
            ->where('date', $dateFormat)
            ->first();
        
        if ($employeeAttendance) {
            echo "   Found: ID=" . $employeeAttendance->id . ", Status=" . $employeeAttendance->status . ", Date=" . $employeeAttendance->date . "\n";
            
            if ($employeeAttendance->status == 'Present') {
                $attendanceStatus[$date] = 'P';
                echo "   ✅ Setting attendanceStatus[" . $date . "] = 'P'\n";
            } elseif ($employeeAttendance->status == 'Leave') {
                $attendanceStatus[$date] = 'A';
                echo "   🏖️ Setting attendanceStatus[" . $date . "] = 'A'\n";
            } else {
                $attendanceStatus[$date] = '';
                echo "   ⚪ Setting attendanceStatus[" . $date . "] = ''\n";
            }
        } else {
            echo "   ❌ No record found for " . $dateFormat . "\n";
            $attendanceStatus[$date] = '';
        }
    }
    
    echo "\n📊 Final attendanceStatus array:\n";
    foreach ($attendanceStatus as $date => $status) {
        $display = $status ? $status : 'EMPTY';
        echo "   " . $date . " -> '" . $display . "'\n";
    }
    
    // Build final data structure like controller
    $attendances = [
        'name' => 'Test Ninad',
        'attendance' => $attendanceStatus
    ];
    
    echo "\n🔍 Testing data structure building:\n";
    $employeesAttendance = [];
    $employeesAttendance[$ninadEmployeeId] = $attendances;
    
    // Test final structure
    if (isset($employeesAttendance[$ninadEmployeeId])) {
        echo "✅ attendanceData[" . $ninadEmployeeId . "] exists\n";
        
        $finalData = $employeesAttendance[$ninadEmployeeId];
        echo "   Name: " . $finalData['name'] . "\n";
        
        if (isset($finalData['attendance'])) {
            $finalPresentCount = 0;
            foreach ($finalData['attendance'] as $status) {
                if ($status == 'P') {
                    $finalPresentCount++;
                }
            }
            
            echo "   Present days in final structure: " . $finalPresentCount . "\n";
            
            if ($finalPresentCount >= 4) {
                echo "   ✅ SUCCESS: Should show at least 4 present days\n";
            } else {
                echo "   ❌ ISSUE: Shows less than expected\n";
            }
        } else {
            echo "   ❌ attendance key missing\n";
        }
        
    } else {
        echo "   ❌ attendanceData[" . $ninadEmployeeId . "] missing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
