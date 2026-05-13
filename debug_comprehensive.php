<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Comprehensive Debug: Complete Data Flow ===\n";

try {
    // Step 1: Test employees array building
    echo "🔍 Step 1: Testing employees array building\n";
    $employeeQuery = \App\Models\User::leftjoin('employees', 'users.id', '=', 'employees.user_id')
        ->select('users.id', 'users.name', 'employees.id as employee_id');

    $employees = $employeeQuery->get()
        ->mapWithKeys(function($user) {
            return [$user->employee_id => $user->name];
        });
    
    echo "   Employees array keys: " . implode(', ', array_keys($employees->toArray())) . "\n";
    
    // Find Ninad's employee_id
    $ninadEmployeeId = null;
    foreach ($employees as $empId => $empName) {
        if (strpos($empName, 'Ninad') !== false) {
            $ninadEmployeeId = $empId;
            break;
        }
    }
    
    echo "   Ninad employee_id: " . $ninadEmployeeId . "\n";
    
    // Step 2: Test attendance data building
    echo "\n🔍 Step 2: Testing attendance data building\n";
    $dates = [];
    $num_of_days = date('t', mktime(0, 0, 0, 1, 1, 2026));
    for ($i = 1; $i <= $num_of_days; $i++) {
        $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
    }
    
    $attendanceStatus = [];
    foreach ($dates as $date) {
        $dateFormat = '2026-01-' . $date;
        
        $attendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $ninadEmployeeId)
            ->where('date', $dateFormat)
            ->first();
        
        if (!empty($attendance) && $attendance->status == 'Present') {
            $attendanceStatus[$date] = 'P';
        } elseif (!empty($attendance) && $attendance->status == 'Leave') {
            $attendanceStatus[$date] = 'A';
        } else {
            $attendanceStatus[$date] = '';
        }
    }
    
    echo "   Attendance status keys: " . implode(', ', array_keys($attendanceStatus)) . "\n";
    
    // Count present days
    $presentCount = 0;
    foreach ($attendanceStatus as $status) {
        if ($status == 'P') {
            $presentCount++;
        }
    }
    
    echo "   Present days count: " . $presentCount . "\n";
    
    // Step 3: Test controller execution
    echo "\n🔍 Step 3: Testing controller execution\n";
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
        
        echo "   Controller Ninad key: " . $controllerNinadKey . " (Expected: " . $ninadEmployeeId . ")\n";
        
        if ($controllerNinadData && isset($controllerNinadData['attendance'])) {
            $controllerPresentCount = 0;
            foreach ($controllerNinadData['attendance'] as $status) {
                if ($status == 'P') {
                    $controllerPresentCount++;
                }
            }
            echo "   Controller present count: " . $controllerPresentCount . "\n";
            echo "   Expected present count: " . $presentCount . "\n";
            
            if ($controllerPresentCount == $presentCount) {
                echo "   ✅ Controller data matches expected\n";
            } else {
                echo "   ❌ Controller data mismatch\n";
                echo "   Missing present days: " . ($presentCount - $controllerPresentCount) . "\n";
            }
        }
        
    } else {
        echo "❌ Controller failed to return view\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
