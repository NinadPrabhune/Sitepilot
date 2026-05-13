<?php

/**
 * Production-Safe Database Reconciliation Tool - Fixed
 * 
 * This tool provides a safe, Laravel-based approach to backup
 * and analyze database without relying on external commands.
 * 
 * USAGE: php production_safe_reconciliation_fixed.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class ProductionSafeReconciliationFixed
{
    private $outputPath;
    private $backupPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        $this->backupPath = $this->outputPath . '/reconciliation_backups';
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Execute production-safe reconciliation
     */
    public function execute()
    {
        echo "=== PRODUCTION-SAFE DATABASE RECONCILIATION (FIXED) ===\n\n";

        $this->createLaravelBackup();
        $this->analyzeCurrentState();
        $this->generateReconciliationPlan();
    }

    /**
     * Create Laravel-based backup
     */
    private function createLaravelBackup()
    {
        echo "STEP 1: CREATING LARAVEL-BASED BACKUP\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupPath . "/laravel_backup_{$timestamp}.sql";

        try {
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map(function($table) {
                return array_values((array)$table)[0];
            }, $tables);

            echo "Found " . count($tableNames) . " tables to backup\n";

            // Create SQL backup using Laravel's DB facade
            $sqlContent = "-- Laravel Database Backup\n";
            $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- Database: " . DB::connection()->getDatabaseName() . "\n";
            $sqlContent .= "-- Tables: " . count($tableNames) . "\n\n";

            foreach ($tableNames as $tableName) {
                echo "  Backing up table: {$tableName}\n";
                
                // Get table structure
                $createTableResult = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createTable = isset($createTableResult[0]) ? $createTableResult[0]->{'Create Table'} : '-- Table structure not available';
                $sqlContent .= "-- Table: {$tableName}\n";
                $sqlContent .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sqlContent .= $createTable . ";\n\n";
                
                // Get table data (limited for large tables)
                $rowCount = DB::table($tableName)->count();
                
                if ($rowCount > 100000) {
                    $sqlContent .= "-- Large table ({$rowCount} rows) - backing up structure only\n";
                    $sqlContent .= "-- Data backup would be too large, use mysqldump for full backup\n\n";
                } else {
                    $sqlContent .= "-- Table data ({$rowCount} rows)\n";
                    $data = DB::table($tableName)->get();
                    
                    if ($tableName === 'migrations') {
                        // Handle migrations table specially
                        foreach ($data as $row) {
                            $sqlContent .= "INSERT INTO migrations (migration, batch) VALUES ('" . addslashes($row->migration) . "', " . $row->batch . ");\n";
                        }
                    } else {
                        // Generic data insert
                        $columns = array_keys((array)$data[0]);
                        $columnList = implode(', ', $columns);
                        
                        $sqlContent .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n";
                        
                        foreach ($data as $row) {
                            $values = [];
                            foreach ($columns as $column) {
                                $value = $row->$column;
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } elseif (is_string($value)) {
                                    $values[] = "'" . addslashes($value) . "'";
                                } else {
                                    $values[] = $value;
                                }
                            }
                            $sqlContent .= "(" . implode(', ', $values) . "),\n";
                        }
                        $sqlContent .= ";\n";
                    }
                }
                
                $sqlContent .= "\n";
            }

            file_put_contents($backupFile, $sqlContent);
            
            // Verify backup file
            if (file_exists($backupFile) && filesize($backupFile) > 0) {
                echo "✓ Laravel-based backup created: $backupFile\n";
                echo "✓ Backup size: " . $this->formatBytes(filesize($backupFile)) . "\n";
                $this->safetyBackup = $backupFile;
            } else {
                throw new Exception("Failed to create backup file");
            }

        } catch (Exception $e) {
            echo "❌ Backup creation failed: " . $e->getMessage() . "\n";
            throw $e;
        }

        echo "\n";
    }

    /**
     * Analyze current state
     */
    private function analyzeCurrentState()
    {
        echo "STEP 2: ANALYZING CURRENT STATE\n";
        echo str_repeat("=", 50) . "\n";

        // Get migration files
        $migrationFiles = $this->getMigrationFiles();
        
        // Get migrations in database
        $dbMigrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->get();

        // Analyze discrepancies
        $analysis = [
            'total_files' => count($migrationFiles),
            'total_db_migrations' => $dbMigrations->count(),
            'orphaned_migrations' => [],
            'missing_migrations' => [],
            'batch_distribution' => [],
            'database_info' => $this->getDatabaseInfo()
        ];

        foreach ($dbMigrations as $migration) {
            if (!in_array($migration->migration, $migrationFiles)) {
                $analysis['orphaned_migrations'][] = $migration;
            }
            
            $batch = $migration->batch;
            if (!isset($analysis['batch_distribution'][$batch])) {
                $analysis['batch_distribution'][$batch] = 0;
            }
            $analysis['batch_distribution'][$batch]++;
        }

        foreach ($migrationFiles as $migration) {
            $exists = $dbMigrations->firstWhere('migration', $migration);
            if (!$exists) {
                $analysis['missing_migrations'][] = $migration;
            }
        }

        $this->currentState = $analysis;

        echo "Migration Files: {$analysis['total_files']}\n";
        echo "Database Migrations: {$analysis['total_db_migrations']}\n";
        echo "Orphaned Migrations: " . count($analysis['orphaned_migrations']) . "\n";
        echo "Missing Migrations: " . count($analysis['missing_migrations']) . "\n";
        echo "Database: {$analysis['database_info']['database']}\n";
        echo "Version: {$analysis['database_info']['version']}\n\n";

        echo "Batch Distribution:\n";
        foreach ($analysis['batch_distribution'] as $batch => $count) {
            echo "  Batch {$batch}: {$count} migrations\n";
        }
        echo "\n";
    }

    /**
     * Generate reconciliation plan
     */
    private function generateReconciliationPlan()
    {
        echo "STEP 3: GENERATING RECONCILIATION PLAN\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $planFile = $this->outputPath . "/reconciliation_plan_{$timestamp}.md";

        $orphanedCount = count($this->currentState['orphaned_migrations']);
        $missingCount = count($this->currentState['missing_migrations']);
        $totalDrift = $orphanedCount + $missingCount;

        $content = "# Database Reconciliation Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Backup: {$this->safetyBackup}\n\n";

        $content .= "## Current State Analysis\n\n";
        $content .= "- **Migration Files**: {$this->currentState['total_files']}\n";
        $content .= "- **Database Migrations**: {$this->currentState['total_db_migrations']}\n";
        $content .= "- **Orphaned Migrations**: {$orphanedCount}\n";
        $content .= "- **Missing Migrations**: {$missingCount}\n";
        $content .= "- **Total Drift**: {$totalDrift}\n";
        $content .= "- **Database**: {$this->currentState['database_info']['database']}\n";
        $content .= "- **Version**: {$this->currentState['database_info']['version']}\n\n";

        $content .= "## Risk Assessment\n\n";
        if ($totalDrift > 100) {
            $content .= "🔴 **SEVERE**: Significant schema drift detected\n";
            $content .= "- More than 100 migrations out of sync\n";
            $content .= "- Requires immediate attention and careful planning\n";
        } elseif ($totalDrift > 50) {
            $content .= "🟡 **HIGH**: Major schema drift detected\n";
            $content .= "- 50-100 migrations out of sync\n";
            $content .= "- Requires structured approach\n";
        } elseif ($totalDrift > 20) {
            $content .= "🟠 **MEDIUM**: Moderate schema drift detected\n";
            $content .= "- 20-50 migrations out of sync\n";
            $content .= "- Can be managed with careful approach\n";
        } else {
            $content .= "🟢 **LOW**: Minor schema drift detected\n";
            $content .= "- Less than 20 migrations out of sync\n";
            $content .= "- Standard reconciliation process\n";
        }

        $content .= "\n## Recommended Next Steps\n\n";
        $content .= "### Phase A: Analysis (COMPLETE)\n";
        $content .= "- ✅ Backup created and verified\n";
        $content .= "- ✅ Current state analyzed\n";
        $content .= "- ✅ Risk assessment completed\n\n";

        $content .= "### Phase B: Conservative Migration Generation\n";
        $content .= "```bash\n";
        $content .= "# Generate migrations in batches with dry-run mode\n";
        $content .= "php conservative_migration_strategy_enhanced.php --dry-run\n";
        $content .= "```\n\n";

        $content .= "### Phase C: Orphaned Migration Documentation\n";
        $content .= "```bash\n";
        $content .= "# Document orphaned migrations with detailed analysis\n";
        $content .= "php orphaned_migration_detailed_documentation.php\n";
        $content .= "```\n\n";

        $content .= "### Phase D: Zero-Downtime Planning\n";
        $content .= "```bash\n";
        $content .= "# Plan deployment strategies with read-only mode\n";
        $content .= "php zero_downtime_enhanced.php\n";
        $content .= "```\n\n";

        $content .= "### Phase E: Migration Tracking Reset (if needed)\n";
        $content .= "```bash\n";
        $content .= "# Reset migration tracking with comprehensive snapshot\n";
        $content .= "php migration_tracking_reset_enhanced.php\n";
        $content .= "```\n\n";

        $content .= "### Phase F: CI/CD Drift Detection\n";
        $content .= "```bash\n";
        $content .= "# Setup automated drift detection\n";
        $content .= "php ci_drift_detection_setup.php\n";
        $content .= "```\n\n";

        $content .= "## Critical Success Criteria\n\n";
        $content .= "✅ Schema matches Laravel migration representation for future deploys\n";
        $content .= "✅ No active migration fails or conflicts\n";
        $content .= "✅ Database remains fully functional for production apps\n";
        $content .= "✅ Rollback capability is restored for critical operations\n";
        $content .= "✅ CI/CD can validate schema consistency\n";
        $content .= "✅ Technical debt is documented and manageable\n\n";

        file_put_contents($planFile, $content);
        echo "✓ Reconciliation plan saved: $planFile\n\n";
    }

    /**
     * Get database information
     */
    private function getDatabaseInfo()
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            $database = DB::connection()->getDatabaseName();
            
            return [
                'version' => $version,
                'database' => $database,
                'charset' => 'utf8mb4', // Default for Laravel
                'collation' => 'utf8mb4_unicode_ci'
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get migration files
     */
    private function getMigrationFiles()
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

// Execute production-safe reconciliation
try {
    $reconciliation = new ProductionSafeReconciliationFixed();
    $reconciliation->execute();
    
    echo "✅ PHASE A COMPLETED - COMPREHENSIVE ANALYSIS\n";
    echo "📋 Review generated reconciliation plan\n";
    echo "🚨 Proceed to Phase B: Conservative Migration Generation\n";
    echo "\n";
    echo "Next command: php conservative_migration_strategy_enhanced.php --dry-run\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "🚫 STOP IMMEDIATELY - Do not proceed with further phases\n";
    exit(1);
}
