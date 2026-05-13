<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        
          // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table
        DB::table('attendances')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        
        $userIds = [1, 2, 3];
        $siteIds = DB::table('projects')->select('id', 'workspace')->get();

        $startDate = Carbon::now()->subMonths(4)->startOfMonth();
        $endDate   = Carbon::now();

        $dates = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekend()) continue; // skip weekends
            $dates[] = $date->copy();
        }

        foreach ($userIds as $userId) {
            foreach ($dates as $date) {
                $randomSite = $siteIds->random();

                DB::table('attendances')->insert([
                    'employee_id'        => $userId,
                    'date'               => $date->toDateString(),
                    'status'             => 'Present',
                    'clock_in'           => '09:00:00',
                    'clock_out'          => '17:00:00',
                    'late'               => null,
                    'early_leaving'      => null,
                    'overtime'           => null,
                    'total_rest'         => '01:00:00',

                    'clock_in_latitude'  => '18.5204',   // Pune sample lat
                    'clock_in_longitude' => '73.8567',   // Pune sample long
                    'clock_out_latitude' => '18.5204',
                    'clock_out_longitude'=> '73.8567',
                    'clock_in_image'     => 'in_image.png',
                    'clock_out_image'    => 'out_image.png',

                    'workspace'          => $randomSite->workspace,
                    'site_id'            => $randomSite->id,
                    'created_by'         => 1,

                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }
}
