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
         DB::statement('SET FOREIGN_KEY_CHECKS=0;');
         DB::table('purchase_invoices')->truncate();
         DB::table('purchase_invoice_items')->truncate();
         DB::statement('SET FOREIGN_KEY_CHECKS=1;');

         PurchaseInvoice::unsetEventDispatcher();

         $SupplierIds = DB::table('suppliers')->select('id','site_id')->get();

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
                 'workspace_id' => 1,
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
