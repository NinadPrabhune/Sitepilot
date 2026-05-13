<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all supplier data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ SupplierSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all supplier data!
        DB::table('suppliers')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        */

        $suppliers = [];

        $names = [
            'Shivam Constructions',
            'Raj Materials',
            'ACE Equipment Rentals',
            'InteriorCraft Solutions',
            'GreenLogix Services',
            'BuildTech Software Pvt Ltd',
            'Bharat Petroleum Distributors',
            'Hindustan Petroleum Product Vendors',
            'Shell Petroleum',
        ];

        $types = ['company', 'individual'];
        $cities = ['Pune', 'Mumbai', 'Nagpur', 'Nashik', 'Aurangabad'];
        $states = ['Maharashtra'];
        $banks = ['SBI', 'HDFC', 'ICICI', 'Axis', 'Bank of Maharashtra'];

        for ($i = 0; $i < 20; $i++) {
            
            $category_id = ($i % 8) + 1; // rotate through 1–6
            $suppliers[] = [
                'name' => $names[$category_id - 1] . ' ' . ($i + 1),
                'category_id' => $category_id,
                'type' => $types[rand(0, 1)],
                'contact_person' => 'Contact ' . ($i + 1),
                'phone' => '91' . rand(7000000000, 9999999999),
                'email' => 'supplier' . ($i + 1) . '@example.com',
                'address' => 'Plot ' . rand(1, 100) . ', Industrial Area',
                'city' => $cities[array_rand($cities)],
                'state' => $states[0],
                'pincode' => rand(411001, 411099),
                'country' => 'India',
                'upi_screenshot_1' => null,
                'upi_screenshot_2' => null,
                'gst_number' => '27ABCDE' . rand(1000, 9999) . 'Z5',
                'pan_number' => 'ABCDE' . rand(1000, 9999) . 'F',
                'registration_number' => 'REG' . rand(10000, 99999),
                'bank_name' => $banks[array_rand($banks)],
                'account_number' => rand(1000000000, 9999999999),
                'ifsc_code' => 'SBIN000' . rand(1000, 9999),
                'payment_terms' => 'Net ' . [15, 30, 45][rand(0, 2)],
                'created_by' => 1,
                'is_active' => true,
                'status' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('suppliers')->insert($suppliers);
    }
}
