<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;

class RecalculatePaymentFlags extends Command
{
    protected $signature = 'payments:recalculate-flags';
    protected $description = 'Recalculate payment_flag for all purchase orders';

    public function handle(): int
    {
        $pos = PurchaseOrder::with(['items', 'invoices', 'payments'])->get();
        
        $this->info("Recalculating payment flags for " . $pos->count() . " purchase orders...");
        
        $updated = 0;
        foreach ($pos as $po) {
            $oldFlag = $po->payment_flag;
            $po->updatePaymentFlag();
            
            if ($oldFlag !== $po->payment_flag) {
                $this->line("PO #{$po->id} ({$po->po_number}): {$oldFlag} -> {$po->payment_flag}");
                $updated++;
            }
        }
        
        $this->info("Completed. Updated {$updated} payment flags.");
        
        return Command::SUCCESS;
    }
}