<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Direct Controller Test ===\n";

try {
    // Test the controller's monthlyAttendance method directly
    $controller = new \Workdo\Hrm\Http\Controllers\ReportController();
    $request = new \Illuminate\Http\Request([
        'month' => '2026-01',
        'employee_id' => null
    ]);
    
    auth()->loginUsingId(2);
    
    echo "🔍 Testing controller directly...\n";
    $response = $controller->monthlyAttendance($request);
    
    if ($response instanceof \Illuminate\View\View) {
        $viewData = $response->getData();
        
        echo "✅ Controller returned view\n";
        echo "   attendanceData count: " . count($viewData['attendanceData']) . "\n";
        
        // Find Ninad
        $ninadFound = false;
        foreach ($viewData['attendanceData'] as $key => $data) {
            if (strpos($data['name'], 'Ninad') !== false) {
                echo "✅ Ninad found at key: " . $key . "\n";
                $ninadFound = true;
                
                if (isset($data['attendance'])) {
                    $presentCount = 0;
                    foreach ($data['attendance'] as $status) {
                        if ($status == 'P') {
                            $presentCount++;
                        }
                    }
                    echo "   Present days: " . $presentCount . "\n";
                    
                    if ($presentCount >= 15) {
                        echo "   ✅ SUCCESS: Ninad shows MAXIMUM days\n";
                    } else {
                        echo "   ❌ ISSUE: Ninad shows LIMITED days\n";
                    }
                }
                break;
            }
        }
        
        if (!$ninadFound) {
            echo "❌ Ninad not found in attendanceData\n";
        }
        
    } else {
        echo "❌ Controller failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
