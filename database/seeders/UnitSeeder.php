<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // This ensures reference data exists without wiping existing data

        $units = [
            ['name' => 'Number', 'symbol' => 'Nos'],
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Meter', 'symbol' => 'm'],
            ['name' => 'Square Meter', 'symbol' => 'm²'],
            ['name' => 'Cubic Meter', 'symbol' => 'm³'],
            ['name' => 'Ton', 'symbol' => 'T'],
            ['name' => 'Liter', 'symbol' => 'L'],
            ['name' => 'Bag', 'symbol' => 'bag'],
            ['name' => 'Foot', 'symbol' => 'ft'],
            ['name' => 'Inch', 'symbol' => 'in'],
        ];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                ['name' => $unit['name'], 'symbol' => $unit['symbol']], // Unique keys
                [
                    'created_by' => 1,
                    'workspace_id' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
