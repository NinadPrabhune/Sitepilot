<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SupplierTransaction;

class RebuildSupplierLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:rebuild {--supplier-id= : Rebuild ledger for specific supplier} {--site-id= : Rebuild ledger for specific site} {--dry-run : Compare balances without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild supplier ledger balances from scratch (reconciliation tool)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Supplier Ledger Rebuild ===');
        $this->warn('This will recalculate all running balances from ledger entries.');
        
        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $supplierId = $this->option('supplier-id');
        $siteId = $this->option('site-id');

        $query = SupplierTransaction::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
            $this->info("Filtering by supplier_id: {$supplierId}");
        }

        if ($siteId) {
            $query->where('site_id', $siteId);
            $this->info("Filtering by site_id: {$siteId}");
        }

        $this->info('Fetching transactions...');
        $transactions = $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc')->get();
        
        $this->info("Found {$transactions->count()} transactions to process.");

        $runningBalance = 0;
        $balanceUpdates = [];
        $ids = [];
        $ignoredTypes = [
            SupplierTransaction::TYPE_PO,
            SupplierTransaction::TYPE_GRN,
        ];

        $this->newLine();
        $bar = $this->output->createProgressBar($transactions->count());
        $bar->start();

        foreach ($transactions as $transaction) {
            $isIgnoredType = in_array($transaction->reference_type, $ignoredTypes);
            $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
            $isNonAccounting = !empty($meta['non_accounting']);

            if ($isIgnoredType || $isNonAccounting) {
                $transaction->balance = $runningBalance;
            } else {
                $runningBalance = $runningBalance + $transaction->debit - $transaction->credit;
                $transaction->balance = $runningBalance;
            }
            
            $balanceUpdates[$transaction->id] = $transaction->balance;
            $ids[] = $transaction->id;
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Applying batch update...');

        if ($this->option('dry-run')) {
            // Compare without updating
            $mismatches = 0;
            foreach ($balanceUpdates as $id => $calculatedBalance) {
                $stored = SupplierTransaction::find($id);
                if ($stored && abs($stored->balance - $calculatedBalance) > 0.01) {
                    $mismatches++;
                    $this->warn("Mismatch ID {$id}: stored={$stored->balance}, calculated={$calculatedBalance}");
                }
            }
            
            $this->info("✓ Dry-run complete. Found {$mismatches} balance mismatches.");
            
            if ($mismatches > 0) {
                $this->error('⚠ Snapshot drift detected! Run without --dry-run to fix.');
                return 1;
            }
            
            $this->info('✓ No drift detected. Balances are consistent.');
            return 0;
        }

        DB::transaction(function () use ($balanceUpdates, $ids) {
            if (!empty($balanceUpdates)) {
                $caseWhen = 'CASE id ';
                foreach ($balanceUpdates as $id => $balance) {
                    $caseWhen .= "WHEN {$id} THEN {$balance} ";
                }
                $caseWhen .= 'END';
                
                DB::table('supplier_transactions')
                    ->whereIn('id', $ids)
                    ->update(['balance' => DB::raw($caseWhen)]);
            }
        });

        $this->info('✓ Ledger rebuild complete.');
        $this->info("Updated " . count($balanceUpdates) . " transaction balances.");
        $this->info("Final running balance: {$runningBalance}");

        // Log critical event for monitoring
        \App\Services\LedgerService::logLedgerRebuild($supplierId, $siteId);

        return 0;
    }
}
