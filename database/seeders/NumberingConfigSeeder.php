<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NumberingConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('numbering_configs')->insert([
            ['module' => 'po', 'scope_type' => 'workspace', 'scope_id' => null, 'prefix' => 'PO', 'starting_number' => 1, 'padding_length' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['module' => 'indent', 'scope_type' => 'site', 'scope_id' => null, 'prefix' => 'IND', 'starting_number' => 1, 'padding_length' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['module' => 'grn', 'scope_type' => 'site', 'scope_id' => null, 'prefix' => 'GRN-', 'starting_number' => 1, 'padding_length' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['module' => 'invoice', 'scope_type' => 'site', 'scope_id' => null, 'prefix' => 'INV-', 'starting_number' => 1, 'padding_length' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['module' => 'payment', 'scope_type' => 'site', 'scope_id' => null, 'prefix' => 'PAY-', 'starting_number' => 1, 'padding_length' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
