<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\MaterialTransfer;
use App\Models\MaterialTransferItem;

class MaterialTransferSeeder extends Seeder
{
    public function run(): void
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all material transfer data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ MaterialTransferSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('material_transfer_items')->truncate(); // DANGEROUS: Deletes all data!
        DB::table('material_transfers')->truncate(); // DANGEROUS: Deletes all data!
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        */

        for ($i = 1; $i <= 10; $i++) {
            $transfer = MaterialTransfer::create([
                'record_number' => 'MT-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'record_date' => Carbon::now()->subDays(rand(1, 30)),
                'from_site_id' => rand(1, 3),
                'to_site_id' => rand(4, 6),
                'total_amount' => 0, // will be updated after items
                'status' => ['Pending', 'Approved', 'Rejected'][rand(0, 2)],
                'created_by' => 1,
                'workspace_id' => 1,
                'record_file' => null,
            ]);

            $total = 0;
            $itemCount = rand(1, 5);

            for ($j = 1; $j <= $itemCount; $j++) {
                $quantity = rand(1, 20);
                $price = rand(100, 1000);
                $subtotal = $quantity * $price;

                MaterialTransferItem::create([
                    'material_transfer_id' => $transfer->id,
                    'material_id' => rand(1, 10), // assumes material IDs 1–10 exist
                    'quantity' => $quantity,
                    'unit' => ['kg', 'liters', 'pcs'][rand(0, 2)],
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $transfer->update(['total_amount' => $total]);
        }
    }
}
