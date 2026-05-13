<?php

/**
 * Orphaned Migration Documentation Tool
 * 
 * This tool helps document and manage orphaned migrations safely
 * without breaking the existing database state.
 * 
 * USAGE: php orphaned_migration_documentation.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class OrphanedMigrationDocumentation
{
    private $outputPath;
    private $orphanedMigrations;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/database_backups';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute orphaned migration analysis
     */
    public function execute()
    {
        echo "=== ORPHANED MIGRATION DOCUMENTATION ===\n\n";

        $this->identifyOrphanedMigrations();
        $this->analyzeOrphanedMigrations();
        $this->generateDocumentation();
        $this->provideOptions();
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
        $dbMigrations = DB::table('migrations')->pluck('migration')->toArray();
        
        // Find orphaned (in DB but not in files)
        $this->orphanedMigrations = array_diff($dbMigrations, $migrationFiles);
        
        echo "Migration files found: " . count($migrationFiles) . "\n";
        echo "Migrations in database: " . count($dbMigrations) . "\n";
        echo "Orphaned migrations: " . count($this->orphanedMigrations) . "\n\n";

        if (!empty($this->orphanedMigrations)) {
            echo "Orphaned migrations list:\n";
            foreach ($this->orphanedMigrations as $migration) {
                echo "  - $migration\n";
            }
        }
        echo "\n";
    }

    /**
     * Analyze orphaned migrations
     */
    private function analyzeOrphanedMigrations()
    {
        echo "STEP 2: ANALYZING ORPHANED MIGRATIONS\n";
        echo str_repeat("=", 50) . "\n";

        $analysis = [];
        
        foreach ($this->orphanedMigrations as $migration) {
            $analysis[$migration] = $this->analyzeMigration($migration);
        }

        $this->displayAnalysis($analysis);
    }

    /**
     * Analyze individual migration
     */
    private function analyzeMigration($migration)
    {
        // Get migration record details
        $record = DB::table('migrations')
            ->where('migration', $migration)
            ->first();

        if (!$record) {
            return ['status' => 'not_found', 'batch' => null, 'date' => null];
        }

        // Try to infer what the migration might have done based on name
        $inferredAction = $this->inferMigrationAction($migration);
        
        return [
            'status' => 'orphaned',
            'batch' => $record->batch,
            'date' => $record->created_at,
            'inferred_action' => $inferredAction,
            'risk_level' => $this->assessRiskLevel($migration, $inferredAction)
        ];
    }

    /**
     * Infer migration action from name
     */
    private function inferMigrationAction($migration)
    {
        if (strpos($migration, 'create_') !== false && strpos($migration, '_table') !== false) {
            return 'create_table';
        } elseif (strpos($migration, 'add_') !== false && strpos($migration, '_to_') !== false) {
            return 'add_columns';
        } elseif (strpos($migration, 'drop_') !== false) {
            return 'drop_columns_or_table';
        } elseif (strpos($migration, 'rename_') !== false) {
            return 'rename_table_or_columns';
        } elseif (strpos($migration, 'modify_') !== false || strpos($migration, 'update_') !== false) {
            return 'modify_structure';
        } else {
            return 'unknown';
        }
    }

    /**
     * Assess risk level of orphaned migration
     */
    private function assessRiskLevel($migration, $action)
    {
        // Recent migrations are lower risk
        $datePart = substr($migration, 0, 10);
        $migrationDate = DateTime::createFromFormat('Y_m_d', $datePart);
        
        if ($migrationDate) {
            $daysAgo = (new DateTime())->diff($migrationDate)->days;
            if ($daysAgo < 30) return 'low';
            if ($daysAgo < 90) return 'medium';
        }

        // Certain actions are higher risk
        if (in_array($action, ['drop_columns_or_table', 'rename_table_or_columns'])) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Display analysis results
     */
    private function displayAnalysis($analysis)
    {
        echo "Risk Analysis:\n";
        echo "High Risk: " . count(array_filter($analysis, fn($a) => $a['risk_level'] === 'high')) . "\n";
        echo "Medium Risk: " . count(array_filter($analysis, fn($a) => $a['risk_level'] === 'medium')) . "\n";
        echo "Low Risk: " . count(array_filter($analysis, fn($a) => $a['risk_level'] === 'low')) . "\n\n";

        echo "Detailed Analysis (High Risk first):\n";
        uasort($analysis, function($a, $b) {
            $riskOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $riskOrder[$b['risk_level']] - $riskOrder[$a['risk_level']];
        });

        foreach ($analysis as $migration => $details) {
            $riskIcon = $details['risk_level'] === 'high' ? '🔴' : 
                       ($details['risk_level'] === 'medium' ? '🟡' : '🟢');
            echo "  {$riskIcon} {$migration}\n";
            echo "     Action: {$details['inferred_action']}\n";
            echo "     Batch: {$details['batch']}, Date: {$details['date']}\n\n";
        }
    }

    /**
     * Generate documentation
     */
    private function generateDocumentation()
    {
        echo "STEP 3: GENERATING DOCUMENTATION\n";
        echo str_repeat("=", 50) . "\n";

        $timestamp = date('Y-m-d_H-i-s');
        $docFile = $this->outputPath . "/orphaned_migrations_doc_{$timestamp}.md";

        $content = "# Orphaned Migrations Documentation\n\n";
        $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "## Summary\n\n";
        $content .= "- Total orphaned migrations: " . count($this->orphanedMigrations) . "\n\n";

        $content .= "## Migration Details\n\n";

        foreach ($this->orphanedMigrations as $migration) {
            $details = $this->analyzeMigration($migration);
            $riskIcon = $details['risk_level'] === 'high' ? '🔴' : 
                       ($details['risk_level'] === 'medium' ? '🟡' : '🟢');
            
            $content .= "### {$riskIcon} {$migration}\n\n";
            $content .= "- **Inferred Action**: {$details['inferred_action']}\n";
            $content .= "- **Risk Level**: {$details['risk_level']}\n";
            $content .= "- **Batch**: {$details['batch']}\n";
            $content .= "- **Date**: {$details['date']}\n\n";
        }

        $content .= "## Recommendations\n\n";
        $content .= "1. **High Risk migrations**: Consider creating stub migration files for proper rollback capability\n";
        $content .= "2. **Medium Risk migrations**: Document thoroughly and monitor during future changes\n";
        $content .= "3. **Low Risk migrations**: Generally safe to leave as-is\n\n";

        $content .= "## SQL for Reference\n\n";
        $content .= "```sql\n";
        $content .= "-- Query to get orphaned migrations\n";
        $content .= "SELECT migration, batch, created_at \n";
        $content .= "FROM migrations \n";
        $content .= "WHERE migration NOT IN (\n";
        $content .= "    -- List your migration files here\n";
        $content .= ");\n";
        $content .= "```\n\n";

        file_put_contents($docFile, $content);

        echo "✓ Documentation generated: $docFile\n\n";
        $this->analysisResults['documentation_file'] = $docFile;
    }

    /**
     * Provide management options
     */
    private function provideOptions()
    {
        echo "STEP 4: MANAGEMENT OPTIONS\n";
        echo str_repeat("=", 50) . "\n";

        echo "OPTION A: DOCUMENTATION ONLY (Recommended for production)\n";
        echo "- Keep orphaned migrations as-is\n";
        echo "- Maintain documentation for future reference\n";
        echo "- No risk to existing functionality\n\n";

        echo "OPTION B: CREATE STUB MIGRATIONS\n";
        echo "- Create minimal migration files for rollback capability\n";
        echo "- Higher risk but better long-term management\n";
        echo "- Requires careful testing\n\n";

        echo "OPTION C: CLEANUP (DANGEROUS)\n";
        echo "- Remove orphaned entries from migrations table\n";
        echo "- ⚠️  HIGH RISK - May break rollback functionality\n";
        echo "- Only if absolutely certain migrations are obsolete\n\n";

        echo "Recommended Next Steps:\n";
        echo "1. Review generated documentation\n";
        echo "2. Test application functionality\n";
        echo "3. Consider Option A for immediate safety\n";
        echo "4. Plan Option B for future maintenance window\n\n";
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

// Execute orphaned migration documentation
try {
    $documentation = new OrphanedMigrationDocumentation();
    $documentation->execute();
    
    echo "\n✅ ORPHANED MIGRATION DOCUMENTATION COMPLETED\n";
    echo "📄 Review generated documentation for next steps\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
