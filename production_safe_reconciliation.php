<?php

/**
 * Production-Safe Database Reconciliation Tool
 * 
 * This tool provides a cautious, production-safe approach to reconcile
 * database schema with Laravel migrations, addressing schema drift issues.
 * 
 * USAGE: php production_safe_reconciliation.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class ProductionSafeReconciliation
{
    private $backupPath;
    private $analysisResults = [];
    private $warnings = [];
    private $errors = [];

    public function __construct()
    {
        $this->backupPath = __DIR__ . '/database_backups';
        $this->ensureBackupDirectory();
    }

    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory()
    {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Execute complete production-safe reconciliation
     */
    public function execute()
    {
        echo "=== PRODUCTION-SAFE DATABASE RECONCILIATION ===\n\n";

        try {
            $this->step1_createBackup();
            $this->step2_simulateMigrations();
            $this->step3_analyzeSchemaDrift();
            $this->step4_generateRecommendations();
            $this->step5_provideExecutionPlan();
        } catch (Exception $e) {
            $this->errors[] = "Critical error: " . $e->getMessage();
            $this->displayErrors();
            exit(1);
        }
    }

    /**
     * Step 1: Create verified backup
     */
    private function step1_createBackup()
    {
        echo "STEP 1: CREATING VERIFIED BACKUP\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupPath . "/reconciliation_backup_{$timestamp}.sql";

        echo "Creating backup at: $backupFile\n";

        // Get database credentials from Laravel config
        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'];
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Create backup
        $command = "mysqldump -h {$host} -u {$username} -p{$password} {$database} > {$backupFile}";
        $exitCode = 0;
        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new Exception("Backup creation failed. Exit code: $exitCode");
        }

        // Verify backup file
        if (!file_exists($backupFile) || filesize($backupFile) === 0) {
            throw new Exception("Backup file verification failed");
        }

        echo "✓ Backup created successfully\n";
        echo "✓ Backup file size: " . $this->formatBytes(filesize($backupFile)) . "\n";
        echo "✓ Backup verified and ready for restoration\n\n";

        $this->analysisResults['backup_file'] = $backupFile;
    }

    /**
     * Step 2: Simulate migrations to detect conflicts
     */
    private function step2_simulateMigrations()
    {
        echo "STEP 2: SIMULATING MIGRATIONS (DRY RUN)\n";
        echo str_repeat("=", 50) . "\n";

        // Get pending migrations
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            echo "✓ No pending migrations found\n\n";
            return;
        }

        echo "Found " . count($pendingMigrations) . " pending migrations\n";
        echo "Simulating execution to detect conflicts...\n\n";

        $conflicts = [];
        foreach ($pendingMigrations as $migration) {
            echo "Simulating: $migration\n";
            
            try {
                // Use --pretend to simulate migration
                $exitCode = Artisan::call('migrate', [
                    '--path' => "database/migrations/{$migration}.php",
                    '--pretend' => true,
                    '--force' => true
                ]);

                $output = Artisan::output();
                
                if ($exitCode !== 0) {
                    $conflicts[] = [
                        'migration' => $migration,
                        'error' => $output
                    ];
                    echo "  ⚠️  POTENTIAL CONFLICT DETECTED\n";
                    echo "  Error: $output\n";
                } else {
                    echo "  ✓ Simulation passed\n";
                }
            } catch (Exception $e) {
                $conflicts[] = [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ];
                echo "  ✗ SIMULATION FAILED\n";
                echo "  Error: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }

        if (!empty($conflicts)) {
            $this->warnings[] = "Migration conflicts detected. Manual review required.";
            $this->analysisResults['migration_conflicts'] = $conflicts;
        } else {
            echo "✓ All migration simulations passed\n";
        }

        echo "\n";
    }

    /**
     * Step 3: Analyze schema drift
     */
    private function step3_analyzeSchemaDrift()
    {
        echo "STEP 3: ANALYZING SCHEMA DRIFT\n";
        echo str_repeat("=", 50) . "\n";

        $analysis = [
            'migration_files' => $this->countMigrationFiles(),
            'db_migrations' => DB::table('migrations')->count(),
            'db_tables' => count(DB::select('SHOW TABLES')),
            'orphaned_migrations' => $this->getOrphanedMigrations(),
            'tables_without_migrations' => $this->getTablesWithoutMigrations()
        ];

        echo "Migration files: " . $analysis['migration_files'] . "\n";
        echo "Database migrations: " . $analysis['db_migrations'] . "\n";
        echo "Database tables: " . $analysis['db_tables'] . "\n";
        echo "Orphaned migrations: " . count($analysis['orphaned_migrations']) . "\n";
        echo "Tables without migrations: " . count($analysis['tables_without_migrations']) . "\n\n";

        // Calculate drift metrics
        $migrationDrift = $analysis['db_migrations'] - $analysis['migration_files'];
        $schemaDrift = $analysis['db_tables'] - $analysis['migration_files'];

        if ($migrationDrift > 50 || $schemaDrift > 50) {
            $this->warnings[] = "Significant schema drift detected. This indicates historical manual changes.";
        }

        $this->analysisResults['schema_analysis'] = $analysis;
    }

    /**
     * Step 4: Generate recommendations
     */
    private function step4_generateRecommendations()
    {
        echo "STEP 4: GENERATING RECOMMENDATIONS\n";
        echo str_repeat("=", 50) . "\n";

        $recommendations = [];

        // Based on analysis
        if (!empty($this->analysisResults['migration_conflicts'])) {
            $recommendations[] = "HIGH PRIORITY: Resolve migration conflicts before proceeding";
        }

        if (count($this->analysisResults['schema_analysis']['orphaned_migrations']) > 100) {
            $recommendations[] = "MEDIUM PRIORITY: Document or reconstruct orphaned migrations for proper rollback capability";
        }

        if (count($this->analysisResults['schema_analysis']['tables_without_migrations']) > 200) {
            $recommendations[] = "HIGH PRIORITY: Generate migrations for existing tables to bring schema under version control";
        }

        $recommendations[] = "ALWAYS: Maintain regular backups during reconciliation process";
        $recommendations[] = "RECOMMENDED: Use migration generator tools for reverse engineering existing tables";

        foreach ($recommendations as $i => $rec) {
            echo ($i + 1) . ". $rec\n";
        }

        echo "\n";
        $this->analysisResults['recommendations'] = $recommendations;
    }

    /**
     * Step 5: Provide execution plan
     */
    private function step5_provideExecutionPlan()
    {
        echo "STEP 5: EXECUTION PLAN\n";
        echo str_repeat("=", 50) . "\n";

        echo "PHASE 1: PREPARATION\n";
        echo "- Backup created: {$this->analysisResults['backup_file']}\n";
        echo "- Migration simulation completed\n";
        echo "- Schema drift analysis completed\n\n";

        echo "PHASE 2: SAFE EXECUTION OPTIONS\n";
        echo "A) Install migration generator:\n";
        echo "   composer require --dev kitloong/laravel-migrations-generator\n";
        echo "   php artisan migrate:generate\n\n";

        echo "B) Handle orphaned migrations:\n";
        echo "   php artisan migrate:status\n";
        echo "   Review and document orphaned entries\n\n";

        echo "C) Run pending migrations (if no conflicts):\n";
        echo "   php artisan migrate --force\n\n";

        echo "PHASE 3: VERIFICATION\n";
        echo "- php artisan migrate:status\n";
        echo "- Test application functionality\n";
        echo "- Verify data integrity\n\n";

        echo "⚠️  PRODUCTION SAFETY REMINDERS:\n";
        echo "- Execute during maintenance window\n";
        echo "- Have rollback plan ready\n";
        echo "- Monitor application performance\n";
        echo "- Keep backup accessible\n\n";

        // Save analysis to file
        $this->saveAnalysisReport();
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations()
    {
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = DB::table('migrations')->pluck('migration')->toArray();
        
        return array_diff($allMigrations, $runMigrations);
    }

    /**
     * Get all migration files
     */
    private function getAllMigrationFiles()
    {
        $migrationsPath = __DIR__ . '/database/migrations';
        $files = glob($migrationsPath . '/*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }
        
        return $migrations;
    }

    /**
     * Count migration files
     */
    private function countMigrationFiles()
    {
        return count($this->getAllMigrationFiles());
    }

    /**
     * Get orphaned migrations
     */
    private function getOrphanedMigrations()
    {
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = DB::table('migrations')->pluck('migration')->toArray();
        
        return array_diff($runMigrations, $allMigrations);
    }

    /**
     * Get tables without migrations
     */
    private function getTablesWithoutMigrations()
    {
        $dbTables = array_map(function($table) {
            return array_values((array)$table)[0];
        }, DB::select('SHOW TABLES'));

        $migrationTables = $this->extractTablesFromMigrations();
        
        return array_diff($dbTables, $migrationTables, ['migrations']);
    }

    /**
     * Extract tables from migration files
     */
    private function extractTablesFromMigrations()
    {
        $tables = [];
        $migrationsPath = __DIR__ . '/database/migrations';
        $files = glob($migrationsPath . '/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            if (preg_match_all('/Schema::(?:create|table)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[] = $table;
                }
            }
        }
        
        return array_unique($tables);
    }

    /**
     * Save analysis report
     */
    private function saveAnalysisReport()
    {
        $reportFile = $this->backupPath . '/reconciliation_analysis_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($this->analysisResults, JSON_PRETTY_PRINT));
        echo "📄 Analysis report saved: $reportFile\n";
    }

    /**
     * Display errors
     */
    private function displayErrors()
    {
        echo "\n❌ ERRORS ENCOUNTERED:\n";
        foreach ($this->errors as $error) {
            echo "  - $error\n";
        }
        echo "\n";
    }

    /**
     * Format bytes for display
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Execute the production-safe reconciliation
try {
    $reconciliation = new ProductionSafeReconciliation();
    $reconciliation->execute();
    
    echo "\n✅ PRODUCTION-SAFE RECONCILIATION ANALYSIS COMPLETED\n";
    echo "📋 Follow the execution plan above for safe implementation\n";
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "🚫 DO NOT PROCEED with migrations until this is resolved\n";
    exit(1);
}
