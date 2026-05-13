<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Testing View Syntax ===\n";

try {
    // Test if view file has syntax errors
    $viewPath = 'c:\wamp64\www\SitePilot\packages\workdo\Hrm\src\Resources\views\report\monthlyAttendance.blade.php';
    
    if (file_exists($viewPath)) {
        echo "✅ View file exists\n";
        
        // Try to compile the view
        $blade = new \Illuminate\View\Compilers\BladeCompiler();
        $content = file_get_contents($viewPath);
        
        try {
            $blade->compileString($content);
            echo "✅ View syntax is VALID\n";
        } catch (\Exception $e) {
            echo "❌ View syntax ERROR: " . $e->getMessage() . "\n";
            echo "   Line: " . $e->getLine() . "\n";
        }
    } else {
        echo "❌ View file not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>
