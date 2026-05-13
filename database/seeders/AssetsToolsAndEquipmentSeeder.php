<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AssetsToolsAndEquipmentSeeder extends Seeder {

    public function run(): void {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table
        DB::table('assets_tools_and_equipment')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Fetch material IDs with category_id = 3
        $validMaterialIds = DB::table('materials')
                ->where('category_id', 3)
                ->pluck('id')
                ->toArray();

        // If no valid materials found, exit early
        if (empty($validMaterialIds)) {
            return;
        }

        $tools = [];

         $siteIds = DB::table('projects')->select('id', 'workspace')->get();
        
        // If no projects found, use default site_id = 1 and workspace_id = 1
        if ($siteIds->isEmpty()) {
            for ($i = 1; $i <= 20; $i++) {
                $tools[] = [
                    'material_id' => $validMaterialIds[array_rand($validMaterialIds)],
                    'quantity' => rand(1, 5),
                    'operational_status' => ['active', 'breakdown', 'scrap'][rand(0, 2)],
                    'site_id' => 1,
                    'workspace_id' => 1,
                    'status' => '0',
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
            for ($i = 1; $i <= 20; $i++) {
                $randomSite = $siteIds->random();
            
                $tools[] = [
                    'material_id' => $validMaterialIds[array_rand($validMaterialIds)],
                    'quantity' => rand(1, 5),
                    'operational_status' => ['active', 'breakdown', 'scrap'][rand(0, 2)],
                     'site_id' => $randomSite->id,
                    'workspace_id' => $randomSite->workspace,
                    'status' => '0',
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('assets_tools_and_equipment')->insert($tools);
    }
}
