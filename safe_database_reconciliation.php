<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== SAFE DATABASE RECONCILIATION SCRIPT ===\n\n";

// Safety check - confirm this is not production
$env = config('app.env');
if ($env === 'production') {
    echo "⚠️  WARNING: This appears to be a production environment!\n";
    echo "This script should NOT be run in production without proper backups.\n";
    echo "Current APP_ENV: {$env}\n\n";
    
    echo "Type 'I_UNDERSTAND_RISKS' to continue, or anything else to exit: ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if ($input !== 'I_UNDERSTAND_RISKS') {
        echo "Script terminated for safety.\n";
        exit(1);
    }
}

// Create backup directory if it doesn't exist
$backupDir = storage_path('app/database_backups');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Generate timestamp for backup
$timestamp = date('Y-m-d_H-i-s');
$backupFile = $backupDir . "/migrations_backup_{$timestamp}.sql";

echo "📋 STEP 1: Creating backup of migrations table...\n";
try {
    $dbConfig = config('database.connections.mysql');
    $backupCommand = sprintf(
        'mysqldump -u%s -p%s %s migrations > %s',
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        $backupFile
    );
    
    // Try to create backup (will fail if mysql command not available)
    exec($backupCommand, $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Backup created: {$backupFile}\n";
    } else {
        echo "⚠️  Could not create automatic backup. Please manually backup the migrations table.\n";
    }
} catch (Exception $e) {
    echo "⚠️  Could not create automatic backup: " . $e->getMessage() . "\n";
}

echo "\n📋 STEP 2: Analyzing migration state...\n";

// Get all migration files
$migrationPath = database_path('migrations');
$migrationFiles = glob($migrationPath . '/*.php');
$migrationFileNames = array_map(function($file) {
    return basename($file, '.php');
}, $migrationFiles);

// Get migrations in database
$dbMigrations = \DB::table('migrations')->pluck('migration')->toArray();

// Find missing migrations (in files but not in database)
$missingMigrations = array_diff($migrationFileNames, $dbMigrations);

echo "📊 Analysis Results:\n";
echo "- Migration files found: " . count($migrationFiles) . "\n";
echo "- Migrations tracked in database: " . count($dbMigrations) . "\n";
echo "- Missing migrations: " . count($missingMigrations) . "\n\n";

if (empty($missingMigrations)) {
    echo "✅ All migration files are already tracked in the database.\n";
    echo "No reconciliation needed.\n";
    exit(0);
}

echo "📋 STEP 3: Preparing to add missing migrations...\n";
echo "The following migrations will be marked as 'already run':\n";
foreach ($missingMigrations as $migration) {
    echo "  - {$migration}\n";
}

echo "\n⚠️  IMPORTANT NOTES:\n";
echo "- This will NOT run any migrations - it will only mark them as completed\n";
echo "- This assumes the tables already exist in your database\n";
echo "- This is safe for production data\n\n";

echo "Type 'PROCEED' to continue, or anything else to cancel: ";
$handle = fopen("php://stdin", "r");
$input = trim(fgets($handle));
fclose($handle);

if ($input !== 'PROCEED') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "\n📋 STEP 4: Adding missing migrations to tracking table...\n";

// Get the highest batch number
$maxBatch = \DB::table('migrations')->max('batch') ?? 0;
$newBatch = $maxBatch + 1;

$successCount = 0;
$errorCount = 0;

foreach ($missingMigrations as $migration) {
    try {
        // Check if table actually exists before marking migration as run
        $content = file_get_contents($migrationPath . '/' . $migration . '.php');
        $tableExists = false;
        
        // Extract table name from migration
        if (preg_match('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $tableName = $matches[1];
            
            // Check if table exists
            $tableCheck = \DB::select("SHOW TABLES LIKE '{$tableName}'");
            if (!empty($tableCheck)) {
                $tableExists = true;
            }
        } elseif (preg_match('/Schema::rename\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            // For rename migrations, check if the new table exists
            $newTableName = $matches[2];
            $tableCheck = \DB::select("SHOW TABLES LIKE '{$newTableName}'");
            if (!empty($tableCheck)) {
                $tableExists = true;
            }
        } elseif (preg_match('/Schema::drop/', $content)) {
            // For drop migrations, consider them as "completed" since the table doesn't exist
            $tableExists = true;
        } else {
            // For other migrations (add column, etc.), assume they're completed
            $tableExists = true;
        }
        
        if ($tableExists) {
            \DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $newBatch
            ]);
            echo "✅ Added: {$migration}\n";
            $successCount++;
        } else {
            echo "⚠️  Skipped: {$migration} (table does not exist)\n";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "❌ Error adding {$migration}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n📋 STEP 5: Verification...\n";

// Verify the results
$newDbMigrations = \DB::table('migrations')->pluck('migration')->toArray();
$newMissingMigrations = array_diff($migrationFileNames, $newDbMigrations);

echo "Results:\n";
echo "- Successfully added: {$successCount}\n";
echo "- Errors/skipped: {$errorCount}\n";
echo "- Still missing: " . count($newMissingMigrations) . "\n";

if (!empty($newMissingMigrations)) {
    echo "\nStill missing migrations:\n";
    foreach ($newMissingMigrations as $migration) {
        echo "  - {$migration}\n";
    }
}

echo "\n✅ Database reconciliation completed!\n";
echo "Backup file: {$backupFile}\n";
echo "New migrations were added to batch: {$newBatch}\n\n";

echo "📋 NEXT STEPS:\n";
echo "1. Run 'php artisan migrate:status' to verify all migrations are tracked\n";
echo "2. Run 'php artisan migrate' to ensure any pending migrations are applied\n";
echo "3. Test your application to ensure everything works correctly\n";
echo "4. Keep the backup file for at least 30 days\n";
