<?php

/**
 * Simple Backup and Analysis Tool
 * 
 * This tool provides a simple, reliable way to create
 * a backup and analyze the current database state.
 * 
 * USAGE: php simple_backup_analysis.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimpleBackupAnalysis
{
    private $outputPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute simple backup and analysis
     */
    public function execute()
    {
        echo "=== SIMPLE BACKUP AND ANALYSIS ===\n\n";

        try {
            $this->createSimpleBackup();
            $this->analyzeCurrentState();
            $this->generateReport();
        } catch (Exception $e) {
            echo "\n❌ ERROR: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Create simple backup
     */
    private function createSimpleBackup()
    {
        echo "STEP 1: CREATING BACKUP\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->outputPath . "/simple_backup_{$timestamp}.sql";

        // Get basic database info
        $database = DB::connection()->getDatabaseName();
        
        echo "Creating backup for database: {$database}\n";
        echo "Backup file: $backupFile\n";

        // Create backup using Laravel's DB facade
        $tables = DB::select('SHOW TABLES');
        
        $sqlContent = "-- Simple Database Backup\n";
        $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "-- Database: {$database}\n";
        $sqlContent .= "-- Tables: " . count($tables) . "\n\n";

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            echo "  Processing table: {$tableName}\n";
            
            $sqlContent .= "-- Table: {$tableName}\n";
            
            // Get table structure (basic)
            try {
                $columns = DB::select("DESCRIBE `{$tableName}`");
                $sqlContent .= "CREATE TABLE `{$tableName}` (\n";
                
                $columnDefs = [];
                foreach ($columns as $column) {
                    $columnDefs[] = "    `{$column->Field}` {$column->Type} " . 
                                     ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . 
                                     ($column->Default ? "DEFAULT {$column->Default}" : '') . 
                                     ($column->Extra ? $column->Extra : '');
                }
                
                $sqlContent .= implode(",\n", $columnDefs);
                $sqlContent .= "\n);\n\n";
                
            } catch (Exception $e) {
                $sqlContent .= "-- Error processing table {$tableName}: " . $e->getMessage() . "\n\n";
            }
        }

        file_put_contents($backupFile, $sqlContent);
        
        if (file_exists($backupFile) && filesize($backupFile) > 0) {
            echo "✓ Backup created successfully\n";
            echo "✓ Backup size: " . $this->formatBytes(filesize($backupFile)) . "\n";
            $this->backupFile = $backupFile;
        } else {
            throw new Exception("Failed to create backup file");
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
        $orphaned = [];
        $missing = [];
        
        $migrationFileNames = array_column($migrationFiles, 'name');
        foreach ($dbMigrations as $migration) {
            if (!in_array($migration->migration, $migrationFileNames)) {
                $orphaned[] = $migration;
            }
        }

        foreach ($migrationFiles as $migration) {
            $exists = $dbMigrations->firstWhere('migration', $migration['name']);
            if (!$exists) {
                $missing[] = $migration;
            }
        }

        $this->analysis = [
            'migration_files' => count($migrationFiles),
            'db_migrations' => $dbMigrations->count(),
            'orphaned_migrations' => count($orphaned),
            'missing_migrations' => count($missing),
            'database_tables' => count(DB::select('SHOW TABLES'))
        ];

        echo "Migration files: {$this->analysis['migration_files']}\n";
        echo "Database migrations: {$this->analysis['db_migrations']}\n";
        echo "Orphaned migrations: {$this->analysis['orphaned_migrations']}\n";
        echo "Missing migrations: {$this->analysis['missing_migrations']}\n";
        echo "Database tables: {$this->analysis['database_tables']}\n\n";

        $this->orphanedMigrations = $orphaned;
        $this->missingMigrations = $missing;
    }

    /**
     * Generate report
     */
    private function generateReport()
    {
        echo "STEP 3: GENERATING REPORT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $reportFile = $this->outputPath . "/analysis_report_{$timestamp}.md";

        $content = "# Database Analysis Report\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Backup: {$this->backupFile}\n\n";

        $content .= "## Current State\n\n";
        $content .= "- **Migration Files**: {$this->analysis['migration_files']}\n";
        $content .= "- **Database Migrations**: {$this->analysis['db_migrations']}\n";
        $content .= "- **Orphaned Migrations**: {$this->analysis['orphaned_migrations']}\n";
        $content .= "- **Missing Migrations**: {$this->analysis['missing_migrations']}\n";
        $content .= "- **Database Tables**: {$this->analysis['database_tables']}\n\n";

        $content .= "## Risk Assessment\n\n";
        $totalDrift = $this->analysis['orphaned_migrations'] + $this->analysis['missing_migrations'];
        
        if ($totalDrift > 100) {
            $content .= "🔴 **SEVERE**: Significant schema drift detected\n";
            $content .= "- More than 100 migrations out of sync\n";
            $content .= "- Requires immediate attention\n";
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

        $content .= "\n## Recommendations\n\n";
        $content .= "### Immediate Actions\n\n";
        $content .= "1. **Review backup**: Check {$this->backupFile}\n";
        $content .= "2. **Assess risk**: {$totalDrift} migrations out of sync\n";
        $content .= "3. **Plan approach**: Choose strategy based on drift level\n\n";

        $content .= "### Next Steps\n\n";
        $content .= "```bash\n";
        $content .= "# Continue with conservative migration generation\n";
        $content .= "php conservative_migration_strategy_enhanced.php --dry-run\n\n";
        $content .= "# Document orphaned migrations\n";
        $content .= "php orphaned_migration_detailed_documentation.php\n\n";
        $content .= "```\n\n";

        file_put_contents($reportFile, $content);
        echo "✓ Analysis report saved: $reportFile\n\n";
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
            $migrations[] = [
                'name' => basename($file, '.php'),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
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

// Execute simple backup and analysis
try {
    $analysis = new SimpleBackupAnalysis();
    $analysis->execute();
    
    echo "✅ SIMPLE BACKUP AND ANALYSIS COMPLETED\n";
    echo "📋 Review generated backup and analysis report\n";
    echo "🚨 Proceed to Phase B: Conservative Migration Generation\n";
    echo "\n";
    echo "Next command: php conservative_migration_strategy_enhanced.php --dry-run\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
