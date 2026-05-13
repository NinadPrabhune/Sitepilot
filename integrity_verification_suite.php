<?php

/**
 * Comprehensive Database Integrity Verification Suite
 * 
 * This tool provides thorough verification of database integrity
 * after migration reconciliation processes.
 * 
 * USAGE: php integrity_verification_suite.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityVerificationSuite
{
    private $results = [];
    private $warnings = [];
    private $errors = [];
    private $criticalTables = [];

    public function __construct()
    {
        // Define critical tables that must be verified
        $this->criticalTables = [
            'users', 'migrations', 'work_spaces', 'projects',
            'purchase_orders', 'purchase_invoices', 'payments_module',
            'machineries', 'daily_progress_reports', 'suppliers'
        ];
    }

    /**
     * Execute comprehensive integrity verification
     */
    public function execute()
    {
        echo "=== COMPREHENSIVE DATABASE INTEGRITY VERIFICATION ===\n\n";

        try {
            $this->verifyTableStructures();
            $this->verifyForeignKeys();
            $this->verifyIndexes();
            $this->verifyDataConsistency();
            $this->verifyApplicationDependencies();
            $this->generateIntegrityReport();
        } catch (Exception $e) {
            $this->errors[] = "Critical verification error: " . $e->getMessage();
            $this->displayResults();
            exit(1);
        }
    }

    /**
     * Verify table structures
     */
    private function verifyTableStructures()
    {
        echo "STEP 1: VERIFYING TABLE STRUCTURES\n";
        echo str_repeat("=", 50) . "\n";

        $tables = DB::select('SHOW TABLES');
        $tableCount = count($tables);
        $verifiedTables = 0;
        $issues = [];

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            
            try {
                $columns = DB::select("DESCRIBE `{$tableName}`");
                $columnCount = count($columns);
                
                // Check for common issues
                $tableIssues = $this->checkTableIssues($tableName, $columns);
                
                if (empty($tableIssues)) {
                    $verifiedTables++;
                } else {
                    $issues[$tableName] = $tableIssues;
                }

                // Highlight critical tables
                if (in_array($tableName, $this->criticalTables)) {
                    $status = empty($tableIssues) ? '✓' : '⚠️';
                    echo "  {$status} {$tableName} ({$columnCount} columns)\n";
                }

            } catch (Exception $e) {
                $issues[$tableName] = ["Failed to describe table: " . $e->getMessage()];
            }
        }

        $this->results['table_structure'] = [
            'total_tables' => $tableCount,
            'verified_tables' => $verifiedTables,
            'issues' => $issues
        ];

        echo "\nTable Structure Summary:\n";
        echo "  Total tables: {$tableCount}\n";
        echo "  Verified: {$verifiedTables}\n";
        echo "  Issues: " . count($issues) . "\n\n";
    }

    /**
     * Check for table-specific issues
     */
    private function checkTableIssues($tableName, $columns)
    {
        $issues = [];

        // Check for missing primary keys
        $hasPrimaryKey = false;
        foreach ($columns as $column) {
            if ($column->Key === 'PRI') {
                $hasPrimaryKey = true;
                break;
            }
        }

        if (!$hasPrimaryKey && !in_array($tableName, ['pivot_tables', 'join_tables'])) {
            $issues[] = "Missing primary key";
        }

        // Check for problematic column types
        foreach ($columns as $column) {
            if (strpos(strtolower($column->Type), 'text') !== false && $column->Null === 'NO') {
                $issues[] = "Non-nullable TEXT column: {$column->Field}";
            }
        }

        // Check for timestamp columns without default values
        foreach ($columns as $column) {
            if (strpos(strtolower($column->Type), 'timestamp') !== false && 
                $column->Default === null && 
                $column->Null === 'NO' && 
                $column->Field !== 'updated_at') {
                $issues[] = "Non-nullable TIMESTAMP without default: {$column->Field}";
            }
        }

        return $issues;
    }

    /**
     * Verify foreign key constraints
     */
    private function verifyForeignKeys()
    {
        echo "STEP 2: VERIFYING FOREIGN KEY CONSTRAINTS\n";
        echo str_repeat("=", 50) . "\n";

        try {
            $constraints = DB::select("
                SELECT 
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            $verifiedConstraints = 0;
            $orphanedConstraints = [];

            foreach ($constraints as $constraint) {
                // Check if referenced table exists
                $refTableExists = Schema::hasTable($constraint->REFERENCED_TABLE_NAME);
                
                if ($refTableExists) {
                    $verifiedConstraints++;
                } else {
                    $orphanedConstraints[] = [
                        'table' => $constraint->TABLE_NAME,
                        'column' => $constraint->COLUMN_NAME,
                        'constraint' => $constraint->CONSTRAINT_NAME,
                        'missing_ref' => $constraint->REFERENCED_TABLE_NAME
                    ];
                }
            }

            $this->results['foreign_keys'] = [
                'total_constraints' => count($constraints),
                'verified_constraints' => $verifiedConstraints,
                'orphaned_constraints' => $orphanedConstraints
            ];

            echo "Foreign Key Summary:\n";
            echo "  Total constraints: " . count($constraints) . "\n";
            echo "  Verified: {$verifiedConstraints}\n";
            echo "  Orphaned: " . count($orphanedConstraints) . "\n";

            if (!empty($orphanedConstraints)) {
                echo "\n⚠️  Orphaned Foreign Keys:\n";
                foreach ($orphanedConstraints as $orphan) {
                    echo "  - {$orphan['table']}.{$orphan['column']} → {$orphan['missing_ref']}\n";
                }
            }

        } catch (Exception $e) {
            $this->warnings[] = "Could not verify foreign keys: " . $e->getMessage();
        }

        echo "\n";
    }

    /**
     * Verify indexes
     */
    private function verifyIndexes()
    {
        echo "STEP 3: VERIFYING INDEXES\n";
        echo str_repeat("=", 50) . "\n";

        $indexIssues = [];
        $criticalTableIndexes = [];

        foreach ($this->criticalTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            try {
                $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
                $indexNames = array_unique(array_column($indexes, 'Key_name'));
                
                // Check for missing indexes on commonly queried columns
                $missingIndexes = $this->checkMissingIndexes($tableName, $indexes);
                
                if (!empty($missingIndexes)) {
                    $indexIssues[$tableName] = $missingIndexes;
                }

                $criticalTableIndexes[$tableName] = [
                    'total' => count($indexes),
                    'unique' => count(array_filter($indexes, fn($i) => $i->Non_unique == 0)),
                    'index_names' => $indexNames
                ];

            } catch (Exception $e) {
                $this->warnings[] = "Could not analyze indexes for {$tableName}: " . $e->getMessage();
            }
        }

        $this->results['indexes'] = [
            'critical_tables' => $criticalTableIndexes,
            'issues' => $indexIssues
        ];

        echo "Index Analysis for Critical Tables:\n";
        foreach ($criticalTableIndexes as $table => $info) {
            $status = isset($indexIssues[$table]) ? '⚠️' : '✓';
            echo "  {$status} {$table}: {$info['total']} indexes ({$info['unique']} unique)\n";
        }

        if (!empty($indexIssues)) {
            echo "\n⚠️  Potential Missing Indexes:\n";
            foreach ($indexIssues as $table => $issues) {
                foreach ($issues as $issue) {
                    echo "  - {$table}: {$issue}\n";
                }
            }
        }

        echo "\n";
    }

    /**
     * Check for missing indexes
     */
    private function checkMissingIndexes($tableName, $indexes)
    {
        $missing = [];
        $indexedColumns = array_unique(array_column($indexes, 'Column_name'));

        // Common columns that should be indexed
        $commonIndexes = [
            'id', 'created_at', 'updated_at', 'user_id', 'project_id',
            'status', 'workspace_id', 'supplier_id', 'purchase_order_id'
        ];

        $columns = DB::select("DESCRIBE `{$tableName}`");
        $columnNames = array_column($columns, 'Field');

        foreach ($commonIndexes as $col) {
            if (in_array($col, $columnNames) && !in_array($col, $indexedColumns)) {
                $missing[] = "Consider adding index on {$col}";
            }
        }

        return $missing;
    }

    /**
     * Verify data consistency
     */
    private function verifyDataConsistency()
    {
        echo "STEP 4: VERIFYING DATA CONSISTENCY\n";
        echo str_repeat("=", 50) . "\n";

        $consistencyChecks = [];

        // Check for orphaned records in critical relationships
        $criticalRelationships = [
            'users' => ['workspace_id' => 'work_spaces'],
            'projects' => ['workspace_id' => 'work_spaces', 'user_id' => 'users'],
            'purchase_orders' => ['workspace_id' => 'work_spaces', 'supplier_id' => 'suppliers'],
            'daily_progress_reports' => ['machinery_id' => 'machineries', 'project_id' => 'projects']
        ];

        foreach ($criticalRelationships as $table => $relationships) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($relationships as $fkColumn => $refTable) {
                if (!Schema::hasTable($refTable)) {
                    continue;
                }

                try {
                    $orphanedCount = DB::table($table)
                        ->leftJoin($refTable, "{$table}.{$fkColumn}", "=", "{$refTable}.id")
                        ->whereNull("{$refTable}.id")
                        ->whereNotNull("{$table}.{$fkColumn}")
                        ->count();

                    if ($orphanedCount > 0) {
                        $consistencyChecks[] = [
                            'table' => $table,
                            'column' => $fkColumn,
                            'reference' => $refTable,
                            'orphaned_count' => $orphanedCount
                        ];
                    }
                } catch (Exception $e) {
                    $this->warnings[] = "Could not check consistency for {$table}.{$fkColumn}: " . $e->getMessage();
                }
            }
        }

        $this->results['data_consistency'] = $consistencyChecks;

        echo "Data Consistency Check:\n";
        if (empty($consistencyChecks)) {
            echo "  ✓ No orphaned records found in critical relationships\n";
        } else {
            echo "  ⚠️  Found orphaned records:\n";
            foreach ($consistencyChecks as $check) {
                echo "    - {$check['table']}.{$check['column']} → {$check['reference']} ({$check['orphaned_count']} records)\n";
            }
        }

        echo "\n";
    }

    /**
     * Verify application dependencies
     */
    private function verifyApplicationDependencies()
    {
        echo "STEP 5: VERIFYING APPLICATION DEPENDENCIES\n";
        echo str_repeat("=", 50) . "\n";

        $dependencyChecks = [];

        // Check Laravel-specific tables
        $laravelTables = ['users', 'migrations', 'password_resets', 'failed_jobs', 'personal_access_tokens'];
        foreach ($laravelTables as $table) {
            $dependencyChecks[$table] = [
                'exists' => Schema::hasTable($table),
                'required' => true
            ];
        }

        // Check application-specific critical tables
        $appTables = ['work_spaces', 'projects', 'machineries'];
        foreach ($appTables as $table) {
            $dependencyChecks[$table] = [
                'exists' => Schema::hasTable($table),
                'required' => true
            ];
        }

        $missingRequired = array_filter($dependencyChecks, fn($check) => $check['required'] && !$check['exists']);

        $this->results['application_dependencies'] = [
            'checks' => $dependencyChecks,
            'missing_required' => $missingRequired
        ];

        echo "Application Dependencies:\n";
        foreach ($dependencyChecks as $table => $check) {
            $status = $check['exists'] ? '✓' : '✗';
            echo "  {$status} {$table}\n";
        }

        if (!empty($missingRequired)) {
            echo "\n❌ Missing Required Tables:\n";
            foreach (array_keys($missingRequired) as $table) {
                echo "  - {$table}\n";
            }
        }

        echo "\n";
    }

    /**
     * Generate comprehensive integrity report
     */
    private function generateIntegrityReport()
    {
        echo "STEP 6: GENERATING INTEGRITY REPORT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $reportFile = __DIR__ . "/database_backups/integrity_report_{$timestamp}.json";

        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_warnings' => count($this->warnings),
                'total_errors' => count($this->errors),
                'overall_status' => empty($this->errors) ? 'PASS' : 'FAIL'
            ],
            'results' => $this->results,
            'warnings' => $this->warnings,
            'errors' => $this->errors
        ];

        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        echo "📄 Integrity report saved: $reportFile\n\n";

        // Display summary
        echo "=== INTEGRITY VERIFICATION SUMMARY ===\n";
        echo "Overall Status: " . $report['summary']['overall_status'] . "\n";
        echo "Warnings: " . $report['summary']['total_warnings'] . "\n";
        echo "Errors: " . $report['summary']['total_errors'] . "\n";

        if (!empty($this->warnings)) {
            echo "\n⚠️  WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "  - $warning\n";
            }
        }

        if (!empty($this->errors)) {
            echo "\n❌ ERRORS:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        if (empty($this->errors) && empty($this->warnings)) {
            echo "\n✅ ALL INTEGRITY CHECKS PASSED\n";
        } elseif (empty($this->errors)) {
            echo "\n✅ INTEGRITY CHECKS PASSED WITH WARNINGS\n";
        } else {
            echo "\n❌ INTEGRITY CHECKS FAILED\n";
        }

        echo "\n";
    }

    /**
     * Display results
     */
    private function displayResults()
    {
        echo "\n=== VERIFICATION RESULTS ===\n";
        
        if (!empty($this->errors)) {
            echo "❌ ERRORS:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        if (!empty($this->warnings)) {
            echo "\n⚠️  WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "  - $warning\n";
            }
        }
    }
}

// Execute integrity verification
try {
    $verification = new IntegrityVerificationSuite();
    $verification->execute();
    
    echo "🔍 COMPREHENSIVE INTEGRITY VERIFICATION COMPLETED\n";
    echo "📋 Review generated report for detailed findings\n";
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL VERIFICATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
