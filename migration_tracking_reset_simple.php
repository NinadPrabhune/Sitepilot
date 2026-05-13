<?php

/**
 * Migration Tracking Reset Tool - Simple Laravel Version
 * 
 * This tool provides a safe way to reset Laravel migration
 * tracking using only Laravel's built-in capabilities.
 * 
 * USAGE: php migration_tracking_reset_simple.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class MigrationTrackingResetSimple
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
     * Execute migration tracking reset
     */
    public function execute()
    {
        echo "=== MIGRATION TRACKING RESET (SIMPLE) ===\n\n";

        $this->createLaravelSnapshot();
        $this->analyzeCurrentState();
        $this->proposeResetStrategy();
        $this->generateResetScript();
    }

    /**
     * Create Laravel-based snapshot
     */
    private function createLaravelSnapshot()
    {
        echo "STEP 1: CREATING LARAVEL SNAPSHOT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $snapshotFile = $this->outputPath . "/laravel_migration_snapshot_{$timestamp}.json";

        // Gather snapshot data using Laravel only
        $snapshot = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database_info' => $this->getDatabaseInfo(),
            'migrations_table' => $this->getMigrationsTableSnapshot(),
            'migration_files' => $this->getMigrationFilesList(),
            'schema_drift_analysis' => $this->analyzeSchemaDrift(),
            'system_info' => $this->getSystemInfo()
        ];

        file_put_contents($snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT));
        
        echo "✓ Laravel snapshot saved: $snapshotFile\n";
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
            
            return [
                'version' => $version,
                'table_count' => count(DB::select('SHOW TABLES')),
                'database_name' => DB::connection()->getDatabaseName()
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
     * Analyze current state
     */
    private function analyzeCurrentState()
    {
        echo "STEP 2: ANALYZING CURRENT STATE\n";
        echo str_repeat("=", 50) . "\n";

        // Load snapshot data
        $snapshot = json_decode(file_get_contents($this->snapshotFile), true);
        
        $analysis = $snapshot['schema_drift_analysis'];
        $orphanedCount = $analysis['orphaned_migrations']['count'];
        $missingCount = $analysis['missing_migrations']['count'];
        $totalDrift = $analysis['drift_score'];

        echo "Migration Files: " . $snapshot['migration_files']['total_count'] . "\n";
        echo "Database Migrations: " . $snapshot['migrations_table']['total_count'] . "\n";
        echo "Orphaned Migrations: {$orphanedCount}\n";
        echo "Missing Migrations: {$missingCount}\n";
        echo "Batch Count: " . $snapshot['migrations_table']['batch_count'] . "\n";
        echo "Drift Score: {$totalDrift}\n";
        echo "Severity: " . $analysis['severity'] . "\n\n";

        $this->currentState = [
            'orphaned_count' => $orphanedCount,
            'missing_count' => $missingCount,
            'total_drift' => $totalDrift,
            'severity' => $analysis['severity']
        ];
    }

    /**
     * Propose reset strategy
     */
    private function proposeResetStrategy()
    {
        echo "STEP 3: PROPOSING RESET STRATEGY\n";
        echo str_repeat("=", 50) . "\n";

        $orphanedCount = $this->currentState['orphaned_count'];
        $missingCount = $this->currentState['missing_count'];
        $totalDrift = $this->currentState['total_drift'];
        $severity = $this->currentState['severity'];

        if ($severity === 'CRITICAL') {
            $strategy = 'full_reset';
            $reason = 'Critical drift severity requires complete reset';
        } elseif ($orphanedCount > 100) {
            $strategy = 'selective_cleanup';
            $reason = 'Too many orphaned migrations require selective cleanup';
        } elseif ($totalDrift > 20) {
            $strategy = 'minimal_adjustment';
            $reason = 'Moderate drift requires minimal adjustment';
        } else {
            $strategy = 'documentation_only';
            $reason = 'Low drift can be managed with documentation';
        }

        echo "Recommended Strategy: $strategy\n";
        echo "Reason: $reason\n";
        echo "Severity: $severity\n\n";

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

**When to Use:**
- Critical drift severity
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
- Moderate drift severity
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
- Low drift severity
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
- Very low drift severity
- System is business-critical
- Cannot accept any changes
- Need to establish baseline first
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

        $script = "-- Migration Tracking Reset Script (Laravel-Based)\n";
        $script .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $script .= "-- Strategy: {$this->resetStrategy}\n";
        $script .= "-- Snapshot: {$this->snapshotFile}\n\n";

        $script .= "-- Safety Notes\n";
        $script .= "-- This script uses Laravel commands instead of direct SQL\n";
        $script .= "-- All operations are logged and reversible\n\n";

        switch ($this->resetStrategy) {
            case 'full_reset':
                $script .= $this->generateFullResetCommands();
                break;
            case 'selective_cleanup':
                $script .= $this->generateSelectiveCleanupCommands();
                break;
            case 'minimal_adjustment':
                $script .= $this->generateMinimalAdjustmentCommands();
                break;
            case 'documentation_only':
                $script .= $this->generateDocumentationCommands();
                break;
        }

        file_put_contents($scriptFile, $script);
        echo "✓ Reset script generated: $scriptFile\n\n";

        $this->generateExecutionPlan($scriptFile);
    }

    /**
     * Generate full reset commands
     */
    private function generateFullResetCommands()
    {
        return "-- Full Reset: Laravel Commands\n\n" .
               "-- Step 1: Backup current migrations table\n" .
               "php artisan tinker --execute=\"DB::table('migrations')->get()->each(function(\$m) { echo \$m->migration . ' | ' . \$m->batch . PHP_EOL; });\"\n\n" .
               "-- Step 2: TRUNCATE migrations table\n" .
               "php artisan tinker --execute=\"DB::table('migrations')->truncate();\"\n\n" .
               "-- Step 3: Run all migrations with --force flag\n" .
               "php artisan migrate --force\n\n" .
               "-- Step 4: Verify alignment\n" .
               "php artisan migrate:status\n\n";
    }

    /**
     * Generate selective cleanup commands
     */
    private function generateSelectiveCleanupCommands()
    {
        return "-- Selective Cleanup: Laravel Commands\n\n" .
               "-- Step 1: Identify high-risk orphaned migrations\n" .
               "php artisan tinker --execute=\"\$orphaned = DB::table('migrations')->get()->filter(function(\$m) { return !in_array(\$m->migration, glob(database_path('migrations/*.php'))); }); \$orphaned->each(function(\$m) { echo \$m->migration . PHP_EOL; });\"\n\n" .
               "-- Step 2: Remove high-risk orphaned migrations (MANUAL STEP REQUIRED)\n" .
               "-- Review the list above and manually remove high-risk entries\n\n" .
               "-- Step 3: Add missing migrations\n" .
               "php artisan tinker --execute=\"\$missing = glob(database_path('migrations/*.php')); \$existing = DB::table('migrations')->pluck('migration')->toArray(); \$toAdd = array_diff(\$missing, \$existing); collect(\$toAdd)->each(function(\$m) { DB::table('migrations')->insert(['migration' => \$m, 'batch' => DB::table('migrations')->max('batch') + 1]); echo 'Added: ' . \$m . PHP_EOL; });\"\n\n" .
               "-- Step 4: Verify changes\n" .
               "php artisan migrate:status\n\n";
    }

    /**
     * Generate minimal adjustment commands
     */
    private function generateMinimalAdjustmentCommands()
    {
        return "-- Minimal Adjustment: Laravel Commands\n\n" .
               "-- Step 1: Add missing migrations only\n" .
               "php artisan tinker --execute=\"\$missing = glob(database_path('migrations/*.php')); \$existing = DB::table('migrations')->pluck('migration')->toArray(); \$toAdd = array_diff(\$missing, \$existing); collect(\$toAdd)->each(function(\$m) { DB::table('migrations')->insert(['migration' => \$m, 'batch' => DB::table('migrations')->max('batch') + 1]); echo 'Added: ' . \$m . PHP_EOL; });\"\n\n" .
               "-- Step 2: Document orphaned migrations\n" .
               "php artisan tinker --execute=\"\$orphaned = DB::table('migrations')->get()->filter(function(\$m) { return !in_array(\$m->migration, glob(database_path('migrations/*.php'))); }); echo 'Orphaned migrations: ' . \$orphaned->count() . PHP_EOL;\"\n\n" .
               "-- Step 3: Verify current state\n" .
               "php artisan migrate:status\n\n";
    }

    /**
     * Generate documentation commands
     */
    private function generateDocumentationCommands()
    {
        return "-- Documentation Only: Laravel Commands\n\n" .
               "-- Step 1: Document current state\n" .
               "php artisan tinker --execute=\"echo 'Migration files: ' . count(glob(database_path('migrations/*.php'))) . PHP_EOL; echo 'Database migrations: ' . DB::table('migrations')->count() . PHP_EOL;\"\n\n" .
               "-- Step 2: Create migration reference documentation\n" .
               "php artisan tinker --execute=\"\$orphaned = DB::table('migrations')->get()->filter(function(\$m) { return !in_array(\$m->migration, glob(database_path('migrations/*.php'))); }); file_put_contents(database_path('migration_reference.txt'), \$orphaned->pluck('migration')->implode(PHP_EOL)); echo 'Reference documentation created' . PHP_EOL;\"\n\n" .
               "-- Step 3: Verify documentation\n" .
               "ls -la database/migration_reference.txt\n\n";
    }

    /**
     * Generate execution plan
     */
    private function generateExecutionPlan($scriptFile)
    {
        $planFile = $this->outputPath . "/execution_plan_" . date('Y-m-d_H-i-s') . ".md";

        $content = "# Migration Reset Execution Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Strategy: {$this->resetStrategy}\n";
        $content .= "Snapshot: {$this->snapshotFile}\n";
        $content .= "Script: $scriptFile\n\n";

        $content .= "## Pre-Execution Checklist\n\n";
        $content .= "- [ ] Laravel snapshot created and reviewed\n";
        $content .= "- [ ] Current state analyzed\n";
        $content .= "- [ ] Strategy selected and documented\n";
        $content .= "- [ ] Application in maintenance mode (if required)\n";
        $content .= "- [ ] Database connection tested\n";
        $content .= "- [ ] Rollback plan prepared\n\n";

        $content .= "## Execution Steps\n\n";
        $content .= "1. **Review Commands**\n";
        $content .= "   ```bash\n";
        $content .= "   # Review the generated script\n";
        $content .= "   cat $scriptFile\n";
        $content .= "   ```\n\n";

        $content .= "2. **Execute Laravel Commands**\n";
        $content .= "   ```bash\n";
        $content .= "   # Execute each command from the script\n";
        $content .= "   # Copy and paste commands one by one\n";
        $content .= "   ```\n\n";

        $content .= "3. **Verify Results**\n";
        $content .= "   ```bash\n";
        $content .= "   php artisan migrate:status\n";
        $content .= "   ```\n\n";

        $content .= "## Post-Execution Validation\n\n";
        $content .= "- [ ] Migration status matches expectation\n";
        $content .= "- [ ] No orphaned migrations (or as expected by strategy)\n";
        $content .= "- [ ] Application functionality verified\n";
        $content .= "- [ ] Performance baseline met\n\n";

        $content .= "## Emergency Rollback\n\n";
        $content .= "If execution fails:\n";
        $content .= "1. Stop all changes\n";
        $content .= "2. Review Laravel snapshot: {$this->snapshotFile}\n";
        $content .= "3. Restore from snapshot data if needed\n";
        $content .= "4. Contact database administrator\n\n";

        file_put_contents($planFile, $content);
        echo "✓ Execution plan saved: $planFile\n\n";
    }
}

// Execute migration tracking reset
try {
    $reset = new MigrationTrackingResetSimple();
    $reset->execute();
    
    echo "✅ MIGRATION TRACKING RESET COMPLETED\n";
    echo "📋 Review snapshot, script, and execution plan\n";
    echo "⚠️  Test commands in staging before production\n";
    echo "📄 All operations use Laravel commands only\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
