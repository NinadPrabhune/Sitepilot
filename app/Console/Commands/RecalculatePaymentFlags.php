<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;

class RecalculatePaymentFlags extends Command
{
    protected $signature = 'payments:recalculate-flags';
    protected $description = 'Recalculate payment flags for all purchase orders (deprecated)';

    public function handle(): int
    {
        $this->warn('The "payments:recalculate-flags" command is deprecated. Use "payments:recalculate-invoicing-status" instead.');
        
        $pos = PurchaseOrder::with(['items', 'invoices', 'payments'])->get();
        
        $this->info("Recalculating payment flags for " . $pos->count() . " purchase orders...");
        
        $updated = 0;
        foreach ($pos as $po) {
            // Map the current invoiced status to the old payment flag for comparison
            $oldFlag = $this->mapInvoicedStatusToPaymentFlag($po->invoiced_status ?? 'not_invoiced');
            
            // Update the invoiced status (via the deprecated method which now calls updateInvoicedStatus)
            $po->updatePaymentFlag();
            
            // Refresh to get the updated invoiced status
            $po->refresh();
            
            // Map the new invoiced status to the old payment flag
            $newFlag = $this->mapInvoicedStatusToPaymentFlag($po->invoiced_status ?? 'not_invoiced');
            
            if ($oldFlag !== $newFlag) {
                $this->line("PO #{$po->id} ({$po->po_number}): {$oldFlag} -> {$newFlag}");
                $updated++;
            }
        }
        
        $this->info("Completed. Updated {$updated} payment flags.");
        
        return Command::SUCCESS;
    }
    
    /**
     * Map invoiced status to legacy payment flag for display purposes.
     * 
     * @param string $status
     * @return string
     */
    protected function mapInvoicedStatusToPaymentFlag(string $status): string
    {
        return match ($status) {
            'not_invoiced' => 'Pending',
            'partially_invoiced' => 'Partial Received',
            'fully_invoiced' => 'Fully Received',
            default => 'Pending',
        };
    }
}