<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use Illuminate\Support\Facades\DB;

class DailyConsumptionSeeder extends Seeder
{
    public function run()
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all daily consumption data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ DailyConsumptionSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('daily_consumption_details')->truncate(); // DANGEROUS: Deletes all data!
        DB::table('daily_consumption_masters')->truncate(); // DANGEROUS: Deletes all data!
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        */

        for ($i = 1; $i <= 10; $i++) {
            $consumptionType = $i % 2 === 0 ? 'fuel' : 'all';
            $machineryType = $consumptionType === 'fuel' ? ($i % 3 === 0 ? 'rental' : 'own') : null;
            $machineryId = $machineryType === 'own' ? rand(1, 5) : null;

            $master = DailyConsumptionMaster::create([
                'consumption_number' => 'DCM-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'consumption_date' => now()->subDays($i),
                'consumption_type' => $consumptionType,
                'machinery_type' => $machineryType,
                'machinery_id' => $machineryId,
                'site_id' => rand(1, 5),
                'status' => '0',
                'created_by' => 1,
                'workspace_id' => 1,
                'consumption_file' => null,
            ]);

            for ($j = 1; $j <= 3; $j++) {
                DailyConsumptionDetails::create([
                    'daily_consumption_master_id' => $master->id,
                    'material_id' => $j,
                    'quantity' => rand(10, 100),
                    'unit' => 'kg',
                    'remarks' => "Consumption entry $j for {$master->consumption_number}",
                ]);
            }
        }
    }
}
