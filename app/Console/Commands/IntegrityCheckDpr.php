<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DailyProgressReport;

class IntegrityCheckDpr extends Command
{
    protected $signature = 'dpr:integrity-check {--fix : Attempt to fix issues}';
    protected $description = 'Comprehensive DPR/Ledger integrity check';
    
    public function handle()
    {
        $issues = [];
        $fix = $this->option('fix');
        
        $this->info('Running DPR integrity checks...');
        
        // Check 1: Activity flow without activity_completed_id
        $this->info('Checking: Activity flow DPRs without activity_completed_id...');
        $orphaned = DailyProgressReport::where('source_type', 'activity')
            ->whereNull('activity_completed_id')
            ->get();
        if ($orphaned->count() > 0) {
            $issues[] = "{$orphaned->count()} activity flow DPRs without activity_completed_id";
            if ($fix) {
                foreach ($orphaned as $dpr) {
                    $dpr->update(['source_type' => DailyProgressReport::SOURCE_TYPE_MACHINERY_DIRECT]);
                }
                $this->info("  ✅ Fixed {$orphaned->count()} records");
            }
        }
        
        // Check 2: Direct flow with activity_completed_id
        $this->info('Checking: Direct flow DPRs with activity_completed_id...');
        $contaminated = DailyProgressReport::where('source_type', 'machinery_direct')
            ->whereNotNull('activity_completed_id')
            ->get();
        if ($contaminated->count() > 0) {
            $issues[] = "{$contaminated->count()} direct flow DPRs with activity_completed_id";
            if ($fix) {
                foreach ($contaminated as $dpr) {
                    $dpr->update(['source_type' => DailyProgressReport::SOURCE_TYPE_ACTIVITY]);
                }
                $this->info("  ✅ Fixed {$contaminated->count()} records");
            }
        }
        
        // Check 3: DPRs without ledger entries
        $this->info('Checking: DPRs without ledger entries...');
        $dprsWithoutLedger = DB::table('daily_progress_reports as dpr')
            ->leftJoin('machinery_ledgers as ml', function ($join) {
                $join->on('ml.reference_id', '=', 'dpr.id')
                     ->where('ml.reference_type', 'DailyProgressReport');
            })
            ->whereNull('ml.id')
            ->count();
        if ($dprsWithoutLedger > 0) {
            $issues[] = "{$dprsWithoutLedger} DPRs without ledger entries";
        }
        
        // Check 4: CRITICAL - Ledger entries without DPRs (orphaned financial records)
        $this->info('Checking: Ledger entries without DPRs (orphaned)...');
        $ledgerWithoutDpr = DB::table('machinery_ledgers as ml')
            ->leftJoin('daily_progress_reports as dpr', function ($join) {
                $join->on('dpr.id', '=', 'ml.reference_id')
                     ->where('ml.reference_type', 'DailyProgressReport');
            })
            ->where('ml.reference_type', 'DailyProgressReport')
            ->whereNull('dpr.id')
            ->count();
        if ($ledgerWithoutDpr > 0) {
            $issues[] = "CRITICAL: {$ledgerWithoutDpr} ledger entries without DPRs (orphaned financial records)";
        }
        
        // Check 5: Missing idempotency keys
        $this->info('Checking: Missing idempotency keys...');
        $missingIdempotency = DB::table('machinery_ledgers')
            ->whereNull('idempotency_key')
            ->count();
        if ($missingIdempotency > 0) {
            $issues[] = "{$missingIdempotency} ledger entries without idempotency keys";
            if ($fix) {
                $this->call('dpr:backfill');
            }
        }
        
        // Check 6: Duplicate idempotency keys
        $this->info('Checking: Duplicate idempotency keys...');
        $duplicates = DB::table('machinery_ledgers')
            ->select('idempotency_key', DB::raw('COUNT(*) as count'))
            ->whereNotNull('idempotency_key')
            ->groupBy('idempotency_key')
            ->having('count', '>', 1)
            ->get();
        if ($duplicates->count() > 0) {
            $issues[] = "{$duplicates->count()} duplicate idempotency keys found";
        }
        
        // Check 7: Settled but not locked ledger entries
        $this->info('Checking: Settled ledger entries...');
        $settledWithoutLock = DB::table('machinery_ledgers')
            ->where('is_settled', true)
            ->whereNull('payment_request_id')
            ->count();
        if ($settledWithoutLock > 0) {
            $issues[] = "{$settledWithoutLock} settled ledger entries without payment_request_id";
        }
        
        // Report
        $this->newLine();
        if (empty($issues)) {
            $this->info('✅ All integrity checks passed!');
            return 0;
        }
        
        $this->warn('❌ Integrity issues found:');
        foreach ($issues as $issue) {
            $this->warn("  - {$issue}");
        }
        
        if (!$fix) {
            $this->newLine();
            $this->info('Run with --fix to attempt automatic fixes');
        }
        
        return 1;
    }
}
