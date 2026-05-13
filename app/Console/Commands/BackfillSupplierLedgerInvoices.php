<?php

namespace App\Console\Commands;

use App\Models\SupplierTransaction;
use App\Models\PurchaseInvoice;
use App\Helpers\LedgerHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillSupplierLedgerInvoices extends Command
{
    protected $signature = 'supplier-ledger:backfill-invoices 
                            {--dry-run : Show what would be created without making changes}';
    protected $description = 'Backfill missing TYPE_INVOICE records in supplier_transactions (Invoice-based accounting)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $invoices = PurchaseInvoice::all();
        $created = 0;
        $skipped = 0;

        $this->info('Starting backfill of TYPE_INVOICE records...');
        $this->info('Using Invoice-based accounting model (debit = invoice amount)');
        $this->newLine();

        foreach ($invoices as $invoice) {
            $exists = SupplierTransaction::where('reference_type', 'invoice')
                ->where('reference_id', $invoice->id)
                ->exists();

            if (!$exists) {
                if ($dryRun) {
                    $this->line("Would create invoice entry for: {$invoice->invoice_number} (Amount: " . number_format($invoice->grand_total, 2) . ")");
                    $skipped++;
                } else {
                    // Calculate running balance - carry forward from previous transaction
                    $lastTransaction = SupplierTransaction::where('supplier_id', $invoice->supplier_id)
                        ->orderBy('transaction_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    $previousBalance = $lastTransaction ? $lastTransaction->balance : 0;

                    SupplierTransaction::create([
                        'supplier_id' => $invoice->supplier_id,
                        'site_id' => $invoice->site_id,
                        'reference_type' => 'invoice',
                        'reference_id' => $invoice->id,
                        'reference_amount' => $invoice->grand_total,
                        'transaction_date' => $invoice->invoice_date,
                        'debit' => $invoice->grand_total,  // Invoice-based: debit = invoice amount
                        'credit' => 0,
                        'balance' => $previousBalance + $invoice->grand_total,  // Calculate new balance
                        'description' => "{$invoice->invoice_number} / Invoice Generated / ₹" . number_format($invoice->grand_total, 2),
                        'workspace_id' => $invoice->workspace_id,
                        'created_by' => $invoice->created_by,
                    ]);
                    
                    Log::info('Backfilled invoice ledger entry', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'supplier_id' => $invoice->supplier_id,
                        'debit' => $invoice->grand_total,
                    ]);
                    
                    $created++;
                    $this->line("Created TYPE_INVOICE for invoice: {$invoice->invoice_number} (Debit: " . number_format($invoice->grand_total, 2) . ")");
                }
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        
        if (!$dryRun) {
            // Recalculate balances for all suppliers with transactions
            $this->info('Recalculating supplier balances...');
            
            $supplierIds = SupplierTransaction::pluck('supplier_id')->unique();
            $recalculated = 0;
            
            foreach ($supplierIds as $supplierId) {
                LedgerHelper::recalculateSupplierBalance($supplierId);
                $recalculated++;
            }
            
            $this->info("Recalculated balances for {$recalculated} supplier(s)");
        } else {
            $this->warn('Run without --dry-run to apply changes');
        }

        $this->newLine();
        $this->info("Backfill complete! Created {$created} records, skipped {$skipped} existing records.");

        return Command::SUCCESS;
    }
}