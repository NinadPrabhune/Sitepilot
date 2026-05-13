<?php

/**
 * Schema Snapshot Comparison Tool
 * 
 * This tool creates a comprehensive schema snapshot and compares
 * it against generated migrations to ensure safety before reset.
 * 
 * USAGE: php schema_snapshot_comparison.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaSnapshotComparison
{
    private $outputPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups/schema_comparison';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute schema snapshot comparison
     */
    public function execute()
    {
        echo "=== SCHEMA SNAPSHOT COMPARISON ===\n\n";

        $this->createCurrentSchemaSnapshot();
        $this->generateMigrationSchema();
        $this->compareSchemas();
        $this->generateSafetyReport();
    }

    /**
     * Create current schema snapshot
     */
    private function createCurrentSchemaSnapshot()
    {
        echo "STEP 1: CREATING CURRENT SCHEMA SNAPSHOT\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $schemaFile = $this->outputPath . "/current_schema_{$timestamp}.json";

        $tables = DB::select('SHOW TABLES');
        $schema = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => DB::connection()->getDatabaseName(),
            'tables' => []
        ];

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            echo "  Analyzing table: {$tableName}\n";
            
            $tableSchema = $this->analyzeTableSchema($tableName);
            $schema['tables'][$tableName] = $tableSchema;
        }

        file_put_contents($schemaFile, json_encode($schema, JSON_PRETTY_PRINT));
        
        echo "✓ Current schema snapshot: $schemaFile\n";
        echo "✓ Tables analyzed: " . count($schema['tables']) . "\n\n";
        
        $this->currentSchema = $schema;
    }

    /**
     * Analyze table schema
     */
    private function analyzeTableSchema($tableName)
    {
        try {
            $columns = DB::select("DESCRIBE `{$tableName}`");
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableName}'
            ");
            
            $rowCount = DB::table($tableName)->count();
            
            return [
                'columns' => array_map(function($col) {
                    return [
                        'name' => $col->Field,
                        'type' => $col->Type,
                        'nullable' => $col->Null === 'YES',
                        'default' => $col->Default,
                        'key' => $col->Key,
                        'extra' => $col->Extra
                    ];
                }, $columns),
                'indexes' => array_map(function($index) {
                    return [
                        'name' => $index->Key_name,
                        'columns' => explode(',', $index->Column_name),
                        'unique' => $index->Non_unique == 0,
                        'type' => $index->Index_type
                    ];
                }, $indexes),
                'constraints' => array_map(function($constraint) {
                    return [
                        'name' => $constraint->CONSTRAINT_NAME,
                        'type' => $constraint->CONSTRAINT_TYPE,
                        'column' => $constraint->COLUMN_NAME,
                        'references_table' => $constraint->REFERENCED_TABLE_NAME,
                        'references_column' => $constraint->REFERENCED_COLUMN_NAME
                    ];
                }, $constraints),
                'row_count' => $rowCount,
                'checksum' => $this->calculateTableChecksum($tableName)
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'columns' => [],
                'indexes' => [],
                'constraints' => [],
                'row_count' => 0,
                'checksum' => null
            ];
        }
    }

    /**
     * Calculate table checksum
     */
    private function calculateTableChecksum($tableName)
    {
        try {
            $data = DB::table($tableName)->limit(100)->get();
            $checksum = '';
            
            foreach ($data as $row) {
                $checksum .= md5(json_encode((array)$row));
            }
            
            return md5($checksum);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate migration schema
     */
    private function generateMigrationSchema()
    {
        echo "STEP 2: GENERATING MIGRATION SCHEMA\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $migrationSchemaFile = $this->outputPath . "/migration_schema_{$timestamp}.json";

        // Get migration files
        $migrationFiles = glob(__DIR__ . '/database/migrations/*.php');
        $migrationSchema = [
            'timestamp' => date('Y-m-d H:i:s'),
            'migrations' => []
        ];

        foreach ($migrationFiles as $file) {
            $migrationName = basename($file, '.php');
            echo "  Analyzing migration: $migrationName\n";
            
            $migrationContent = file_get_contents($file);
            $parsedMigration = $this->parseMigrationFile($migrationContent);
            
            if ($parsedMigration) {
                $migrationSchema['migrations'][$migrationName] = $parsedMigration;
            }
        }

        file_put_contents($migrationSchemaFile, json_encode($migrationSchema, JSON_PRETTY_PRINT));
        
        echo "✓ Migration schema: $migrationSchemaFile\n";
        echo "✓ Migrations analyzed: " . count($migrationSchema['migrations']) . "\n\n";
        
        $this->migrationSchema = $migrationSchema;
    }

    /**
     * Parse migration file
     */
    private function parseMigrationFile($content)
    {
        try {
            // Extract table operations from migration content
            $operations = [];
            
            // Look for Schema::create
            if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $tableName) {
                    $operations[] = [
                        'type' => 'create_table',
                        'table' => $tableName,
                        'content' => $this->extractTableDefinition($content, $tableName)
                    ];
                }
            }
            
            // Look for Schema::table with addColumn
            if (preg_match_all('/Schema::table\s*\(\s*[\'"]([^\'"]+)[\'"]\s*->.*?addColumn\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $operations[] = [
                        'type' => 'add_column',
                        'table' => $matches[1][$i],
                        'column' => $matches[2][$i]
                    ];
                }
            }
            
            // Look for dropColumn
            if (preg_match_all('/Schema::table\s*\(\s*[\'"]([^\'"]+)[\'"]\s*->.*?dropColumn\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $operations[] = [
                        'type' => 'drop_column',
                        'table' => $matches[1][$i],
                        'column' => $matches[2][$i]
                    ];
                }
            }
            
            return [
                'operations' => $operations,
                'raw_content' => $content
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract table definition
     */
    private function extractTableDefinition($content, $tableName)
    {
        // Look for the table definition in the migration
        if (preg_match('/Schema::create\s*\(\s*[\'"]' . preg_quote($tableName, '/') . '[\'"]\s*,\s*function\s*\(\s*Blueprint\s*\$table\s*\)\s*{(.*?)}/s', $content, $matches)) {
            return $matches[1] ?? '';
        }
        
        return '';
    }

    /**
     * Compare schemas
     */
    private function compareSchemas()
    {
        echo "STEP 3: COMPARING SCHEMAS\n";
        echo str_repeat("=", 50) . "\n";

        $differences = [];
        $criticalTables = ['users', 'work_spaces', 'projects', 'machineries', 'purchase_orders', 'purchase_invoices', 'payments_module', 'daily_progress_reports', 'suppliers'];

        foreach ($this->currentSchema['tables'] as $tableName => $currentTable) {
            $migrationTable = $this->findMigrationForTable($tableName);
            
            if ($migrationTable) {
                $diff = $this->compareTableWithMigration($tableName, $currentTable, $migrationTable);
                if (!empty($diff)) {
                    $differences[$tableName] = $diff;
                }
            } else {
                // Table exists but no migration found
                $differences[$tableName] = [
                    'status' => 'no_migration',
                    'severity' => in_array($tableName, $criticalTables) ? 'critical' : 'high',
                    'message' => "Table exists but no migration file found"
                ];
            }
        }

        $this->differences = $differences;
        
        echo "✓ Schema comparison completed\n";
        echo "✓ Differences found: " . count($differences) . "\n\n";
    }

    /**
     * Find migration for table
     */
    private function findMigrationForTable($tableName)
    {
        foreach ($this->migrationSchema['migrations'] as $migrationName => $migration) {
            if ($migration && isset($migration['operations'])) {
                foreach ($migration['operations'] as $operation) {
                    if (($operation['type'] === 'create_table' && $operation['table'] === $tableName) ||
                        ($operation['type'] === 'add_column' && $operation['table'] === $tableName) ||
                        ($operation['type'] === 'drop_column' && $operation['table'] === $tableName)) {
                        return $migration;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Compare table with migration
     */
    private function compareTableWithMigration($tableName, $currentTable, $migrationTable)
    {
        $differences = [];
        
        // Compare columns
        if (isset($currentTable['columns']) && isset($migrationTable['operations'])) {
            $migrationColumns = $this->extractColumnsFromMigration($migrationTable, $tableName);
            
            if ($migrationColumns) {
                $columnDiff = $this->compareColumns($currentTable['columns'], $migrationColumns);
                if (!empty($columnDiff)) {
                    $differences['columns'] = $columnDiff;
                }
            }
        }
        
        // Compare indexes
        if (isset($currentTable['indexes'])) {
            $migrationIndexes = $this->extractIndexesFromMigration($migrationTable, $tableName);
            if ($migrationIndexes) {
                $indexDiff = $this->compareIndexes($currentTable['indexes'], $migrationIndexes);
                if (!empty($indexDiff)) {
                    $differences['indexes'] = $indexDiff;
                }
            }
        }
        
        if (!empty($differences)) {
            $differences['status'] = 'mismatch';
            $differences['severity'] = $this->assessDifferenceSeverity($differences);
            $differences['current_table'] = $currentTable;
            $differences['migration_table'] = $migrationTable;
        } else {
            $differences['status'] = 'match';
        }
        
        return $differences;
    }

    /**
     * Extract columns from migration
     */
    private function extractColumnsFromMigration($migrationTable, $tableName)
    {
        // This is a simplified extraction - in practice, you'd need more sophisticated parsing
        $columns = [];
        
        foreach ($migrationTable['operations'] as $operation) {
            if ($operation['type'] === 'create_table' && $operation['table'] === $tableName) {
                // Parse the table definition to extract columns
                preg_match_all('/\$table->([a-zA-Z]+)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $operation['content'], $matches);
                for ($i = 0; $i < count($matches[1]); $i++) {
                    if ($matches[1][$i] === 'string' || $matches[1][$i] === 'text' || $matches[1][$i] === 'integer') {
                        $columns[] = [
                            'name' => $matches[2][$i],
                            'type' => $matches[1][$i]
                        ];
                    }
                }
            } elseif ($operation['type'] === 'add_column' && $operation['table'] === $tableName) {
                $columns[] = [
                    'name' => $operation['column'],
                    'type' => 'unknown'
                ];
            }
        }
        
        return $columns;
    }

    /**
     * Extract indexes from migration
     */
    private function extractIndexesFromMigration($migrationTable, $tableName)
    {
        // Simplified index extraction
        $indexes = [];
        
        foreach ($migrationTable['operations'] as $operation) {
            if ($operation['type'] === 'create_table' && $operation['table'] === $tableName) {
                preg_match_all('/\$table->index\s*\(\s*\[([^\]]+)\]/', $operation['content'], $matches);
                if (isset($matches[1])) {
                    $indexColumns = array_map('trim', explode(',', $matches[1]));
                    $indexes[] = [
                        'columns' => $indexColumns,
                        'unique' => false,
                        'type' => 'btree'
                    ];
                }
            }
        }
        
        return $indexes;
    }

    /**
     * Compare columns
     */
    private function compareColumns($currentColumns, $migrationColumns)
    {
        $differences = [];
        
        $currentColumnNames = array_column($currentColumns, 'name');
        $migrationColumnNames = array_column($migrationColumns, 'name');
        
        $missingInMigration = array_diff($currentColumnNames, $migrationColumnNames);
        $extraInMigration = array_diff($migrationColumnNames, $currentColumnNames);
        
        if (!empty($missingInMigration)) {
            $differences['missing_in_migration'] = $missingInMigration;
        }
        
        if (!empty($extraInMigration)) {
            $differences['extra_in_migration'] = $extraInMigration;
        }
        
        return $differences;
    }

    /**
     * Compare indexes
     */
    private function compareIndexes($currentIndexes, $migrationIndexes)
    {
        $differences = [];
        
        // Simplified comparison
        if (count($currentIndexes) !== count($migrationIndexes)) {
            $differences['count_mismatch'] = [
                'current' => count($currentIndexes),
                'migration' => count($migrationIndexes)
            ];
        }
        
        return $differences;
    }

    /**
     * Assess difference severity
     */
    private function assessDifferenceSeverity($differences)
    {
        $severity = 'low';
        
        if (isset($differences['columns'])) {
            if (!empty($differences['columns']['missing_in_migration']) || !empty($differences['columns']['extra_in_migration'])) {
                $severity = 'high';
            }
        }
        
        if (isset($differences['indexes'])) {
            $severity = 'medium';
        }
        
        return $severity;
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

        $criticalIssues = array_filter($this->differences, function($diff) {
            return ($diff['severity'] ?? 'low') === 'critical';
        });
        
        $highIssues = array_filter($this->differences, function($diff) {
            return ($diff['severity'] ?? 'low') === 'high';
        });

        $content = "# Schema Comparison Safety Report\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "## Executive Summary\n\n";
        $content .= "- **Total Tables Analyzed**: " . count($this->currentSchema['tables']) . "\n";
        $content .= "- **Tables with Differences**: " . count($this->differences) . "\n";
        $content .= "- **Critical Issues**: " . count($criticalIssues) . "\n";
        $content .= "- **High Priority Issues**: " . count($highIssues) . "\n\n";

        $content .= "## 🚨 CRITICAL FINDINGS\n\n";
        
        if (!empty($criticalIssues)) {
            $content .= "The following tables have CRITICAL schema mismatches:\n\n";
            
            foreach ($criticalIssues as $tableName => $diff) {
                $content .= "### {$tableName}\n";
                $content .= "**Status**: {$diff['status']}\n";
                $content .= "**Severity**: {$diff['severity']}\n";
                $content .= "**Issue**: {$diff['message']}\n\n";
            }
            
            $content .= "## ⚠️ PRODUCTION EXECUTION BLOCKED\n\n";
            $content .= "**DO NOT PROCEED with migration reset until critical issues are resolved.**\n\n";
            $content .= "Required actions:\n";
            $content .= "1. Review and fix migration files for critical tables\n";
            $content .= "2. Ensure schema definitions match current database\n";
            $content .= "3. Test in staging environment\n";
            $content .= "4. Re-run this comparison tool\n\n";
        } else {
            $content .= "✅ **No critical schema mismatches detected**\n\n";
            $content .= "Schema appears to be in a safe state for migration execution.\n\n";
        }

        $content .= "## Detailed Differences\n\n";
        
        foreach ($this->differences as $tableName => $diff) {
            $content .= "### {$tableName}\n";
            $content .= "**Status**: {$diff['status']}\n";
            $content .= "**Severity**: {$diff['severity']}\n";
            
            if (isset($diff['columns'])) {
                $content .= "**Column Differences**:\n";
                if (isset($diff['columns']['missing_in_migration'])) {
                    $content .= "- Missing in migration: " . implode(', ', $diff['columns']['missing_in_migration']) . "\n";
                }
                if (isset($diff['columns']['extra_in_migration'])) {
                    $content .= "- Extra in migration: " . implode(', ', $diff['columns']['extra_in_migration']) . "\n";
                }
            }
            
            if (isset($diff['indexes'])) {
                $content .= "**Index Differences**: " . json_encode($diff['indexes']) . "\n";
            }
            
            $content .= "\n";
        }

        $content .= "## Recommendations\n\n";
        
        if (!empty($criticalIssues)) {
            $content .= "1. **IMMEDIATE**: Fix critical schema mismatches\n";
            $content .= "2. **HIGH PRIORITY**: Review and update migration files\n";
            $content .= "3. **VALIDATION**: Re-run schema comparison\n";
            $content .= "4. **TESTING**: Validate in staging environment\n";
        } else {
            $content .= "1. **SAFE**: Proceed with migration tracking reset\n";
            $content .= "2. **MONITOR**: Use generated migration scripts carefully\n";
            $content .= "3. **VALIDATE**: Verify results after each step\n";
        }

        $content .= "\n## Next Steps\n\n";
        
        if (!empty($criticalIssues)) {
            $content .= "```bash\n";
            $content .= "# STOP - Do not proceed with migration reset\n";
            $content .= "# Fix critical issues first, then re-run:\n";
            $content .= "php schema_snapshot_comparison.php\n";
            $content .= "```\n";
        } else {
            $content .= "```bash\n";
            $content .= "# SAFE to proceed with migration reset:\n";
            $content .= "php migration_tracking_reset_simple.php\n";
            $content .= "```\n";
        }

        file_put_contents($reportFile, $content);
        
        echo "✓ Safety report: $reportFile\n\n";
        
        if (!empty($criticalIssues)) {
            echo "🚨 CRITICAL ISSUES DETECTED - EXECUTION BLOCKED\n";
            echo "📋 Review safety report: $reportFile\n";
            echo "⚠️  Fix critical issues before proceeding\n\n";
        } else {
            echo "✅ NO CRITICAL ISSUES - SAFE TO PROCEED\n";
            echo "📋 Review safety report: $reportFile\n";
            echo "🚀 Ready for migration tracking reset\n\n";
        }
    }
}

// Execute schema snapshot comparison
try {
    $comparison = new SchemaSnapshotComparison();
    $comparison->execute();
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
