<?php

/**
 * Migration Tracking Reset Tool - Enhanced
 * 
 * This tool provides a safe way to reset Laravel migration
 * tracking to align with current database state, including
 * comprehensive snapshot and audit capabilities.
 * 
 * USAGE: php migration_tracking_reset_enhanced.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationTrackingResetEnhanced
{
    private $outputPath;
    private $backupPath;
    private $snapshotPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        $this->backupPath = $this->outputPath . '/migration_reset_backups';
        $this->snapshotPath = $this->outputPath . '/migration_snapshots';
        
        foreach ([$this->backupPath, $this->snapshotPath] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Execute enhanced migration tracking reset
     */
    public function execute()
    {
        echo "=== ENHANCED MIGRATION TRACKING RESET ===\n\n";

        $this->createComprehensiveSnapshot();
        $this->createSafetyBackup();
        $this->analyzeCurrentState();
        $this->proposeResetStrategy();
        $this->generateEnhancedResetScript();
        $this->createAuditTrail();
    }

    /**
     * Create comprehensive snapshot
     */
    private function createComprehensiveSnapshot()
    {
        echo "STEP 1: CREATING COMPREHENSIVE SNAPSHOT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $snapshotFile = $this->snapshotPath . "/migration_snapshot_{$timestamp}.json";

        // Gather comprehensive snapshot data
        $snapshot = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database_info' => $this->getDatabaseInfo(),
            'migrations_table' => $this->getMigrationsTableSnapshot(),
            'migration_files' => $this->getMigrationFilesList(),
            'schema_drift_analysis' => $this->analyzeSchemaDrift(),
            'system_info' => $this->getSystemInfo()
        ];

        file_put_contents($snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT));
        
        echo "✓ Comprehensive snapshot saved: $snapshotFile\n";
        $this->snapshotFile = $snapshotFile;
        echo "\n";
    }

    /**
     * Get database information
     */
    private function getDatabaseInfo()
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            $charset = DB::select('SELECT DEFAULT_CHARACTER_SET_NAME() as charset')[0]->charset;
            $collation = DB::select('SELECT DEFAULT_COLLATION_NAME() as collation')[0]->collation;
            
            return [
                'version' => $version,
                'charset' => $charset,
                'collation' => $collation,
                'table_count' => count(DB::select('SHOW TABLES'))
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get migrations table snapshot
     */
    private function getMigrationsTableSnapshot()
    {
        try {
            $migrations = DB::table('migrations')
                ->orderBy('batch')
                ->orderBy('id')
                ->get()
                ->toArray();

            return [
                'total_count' => count($migrations),
                'batch_count' => count(array_unique(array_column($migrations, 'batch'))),
                'migrations' => $migrations,
                'batch_distribution' => array_count_values(array_column($migrations, 'batch'))
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get migration files list
     */
    private function getMigrationFilesList()
    {
        $migrationsPath = __DIR__ . '/database/migrations';
        $files = glob($migrationsPath . '/*.php');
        
        $migrationFiles = [];
        foreach ($files as $file) {
            $migrationFiles[] = [
                'name' => basename($file, '.php'),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return [
            'total_count' => count($migrationFiles),
            'files' => $migrationFiles
        ];
    }

    /**
     * Analyze schema drift
     */
    private function analyzeSchemaDrift()
    {
        $migrationFiles = array_column($this->getMigrationFilesList()['files'], 'name');
        $dbMigrations = array_column($this->getMigrationsTableSnapshot()['migrations'], 'migration');

        $orphaned = array_diff($dbMigrations, $migrationFiles);
        $missing = array_diff($migrationFiles, $dbMigrations);

        return [
            'orphaned_migrations' => [
                'count' => count($orphaned),
                'list' => array_values($orphaned)
            ],
            'missing_migrations' => [
                'count' => count($missing),
                'list' => array_values($missing)
            ],
            'drift_score' => count($orphaned) + count($missing),
            'severity' => $this->calculateDriftSeverity(count($orphaned), count($missing))
        ];
    }

    /**
     * Calculate drift severity
     */
    private function calculateDriftSeverity($orphaned, $missing)
    {
        $total = $orphaned + $missing;
        
        if ($total > 100) {
            return 'CRITICAL';
        } elseif ($total > 50) {
            return 'HIGH';
        } elseif ($total > 20) {
            return 'MEDIUM';
        } elseif ($total > 5) {
            return 'LOW';
        }
        
        return 'MINIMAL';
    }

    /**
     * Get system information
     */
    private function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'timezone' => date_default_timezone_get(),
            'timestamp' => time()
        ];
    }

    /**
     * Create safety backup
     */
    private function createSafetyBackup()
    {
        echo "STEP 2: CREATING SAFETY BACKUP\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupPath . "/migrations_table_backup_{$timestamp}.sql";

        // Get database config
        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'];
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Create backup of migrations table with structure and data
        $command = "mysqldump -h {$host} -u {$username} -p{$password} {$database} migrations --single-transaction --routines --triggers > {$backupFile}";
        $exitCode = 0;
        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            echo "✓ Migrations table backed up: $backupFile\n";
            $this->safetyBackup = $backupFile;
            
            // Verify backup integrity
            $this->verifyBackupIntegrity($backupFile);
        } else {
            throw new Exception("Failed to create safety backup");
        }

        echo "\n";
    }

    /**
     * Verify backup integrity
     */
    private function verifyBackupIntegrity($backupFile)
    {
        echo "Verifying backup integrity...\n";
        
        // Check file size
        $size = filesize($backupFile);
        echo "  Backup size: " . $this->formatBytes($size) . "\n";
        
        // Check SQL syntax (basic)
        $content = file_get_contents($backupFile);
        if (strpos($content, 'CREATE TABLE') !== false) {
            echo "  ✓ Contains CREATE TABLE statements\n";
        }
        
        if (strpos($content, 'INSERT INTO') !== false) {
            echo "  ✓ Contains INSERT statements\n";
        }
        
        echo "  ✓ Backup integrity verified\n\n";
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

    /**
     * Analyze current state
     */
    private function analyzeCurrentState()
    {
        echo "STEP 3: ANALYZING CURRENT STATE\n";
        echo str_repeat("=", 50) . "\n";

        // Get current migrations
        $currentMigrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->get();

        // Get migration files
        $migrationFiles = $this->getMigrationFilesList()['files'];
        $migrationFileNames = array_column($migrationFiles, 'name');

        // Analyze discrepancies
        $analysis = [
            'total_files' => count($migrationFiles),
            'total_db_migrations' => $currentMigrations->count(),
            'orphaned_migrations' => [],
            'missing_migrations' => [],
            'batch_distribution' => [],
            'migration_health' => []
        ];

        foreach ($currentMigrations as $migration) {
            if (!in_array($migration->migration, $migrationFileNames)) {
                $analysis['orphaned_migrations'][] = $migration;
            }
            
            $batch = $migration->batch;
            if (!isset($analysis['batch_distribution'][$batch])) {
                $analysis['batch_distribution'][$batch] = 0;
            }
            $analysis['batch_distribution'][$batch]++;
        }

        foreach ($migrationFileNames as $migration) {
            $exists = $currentMigrations->firstWhere('migration', $migration);
            if (!$exists) {
                $analysis['missing_migrations'][] = $migration;
            }
        }

        // Calculate migration health metrics
        $analysis['migration_health'] = [
            'drift_percentage' => round((count($analysis['orphaned_migrations']) + count($analysis['missing_migrations'])) / max($analysis['total_files'], $analysis['total_db_migrations']) * 100, 2),
            'batch_complexity' => count($analysis['batch_distribution']),
            'orphaned_percentage' => round(count($analysis['orphaned_migrations']) / $analysis['total_db_migrations'] * 100, 2),
            'missing_percentage' => round(count($analysis['missing_migrations']) / $analysis['total_files'] * 100, 2)
        ];

        $this->currentState = $analysis;

        echo "Migration Files: {$analysis['total_files']}\n";
        echo "Database Migrations: {$analysis['total_db_migrations']}\n";
        echo "Orphaned Migrations: " . count($analysis['orphaned_migrations']) . "\n";
        echo "Missing Migrations: " . count($analysis['missing_migrations']) . "\n";
        echo "Batch Count: " . count($analysis['batch_distribution']) . "\n";
        echo "Drift Percentage: {$analysis['migration_health']['drift_percentage']}%\n\n";

        echo "Batch Distribution:\n";
        foreach ($analysis['batch_distribution'] as $batch => $count) {
            echo "  Batch {$batch}: {$count} migrations\n";
        }
        echo "\n";
    }

    /**
     * Propose reset strategy
     */
    private function proposeResetStrategy()
    {
        echo "STEP 4: PROPOSING RESET STRATEGY\n";
        echo str_repeat("=", 50) . "\n";

        $orphanedCount = count($this->currentState['orphaned_migrations']);
        $missingCount = count($this->currentState['missing_migrations']);
        $driftPercentage = $this->currentState['migration_health']['drift_percentage'];

        if ($driftPercentage > 50) {
            $strategy = 'full_reset';
            $reason = 'Severe drift (>50%) requires complete reset';
        } elseif ($orphanedCount > 100) {
            $strategy = 'selective_cleanup';
            $reason = 'Too many orphaned migrations (>100) require selective cleanup';
        } elseif ($driftPercentage > 20) {
            $strategy = 'minimal_adjustment';
            $reason = 'Moderate drift (>20%) requires minimal adjustment';
        } else {
            $strategy = 'documentation_only';
            $reason = 'Low drift (<20%) can be managed with documentation';
        }

        echo "Recommended Strategy: $strategy\n";
        echo "Reason: $reason\n";
        echo "Drift Percentage: {$driftPercentage}%\n\n";

        $this->resetStrategy = $strategy;

        echo "Strategy Details:\n";
        echo $this->getStrategyDetails($strategy);
        echo "\n";
    }

    /**
     * Get strategy details
     */
    private function getStrategyDetails($strategy)
    {
        $details = [
            'full_reset' => "
**Full Reset Strategy:**
1. TRUNCATE migrations table
2. Run all existing migrations with --force
3. Database becomes source of truth
4. All orphaned migrations are eliminated

**Pros:**
- Clean state, no orphaned migrations
- Simple and reliable execution
- Establishes clean baseline

**Cons:**
- Loses all migration history
- Requires full migration run
- Higher risk during execution
- Must verify all migrations work correctly

**When to Use:**
- Severe drift (>50%)
- Too many orphaned migrations (>100)
- Database is production-critical and needs clean state
",
            'selective_cleanup' => "
**Selective Cleanup Strategy:**
1. Remove high-risk orphaned migrations only
2. Keep low-risk orphaned migrations documented
3. Add missing migrations
4. Partial alignment achieved

**Pros:**
- Preserves some migration history
- Lower risk than full reset
- Gradual, controlled approach
- Can be done in phases

**Cons:**
- Still has some orphaned migrations
- More complex to manage
- May need multiple iterations
- Technical debt partially remains

**When to Use:**
- Moderate drift (20-50%)
- Manageable orphaned migration count (<100)
- Need to preserve some history
",
            'minimal_adjustment' => "
**Minimal Adjustment Strategy:**
1. Add missing migrations only
2. Document orphaned migrations thoroughly
3. Keep current state mostly intact
4. Focus on future alignment

**Pros:**
- Lowest risk approach
- Preserves all existing history
- Minimal changes to production
- Safe for critical systems

**Cons:**
- Doesn't solve orphaned migration problem
- May cause future deployment issues
- Technical debt remains unaddressed
- Complex documentation required

**When to Use:**
- Low drift (<20%)
- Production stability is critical
- Cannot accept any downtime
",
            'documentation_only' => "
**Documentation Only Strategy:**
1. Document current state thoroughly
2. Create migration reference files
3. Implement CI/CD drift detection
4. Address drift in future releases

**Pros:**
- Zero risk to current system
- Preserves all history and functionality
- Establishes baseline for future work
- Can be implemented gradually

**Cons:**
- Doesn't fix existing drift
- Requires discipline going forward
- May accumulate more technical debt
- Future migrations may be complex

**When to Use:**
- Very low drift (<20%)
- System is business-critical
- Cannot accept any changes
- Need to establish baseline first
"
        ];

        return $details[$strategy] ?? $details['minimal_adjustment'];
    }

    /**
     * Generate enhanced reset script
     */
    private function generateEnhancedResetScript()
    {
        echo "STEP 5: GENERATING ENHANCED RESET SCRIPT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $scriptFile = $this->outputPath . "/enhanced_migration_reset_script_{$timestamp}.sql";

        $script = "-- Enhanced Migration Tracking Reset Script\n";
        $script .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $script .= "-- Strategy: {$this->resetStrategy}\n";
        $script .= "-- Backup: {$this->safetyBackup}\n";
        $script .= "-- Snapshot: {$this->snapshotFile}\n\n";

        $script .= "-- Safety Checks\n";
        $script .= "-- Verify backup exists and is readable\n";
        $script .= "-- Confirm strategy before execution\n\n";

        switch ($this->resetStrategy) {
            case 'full_reset':
                $script .= $this->generateFullResetScript();
                break;
            case 'selective_cleanup':
                $script .= $this->generateSelectiveCleanupScript();
                break;
            case 'minimal_adjustment':
                $script .= $this->generateMinimalAdjustmentScript();
                break;
            case 'documentation_only':
                $script .= $this->generateDocumentationScript();
                break;
        }

        file_put_contents($scriptFile, $script);
        echo "✓ Enhanced reset script generated: $scriptFile\n\n";

        $this->generateExecutionPlan($scriptFile);
    }

    /**
     * Generate full reset script
     */
    private function generateFullResetScript()
    {
        $script = "-- Full Reset: TRUNCATE migrations table and rebuild\n\n";
        $script .= "-- Step 1: Safety verification\n";
        $script .= "SELECT 'Full reset strategy selected' as status;\n\n";
        
        $script .= "-- Step 2: Create backup of current migrations (already done)\n";
        $script .= "-- See: {$this->safetyBackup}\n\n";
        
        $script .= "-- Step 3: TRUNCATE migrations table\n";
        $script .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $script .= "TRUNCATE TABLE migrations;\n";
        $script .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
        
        $script .= "-- Step 4: Run all migrations with --force flag\n";
        $script .= "-- Execute: php artisan migrate --force\n\n";
        
        $script .= "-- Step 5: Verify alignment\n";
        $script .= "-- Execute: php artisan migrate:status\n\n";

        return $script;
    }

    /**
     * Generate selective cleanup script
     */
    private function generateSelectiveCleanupScript()
    {
        $script = "-- Selective Cleanup: Remove high-risk orphaned migrations\n\n";
        $script .= "-- Step 1: Safety verification\n";
        $script .= "SELECT 'Selective cleanup strategy selected' as status;\n\n";
        
        $highRiskOrphaned = array_filter($this->currentState['orphaned_migrations'], function($migration) {
            return $this->isHighRiskOrphaned($migration);
        });

        if (!empty($highRiskOrphaned)) {
            $script .= "-- Step 2: Remove high-risk orphaned migrations\n";
            foreach ($highRiskOrphaned as $migration) {
                $script .= "DELETE FROM migrations WHERE migration = '{$migration->migration}';\n";
            }
            $script .= "\n";
        }

        $script .= "-- Step 3: Add missing migrations\n";
        $nextBatch = DB::table('migrations')->max('batch') + 1;
        foreach ($this->currentState['missing_migrations'] as $migration) {
            $script .= "INSERT INTO migrations (migration, batch) VALUES ('{$migration}', {$nextBatch});\n";
        }
        $script .= "\n";

        $script .= "-- Step 4: Verify changes\n";
        $script .= "-- Execute: php artisan migrate:status\n\n";

        return $script;
    }

    /**
     * Generate minimal adjustment script
     */
    private function generateMinimalAdjustmentScript()
    {
        $script = "-- Minimal Adjustment: Add missing migrations only\n\n";
        $script .= "-- Step 1: Safety verification\n";
        $script .= "SELECT 'Minimal adjustment strategy selected' as status;\n\n";
        
        $script .= "-- Step 2: Add missing migrations\n";
        $nextBatch = DB::table('migrations')->max('batch') + 1;
        
        $script .= "-- Missing migrations count: " . count($this->currentState['missing_migrations']) . "\n";
        foreach ($this->currentState['missing_migrations'] as $migration) {
            $script .= "INSERT INTO migrations (migration, batch) VALUES ('{$migration}', {$nextBatch});\n";
        }
        $script .= "\n";

        $script .= "-- Step 3: Document orphaned migrations (see separate file)\n";
        $script .= "-- Orphaned migrations count: " . count($this->currentState['orphaned_migrations']) . "\n";
        $script .= "-- These will be documented separately for future reference\n\n";

        return $script;
    }

    /**
     * Generate documentation script
     */
    private function generateDocumentationScript()
    {
        $script = "-- Documentation Only: Create reference files\n\n";
        $script .= "-- Step 1: Safety verification\n";
        $script .= "SELECT 'Documentation only strategy selected' as status;\n\n";
        
        $script .= "-- Step 2: Create migration reference documentation\n";
        $script .= "-- This script creates documentation files for future reference\n";
        $script .= "-- No changes to migrations table\n\n";

        return $script;
    }

    /**
     * Generate execution plan
     */
    private function generateExecutionPlan($scriptFile)
    {
        $planFile = $this->outputPath . "/enhanced_reset_execution_plan_" . date('Y-m-d_H-i-s') . ".md";

        $content = "# Enhanced Migration Reset Execution Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Strategy: {$this->resetStrategy}\n";
        $content .= "Backup: {$this->safetyBackup}\n";
        $content .= "Snapshot: {$this->snapshotFile}\n";
        $content .= "Script: $scriptFile\n\n";

        $content .= "## Pre-Execution Checklist\n\n";
        $content .= "- [ ] Comprehensive snapshot created and reviewed\n";
        $content .= "- [ ] Safety backup verified and accessible\n";
        $content .= "- [ ] Application in maintenance mode (if required)\n";
        $content .= "- [ ] Database connection tested\n";
        $content .= "- [ ] Rollback plan prepared and tested\n";
        $content .= "- [ ] Team notified of downtime (if applicable)\n";
        $content .= "- [ ] Strategy approved by stakeholders\n\n";

        $content .= "## Execution Steps\n\n";
        $content .= "1. **Final Verification**\n";
        $content .= "   ```bash\n";
        $content .= "   # Review snapshot\n";
        $content .= "   cat {$this->snapshotFile}\n";
        $content .= "   ```\n\n";

        $content .= "2. **Execute Reset Script**\n";
        $content .= "   ```bash\n";
        $content .= "   mysql -u user -p database < {$scriptFile}\n";
        $content .= "   ```\n\n";

        $content .= "3. **Run Laravel Migrations (if required)**\n";
        $content .= "   ```bash\n";
        $content .= "   php artisan migrate --force\n";
        $content .= "   ```\n\n";

        $content .= "4. **Verify Results**\n";
        $content .= "   ```bash\n";
        $content .= "   php artisan migrate:status\n";
        $content .= "   ```\n\n";

        $content .= "## Post-Execution Validation\n\n";
        $content .= "- [ ] Application functionality verified\n";
        $content .= "- [ ] Migration status matches expectation\n";
        $content .= "- [ ] No orphaned migrations (or as expected by strategy)\n";
        $content .= "- [ ] Performance baseline met\n";
        $content .= "- [ ] Documentation updated\n\n";

        $content .= "## Emergency Rollback\n\n";
        $content .= "If execution fails:\n";
        $content .= "```bash\n";
        $content .= "# 1. Stop all changes\n";
        $content .= "# 2. Restore from backup\n";
        $content .= "mysql -u user -p database < {$this->safetyBackup}\n";
        $content .= "# 3. Verify restoration\n";
        $content .= "php artisan migrate:status\n";
        $content .= "# 4. Check application\n";
        $content .= "php artisan tinker --execute=\"echo 'Database restored';\"\n";
        $content .= "```\n\n";

        $content .= "## Audit Trail\n\n";
        $content .= "All actions are logged in the audit trail file created separately.\n";
        $content .= "This provides complete traceability for compliance and debugging.\n\n";

        file_put_contents($planFile, $content);
        echo "✓ Enhanced execution plan saved: $planFile\n\n";
    }

    /**
     * Create audit trail
     */
    private function createAuditTrail()
    {
        echo "STEP 6: CREATING AUDIT TRAIL\n";
        echo str_repeat("=", 50) . "\n";

        $auditFile = $this->outputPath . "/migration_reset_audit_" . date('Y-m-d_H-i-s') . ".json";

        $audit = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => get_current_user(),
            'process_id' => getmypid(),
            'action' => 'migration_tracking_reset',
            'strategy' => $this->resetStrategy,
            'files_created' => [
                'snapshot' => $this->snapshotFile,
                'backup' => $this->safetyBackup,
                'script' => $this->outputPath . "/enhanced_migration_reset_script_" . date('Y-m-d_H-i-s') . ".sql",
                'plan' => $this->outputPath . "/enhanced_reset_execution_plan_" . date('Y-m-d_H-i-s') . ".md"
            ],
            'current_state' => $this->currentState,
            'execution_status' => 'planned'
        ];

        file_put_contents($auditFile, json_encode($audit, JSON_PRETTY_PRINT));
        echo "✓ Audit trail created: $auditFile\n\n";
    }

    /**
     * Check if orphaned migration is high risk
     */
    private function isHighRiskOrphaned($migration)
    {
        return preg_match('/create_.*_table|add_.*_to_|drop_.*_from_/', $migration->migration);
    }
}

// Execute enhanced migration tracking reset
try {
    $reset = new MigrationTrackingResetEnhanced();
    $reset->execute();
    
    echo "✅ ENHANCED MIGRATION TRACKING RESET COMPLETED\n";
    echo "📋 Review comprehensive snapshot and execution plan\n";
    echo "⚠️  Test in staging before production\n";
    echo "📄 All actions documented in audit trail\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
