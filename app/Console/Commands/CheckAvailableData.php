<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAvailableData extends Command
{
    protected $signature = 'machinery:check-data {table=projects : Table to check}';
    protected $description = 'Check available data for ERP integration';

    public function handle()
    {
        $table = $this->argument('table');
        
        $this->info("🔍 Checking available data in: {$table}");
        
        try {
            $data = DB::table($table)->limit(3)->get();
            
            if ($data->isEmpty()) {
                $this->line("❌ No data found in {$table}");
                return 1;
            }

            $this->table(array_keys((array)$data->first()), 
                array_map(function($row) {
                    return array_values((array)$row);
                }, $data->toArray()));

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
