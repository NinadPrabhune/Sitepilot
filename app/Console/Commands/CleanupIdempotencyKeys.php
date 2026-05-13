<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupIdempotencyKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numbering:cleanup-idempotency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up idempotency keys older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Clean up idempotency keys older than 7 days
        $tables = ['purchase_orders', 'grns', 'purchase_invoices'];
        
        $totalDeleted = 0;
        
        foreach ($tables as $table) {
            $deleted = DB::table($table)
                ->whereNotNull('idempotency_key')
                ->where('created_at', '<', now()->subDays(7))
                ->update(['idempotency_key' => null]);
            
            $totalDeleted += $deleted;
            $this->info("Cleaned {$deleted} idempotency keys from {$table}");
        }
        
        $this->info("Total idempotency keys cleaned: {$totalDeleted}");
        
        return 0;
    }
}
