<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // Materials are reference data that should exist without wiping existing data

        $materials = [
            // Building Materials
            ['Cement', 'Building Materials'],
            ['Sand', 'Building Materials'],
            ['Bricks', 'Building Materials'],
            ['Concrete', 'Building Materials'],
            ['Steel', 'Building Materials'],
            ['Aggregates', 'Building Materials'],
            ['Blocks (AAC, concrete)', 'Building Materials'],

            // Tools & Equipment
            ['Shovel', 'Tools & Equipment'],
            ['Hammer', 'Tools & Equipment'],
            ['Drill machine', 'Tools & Equipment'],
            ['Concrete mixer', 'Tools & Equipment'],
            ['Vibrator', 'Tools & Equipment'],
            ['Measuring tape', 'Tools & Equipment'],
            ['Scaffolding', 'Tools & Equipment'],

            // Plumbing Materials
            ['PVC pipes', 'Plumbing Materials'],
            ['GI pipes', 'Plumbing Materials'],
            ['Faucets', 'Plumbing Materials'],
            ['Valves', 'Plumbing Materials'],
            ['Water tanks', 'Plumbing Materials'],
            ['Pipe fittings', 'Plumbing Materials'],

            // Electrical Items
            ['Wires', 'Electrical Items'],
            ['Switches', 'Electrical Items'],
            ['Bulbs', 'Electrical Items'],
            ['Circuit breakers', 'Electrical Items'],
            ['Conduits', 'Electrical Items'],
            ['Distribution boards', 'Electrical Items'],

            // Finishing Materials
            ['Paint', 'Finishing Materials'],
            ['Tiles', 'Finishing Materials'],
            ['Marble/Granite', 'Finishing Materials'],
            ['Glass', 'Finishing Materials'],
            ['Wallpaper', 'Finishing Materials'],
            ['False ceiling boards', 'Finishing Materials'],

            // Doors & Windows
            ['Wooden doors', 'Doors & Windows'],
            ['Aluminum frames', 'Doors & Windows'],
            ['Glass panels', 'Doors & Windows'],
            ['Hinges', 'Doors & Windows'],
            ['Locks', 'Doors & Windows'],
            ['Handles', 'Doors & Windows'],

            // Exterior & Landscaping
            ['Paving stones', 'Exterior & Landscaping'],
            ['Fencing materials', 'Exterior & Landscaping'],
            ['Garden soil', 'Exterior & Landscaping'],
            ['Outdoor lighting', 'Exterior & Landscaping'],
            ['Drainage pipes', 'Exterior & Landscaping'],

            // Fuels & Lubricants

            ['Petrol', 'Fuels'],
            ['Diesel', 'Fuels'],
            ['CNG', 'Fuels'],
            ['LPG', 'Fuels'],
            ['Electric', 'Fuels'],
            ['Hybrid', 'Fuels'],
            ['Bio-Diesel', 'Fuels'],
            ['Ethanol', 'Fuels'],
            ['Methanol', 'Fuels'],
            ['Hydrogen', 'Fuels'],
            ['Solar', 'Fuels'],
            ['Engine Oil', 'Lubricants'],
            ['Hydraulic Oil', 'Lubricants'],
            ['Transmission Fluid', 'Lubricants'],
            ['Grease', 'Lubricants'],
            ['Coolant', 'Lubricants'],
            ['Brake Fluid', 'Lubricants'],
        ];

        // Unit mapping logic
        $unitMap = [
            'Cement' => 'Bag',
            'Sand' => 'Cubic Meter',
            'Bricks' => 'Number',
            'Concrete' => 'Cubic Meter',
            'Steel' => 'Kilogram',
            'Aggregates' => 'Cubic Meter',
            'Blocks (AAC, concrete)' => 'Number',

            'Shovel' => 'Number',
            'Hammer' => 'Number',
            'Drill machine' => 'Number',
            'Concrete mixer' => 'Number',
            'Vibrator' => 'Number',
            'Measuring tape' => 'Number',
            'Scaffolding' => 'Square Meter',

            'PVC pipes' => 'Meter',
            'GI pipes' => 'Meter',
            'Faucets' => 'Number',
            'Valves' => 'Number',
            'Water tanks' => 'Number',
            'Pipe fittings' => 'Number',

            'Wires' => 'Meter',
            'Switches' => 'Number',
            'Bulbs' => 'Number',
            'Circuit breakers' => 'Number',
            'Conduits' => 'Meter',
            'Distribution boards' => 'Number',

            'Paint' => 'Liter',
            'Tiles' => 'Square Meter',
            'Marble/Granite' => 'Square Meter',
            'Glass' => 'Square Meter',
            'Wallpaper' => 'Square Meter',
            'False ceiling boards' => 'Square Meter',

            'Wooden doors' => 'Number',
            'Aluminum frames' => 'Number',
            'Glass panels' => 'Square Meter',
            'Hinges' => 'Number',
            'Locks' => 'Number',
            'Handles' => 'Number',

            'Paving stones' => 'Square Meter',
            'Fencing materials' => 'Meter',
            'Garden soil' => 'Cubic Meter',
            'Outdoor lighting' => 'Number',
            'Drainage pipes' => 'Meter',

            'Petrol' => 'Liter',
            'Diesel' => 'Liter',
            'Engine Oil' => 'Liter',
            'Hydraulic Oil' => 'Liter',
            'Transmission Fluid' => 'Liter',
            'Grease' => 'Kilogram',
            'Coolant' => 'Liter',
            'Brake Fluid' => 'Liter',
        ];

        foreach ($materials as [$name, $categoryName]) {
            $category = DB::table('material_categories')->where('name', $categoryName)->first();
            $unitName = $unitMap[$name] ?? 'Number';
            $unit = DB::table('units')->where('name', $unitName)->first();

            if ($category && $unit) {
                DB::table('materials')->updateOrInsert(
                    ['name' => $name], // Unique key
                    [
                        'sku' => Str::slug($name) . '-' . Str::random(5),
                        'category_id' => $category->id,
                        'unit_id' => $unit->id,
                        'description' => 'High-quality ' . strtolower($name) . ' used in construction.',
                        'price' => rand(100, 5000),
                        'reorder_level' => rand(5, 50),
                        'status' => 'active',
                        'image' => null,
                        'created_by' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
