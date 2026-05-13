<?php

/**
 * Migration Tracking Reset Tool
 * 
 * This tool provides a safe way to reset Laravel migration
 * tracking to align with current database state.
 * 
 * USAGE: php migration_tracking_reset.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationTrackingReset
{
    private $outputPath;
    private $backupPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        $this->backupPath = $this->outputPath . '/migration_reset_backups';
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Execute migration tracking reset
     */
    public function execute()
    {
        echo "=== MIGRATION TRACKING RESET ===\n\n";

        $this->createSafetyBackup();
        $this->analyzeCurrentState();
        $this->proposeResetStrategy();
        $this->generateResetScript();
    }

    /**
     * Create safety backup
     */
    private function createSafetyBackup()
    {
        echo "STEP 1: CREATING SAFETY BACKUP\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupPath . "/migrations_table_backup_{$timestamp}.sql";

        // Get database config
        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'];
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Create backup of migrations table
        $command = "mysqldump -h {$host} -u {$username} -p{$password} {$database} migrations > {$backupFile}";
        $exitCode = 0;
        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            echo "✓ Migrations table backed up: $backupFile\n";
            $this->safetyBackup = $backupFile;
        } else {
            throw new Exception("Failed to create safety backup");
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

        // Get current migrations
        $currentMigrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->get();

        // Get migration files
        $migrationFiles = $this->getMigrationFiles();

        // Analyze discrepancies
        $analysis = [
            'total_files' => count($migrationFiles),
            'total_db_migrations' => $currentMigrations->count(),
            'orphaned_migrations' => [],
            'missing_migrations' => [],
            'batch_distribution' => []
        ];

        foreach ($currentMigrations as $migration) {
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
            $exists = $currentMigrations->firstWhere('migration', $migration);
            if (!$exists) {
                $analysis['missing_migrations'][] = $migration;
            }
        }

        $this->currentState = $analysis;

        echo "Migration Files: {$analysis['total_files']}\n";
        echo "Database Migrations: {$analysis['total_db_migrations']}\n";
        echo "Orphaned Migrations: " . count($analysis['orphaned_migrations']) . "\n";
        echo "Missing Migrations: " . count($analysis['missing_migrations']) . "\n";
        echo "Batch Count: " . count($analysis['batch_distribution']) . "\n\n";

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
        echo "STEP 3: PROPOSING RESET STRATEGY\n";
        echo str_repeat("=", 50) . "\n";

        $orphanedCount = count($this->currentState['orphaned_migrations']);
        $missingCount = count($this->currentState['missing_migrations']);

        if ($orphanedCount > 100) {
            $strategy = 'full_reset';
            $reason = 'Too many orphaned migrations (>100)';
        } elseif ($orphanedCount > 50) {
            $strategy = 'selective_cleanup';
            $reason = 'Moderate number of orphaned migrations (>50)';
        } else {
            $strategy = 'minimal_adjustment';
            $reason = 'Manageable number of orphaned migrations';
        }

        echo "Recommended Strategy: $strategy\n";
        echo "Reason: $reason\n\n";

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
- Clean state
- No orphaned migrations
- Simple and reliable

**Cons:**
- Loses migration history
- Requires full migration run
- Higher risk during execution
",
            'selective_cleanup' => "
**Selective Cleanup Strategy:**
1. Remove high-risk orphaned migrations only
2. Keep low-risk orphaned migrations
3. Add missing migrations
4. Partial alignment achieved

**Pros:**
- Preserves some history
- Lower risk than full reset
- Gradual approach

**Cons:**
- Still has some orphaned migrations
- More complex to manage
- May need multiple iterations
",
            'minimal_adjustment' => "
**Minimal Adjustment Strategy:**
1. Add missing migrations only
2. Document orphaned migrations
3. Keep current state mostly intact
4. Focus on future alignment

**Pros:**
- Lowest risk
- Preserves all history
- Minimal changes

**Cons:**
- Doesn't solve orphaned migration problem
- May cause future issues
- Technical debt remains
"
        ];

        return $details[$strategy] ?? $details['minimal_adjustment'];
    }

    /**
     * Generate reset script
     */
    private function generateResetScript()
    {
        echo "STEP 4: GENERATING RESET SCRIPT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $scriptFile = $this->outputPath . "/migration_reset_script_{$timestamp}.sql";

        $script = "-- Migration Tracking Reset Script\n";
        $script .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $script .= "-- Strategy: {$this->resetStrategy}\n";
        $script .= "-- Backup: {$this->safetyBackup}\n\n";

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
        }

        file_put_contents($scriptFile, $script);
        echo "✓ Reset script generated: $scriptFile\n\n";

        $this->generateExecutionPlan($scriptFile);
    }

    /**
     * Generate full reset script
     */
    private function generateFullResetScript()
    {
        $script = "-- Full Reset: TRUNCATE migrations table and rebuild\n\n";
        $script .= "-- Step 1: Backup current migrations (already done)\n";
        $script .= "-- Step 2: TRUNCATE migrations table\n";
        $script .= "TRUNCATE TABLE migrations;\n\n";
        $script .= "-- Step 3: Run all migrations with --force flag\n";
        $script .= "-- php artisan migrate --force\n\n";
        $script .= "-- Step 4: Verify alignment\n";
        $script .= "-- php artisan migrate:status\n\n";

        return $script;
    }

    /**
     * Generate selective cleanup script
     */
    private function generateSelectiveCleanupScript()
    {
        $script = "-- Selective Cleanup: Remove high-risk orphaned migrations\n\n";
        
        $highRiskOrphaned = array_filter($this->currentState['orphaned_migrations'], function($migration) {
            return $this->isHighRiskOrphaned($migration);
        });

        if (!empty($highRiskOrphaned)) {
            $script .= "-- Step 1: Remove high-risk orphaned migrations\n";
            foreach ($highRiskOrphaned as $migration) {
                $script .= "DELETE FROM migrations WHERE migration = '{$migration->migration}';\n";
            }
            $script .= "\n";
        }

        $script .= "-- Step 2: Add missing migrations\n";
        foreach ($this->currentState['missing_migrations'] as $migration) {
            $script .= "INSERT INTO migrations (migration, batch) VALUES ('{$migration}', " . (DB::table('migrations')->max('batch') + 1) . ");\n";
        }
        $script .= "\n";

        return $script;
    }

    /**
     * Generate minimal adjustment script
     */
    private function generateMinimalAdjustmentScript()
    {
        $script = "-- Minimal Adjustment: Add missing migrations only\n\n";
        $script .= "-- Step 1: Add missing migrations\n";
        $nextBatch = DB::table('migrations')->max('batch') + 1;
        
        foreach ($this->currentState['missing_migrations'] as $migration) {
            $script .= "INSERT INTO migrations (migration, batch) VALUES ('{$migration}', {$nextBatch});\n";
        }
        $script .= "\n";

        $script .= "-- Step 2: Document orphaned migrations (see separate file)\n";
        $script .= "-- Orphaned migrations count: " . count($this->currentState['orphaned_migrations']) . "\n\n";

        return $script;
    }

    /**
     * Generate execution plan
     */
    private function generateExecutionPlan($scriptFile)
    {
        $planFile = $this->outputPath . "/migration_reset_execution_plan_" . date('Y-m-d_H-i-s') . ".md";

        $content = "# Migration Reset Execution Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Strategy: {$this->resetStrategy}\n";
        $content .= "Backup: {$this->safetyBackup}\n";
        $content .= "Script: $scriptFile\n\n";

        $content .= "## Pre-Execution Checklist\n\n";
        $content .= "- [ ] Backup verified and accessible\n";
        $content .= "- [ ] Application in maintenance mode\n";
        $content .= "- [ ] Database connection tested\n";
        $content .= "- [ ] Rollback plan prepared\n";
        $content .= "- [ ] Team notified of downtime\n\n";

        $content .= "## Execution Steps\n\n";
        $content .= "1. **Backup Verification**\n";
        $content .= "   ```bash\n";
        $content .= "   mysql -u user -p database < {$this->safetyBackup}\n";
        $content .= "   ```\n\n";

        $content .= "2. **Execute Reset Script**\n";
        $content .= "   ```bash\n";
        $content .= "   mysql -u user -p database < {$scriptFile}\n";
        $content .= "   ```\n\n";

        $content .= "3. **Run Laravel Migrations**\n";
        $content .= "   ```bash\n";
        $content .= "   php artisan migrate --force\n";
        $content .= "   ```\n\n";

        $content .= "4. **Verify Results**\n";
        $content .= "   ```bash\n";
        $content .= "   php artisan migrate:status\n";
        $content .= "   ```\n\n";

        $content .= "## Post-Execution Validation\n\n";
        $content .= "- [ ] Application functionality verified\n";
        $content .= "- [ ] Migration status clean\n";
        $content .= "- [ ] No orphaned migrations\n";
        $content .= "- [ ] Performance baseline met\n\n";

        $content .= "## Emergency Rollback\n\n";
        $content .= "If execution fails:\n";
        $content .= "```bash\n";
        $content .= "mysql -u user -p database < {$this->safetyBackup}\n";
        $content .= "php artisan migrate:status\n";
        $content .= "```\n\n";

        file_put_contents($planFile, $content);
        echo "✓ Execution plan saved: $planFile\n\n";
    }

    /**
     * Check if orphaned migration is high risk
     */
    private function isHighRiskOrphaned($migration)
    {
        return preg_match('/create_.*_table|add_.*_to_|drop_/', $migration->migration);
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
}

// Execute migration tracking reset
try {
    $reset = new MigrationTrackingReset();
    $reset->execute();
    
    echo "✅ MIGRATION TRACKING RESET COMPLETED\n";
    echo "📋 Review generated script and execution plan\n";
    echo "⚠️  Test in staging before production\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
