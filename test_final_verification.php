<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Final Verification: Ninad Attendance Fix ===\n";

try {
    // Test controller and get response
    $controller = new \Workdo\Hrm\Http\Controllers\ReportController();
    $request = new \Illuminate\Http\Request([
        'month' => '2026-01',
        'employee_id' => null
    ]);
    
    auth()->loginUsingId(2);
    
    echo "🔍 Testing controller...\n";
    $response = $controller->monthlyAttendance($request);
    
    if ($response instanceof \Illuminate\View\View) {
        $viewData = $response->getData();
        
        echo "✅ Controller returned view successfully\n";
        echo "   View data keys: " . implode(', ', array_keys($viewData)) . "\n";
        
        // Find Ninad in attendanceData
        $ninadData = null;
        $ninadKey = null;
        
        foreach ($viewData['attendanceData'] as $key => $employeeData) {
            if (strpos($employeeData['name'], 'Ninad') !== false) {
                $ninadData = $employeeData;
                $ninadKey = $key;
                break;
            }
        }
        
        if ($ninadData) {
            echo "✅ Found Ninad in attendanceData at key: " . $ninadKey . "\n";
            
            if (isset($ninadData['attendance'])) {
                echo "✅ Ninad has attendance data\n";
                
                // Count present days
                $presentCount = 0;
                $checkDates = ['01', '02', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31'];
                
                foreach ($checkDates as $date) {
                    $status = $ninadData['attendance'][$date] ?? 'NOT_FOUND';
                    if ($status == 'P') {
                        $presentCount++;
                    }
                    echo "   " . $date . ": " . $status . "\n";
                }
                
                echo "\n📊 Ninad's January 2026 Summary:\n";
                echo "   - Present days: " . $presentCount . "\n";
                echo "   - Total checked dates: " . count($checkDates) . "\n";
                echo "   - Present rate: " . round(($presentCount / count($checkDates)) * 100, 2) . "%\n";
                
                if ($presentCount >= 15) {
                    echo "   ✅ RESULT: Ninad shows MAXIMUM days (" . $presentCount . ")\n";
                    echo "   🎉 SUCCESS: Issue RESOLVED!\n";
                } else {
                    echo "   ⚠️  RESULT: Ninad shows LIMITED days (" . $presentCount . ")\n";
                    echo "   ❌ Issue persists\n";
                }
                
            } else {
                echo "   ❌ Ninad missing attendance data\n";
            }
            
        } else {
            echo "   ❌ Ninad not found in attendanceData\n";
        }
        
    } else {
        echo "❌ Controller did not return view\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
