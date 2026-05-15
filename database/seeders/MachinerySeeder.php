<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Machinery;

class MachinerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run()
     {
         DB::statement('SET FOREIGN_KEY_CHECKS=0;');
         DB::table('machineries')->truncate();
         DB::statement('SET FOREIGN_KEY_CHECKS=1;');

         // Get sites with workspace info for proper relations
         
         
//        $sites = DB::table('projects')->select('id', 'workspace')->get();
        
         
         $sites = DB::table('projects')->select('id', 'workspace')->where('id', 1)->get();
        

if ($sites->isEmpty()) {
             $this->command->warn('No projects found. Skipping MachinerySeeder.');
             return;
         }

        // Get available categories
        $categories = DB::table('machinery_categories')->pluck('id')->toArray();
        if (empty($categories)) {
            $categories = [1, 2, 3, 4]; // fallback IDs
        }

        // Get available suppliers for rental machinery
        $suppliers = DB::table('suppliers')->pluck('id')->toArray();
        if (empty($suppliers)) {
            $suppliers = [1, 2, 3, 4, 5]; // fallback IDs
        }

        $manufacturers = ['JCB', 'Tata Hitachi', 'ACE', 'Voltas', 'Kirloskar', 'Komatsu', 'Hyundai', 'Liebherr'];
        $rateTypes = ['hourly', 'daily', 'monthly'];
//        $operationalStatuses = ['active', 'breakdown', 'scrap'];
        
        $operationalStatuses = ['active'];

        // Create 12 Owned Machinery
        for ($i = 1; $i <= 12; $i++) {
            $randomSite = $sites->random();
            $purchaseDate = Carbon::now()->subDays(rand(365, 1095));

            Machinery::create([
                'name' => 'Owned Machinery ' . $i,
                'category_id' => $categories[array_rand($categories)],
                'model_number' => 'MDL-' . rand(100, 999) . '-' . chr(rand(65, 90)),
                'manufacturer' => $manufacturers[array_rand($manufacturers)],
                'vehicle_number' => 'MH' . rand(10, 99) . chr(rand(65, 90)) . chr(rand(65, 90)) . rand(1000, 9999),
                'owned_by' => 'owned',
                'supplier_id' => null,
                'operational_status' => $operationalStatuses[array_rand($operationalStatuses)],
                'site_id' => $randomSite->id,
                'workspace_id' => $randomSite->workspace,
                'created_by' => 1,
                'status' => '0',
                'description' => 'Company owned machinery for construction operations.',
                'remarks' => 'Auto-generated owned machinery record.',
                'capacity' => rand(5, 50) . ' tons',

                // Owned-specific fields
                'purchase_date' => $purchaseDate,
                'purchase_value' => rand(500000, 5000000),
                'insurance_due_date' => Carbon::now()->addDays(rand(30, 365)),
                'puc_due_date' => Carbon::now()->addDays(rand(30, 180)),
                'fitness_due_date' => Carbon::now()->addDays(rand(30, 180)),
                'last_service_date' => Carbon::now()->subDays(rand(30, 90)),
                'maintenance_schedule' => Carbon::now()->addDays(rand(30, 180)),
                'ownership_documents_file' => null,

                // Rental fields - null for owned
                'rate_type' => null,
                'rate' => null,
                'minimum_billing_hours' => null,
                'diesel_by_company' => null,
                'operator_by_supplier' => null,
                'number_of_operators' => null,
                'rental_agreement_file' => null,
            ]);
        }

        // Create 8 Rental Machinery
        for ($i = 1; $i <= 8; $i++) {
            $randomSite = $sites->random();
            $rateType = $rateTypes[array_rand($rateTypes)];

            // Set rate based on rate type
            $rate = match($rateType) {
                'hourly' => rand(500, 2000),
                'daily' => rand(3000, 15000),
                'monthly' => rand(50000, 200000),
                default => rand(1000, 5000),
            };

            Machinery::create([
                'name' => 'Rental Machinery ' . $i,
                'category_id' => $categories[array_rand($categories)],
                'model_number' => 'RNT-' . rand(100, 999) . '-' . chr(rand(65, 90)),
                'manufacturer' => $manufacturers[array_rand($manufacturers)],
                'vehicle_number' => 'RNT' . rand(10, 99) . chr(rand(65, 90)) . chr(rand(65, 90)) . rand(1000, 9999),
                'owned_by' => 'rental',
                'supplier_id' => $suppliers[array_rand($suppliers)],
                'operational_status' => $operationalStatuses[array_rand($operationalStatuses)],
                'site_id' => $randomSite->id,
                'workspace_id' => $randomSite->workspace,
                'created_by' => 1,
                'status' => '0',
                'description' => 'Rental machinery from external supplier.',
                'remarks' => 'Auto-generated rental machinery record.',
                'capacity' => rand(5, 50) . ' tons',

                // Rental-specific fields
                'rate_type' => $rateType,
                'rate' => $rate,
                'minimum_billing_hours' => rand(4, 12),
                'diesel_by_company' => (bool)rand(0, 1),
                'operator_by_supplier' => (bool)rand(0, 1),
                'number_of_operators' => rand(1, 3),
                'rental_agreement_file' => null,

                // Owned fields - null for rental
                'purchase_date' => null,
                'purchase_value' => null,
                'insurance_due_date' => null,
                'puc_due_date' => null,
                'fitness_due_date' => null,
                'last_service_date' => null,
                'maintenance_schedule' => null,
                'ownership_documents_file' => null,
            ]);
        }

        $this->command->info('Machinery seeded successfully: 12 owned, 8 rental');
    }
}
