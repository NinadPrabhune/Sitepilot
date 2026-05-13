<?php

/**
 * Orphaned Migration Detailed Documentation Tool - Enhanced
 * 
 * This tool provides detailed documentation of orphaned migrations
 * including exact columns, indexes, and constraints modified.
 * 
 * USAGE: php orphaned_migration_detailed_documentation.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class OrphanedMigrationDetailedDocumentation
{
    private $outputPath;
    private $riskCategories = [
        'high' => [],    // Schema-changing, must reconstruct
        'medium' => [],  // Important but can document
        'low' => []      // Minor, document only
    ];

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute detailed documentation
     */
    public function execute()
    {
        echo "=== ORPHANED MIGRATION DETAILED DOCUMENTATION ===\n\n";

        $this->identifyOrphanedMigrations();
        $this->analyzeDetailedChanges();
        $this->generateDetailedDocumentation();
        $this->createRollbackReferences();
    }

    /**
     * Identify orphaned migrations
     */
    private function identifyOrphanedMigrations()
    {
        echo "STEP 1: IDENTIFYING ORPHANED MIGRATIONS\n";
        echo str_repeat("=", 50) . "\n";

        // Get all migration files
        $migrationFiles = $this->getMigrationFiles();
        
        // Get migrations in database
        $dbMigrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->get()
            ->keyBy('migration');

        // Find orphaned (in DB but not in files)
        $orphaned = [];
        foreach ($dbMigrations as $migration => $record) {
            if (!in_array($migration, $migrationFiles)) {
                $orphaned[$migration] = $record;
            }
        }

        echo "Migration files: " . count($migrationFiles) . "\n";
        echo "Database migrations: " . $dbMigrations->count() . "\n";
        echo "Orphaned migrations: " . count($orphaned) . "\n\n";

        $this->orphanedMigrations = $orphaned;
    }

    /**
     * Analyze detailed changes for orphaned migrations
     */
    private function analyzeDetailedChanges()
    {
        echo "STEP 2: ANALYZING DETAILED CHANGES\n";
        echo str_repeat("=", 50) . "\n";

        foreach ($this->orphanedMigrations as $migration => $record) {
            $risk = $this->assessRisk($migration, $record);
            $details = $this->extractMigrationDetails($migration);
            
            $this->riskCategories[$risk][] = [
                'migration' => $migration,
                'batch' => $record->batch,
                'date' => $record->created_at ?? 'Unknown',
                'reason' => $this->getRiskReason($migration, $risk),
                'details' => $details
            ];
        }

        echo "Risk Categorization:\n";
        echo "High Risk (Reconstruct): " . count($this->riskCategories['high']) . "\n";
        echo "Medium Risk (Consider): " . count($this->riskCategories['medium']) . "\n";
        echo "Low Risk (Document): " . count($this->riskCategories['low']) . "\n\n";
    }

    /**
     * Extract migration details from name and current schema
     */
    private function extractMigrationDetails($migration)
    {
        $details = [
            'operation_type' => 'unknown',
            'target_table' => 'unknown',
            'affected_columns' => [],
            'affected_indexes' => [],
            'affected_constraints' => [],
            'estimated_impact' => 'low'
        ];

        // Parse migration name for operation type
        if (preg_match('/create_(.+)_table/', $migration, $matches)) {
            $details['operation_type'] = 'create_table';
            $details['target_table'] = $matches[1];
            $details['estimated_impact'] = 'high';
            $details = $this->analyzeTableStructure($matches[1], $details);
        }
        elseif (preg_match('/add_(.+)_to_(.+)/', $migration, $matches)) {
            $details['operation_type'] = 'add_columns';
            $details['target_table'] = $matches[2];
            $details['affected_columns'] = explode('_', $matches[1]);
            $details['estimated_impact'] = 'medium';
            $details = $this->analyzeTableColumns($matches[2], $details);
        }
        elseif (preg_match('/drop_(.+)_from_(.+)/', $migration, $matches)) {
            $details['operation_type'] = 'drop_columns';
            $details['target_table'] = $matches[2];
            $details['affected_columns'] = explode('_', $matches[1]);
            $details['estimated_impact'] = 'high';
        }
        elseif (preg_match('/rename_(.+)_to_(.+)/', $migration, $matches)) {
            $details['operation_type'] = 'rename_table';
            $details['target_table'] = $matches[2];
            $details['estimated_impact'] = 'high';
        }
        elseif (preg_match('/(add|drop)_(index|foreign|constraint|unique)/', $migration, $matches)) {
            $details['operation_type'] = $matches[1] . '_' . $matches[2];
            $details['estimated_impact'] = 'medium';
            $details = $this->analyzeTableIndexes($details);
        }

        return $details;
    }

    /**
     * Analyze table structure for create table operations
     */
    private function analyzeTableStructure($tableName, $details)
    {
        try {
            $columns = DB::select("DESCRIBE `{$tableName}`");
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableName}'
            ");

            $details['affected_columns'] = array_map(function($col) {
                return [
                    'name' => $col->Field,
                    'type' => $col->Type,
                    'nullable' => $col->Null === 'YES',
                    'default' => $col->Default,
                    'key' => $col->Key
                ];
            }, $columns);

            $details['affected_indexes'] = array_map(function($index) {
                return [
                    'name' => $index->Key_name,
                    'columns' => explode(',', $index->Column_name),
                    'unique' => $index->Non_unique == 0,
                    'type' => $index->Index_type
                ];
            }, $indexes);

            $details['affected_constraints'] = array_map(function($constraint) {
                return [
                    'name' => $constraint->CONSTRAINT_NAME,
                    'type' => $constraint->CONSTRAINT_TYPE,
                    'column' => $constraint->COLUMN_NAME,
                    'references_table' => $constraint->REFERENCED_TABLE_NAME,
                    'references_column' => $constraint->REFERENCED_COLUMN_NAME
                ];
            }, $constraints);

        } catch (Exception $e) {
            $details['error'] = 'Could not analyze table structure: ' . $e->getMessage();
        }

        return $details;
    }

    /**
     * Analyze table columns for add/drop operations
     */
    private function analyzeTableColumns($tableName, $details)
    {
        try {
            $columns = DB::select("DESCRIBE `{$tableName}`");
            
            $details['current_columns'] = array_map(function($col) {
                return [
                    'name' => $col->Field,
                    'type' => $col->Type,
                    'nullable' => $col->Null === 'YES',
                    'default' => $col->Default,
                    'key' => $col->Key
                ];
            }, $columns);

        } catch (Exception $e) {
            $details['error'] = 'Could not analyze table columns: ' . $e->getMessage();
        }

        return $details;
    }

    /**
     * Analyze table indexes
     */
    private function analyzeTableIndexes($details)
    {
        // This is a placeholder since we don't know the target table
        // In a real implementation, you'd parse the migration name more carefully
        $details['note'] = 'Index analysis requires parsing migration file content';
        return $details;
    }

    /**
     * Assess risk level
     */
    private function assessRisk($migration, $record)
    {
        // High risk indicators
        if (preg_match('/create_.*_table|add_.*_to_|drop_.*_from_/', $migration)) {
            return 'high';
        }

        if (preg_match('/rename_|modify_|alter_/', $migration)) {
            return 'high';
        }

        // Medium risk indicators
        if (preg_match('/index|constraint|foreign/', $migration)) {
            return 'medium';
        }

        if (preg_match('/update_|change_/', $migration)) {
            return 'medium';
        }

        // Recent migrations are higher risk
        $datePart = substr($migration, 0, 10);
        try {
            $migrationDate = DateTime::createFromFormat('Y_m_d', $datePart);
            if ($migrationDate) {
                $daysAgo = (new DateTime())->diff($migrationDate)->days;
                if ($daysAgo < 30) {
                    return 'medium';
                }
            }
        } catch (Exception $e) {
            // Invalid date format, assume low risk
        }

        // Low risk by default
        return 'low';
    }

    /**
     * Get risk reason
     */
    private function getRiskReason($migration, $risk)
    {
        $reasons = [
            'high' => 'Schema-changing operation, affects rollback capability and data integrity',
            'medium' => 'Important structural change, may affect future deployments and performance',
            'low' => 'Minor change or data fix, documentation sufficient for stability'
        ];

        return $reasons[$risk] ?? 'Unknown risk level';
    }

    /**
     * Generate detailed documentation
     */
    private function generateDetailedDocumentation()
    {
        echo "STEP 3: GENERATING DETAILED DOCUMENTATION\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $docFile = $this->outputPath . "/orphaned_migrations_detailed_{$timestamp}.md";

        $content = "# Orphaned Migrations Detailed Documentation\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "## Executive Summary\n\n";
        $content .= "- **High Risk (Reconstruct)**: " . count($this->riskCategories['high']) . " migrations\n";
        $content .= "- **Medium Risk (Consider)**: " . count($this->riskCategories['medium']) . " migrations\n";
        $content .= "- **Low Risk (Document)**: " . count($this->riskCategories['low']) . " migrations\n\n";

        // High risk details with full schema analysis
        if (!empty($this->riskCategories['high'])) {
            $content .= "## High Risk Migrations (Reconstruct)\n\n";
            foreach ($this->riskCategories['high'] as $item) {
                $content .= $this->formatDetailedMigration($item, 'HIGH');
            }
        }

        // Medium risk details
        if (!empty($this->riskCategories['medium'])) {
            $content .= "## Medium Risk Migrations (Consider)\n\n";
            foreach ($this->riskCategories['medium'] as $item) {
                $content .= $this->formatDetailedMigration($item, 'MEDIUM');
            }
        }

        // Low risk summary
        if (!empty($this->riskCategories['low'])) {
            $content .= "## Low Risk Migrations (Document Only)\n\n";
            $content .= "Count: " . count($this->riskCategories['low']) . "\n";
            $content .= "These can be safely documented and ignored for future development.\n\n";
            
            foreach ($this->riskCategories['low'] as $item) {
                $content .= "### {$item['migration']}\n";
                $content .= "- **Batch**: {$item['batch']}\n";
                $content .= "- **Date**: {$item['date']}\n";
                $content .= "- **Reason**: {$item['reason']}\n";
                $content .= "- **Action**: Document and ignore\n\n";
            }
        }

        $content .= "## Recommendations\n\n";
        $content .= "1. **High Risk**: Create stub migrations with exact rollback logic\n";
        $content .= "2. **Medium Risk**: Document thoroughly, consider stubs based on complexity\n";
        $content .= "3. **Low Risk**: Document and ignore for future development\n\n";

        file_put_contents($docFile, $content);
        echo "✓ Detailed documentation saved: $docFile\n\n";
    }

    /**
     * Format detailed migration information
     */
    private function formatDetailedMigration($item, $riskLevel)
    {
        $content = "### {$item['migration']}\n\n";
        $content .= "- **Batch**: {$item['batch']}\n";
        $content .= "- **Date**: {$item['date']}\n";
        $content .= "- **Risk Level**: {$riskLevel}\n";
        $content .= "- **Reason**: {$item['reason']}\n";
        $content .= "- **Operation Type**: {$item['details']['operation_type']}\n";
        $content .= "- **Target Table**: {$item['details']['target_table']}\n";
        $content .= "- **Estimated Impact**: {$item['details']['estimated_impact']}\n\n";

        // Add detailed information based on operation type
        if ($item['details']['operation_type'] === 'create_table') {
            $content .= $this->formatCreateTableDetails($item['details']);
        } elseif (in_array($item['details']['operation_type'], ['add_columns', 'drop_columns'])) {
            $content .= $this->formatColumnOperationDetails($item['details']);
        }

        $content .= "- **Recommended Action**: " . ($riskLevel === 'HIGH' ? 'Create stub migration with rollback logic' : 'Document thoroughly, consider stub') . "\n\n";

        return $content;
    }

    /**
     * Format create table details
     */
    private function formatCreateTableDetails($details)
    {
        $content = "#### Table Structure\n\n";
        
        if (isset($details['affected_columns'])) {
            $content .= "**Columns (" . count($details['affected_columns']) . "):**\n\n";
            $content .= "| Name | Type | Nullable | Default | Key |\n";
            $content .= "|------|------|----------|---------|-----|\n";
            
            foreach ($details['affected_columns'] as $col) {
                $nullable = $col['nullable'] ? 'YES' : 'NO';
                $default = $col['default'] ?? 'NULL';
                $content .= "| {$col['name']} | {$col['type']} | {$nullable} | {$default} | {$col['key']} |\n";
            }
            $content .= "\n";
        }

        if (isset($details['affected_indexes'])) {
            $content .= "**Indexes (" . count($details['affected_indexes']) . "):**\n\n";
            foreach ($details['affected_indexes'] as $index) {
                $unique = $index['unique'] ? 'UNIQUE' : 'NON-UNIQUE';
                $columns = implode(', ', $index['columns']);
                $content .= "- {$index['name']} ({$unique}): {$columns}\n";
            }
            $content .= "\n";
        }

        if (isset($details['affected_constraints'])) {
            $content .= "**Constraints (" . count($details['affected_constraints']) . "):**\n\n";
            foreach ($details['affected_constraints'] as $constraint) {
                if ($constraint['references_table']) {
                    $content .= "- {$constraint['name']}: {$constraint['column']} → {$constraint['references_table']}.{$constraint['references_column']}\n";
                } else {
                    $content .= "- {$constraint['name']}: {$constraint['type']} on {$constraint['column']}\n";
                }
            }
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Format column operation details
     */
    private function formatColumnOperationDetails($details)
    {
        $content = "#### Column Operations\n\n";
        
        if (isset($details['affected_columns'])) {
            $content .= "**Affected Columns:**\n";
            foreach ($details['affected_columns'] as $col) {
                $content .= "- {$col}\n";
            }
            $content .= "\n";
        }

        if (isset($details['current_columns'])) {
            $content .= "**Current Table Structure:**\n\n";
            $content .= "| Name | Type | Nullable | Default | Key |\n";
            $content .= "|------|------|----------|---------|-----|\n";
            
            foreach ($details['current_columns'] as $col) {
                $nullable = $col['nullable'] ? 'YES' : 'NO';
                $default = $col['default'] ?? 'NULL';
                $content .= "| {$col['name']} | {$col['type']} | {$nullable} | {$default} | {$col['key']} |\n";
            }
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Create rollback references
     */
    private function createRollbackReferences()
    {
        echo "STEP 4: CREATING ROLLBACK REFERENCES\n";
        echo str_repeat("=", 50) . "\n";

        $rollbackFile = $this->outputPath . "/rollback_references_" . date('Y-m-d_H-i-s') . ".md";
        
        $content = "# Migration Rollback Reference Guide\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "## Purpose\n\n";
        $content .= "This document provides rollback references for orphaned migrations that need to be reconstructed.\n\n";

        $content .= "## High-Risk Migration Rollback Templates\n\n";

        foreach ($this->riskCategories['high'] as $item) {
            $content .= "### {$item['migration']}\n\n";
            $content .= $this->generateRollbackTemplate($item);
        }

        file_put_contents($rollbackFile, $content);
        echo "✓ Rollback references saved: $rollbackFile\n\n";
    }

    /**
     * Generate rollback template
     */
    private function generateRollbackTemplate($item)
    {
        $operation = $item['details']['operation_type'];
        $table = $item['details']['target_table'];
        
        $template = "**Operation Type**: {$operation}\n";
        $template .= "**Target Table**: {$table}\n\n";
        
        switch ($operation) {
            case 'create_table':
                $template .= "**Rollback Command**:\n";
                $template .= "```php\n";
                $template .= "Schema::dropIfExists('{$table}');\n";
                $template .= "```\n\n";
                break;
                
            case 'add_columns':
                $template .= "**Rollback Command**:\n";
                $template .= "```php\n";
                $template .= "Schema::table('{$table}', function (Blueprint \$table) {\n";
                foreach ($item['details']['affected_columns'] as $col) {
                    $template .= "    \$table->dropColumn('{$col}');\n";
                }
                $template .= "});\n";
                $template .= "```\n\n";
                break;
                
            case 'drop_columns':
                $template .= "**Rollback Command**:\n";
                $template .= "```php\n";
                $template .= "Schema::table('{$table}', function (Blueprint \$table) {\n";
                foreach ($item['details']['affected_columns'] as $col) {
                    // Need to infer column type for rollback
                    $template .= "    \$table->string('{$col}')->nullable(); // TODO: Verify original type\n";
                }
                $template .= "});\n";
                $template .= "```\n\n";
                break;
                
            default:
                $template .= "**Rollback**: Manual review required\n\n";
        }

        $template .= "**Data Impact**: {$item['details']['estimated_impact']}\n";
        $template .= "**Rollback Risk**: HIGH - Test thoroughly in staging\n\n";

        return $template;
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

// Execute detailed documentation
try {
    $documentation = new OrphanedMigrationDetailedDocumentation();
    $documentation->execute();
    
    echo "✅ ORPHANED MIGRATION DETAILED DOCUMENTATION COMPLETED\n";
    echo "📋 Review detailed documentation and rollback references\n";
    echo "⚠️  Focus on high-risk migrations first\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
