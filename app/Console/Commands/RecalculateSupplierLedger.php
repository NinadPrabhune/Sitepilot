<?php

namespace App\Console\Commands;

use App\Models\SupplierTransaction;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateSupplierLedger extends Command
{
    protected $signature = 'ledger:recalculate 
                            {--supplier_id= : Specific supplier ID to recalculate}
                            {--site_id= : Specific site ID to recalculate}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Recalculate supplier ledger running balances';

    public function handle()
    {
        $supplierId = $this->option('supplier_id');
        $siteId = $this->option('site_id');
        $dryRun = $this->option('dry-run');

        $this->info('Starting Supplier Ledger Recalculation...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $suppliers = $supplierId 
            ? Supplier::where('id', $supplierId)->get() 
            : Supplier::all();

        $totalSuppliers = $suppliers->count();
        $processed = 0;
        $updated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($totalSuppliers);
        $bar->start();

        foreach ($suppliers as $supplier) {
            try {
                $query = SupplierTransaction::where('supplier_id', $supplier->id);
                
                if ($siteId) {
                    $query->where('site_id', $siteId);
                }

                $transactions = $query->orderedByDate()->get();

                if ($transactions->isEmpty()) {
                    $bar->advance();
                    continue;
                }

                $runningBalance = 0;
                $hasDrift = false;

                foreach ($transactions as $transaction) {
                    $expectedBalance = $runningBalance + $transaction->debit - $transaction->credit;
                    
                    if (abs($transaction->balance - $expectedBalance) > 0.01) {
                        $hasDrift = true;
                        
                        if (!$dryRun) {
                            $transaction->balance = $expectedBalance;
                            $transaction->save();
                        }
                    }
                    
                    $runningBalance = $expectedBalance;
                }

                if ($hasDrift) {
                    $updated++;
                    $this->line(" Supplier {$supplier->name} (ID: {$supplier->id}): Balance corrected");
                }
                
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error(" Error processing supplier {$supplier->name}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Recalculation Complete!");
        $this->info("Total Suppliers Processed: {$processed}");
        $this->info("Suppliers with Balance Drift: {$updated}");
        $this->info("Errors: {$errors}");

        if ($dryRun) {
            $this->warn('This was a DRY RUN. No actual changes were made.');
            $this->info('Run without --dry-run to apply changes.');
        }

        return 0;
    }
}