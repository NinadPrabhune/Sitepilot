<?php

/**
 * Conservative Migration Generation Strategy - Fixed
 * 
 * This tool provides a safe, batched approach to generating migrations
 * from existing database tables without overwhelming the system.
 * 
 * USAGE: php conservative_migration_strategy_fixed.php [--dry-run]
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConservativeMigrationStrategyFixed
{
    private $outputPath;
    private $dryRun;
    private $criticalTables = [
        'users', 'work_spaces', 'projects', 'machineries', 
        'purchase_orders', 'purchase_invoices', 'payments_module',
        'daily_progress_reports', 'suppliers'
    ];
    
    public function __construct($dryRun = false)
    {
        $this->outputPath = __DIR__ . '/database_backups/migration_batches';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
        $this->dryRun = $dryRun;
    }

    /**
     * Execute conservative migration generation
     */
    public function execute()
    {
        echo "=== CONSERVATIVE MIGRATION GENERATION STRATEGY (FIXED) ===\n";
        echo "Mode: " . ($this->dryRun ? 'DRY RUN' : 'EXECUTION') . "\n\n";

        $this->analyzeTablePriorities();
        $this->generateBatchedMigrations();
        $this->createValidationPlan();
    }

    /**
     * Analyze table priorities
     */
    private function analyzeTablePriorities()
    {
        echo "STEP 1: ANALYZING TABLE PRIORITIES\n";
        echo str_repeat("=", 50) . "\n";

        $allTables = $this->getAllTables();
        $tableAnalysis = [];

        foreach ($allTables as $table) {
            $analysis = $this->analyzeTable($table);
            $tableAnalysis[$table] = $analysis;
        }

        // Categorize tables
        $categories = [
            'critical' => [],
            'high_priority' => [],
            'medium_priority' => [],
            'low_priority' => []
        ];

        foreach ($tableAnalysis as $table => $analysis) {
            if ($analysis['is_critical']) {
                $categories['critical'][] = $table;
            } elseif ($analysis['priority_score'] >= 8) {
                $categories['high_priority'][] = $table;
            } elseif ($analysis['priority_score'] >= 5) {
                $categories['medium_priority'][] = $table;
            } else {
                $categories['low_priority'][] = $table;
            }
        }

        echo "Table Priority Analysis:\n";
        echo "Critical: " . count($categories['critical']) . "\n";
        echo "High Priority: " . count($categories['high_priority']) . "\n";
        echo "Medium Priority: " . count($categories['medium_priority']) . "\n";
        echo "Low Priority: " . count($categories['low_priority']) . "\n\n";

        $this->tableCategories = $categories;
        $this->tableAnalysis = $tableAnalysis;
    }

    /**
     * Analyze individual table
     */
    private function analyzeTable($table)
    {
        try {
            $columns = DB::select("DESCRIBE `{$table}`");
            $indexes = DB::select("SHOW INDEX FROM `{$table}`");
            $row_count = DB::table($table)->count();
            
            // Calculate priority score
            $score = 0;
            
            // Critical business tables
            if (in_array($table, $this->criticalTables)) {
                $score += 10;
            }
            
            // Large tables (harder to migrate)
            if ($row_count > 100000) {
                $score += 3;
            } elseif ($row_count > 10000) {
                $score += 2;
            }
            
            // Complex tables (many columns/indexes)
            if (count($columns) > 20) {
                $score += 2;
            } elseif (count($columns) > 10) {
                $score += 1;
            }
            
            // Frequently modified (heuristic)
            if (preg_match('/(log|audit|activity|session)/i', $table)) {
                $score += 1;
            }

            return [
                'is_critical' => in_array($table, $this->criticalTables),
                'priority_score' => $score,
                'column_count' => count($columns),
                'index_count' => count($indexes),
                'row_count' => $row_count,
                'complexity' => $this->assessComplexity($columns, $indexes)
            ];

        } catch (Exception $e) {
            return [
                'is_critical' => false,
                'priority_score' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Assess table complexity
     */
    private function assessComplexity($columns, $indexes)
    {
        $complexity = 'low';
        
        if (count($columns) > 20 || count($indexes) > 10) {
            $complexity = 'high';
        } elseif (count($columns) > 10 || count($indexes) > 5) {
            $complexity = 'medium';
        }
        
        return $complexity;
    }

    /**
     * Generate batched migrations
     */
    private function generateBatchedMigrations()
    {
        echo "STEP 2: GENERATING BATCHED MIGRATIONS\n";
        echo str_repeat("=", 50) . "\n";

        $batches = $this->createBatches();
        
        foreach ($batches as $batchNum => $tables) {
            echo "Preparing Batch " . ($batchNum + 1) . " (" . count($tables) . " tables):\n";
            
            $batchFile = $this->outputPath . "/batch_" . ($batchNum + 1) . "_tables.txt";
            file_put_contents($batchFile, implode("\n", $tables));
            
            foreach ($tables as $table) {
                $complexity = $this->tableAnalysis[$table]['complexity'] ?? 'unknown';
                $rows = $this->tableAnalysis[$table]['row_count'] ?? 0;
                echo "  - {$table} ({$complexity}, {$rows} rows)\n";
            }
            
            echo "  → Tables list: $batchFile\n\n";
        }

        $this->batches = $batches;
    }

    /**
     * Create logical batches
     */
    private function createBatches()
    {
        $batches = [];
        $batchSize = 10; // Conservative batch size
        $currentBatch = [];
        $currentSize = 0;

        // Process in priority order
        $priorityOrder = ['critical', 'high_priority', 'medium_priority', 'low_priority'];
        
        foreach ($priorityOrder as $category) {
            $tables = $this->tableCategories[$category] ?? [];
            
            foreach ($tables as $table) {
                $complexity = $this->tableAnalysis[$table]['complexity'] ?? 'low';
                
                // Adjust batch size based on complexity
                $effectiveSize = ($complexity === 'high') ? 3 : 
                               (($complexity === 'medium') ? 5 : 1);
                
                if ($currentSize + $effectiveSize > $batchSize) {
                    if (!empty($currentBatch)) {
                        $batches[] = $currentBatch;
                        $currentBatch = [];
                        $currentSize = 0;
                    }
                }
                
                $currentBatch[] = $table;
                $currentSize += $effectiveSize;
            }
        }
        
        if (!empty($currentBatch)) {
            $batches[] = $currentBatch;
        }
        
        return $batches;
    }

    /**
     * Create validation plan
     */
    private function createValidationPlan()
    {
        echo "STEP 3: CREATING VALIDATION PLAN\n";
        echo str_repeat("=", 50) . "\n";

        $planFile = $this->outputPath . "/validation_plan_" . date('Y-m-d_H-i-s') . ".md";
        
        $content = "# Migration Generation Validation Plan\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Mode: " . ($this->dryRun ? 'DRY RUN' : 'EXECUTION') . "\n\n";

        $content .= "## Batch Processing Strategy\n\n";
        $content .= "- Total batches: " . count($this->batches) . "\n";
        $content .= "- Max tables per batch: 10\n";
        $content .= "- Complexity-adjusted sizing\n\n";

        $content .= "## Batch Details\n\n";
        
        foreach ($this->batches as $i => $batch) {
            $content .= "### Batch " . ($i + 1) . "\n\n";
            $content .= "Tables:\n";
            foreach ($batch as $table) {
                $analysis = $this->tableAnalysis[$table];
                $content .= "- {$table} ({$analysis['complexity']}, {$analysis['row_count']} rows)\n";
            }
            $content .= "\n";
        }

        $content .= "## Validation Steps\n\n";
        $content .= "For each batch:\n";
        $content .= "1. Generate migrations: `php artisan migrate:generate --tables=\"batch_X_tables.txt\"`\n";
        $content .= "2. Review generated files manually\n";
        $content .= "3. Check for:\n";
        $content .= "   - Correct column types\n";
        $content .= "   - Proper constraints\n";
        $content .= "   - Missing indexes\n";
        $content .= "4. Test in staging environment\n";
        $content .= "5. Commit to version control\n\n";

        $content .= "## Dry-Run Commands\n\n";
        $content .= "```bash\n";
        $content .= "# Test batch 1\n";
        $content .= "php artisan migrate:generate --tables=\"batch_1_tables.txt\" --pretend\n";
        $content .= "# Test batch 2\n";
        $content .= "php artisan migrate:generate --tables=\"batch_2_tables.txt\" --pretend\n";
        $content .= "# Continue for all batches...\n";
        $content .= "```\n\n";

        $content .= "## High-Risk Tables\n\n";
        $content .= "Manual review required for:\n";
        
        foreach ($this->tableCategories['critical'] as $table) {
            $content .= "- {$table}\n";
        }

        file_put_contents($planFile, $content);
        echo "✓ Validation plan saved: $planFile\n\n";
    }

    /**
     * Get all tables
     */
    private function getAllTables()
    {
        $tables = DB::select('SHOW TABLES');
        return array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
    }
}

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);

// Execute conservative strategy
try {
    $strategy = new ConservativeMigrationStrategyFixed($dryRun);
    $strategy->execute();
    
    echo "✅ CONSERVATIVE MIGRATION STRATEGY COMPLETED\n";
    echo "📋 Review generated batches and validation plan\n";
    echo "⚠️  " . ($dryRun ? 'DRY RUN MODE - No changes applied' : 'EXECUTION MODE - Review before applying') . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
