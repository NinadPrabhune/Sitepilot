<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDprSourceType extends Command
{
    protected $signature = 'dpr:backfill';
    protected $description = 'Backfill source_type and generate idempotency keys for existing records';
    
    public function handle()
    {
        $this->info('Starting DPR and Ledger backfill...');
        
        // Backfill DPR source_type
        $dprCount = DB::table('daily_progress_reports')
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '');
            })
            ->update(['source_type' => 'activity']);
        $this->info("✅ Updated {$dprCount} DPR records");
        
        // Backfill Ledger source_type
        $ledgerCount = DB::table('machinery_ledgers')
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '');
            })
            ->update(['source_type' => 'activity']);
        $this->info("✅ Updated {$ledgerCount} Ledger records");
        
        // Generate idempotency keys for existing ledger entries (DPR references only)
        $existing = DB::table('machinery_ledgers')
            ->whereNull('idempotency_key')
            ->where('reference_type', 'DailyProgressReport')
            ->get();
            
        $generated = 0;
        foreach ($existing as $entry) {
            DB::table('machinery_ledgers')
                ->where('id', $entry->id)
                ->update([
                    'idempotency_key' => "dpr_{$entry->reference_id}_operational_legacy_{$entry->id}",
                    'is_settled' => $entry->is_settled ?? false,
                    'is_reversed' => $entry->is_reversed ?? false,
                ]);
            $generated++;
        }
        $this->info("✅ Generated idempotency keys for {$generated} ledger entries");
        
        // Update unsettled flags
        DB::table('machinery_ledgers')
            ->whereNull('is_settled')
            ->update(['is_settled' => false]);
        DB::table('machinery_ledgers')
            ->whereNull('is_reversed')
            ->update(['is_reversed' => false]);
        
        $this->info('Backfill completed successfully!');
        
        return 0;
    }
}
