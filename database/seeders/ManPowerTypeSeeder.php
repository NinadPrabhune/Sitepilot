<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManPowerTypeSeeder extends Seeder
{
   public function run()
    {
        // 🛡️ SAFE PATTERN: Use upsert instead of truncate to prevent data loss
        // Manpower types are reference data that should exist without wiping existing data

        $manpowerTypes = [
            // Skilled Labor
            'Technician',
            'Electrician',
            'Plumber',
            'Welder',
            'Carpenter',
            'Machinist',
            'Operator (e.g., crane, forklift)',

            // Semi-Skilled Labor
            'Helper',
            'Assistant Technician',
            'Construction Worker',
            'Assembler',

            // Unskilled Labor
            'General Laborer',
            'Loader/Unloader',
            'Cleaner',
            'Peon',

            // Supervisory & Management
            'Supervisor',
            'Foreman',
            'Site Manager',
            'Project Coordinator',
            'Team Leader',

            // Professional & Technical Staff
            'Engineer (Civil, Mechanical, Electrical, etc.)',
            'Architect',
            'Quality Inspector',
            'Surveyor',
            'Planner',

            // Safety & Compliance
            'Safety Officer',
            'Fire Watch',
            'HSE (Health, Safety & Environment) Coordinator',
        ];

        foreach ($manpowerTypes as $type) {
            DB::table('man_power_types')->updateOrInsert(
                ['name' => $type], // Unique key
                [
                    'status' => 0,
                    'site_id' => 1,
                    'created_by' => 1,
                    'workspace_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
