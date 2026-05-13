<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // Material categories are reference data that should exist without wiping existing data

        $categories = [
            'Building Materials',
            'Fuels',
            'Tools & Equipment',
            'Lubricants',
            'Plumbing Materials',
            'Electrical Items',
            'Finishing Materials',
            'Doors & Windows',
            'Exterior & Landscaping',
        ];

        foreach ($categories as $category) {
            DB::table('material_categories')->updateOrInsert(
                ['name' => $category], // Unique key
                [
                    'site_id' => null,
                    'created_by' => 1,
                    'workspace_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
