<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MachineryCategorySeeder extends Seeder
{
    public function run()
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all machinery category data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ MachineryCategorySeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Truncate existing categories - DANGEROUS: Deletes all machinery category data!
        DB::table('machinery_categories')->truncate();
        */

        $categories = [
            ['name' => 'JCB', 'description' => 'Backhoe loaders and excavators used for digging, loading, and material handling. JCB is a leading construction equipment brand in India.'],
            ['name' => 'Hydra', 'description' => 'Hydraulic cranes used for lifting and moving heavy materials on construction sites. Commonly used for loading, unloading, and erection work.'],
            ['name' => 'Tractor', 'description' => 'Agricultural and construction tractors used for pulling heavy loads, earthmoving attachments, and site transportation.'],
            ['name' => 'Crane', 'description' => 'Tower cranes and mobile cranes used for lifting heavy construction materials and equipment to height at building sites.'],
        ];

        foreach ($categories as $category) {
            DB::table('machinery_categories')->insert([
                'name' => $category['name'],
                'description' => $category['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

