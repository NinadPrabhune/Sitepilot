<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all invoice data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ PurchaseInvoiceSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all invoice data!
        DB::table('purchase_invoices')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');


        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all invoice item data!
        DB::table('purchase_invoice_items')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        */

        $SupplierIds = DB::table('suppliers')->select('id','site_id', 'workspace_id')->get();

        for ($i = 1; $i <= 10; $i++) {
            
            $randomSupplier = $SupplierIds->random();
            
            $invoice = PurchaseInvoice::create([
                'invoice_number' => 'INV-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'invoice_date' => Carbon::now()->subDays(rand(1, 30)),
                'supplier_invoice_number' => 'SUP-INV-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $randomSupplier->id, 
                'total_amount' => 0, // will be updated after items
                'status' => ['Pending', 'Approved', 'Cancelled'][rand(0, 2)],                
                'created_by' => 1,               
                'site_id' => $randomSupplier->site_id,
                'workspace_id' => $randomSupplier->workspace_id,
            ]);

            $total = 0;
            $itemCount = rand(1, 5);

            for ($j = 1; $j <= $itemCount; $j++) {
                $quantity = rand(1, 20);
                $price = rand(100, 1000);
                $subtotal = $quantity * $price;

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'material_id' => rand(1, 10), // assumes material IDs 1–10 exist
                    'quantity' => $quantity,
                    'unit' => ['kg', 'liters', 'pcs'][rand(0, 2)],
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $invoice->update(['total_amount' => $total]);
        }
    }
}
