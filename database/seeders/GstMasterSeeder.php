<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GstMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // GST rates are reference data that should exist without wiping existing data

        $gstRecords = [
            [
                'name' => 'GST 5%',
                'cgst' => 2.5,
                'sgst' => 2.5,
                'igst' => 5,
                'total_gst' => 5.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'GST 12%',
                'cgst' => 6,
                'sgst' => 6,
                'igst' => 12,
                'total_gst' => 12.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'GST 18%',
                'cgst' => 9,
                'sgst' => 9,
                'igst' => 18,
                'total_gst' => 18.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'GST 28%',
                'cgst' => 14,
                'sgst' => 14,
                'igst' => 28,
                'total_gst' => 28.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($gstRecords as $record) {
            DB::table('gst_masters')->updateOrInsert(
                ['name' => $record['name']], // Unique key
                $record
            );
        }
    }
}
