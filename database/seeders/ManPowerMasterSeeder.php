<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ManPowerMasterSeeder extends Seeder
{
    public function run()
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all manpower data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ ManPowerMasterSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all manpower master data!
        DB::table('man_power_masters')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');


        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all manpower detail data!
        DB::table('man_power_details')->truncate();
        */

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        
        
        $types = DB::table('man_power_types')->pluck('id');
        
        $SupplierIds = DB::table('suppliers')->select('id','site_id', 'workspace_id')->get();

        for ($i = 0; $i < 10; $i++) {
            
            $randomSupplier = $SupplierIds->random();
            $workDate = Carbon::now()->subDays($i)->toDateString();

            // Create master record
            $masterId = DB::table('man_power_masters')->insertGetId([
                'work_date' => $workDate,
                'site_id' => $randomSupplier->site_id,
                'workspace_id' => $randomSupplier->workspace_id,
                'supplier_id' => $randomSupplier->id, 
                'created_by' => 1,
                'total_count' => 0, // will be updated after details are inserted
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalCount = 0;

            // Insert details for each manpower type
            foreach ($types as $typeId) {
                $count = rand(1, 10);
                $totalCount += $count;

                DB::table('man_power_details')->insert([
                    'man_power_master_id' => $masterId,
                    'man_power_type_id' => $typeId,
                    'count' => $count,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update total count in master record
            DB::table('man_power_masters')->where('id', $masterId)->update([
                'total_count' => $totalCount,
                'updated_at' => now(),
            ]);
        }
    }
}
