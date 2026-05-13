<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // Supplier categories are reference data that should exist without wiping existing data

        $categories = [
            ['name' => 'Subcontractors', 'description' => 'Specialized construction service providers'],
            ['name' => 'Material Suppliers', 'description' => 'Suppliers of raw and finished construction materials'],
            ['name' => 'Equipment Suppliers', 'description' => 'Vendors of construction tools and machinery'],
            ['name' => 'Interior & Finishing Suppliers', 'description' => 'Suppliers for interior design and finishing materials'],
            ['name' => 'Service Providers', 'description' => 'Support services like logistics, waste management, etc.'],
            ['name' => 'Technology Vendors', 'description' => 'Software and smart device providers for construction'],
            ['name' => 'Fuel & Lubricant Suppliers', 'description' => 'Suppliers of petrol, diesel, engine oil, and other fluids for vehicles and machinery'],
            ['name' => 'Fleet Fueling Services', 'description' => 'Vendors offering on-site or mobile fueling for construction equipment and transport vehicles'],
        ];

        foreach ($categories as $category) {
            DB::table('supplier_categories')->updateOrInsert(
                ['name' => $category['name']], // Unique key
                [
                    'description' => $category['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
