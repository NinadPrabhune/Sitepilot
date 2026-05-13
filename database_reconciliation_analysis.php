<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DATABASE RECONCILIATION ANALYSIS ===\n\n";

// Get all migration files
$migrationPath = database_path('migrations');
$migrationFiles = glob($migrationPath . '/*.php');
$migrationFileNames = array_map(function($file) {
    return basename($file, '.php');
}, $migrationFiles);

// Get all migrations in the database
$dbMigrations = \DB::table('migrations')->pluck('migration')->toArray();

// Get all database tables
$dbTables = \DB::select('SHOW TABLES');
$tableNames = array_map(function($table) {
    $tableArray = (array)$table;
    return array_values($tableArray)[0];
}, $dbTables);

echo "SUMMARY:\n";
echo "- Migration files found: " . count($migrationFiles) . "\n";
echo "- Migrations tracked in database: " . count($dbMigrations) . "\n";
echo "- Tables in database: " . count($tableNames) . "\n\n";

// Find migrations that exist in files but not in database
$missingFromDb = array_diff($migrationFileNames, $dbMigrations);
echo "MIGRATIONS IN FILES BUT NOT IN DATABASE (" . count($missingFromDb) . "):\n";
if (empty($missingFromDb)) {
    echo "  None - All migration files are tracked\n";
} else {
    foreach ($missingFromDb as $migration) {
        echo "  - $migration\n";
    }
}
echo "\n";

// Find migrations in database but not in files
$orphanedMigrations = array_diff($dbMigrations, $migrationFileNames);
echo "MIGRATIONS IN DATABASE BUT NOT IN FILES (" . count($orphanedMigrations) . "):\n";
if (empty($orphanedMigrations)) {
    echo "  None - All tracked migrations have corresponding files\n";
} else {
    foreach ($orphanedMigrations as $migration) {
        echo "  - $migration\n";
    }
}
echo "\n";

// Analyze migration files to extract table names
$tablesFromMigrations = [];
foreach ($migrationFiles as $file) {
    $content = file_get_contents($file);
    $migrationName = basename($file, '.php');
    
    // Look for Schema::create calls
    if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $table) {
            $tablesFromMigrations[$table][] = $migrationName;
        }
    }
    
    // Look for Schema::rename calls
    if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $oldTable) {
            $tablesFromMigrations[$oldTable][] = $migrationName . ' (renamed from)';
        }
        foreach ($matches[2] as $newTable) {
            $tablesFromMigrations[$newTable][] = $migrationName . ' (renamed to)';
        }
    }
    
    // Look for Schema::drop calls
    if (preg_match_all('/Schema::drop\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $table) {
            $tablesFromMigrations[$table][] = $migrationName . ' (dropped)';
        }
    }
}

// Find tables that should exist but don't
$expectedTables = array_keys($tablesFromMigrations);
$missingTables = array_diff($expectedTables, $tableNames);
echo "TABLES THAT SHOULD EXIST BUT ARE MISSING (" . count($missingTables) . "):\n";
if (empty($missingTables)) {
    echo "  None - All expected tables exist\n";
} else {
    foreach ($missingTables as $table) {
        echo "  - $table (from: " . implode(', ', $tablesFromMigrations[$table]) . ")\n";
    }
}
echo "\n";

// Find tables that exist but are not tracked by migrations
$untrackedTables = array_diff($tableNames, $expectedTables);
echo "TABLES THAT EXIST BUT ARE NOT TRACKED BY MIGRATIONS (" . count($untrackedTables) . "):\n";
if (empty($untrackedTables)) {
    echo "  None - All tables are tracked by migrations\n";
} else {
    foreach ($untrackedTables as $table) {
        echo "  - $table\n";
    }
}
echo "\n";

// Check for potential duplicate table creation migrations
$duplicateTableCreations = [];
foreach ($tablesFromMigrations as $table => $migrations) {
    $creationMigrations = array_filter($migrations, function($migration) {
        return !str_contains($migration, '(renamed') && !str_contains($migration, '(dropped');
    });
    if (count($creationMigrations) > 1) {
        $duplicateTableCreations[$table] = $creationMigrations;
    }
}

if (!empty($duplicateTableCreations)) {
    echo "POTENTIAL DUPLICATE TABLE CREATION MIGRATIONS:\n";
    foreach ($duplicateTableCreations as $table => $migrations) {
        echo "  - $table: " . implode(', ', $migrations) . "\n";
    }
    echo "\n";
}

// Get migration batch information
$batches = \DB::table('migrations')
    ->select('batch', \DB::raw('COUNT(*) as count'))
    ->groupBy('batch')
    ->orderBy('batch')
    ->get();

echo "MIGRATION BATCHES:\n";
foreach ($batches as $batch) {
    echo "  Batch {$batch->batch}: {$batch->count} migrations\n";
}
echo "\n";

echo "=== ANALYSIS COMPLETE ===\n";
