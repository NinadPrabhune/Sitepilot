<?php

/**
 * Database Migration Reconciliation Script
 * 
 * This script safely reconciles the database schema with Laravel migrations
 * without affecting existing production data.
 * 
 * USAGE: php database_reconciliation_script.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseReconciliation
{
    private $migrationsPath;
    private $dbMigrations;
    private $fileMigrations;
    private $dbTables;
    private $errors = [];
    private $warnings = [];
    private $actions = [];

    public function __construct()
    {
        $this->migrationsPath = __DIR__ . '/database/migrations';
        $this->loadMigrations();
        $this->loadDatabaseTables();
    }

    /**
     * Load migrations from database and files
     */
    private function loadMigrations()
    {
        // Load migrations from database
        $this->dbMigrations = DB::table('migrations')
            ->orderBy('id')
            ->pluck('migration')
            ->toArray();

        // Load migration files
        $this->fileMigrations = [];
        if (is_dir($this->migrationsPath)) {
            $files = glob($this->migrationsPath . '/*.php');
            foreach ($files as $file) {
                $migration = basename($file, '.php');
                $this->fileMigrations[] = $migration;
            }
        }
        sort($this->fileMigrations);
    }

    /**
     * Load all database tables
     */
    private function loadDatabaseTables()
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $this->dbTables = [];
            foreach ($tables as $table) {
                $tableName = array_values((array)$table)[0];
                $this->dbTables[] = $tableName;
            }
            sort($this->dbTables);
        } catch (Exception $e) {
            $this->errors[] = "Failed to load database tables: " . $e->getMessage();
        }
    }

    /**
     * Analyze migration status
     */
    public function analyze()
    {
        echo "=== DATABASE MIGRATION RECONCILIATION ANALYSIS ===\n\n";

        // 1. Check for pending migrations
        $this->checkPendingMigrations();

        // 2. Check for orphaned migrations (in DB but not in files)
        $this->checkOrphanedMigrations();

        // 3. Check for missing migrations (in files but not in DB)
        $this->checkMissingMigrations();

        // 4. Check for tables without corresponding migrations
        $this->checkOrphanedTables();

        // 5. Generate reconciliation plan
        $this->generateReconciliationPlan();

        // 6. Display summary
        $this->displaySummary();
    }

    /**
     * Check for pending migrations
     */
    private function checkPendingMigrations()
    {
        echo "1. CHECKING PENDING MIGRATIONS\n";
        echo str_repeat("-", 50) . "\n";

        $pending = array_diff($this->fileMigrations, $this->dbMigrations);
        
        if (empty($pending)) {
            echo "✓ No pending migrations found\n";
        } else {
            echo "⚠ PENDING MIGRATIONS FOUND:\n";
            foreach ($pending as $migration) {
                echo "  - $migration\n";
                $this->actions[] = "RUN: php artisan migrate --path=database/migrations/{$migration}.php";
            }
        }
        echo "\n";
    }

    /**
     * Check for orphaned migrations
     */
    private function checkOrphanedMigrations()
    {
        echo "2. CHECKING ORPHANED MIGRATIONS\n";
        echo str_repeat("-", 50) . "\n";

        $orphaned = array_diff($this->dbMigrations, $this->fileMigrations);
        
        if (empty($orphaned)) {
            echo "✓ No orphaned migrations found\n";
        } else {
            echo "⚠ ORPHANED MIGRATIONS (in DB but not in files):\n";
            foreach ($orphaned as $migration) {
                echo "  - $migration\n";
                $this->warnings[] = "Migration {$migration} exists in database but file is missing";
            }
        }
        echo "\n";
    }

    /**
     * Check for missing migrations
     */
    private function checkMissingMigrations()
    {
        echo "3. CHECKING MISSING MIGRATIONS\n";
        echo str_repeat("-", 50) . "\n";

        $missing = array_diff($this->fileMigrations, $this->dbMigrations);
        
        if (empty($missing)) {
            echo "✓ All migration files are tracked in database\n";
        } else {
            echo "⚠ MISSING MIGRATIONS (files not tracked in DB):\n";
            foreach ($missing as $migration) {
                echo "  - $migration\n";
                $this->actions[] = "TRACK: INSERT INTO migrations (migration, batch) VALUES ('{$migration}', " . (DB::table('migrations')->max('batch') + 1) . ")";
            }
        }
        echo "\n";
    }

    /**
     * Check for tables without corresponding migrations
     */
    private function checkOrphanedTables()
    {
        echo "4. CHECKING ORPHANED TABLES\n";
        echo str_repeat("-", 50) . "\n";

        // Extract table names from migration files
        $migrationTables = $this->extractTablesFromMigrations();
        
        $orphanedTables = array_diff($this->dbTables, $migrationTables, ['migrations']);
        
        if (empty($orphanedTables)) {
            echo "✓ All tables have corresponding migrations\n";
        } else {
            echo "⚠ ORPHANED TABLES (no corresponding migration found):\n";
            foreach ($orphanedTables as $table) {
                echo "  - $table\n";
                $this->warnings[] = "Table {$table} exists but no migration file found";
            }
        }
        echo "\n";
    }

    /**
     * Extract table names from migration files
     */
    private function extractTablesFromMigrations()
    {
        $tables = [];
        
        foreach ($this->fileMigrations as $migration) {
            $filePath = $this->migrationsPath . '/' . $migration . '.php';
            
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                
                // Look for Schema::create and Schema::table calls
                if (preg_match_all('/Schema::(?:create|table)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                    foreach ($matches[1] as $table) {
                        $tables[] = $table;
                    }
                }
                
                // Look for rename table operations
                if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                    foreach ($matches[2] as $table) {
                        $tables[] = $table;
                    }
                }
            }
        }
        
        return array_unique($tables);
    }

    /**
     * Generate reconciliation plan
     */
    private function generateReconciliationPlan()
    {
        echo "5. RECONCILIATION PLAN\n";
        echo str_repeat("-", 50) . "\n";

        if (empty($this->actions) && empty($this->warnings)) {
            echo "✓ Database schema is in sync with migrations\n";
            return;
        }

        if (!empty($this->actions)) {
            echo "ACTIONS TO PERFORM:\n";
            foreach ($this->actions as $i => $action) {
                echo "  " . ($i + 1) . ". $action\n";
            }
        }

        if (!empty($this->warnings)) {
            echo "\nWARNINGS TO REVIEW:\n";
            foreach ($this->warnings as $i => $warning) {
                echo "  " . ($i + 1) . ". $warning\n";
            }
        }
        echo "\n";
    }

    /**
     * Display summary
     */
    private function displaySummary()
    {
        echo "6. SUMMARY\n";
        echo str_repeat("-", 50) . "\n";
        
        echo "Total migration files: " . count($this->fileMigrations) . "\n";
        echo "Migrations in database: " . count($this->dbMigrations) . "\n";
        echo "Database tables: " . count($this->dbTables) . "\n";
        echo "Actions required: " . count($this->actions) . "\n";
        echo "Warnings: " . count($this->warnings) . "\n";
        echo "Errors: " . count($this->errors) . "\n";

        if (!empty($this->errors)) {
            echo "\nERRORS:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        echo "\n";
    }

    /**
     * Get actions array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Execute reconciliation actions safely
     */
    public function executeReconciliation($dryRun = true)
    {
        if (empty($this->actions)) {
            echo "No actions to execute.\n";
            return;
        }

        echo "=== EXECUTING RECONCILIATION (" . ($dryRun ? "DRY RUN" : "LIVE") . ") ===\n\n";

        foreach ($this->actions as $action) {
            if (strpos($action, 'RUN:') === 0) {
                $migration = substr($action, 5);
                echo "Would execute: $migration\n";
                if (!$dryRun) {
                    // Execute migration
                    passthru("php artisan migrate --path=" . str_replace('database/migrations/', '', $migration));
                }
            } elseif (strpos($action, 'TRACK:') === 0) {
                $sql = substr($action, 7);
                echo "Would execute: $sql\n";
                if (!$dryRun) {
                    try {
                        DB::statement($sql);
                        echo "✓ Migration tracked successfully\n";
                    } catch (Exception $e) {
                        echo "✗ Failed to track migration: " . $e->getMessage() . "\n";
                    }
                }
            }
            echo "\n";
        }
    }
}

// Main execution
try {
    $reconciliation = new DatabaseReconciliation();
    
    // Analyze current state
    $reconciliation->analyze();
    
    // Ask user if they want to proceed with actions
    if (!empty($reconciliation->getActions())) {
        echo "Do you want to execute the reconciliation actions? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) === 'y') {
            echo "Execute in dry-run mode first? (Y/n): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            $dryRun = trim(strtolower($line)) !== 'n';
            
            $reconciliation->executeReconciliation($dryRun);
            
            if ($dryRun) {
                echo "\nDry run completed. Run again with 'y' then 'n' to execute live.\n";
            } else {
                echo "\nReconciliation completed successfully!\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
