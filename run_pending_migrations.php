<?php

/**
 * Quick script to run only the pending migrations safely
 * 
 * USAGE: php run_pending_migrations.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "=== RUNNING PENDING MIGRATIONS SAFELY ===\n\n";

// List of pending migrations identified
$pendingMigrations = [
    '2026_05_03_000001_add_ledger_immutability_constraints',
    '2026_05_03_000002_add_financial_period_locking',
    '2026_05_04_000001_add_source_type_to_daily_progress_reports',
    '2026_05_04_000002_complete_machinery_ledgers',
    '2026_05_05_000001_create_monthly_locks_table',
    '2026_05_05_000002_create_machinery_billing_items_table',
    '2026_05_05_000003_create_machinery_bills_table',
    '2026_05_05_000004_add_machinery_permissions',
    '2026_05_06_000001_add_diesel_audit_fields_to_billing_items',
    '2026_05_06_113000_create_spatie_permission_tables'
];

echo "Found " . count($pendingMigrations) . " pending migrations to run.\n\n";

// Check if migrations already exist in database
$existingMigrations = DB::table('migrations')->pluck('migration')->toArray();
$toRun = array_diff($pendingMigrations, $existingMigrations);

if (empty($toRun)) {
    echo "✓ All pending migrations are already run.\n";
    exit(0);
}

echo "Migrations to run:\n";
foreach ($toRun as $migration) {
    echo "  - $migration\n";
}

echo "\nProceeding with migration execution...\n\n";

// Run each migration individually for better error handling
$successCount = 0;
$errorCount = 0;

foreach ($toRun as $migration) {
    echo "Running: $migration ... ";
    
    try {
        // Run the migration
        $exitCode = Artisan::call('migrate', [
            '--path' => "database/migrations/{$migration}.php",
            '--force' => true
        ]);
        
        if ($exitCode === 0) {
            echo "✓ SUCCESS\n";
            $successCount++;
        } else {
            $output = Artisan::output();
            echo "✗ FAILED\n";
            echo "Error: $output\n";
            $errorCount++;
            break; // Stop on first error
        }
        
    } catch (Exception $e) {
        echo "✗ EXCEPTION\n";
        echo "Error: " . $e->getMessage() . "\n";
        $errorCount++;
        break; // Stop on first error
    }
}

echo "\n=== MIGRATION SUMMARY ===\n";
echo "Successful: $successCount\n";
echo "Failed: $errorCount\n";

if ($errorCount > 0) {
    echo "\n⚠️  MIGRATION FAILED!\n";
    echo "Please check the error above and restore from backup if necessary.\n";
    exit(1);
} else {
    echo "\n✅ ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n";
    
    // Verify final status
    echo "\nVerifying migration status...\n";
    $finalStatus = Artisan::call('migrate:status');
    echo Artisan::output();
    
    exit(0);
}
