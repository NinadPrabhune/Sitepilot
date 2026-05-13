<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDbIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:verify-db-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify critical DB indexes exist for financial integrity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verifying DB Indexes for Financial Integrity (Phase B1.6)');
        $this->line('=============================================================');

        $allValid = true;

        // Check payments_module indexes
        $this->line("\n📋 Checking payments_module indexes:");
        $indexes = DB::select('SHOW INDEXES FROM payments_module');
        
        $requiredIndexes = [
            'payments_module_integration_unique' => [
                'description' => 'Idempotency constraint (source_type, source_id, integration_reference_uuid)',
                'columns' => ['source_type', 'source_id', 'integration_reference_uuid'],
                'unique' => true
            ],
            'payments_module_source_type_index' => [
                'description' => 'Source type index',
                'columns' => ['source_type'],
                'unique' => false
            ],
            'payments_module_source_id_index' => [
                'description' => 'Source ID index',
                'columns' => ['source_id'],
                'unique' => false
            ],
            'payments_module_source_type_source_id_index' => [
                'description' => 'Composite source index',
                'columns' => ['source_type', 'source_id'],
                'unique' => false
            ]
        ];

        $foundIndexes = [];
        foreach ($indexes as $index) {
            if (!isset($foundIndexes[$index->Key_name])) {
                $foundIndexes[$index->Key_name] = [
                    'columns' => [],
                    'unique' => $index->Non_unique == 0,
                    'description' => 'Index on ' . $index->Key_name
                ];
            }
            $foundIndexes[$index->Key_name]['columns'][] = $index->Column_name;
        }

        foreach ($requiredIndexes as $indexName => $required) {
            if (isset($foundIndexes[$indexName])) {
                $found = $foundIndexes[$indexName];
                $columnsMatch = count(array_diff($required['columns'], $found['columns'])) === 0;
                $uniqueMatch = $found['unique'] === $required['unique'];
                
                if ($columnsMatch && $uniqueMatch) {
                    $this->info("  ✅ {$indexName}: {$required['description']}");
                } else {
                    $this->error("  ❌ {$indexName}: Mismatch detected");
                    $this->line("     Expected: " . implode(', ', $required['columns']) . " (Unique: " . ($required['unique'] ? 'Yes' : 'No') . ")");
                    $this->line("     Found: " . implode(', ', $found['columns']) . " (Unique: " . ($found['unique'] ? 'Yes' : 'No') . ")");
                    $allValid = false;
                }
            } else {
                $this->error("  ❌ {$indexName}: NOT FOUND - {$required['description']}");
                $this->line("     Required columns: " . implode(', ', $required['columns']));
                $allValid = false;
            }
        }

        // Check if tables exist
        $this->line("\n📋 Checking table existence:");
        $tables = ['payments_module', 'machinery_payment_requests'];
        
        foreach ($tables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (!empty($exists)) {
                $this->info("  ✅ {$table}: Table exists");
            } else {
                $this->error("  ❌ {$table}: Table NOT FOUND");
                $allValid = false;
            }
        }

        // Summary
        if ($allValid) {
            $this->line("\n🎉 All critical DB indexes verified successfully!");
            $this->info("✅ Financial integrity constraints are in place");
            $this->info("✅ Ready for operational testing");
        } else {
            $this->error("\n❌ Critical DB indexes missing or incorrect!");
            $this->error("⚠️  Financial integrity may be compromised");
            $this->line("\n💡 Run migrations to fix missing indexes:");
            $this->line("   php artisan migrate --path=database/migrations/2026_05_07_103527_add_source_fields_to_payments_module_table.php");
            $this->line("   php artisan migrate --path=database/migrations/2026_05_07_105512_add_integration_reference_to_payments_module.php");
        }

        return $allValid ? 0 : 1;
    }
}
