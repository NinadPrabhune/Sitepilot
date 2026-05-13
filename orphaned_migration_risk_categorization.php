<?php

/**
 * Orphaned Migration Risk Categorization Tool
 * 
 * This tool categorizes orphaned migrations by risk level
 * to determine which need reconstruction vs documentation.
 * 
 * USAGE: php orphaned_migration_risk_categorization.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class OrphanedMigrationRiskCategorization
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
     * Execute risk categorization
     */
    public function execute()
    {
        echo "=== ORPHANED MIGRATION RISK CATEGORIZATION ===\n\n";

        $this->identifyOrphanedMigrations();
        $this->categorizeByRisk();
        $this->generateActionPlan();
        $this->createStubTemplates();
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
     * Categorize by risk level
     */
    private function categorizeByRisk()
    {
        echo "STEP 2: CATEGORIZING BY RISK LEVEL\n";
        echo str_repeat("=", 50) . "\n";

        foreach ($this->orphanedMigrations as $migration => $record) {
            $risk = $this->assessRisk($migration, $record);
            $this->riskCategories[$risk][] = [
                'migration' => $migration,
                'batch' => $record->batch,
                'date' => $record->created_at,
                'reason' => $this->getRiskReason($migration, $risk)
            ];
        }

        echo "Risk Categorization:\n";
        echo "High Risk (Reconstruct): " . count($this->riskCategories['high']) . "\n";
        echo "Medium Risk (Consider): " . count($this->riskCategories['medium']) . "\n";
        echo "Low Risk (Document): " . count($this->riskCategories['low']) . "\n\n";
    }

    /**
     * Assess risk level of orphaned migration
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
            'high' => 'Schema-changing operation, affects rollback capability',
            'medium' => 'Important structural change, may affect future deployments',
            'low' => 'Minor change or data fix, documentation sufficient'
        ];

        return $reasons[$risk] ?? 'Unknown risk level';
    }

    /**
     * Generate action plan
     */
    private function generateActionPlan()
    {
        echo "STEP 3: GENERATING ACTION PLAN\n";
        echo str_repeat("=", 50) . "\n";

        $planFile = $this->outputPath . "/orphaned_migration_action_plan_" . date('Y-m-d_H-i-s') . ".md";

        $content = "# Orphaned Migration Action Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "## Executive Summary\n\n";
        $content .= "- **High Risk (Reconstruct)**: " . count($this->riskCategories['high']) . " migrations\n";
        $content .= "- **Medium Risk (Consider)**: " . count($this->riskCategories['medium']) . " migrations\n";
        $content .= "- **Low Risk (Document)**: " . count($this->riskCategories['low']) . " migrations\n\n";

        $content .= "## Strategy\n\n";
        $content .= "**Goal**: Establish stability going forward, not perfect history\n\n";
        $content .= "1. **High Risk**: Create stub migrations for rollback capability\n";
        $content .= "2. **Medium Risk**: Document thoroughly, consider stubs\n";
        $content .= "3. **Low Risk**: Document and ignore\n\n";

        // High risk details
        if (!empty($this->riskCategories['high'])) {
            $content .= "## High Risk Migrations (Reconstruct)\n\n";
            foreach ($this->riskCategories['high'] as $item) {
                $content .= "### {$item['migration']}\n";
                $content .= "- **Batch**: {$item['batch']}\n";
                $content .= "- **Date**: {$item['date']}\n";
                $content .= "- **Reason**: {$item['reason']}\n";
                $content .= "- **Action**: Create stub migration\n\n";
            }
        }

        // Medium risk details
        if (!empty($this->riskCategories['medium'])) {
            $content .= "## Medium Risk Migrations (Consider)\n\n";
            foreach ($this->riskCategories['medium'] as $item) {
                $content .= "### {$item['migration']}\n";
                $content .= "- **Batch**: {$item['batch']}\n";
                $content .= "- **Date**: {$item['date']}\n";
                $content .= "- **Reason**: {$item['reason']}\n";
                $content .= "- **Action**: Document, consider stub\n\n";
            }
        }

        // Low risk summary
        $content .= "## Low Risk Migrations (Document Only)\n\n";
        $content .= "Count: " . count($this->riskCategories['low']) . "\n";
        $content .= "These can be safely documented and ignored for future development.\n\n";

        file_put_contents($planFile, $content);
        echo "✓ Action plan saved: $planFile\n\n";
    }

    /**
     * Create stub templates
     */
    private function createStubTemplates()
    {
        echo "STEP 4: CREATING STUB TEMPLATES\n";
        echo str_repeat("=", 50) . "\n";

        $templateDir = __DIR__ . '/database_backups/migration_stubs';
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        // Create template for high-risk migrations
        $stubTemplate = $this->generateStubTemplate();
        file_put_contents($templateDir . '/migration_stub_template.php', $stubTemplate);

        // Generate specific stubs for high-risk migrations
        foreach ($this->riskCategories['high'] as $item) {
            $stub = $this->createSpecificStub($item['migration']);
            $stubFile = $templateDir . '/' . $item['migration'] . '.php';
            file_put_contents($stubFile, $stub);
        }

        echo "✓ Stub templates created in: $templateDir\n";
        echo "✓ Generated " . count($this->riskCategories['high']) . " specific stubs\n\n";
    }

    /**
     * Generate stub template
     */
    private function generateStubTemplate()
    {
        return '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STUB MIGRATION - Orphaned Migration Reconstruction
 * 
 * This is a stub migration created to restore rollback capability
 * for an orphaned migration that was lost but executed in production.
 * 
 * Original migration: {{MIGRATION_NAME}}
 * Original batch: {{BATCH_NUMBER}}
 * Original date: {{MIGRATION_DATE}}
 * 
 * WARNING: This is a minimal stub for rollback purposes only.
 * The original migration logic may have been more complex.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // STUB: Original migration logic was lost
        // This migration is marked as already run in production
        // Only implement rollback logic in down()
        
        Log::warning("Stub migration executed: {{MIGRATION_NAME}}");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // TODO: Implement rollback based on what the original migration did
        // Common patterns:
        // - Schema::dropIfExists(\'table_name\');
        // - Schema::table(\'table_name\', function (Blueprint $table) {
        //     $table->dropColumn(\'column_name\');
        // });
        // - Schema::table(\'table_name\', function (Blueprint $table) {
        //     $table->dropForeign(\'foreign_key_name\');
        //     $table->dropIndex(\'index_name\');
        // });
        
        Log::warning("Rollback for stub migration: {{MIGRATION_NAME}} - NOT IMPLEMENTED");
    }
};';
    }

    /**
     * Create specific stub
     */
    private function createSpecificStub($migration)
    {
        $record = $this->orphanedMigrations[$migration];
        $template = $this->generateStubTemplate();
        
        // Replace placeholders
        $stub = str_replace('{{MIGRATION_NAME}}', $migration, $template);
        $stub = str_replace('{{BATCH_NUMBER}}', $record->batch, $stub);
        $stub = str_replace('{{MIGRATION_DATE}}', $record->created_at, $stub);
        
        // Add specific hints based on migration name
        $hints = $this->generateMigrationHints($migration);
        $stub = str_replace('// TODO: Implement rollback based on what the original migration did', 
                          '// TODO: Implement rollback based on what the original migration did' . "\n        " . $hints, $stub);
        
        return $stub;
    }

    /**
     * Generate migration-specific hints
     */
    private function generateMigrationHints($migration)
    {
        $hints = [];
        
        if (preg_match('/create_(.+)_table/', $migration, $matches)) {
            $tableName = $matches[1];
            $hints[] = "// Original likely created table: {$tableName}";
            $hints[] = "// Consider: Schema::dropIfExists('{$tableName}');";
        }
        
        if (preg_match('/add_(.+)_to_(.+)/', $migration, $matches)) {
            $columns = $matches[1];
            $table = $matches[2];
            $hints[] = "// Original likely added columns to: {$table}";
            $hints[] = "// Consider: \$table->dropColumn(['{$columns}']);";
        }
        
        return implode("\n        ", $hints);
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

// Execute risk categorization
try {
    $categorization = new OrphanedMigrationRiskCategorization();
    $categorization->execute();
    
    echo "✅ ORPHANED MIGRATION RISK CATEGORIZATION COMPLETED\n";
    echo "📋 Review action plan and stub templates\n";
    echo "⚠️  Focus on high-risk migrations first\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
