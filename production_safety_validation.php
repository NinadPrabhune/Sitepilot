<?php

/**
 * Production Safety Validation Tool
 * 
 * This tool performs critical safety checks before any
 * migration operations to prevent data corruption.
 * 
 * USAGE: php production_safety_validation.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class ProductionSafetyValidation
{
    private $outputPath;
    private $criticalTables = [
        'users', 'work_spaces', 'projects', 'machineries', 
        'purchase_orders', 'purchase_invoices', 'payments_module',
        'daily_progress_reports', 'suppliers'
    ];

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups/safety_validation';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute production safety validation
     */
    public function execute()
    {
        echo "=== PRODUCTION SAFETY VALIDATION ===\n\n";

        $this->validateDatabaseState();
        $this->checkCriticalTables();
        $this->validateDataIntegrity();
        $this->generateSafetyReport();
    }

    /**
     * Validate database state
     */
    private function validateDatabaseState()
    {
        echo "STEP 1: VALIDATING DATABASE STATE\n";
        echo str_repeat("=", 50) . "\n";

        try {
            // Check database connection
            $databaseName = DB::connection()->getDatabaseName();
            echo "✓ Database connection: {$databaseName}\n";

            // Check migrations table exists
            $migrationsExist = Schema::hasTable('migrations');
            echo $migrationsExist ? "✓ Migrations table exists\n" : "❌ Migrations table missing\n";

            // Get basic stats
            $tableCount = count(DB::select('SHOW TABLES'));
            $migrationCount = DB::table('migrations')->count();
            
            echo "✓ Tables found: {$tableCount}\n";
            echo "✓ Migrations recorded: {$migrationCount}\n";

            $this->validationResults['database_state'] = [
                'database_name' => $databaseName,
                'migrations_table_exists' => $migrationsExist,
                'table_count' => $tableCount,
                'migration_count' => $migrationCount,
                'status' => 'validated'
            ];

        } catch (Exception $e) {
            echo "❌ Database validation failed: " . $e->getMessage() . "\n";
            $this->validationResults['database_state'] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }

        echo "\n";
    }

    /**
     * Check critical tables
     */
    private function checkCriticalTables()
    {
        echo "STEP 2: CHECKING CRITICAL TABLES\n";
        echo str_repeat("=", 50) . "\n";

        $criticalIssues = [];

        foreach ($this->criticalTables as $tableName) {
            echo "Checking table: {$tableName}\n";
            
            try {
                $exists = Schema::hasTable($tableName);
                
                if (!$exists) {
                    $criticalIssues[] = [
                        'table' => $tableName,
                        'issue' => 'missing',
                        'severity' => 'critical'
                    ];
                    echo "  ❌ Table missing\n";
                } else {
                    // Check if table has data
                    $rowCount = DB::table($tableName)->count();
                    echo "  ✓ Table exists ({$rowCount} rows)\n";
                    
                    // Check for basic columns
                    $columns = DB::select("DESCRIBE `{$tableName}`");
                    $hasId = false;
                    foreach ($columns as $column) {
                        if ($column->Field === 'id') {
                            $hasId = true;
                            break;
                        }
                    }
                    
                    if (!$hasId) {
                        $criticalIssues[] = [
                            'table' => $tableName,
                            'issue' => 'no_id_column',
                            'severity' => 'high'
                        ];
                        echo "  ⚠️  No ID column found\n";
                    }
                }
            } catch (Exception $e) {
                $criticalIssues[] = [
                    'table' => $tableName,
                    'issue' => 'error',
                    'severity' => 'critical',
                    'error' => $e->getMessage()
                ];
                echo "  ❌ Error checking table: " . $e->getMessage() . "\n";
            }
        }

        $this->validationResults['critical_tables'] = [
            'checked' => count($this->criticalTables),
            'issues' => $criticalIssues,
            'status' => empty($criticalIssues) ? 'passed' : 'failed'
        ];

        echo "\n";
    }

    /**
     * Validate data integrity
     */
    private function validateDataIntegrity()
    {
        echo "STEP 3: VALIDATING DATA INTEGRITY\n";
        echo str_repeat("=", 50) . "\n";

        $integrityIssues = [];

        try {
            // Check for orphaned records in key tables
            if (Schema::hasTable('users') && Schema::hasTable('work_spaces')) {
                $orphanedUsers = DB::table('users')
                    ->leftJoin('work_spaces', 'users.id', '=', 'work_spaces.user_id')
                    ->whereNull('work_spaces.user_id')
                    ->count();

                if ($orphanedUsers > 0) {
                    $integrityIssues[] = [
                        'type' => 'orphaned_users',
                        'count' => $orphanedUsers,
                        'severity' => 'medium'
                    ];
                    echo "  ⚠️  Found {$orphanedUsers} orphaned users\n";
                } else {
                    echo "  ✓ No orphaned users found\n";
                }
            }

            // Check migration consistency
            $migrationFiles = glob(__DIR__ . '/database/migrations/*.php');
            $dbMigrations = DB::table('migrations')->pluck('migration')->toArray();
            $fileMigrations = array_map(function($file) {
                return basename($file, '.php');
            }, $migrationFiles);

            $orphanedMigrations = array_diff($dbMigrations, $fileMigrations);
            $missingMigrations = array_diff($fileMigrations, $dbMigrations);

            if (!empty($orphanedMigrations)) {
                $integrityIssues[] = [
                    'type' => 'orphaned_migrations',
                    'count' => count($orphanedMigrations),
                    'severity' => 'high'
                ];
                echo "  ⚠️  Found " . count($orphanedMigrations) . " orphaned migrations\n";
            }

            if (!empty($missingMigrations)) {
                $integrityIssues[] = [
                    'type' => 'missing_migrations',
                    'count' => count($missingMigrations),
                    'severity' => 'medium'
                ];
                echo "  ⚠️  Found " . count($missingMigrations) . " missing migrations\n";
            }

            $this->validationResults['data_integrity'] = [
                'migration_files' => count($migrationFiles),
                'db_migrations' => count($dbMigrations),
                'orphaned_migrations' => count($orphanedMigrations),
                'missing_migrations' => count($missingMigrations),
                'issues' => $integrityIssues,
                'status' => empty($integrityIssues) ? 'passed' : 'warning'
            ];

        } catch (Exception $e) {
            echo "❌ Data integrity validation failed: " . $e->getMessage() . "\n";
            $this->validationResults['data_integrity'] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }

        echo "\n";
    }

    /**
     * Generate safety report
     */
    private function generateSafetyReport()
    {
        echo "STEP 4: GENERATING SAFETY REPORT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $reportFile = $this->outputPath . "/safety_report_{$timestamp}.md";

        $overallStatus = 'safe';
        $blockExecution = false;

        // Determine overall status
        if (isset($this->validationResults['database_state']['error']) ||
            isset($this->validationResults['data_integrity']['error'])) {
            $overallStatus = 'error';
            $blockExecution = true;
        } elseif ($this->validationResults['critical_tables']['status'] === 'failed') {
            $overallStatus = 'critical';
            $blockExecution = true;
        } elseif ($this->validationResults['data_integrity']['status'] === 'warning') {
            $overallStatus = 'warning';
        }

        $content = "# Production Safety Validation Report\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "## Overall Status: {$overallStatus}\n\n";

        if ($blockExecution) {
            $content .= "🚨 **PRODUCTION EXECUTION BLOCKED**\n\n";
            $content .= "Critical issues detected. DO NOT proceed with migration operations.\n\n";
        } else {
            $content .= "✅ **PRODUCTION EXECUTION SAFE**\n\n";
            $content .= "No critical issues detected. System appears safe for migration operations.\n\n";
        }

        $content .= "## Validation Results\n\n";

        // Database state
        if (isset($this->validationResults['database_state'])) {
            $content .= "### Database State\n\n";
            $dbState = $this->validationResults['database_state'];
            $content .= "- **Database**: {$dbState['database_name']}\n";
            $content .= "- **Tables**: {$dbState['table_count']}\n";
            $content .= "- **Migrations**: {$dbState['migration_count']}\n";
            $content .= "- **Status**: {$dbState['status']}\n\n";
        }

        // Critical tables
        if (isset($this->validationResults['critical_tables'])) {
            $content .= "### Critical Tables\n\n";
            $criticalTables = $this->validationResults['critical_tables'];
            $content .= "- **Checked**: {$criticalTables['checked']}\n";
            $content .= "- **Issues**: {$criticalTables['issues']} - Status: {$criticalTables['status']}\n\n";
        }

        // Data integrity
        if (isset($this->validationResults['data_integrity'])) {
            $content .= "### Data Integrity\n\n";
            $dataIntegrity = $this->validationResults['data_integrity'];
            $content .= "- **Migration Files**: {$dataIntegrity['migration_files']}\n";
            $content .= "- **DB Migrations**: {$dataIntegrity['db_migrations']}\n";
            $content .= "- **Orphaned Migrations**: {$dataIntegrity['orphaned_migrations']}\n";
            $content .= "- **Missing Migrations**: {$dataIntegrity['missing_migrations']}\n";
            $content .= "- **Status**: {$dataIntegrity['status']}\n\n";
        }

        $content .= "## Recommendations\n\n";

        if ($blockExecution) {
            $content .= "### 🚨 IMMEDIATE ACTIONS REQUIRED\n\n";
            $content .= "1. **STOP** all migration operations\n";
            $content .= "2. **FIX** critical issues identified above\n";
            $content .= "3. **RE-RUN** this validation tool\n";
            $content .= "4. **REVIEW** generated reports before proceeding\n\n";
        } else {
            $content .= "### ✅ SAFE TO PROCEED\n\n";
            $content .= "1. **REVIEW** detailed validation results\n";
            $content .= "2. **PROCEED** with migration tracking reset\n";
            $content .= "3. **MONITOR** during migration execution\n";
            $content .= "4. **VALIDATE** results after each step\n\n";
        }

        $content .= "## Next Steps\n\n";
        
        if ($blockExecution) {
            $content .= "```bash\n";
            $content .= "# STOP - Do not proceed\n";
            $content .= "echo \"Production execution blocked - see safety report\"\n";
            $content .= "```\n";
        } else {
            $content .= "```bash\n";
            $content .= "# SAFE to proceed\n";
            $content .= "php migration_tracking_reset_simple.php\n";
            $content .= "```\n";
        }

        file_put_contents($reportFile, $content);

        echo "✓ Safety report: $reportFile\n";
        echo "✓ Overall status: {$overallStatus}\n\n";

        if ($blockExecution) {
            echo "🚨 PRODUCTION EXECUTION BLOCKED\n";
            echo "📋 Review safety report: $reportFile\n";
            echo "⚠️  Fix critical issues before proceeding\n";
        } else {
            echo "✅ PRODUCTION EXECUTION SAFE\n";
            echo "📋 Review safety report: $reportFile\n";
            echo "🚀 Safe to proceed with migration tracking reset\n";
        }
    }
}

// Execute production safety validation
try {
    $validation = new ProductionSafetyValidation();
    $validation->execute();
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
