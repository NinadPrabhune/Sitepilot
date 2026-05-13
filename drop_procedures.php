<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Dropping existing procedures that might conflict...\n";
    
    // List of procedures to drop
    $procedures = [
        'prevent_ledger_update',
        'prevent_machinery_ledger_update'
    ];
    
    foreach ($procedures as $procedure) {
        try {
            DB::statement("DROP PROCEDURE IF EXISTS $procedure");
            echo "✅ Dropped procedure: $procedure\n";
        } catch (Exception $e) {
            echo "⚠️  Procedure $procedure not found or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Procedure cleanup completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
