<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTableStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:check-erp-structure {table=payments_module : Table to inspect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect actual ERP table structure for integration alignment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        
        $this->info("🔍 Inspecting ERP Table Structure: {$table}");
        $this->line('==========================================');

        try {
            $columns = DB::select("DESCRIBE {$table}");
            
            $this->table(['Column', 'Type', 'Null', 'Key', 'Default', 'Extra'], 
                array_map(function($column) {
                    return [
                        $column->Field,
                        $column->Type,
                        $column->Null,
                        $column->Key,
                        $column->Default,
                        $column->Extra
                    ];
                }, $columns));

            // Show indexes
            $indexes = DB::select("SHOW INDEXES FROM {$table}");
            if ($indexes) {
                $this->line("\n📋 Indexes:");
                $this->table(['Table', 'Non_unique', 'Key_name', 'Seq_in_index', 'Column_name', 'Cardinality'], 
                    array_map(function($index) {
                        return [
                            $index->Table,
                            $index->Non_unique,
                            $index->Key_name,
                            $index->Seq_in_index,
                            $index->Column_name,
                            $index->Cardinality
                        ];
                    }, $indexes));
            }

            // Show sample data
            $sampleData = DB::select("SELECT * FROM {$table} LIMIT 3");
            if ($sampleData) {
                $this->line("\n📄 Sample Data (3 rows):");
                $sampleArray = array_map(function($row) {
                    return (array)$row;
                }, $sampleData);
                
                if (!empty($sampleArray)) {
                    $headers = array_keys($sampleArray[0]);
                    $rows = array_map(function($row) {
                        return array_values($row);
                    }, $sampleArray);
                    $this->table($headers, $rows);
                }
            }

            // Payment-specific analysis
            if ($table === 'payments_module') {
                $this->analyzePaymentStructure($columns);
            }

        } catch (\Exception $e) {
            $this->error("❌ Error inspecting table: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Analyze payment table structure for integration
     */
    protected function analyzePaymentStructure(array $columns): void
    {
        $this->line("\n🔧 Payment Structure Analysis:");
        
        $columnNames = array_column($columns, 'Field');
        
        // Check for critical columns
        $criticalColumns = [
            'id' => in_array('id', $columnNames),
            'amount' => in_array('amount', $columnNames),
            'payment_date' => in_array('payment_date', $columnNames),
            'payment_mode' => in_array('payment_mode', $columnNames),
            'status' => in_array('status', $columnNames),
            'payment_number' => in_array('payment_number', $columnNames),
            'source_type' => in_array('source_type', $columnNames),
            'source_id' => in_array('source_id', $columnNames),
            'integration_reference_uuid' => in_array('integration_reference_uuid', $columnNames),
        ];

        foreach ($criticalColumns as $column => $exists) {
            $status = $exists ? '✅' : '❌';
            $this->line("  {$status} {$column}");
        }

        // Check for status alternatives
        $statusAlternatives = ['state', 'is_posted', 'posted', 'is_active'];
        $foundStatus = false;
        foreach ($statusAlternatives as $alt) {
            if (in_array($alt, $columnNames)) {
                $this->line("  ℹ️  Possible status column: {$alt}");
                $foundStatus = true;
            }
        }

        if (!$foundStatus && !$criticalColumns['status']) {
            $this->line("  ⚠️  No status column found - may need to use other criteria");
        }
    }
}
