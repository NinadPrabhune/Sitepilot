<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MachineryPaymentNumberingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Add default machinery payment numbering configuration
        \DB::table('numbering_configs')->updateOrInsert(
            [
                'module' => 'machinery_payment',
                'scope_type' => 'site',
                'scope_id' => null, // Global configuration
            ],
            [
                'prefix' => 'MPAY-',
                'starting_number' => 1,
                'padding_length' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $this->command->info('Default machinery payment numbering configuration added.');
    }
}
