<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NinadAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // User ninad.easy2it@gmail.com has users table ID 28 but employees table ID 43
        $userTableId = 28;
        $employeeId = 43; // Correct employee_id from employees table
        $userWorkspaceId = 1;
        
        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Remove existing attendance records for this user to avoid duplicates
        DB::table('attendances')->where('employee_id', $employeeId)->delete();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // Get available sites/projects from user's workspace only
        $siteIds = DB::table('projects')
                    ->where('workspace', $userWorkspaceId)
                    ->select('id', 'workspace')
                    ->get();
        
        // Generate attendance from January 1, 2026 to today (May 7, 2026)
        $startDate = Carbon::create(2026, 1, 1);
        $endDate   = Carbon::now(); // Current date: May 7, 2026

        $dates = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Skip weekends (Saturday and Sunday)
            if ($date->isWeekend()) continue;
            
            // Skip some random days for realism (sick days, vacation, etc.)
            if (rand(1, 20) === 1) continue; // Skip ~5% of days
            
            $dates[] = $date->copy();
        }

        foreach ($dates as $date) {
            $randomSite = $siteIds->random();
            
            // Generate realistic clock-in times (between 8:45 AM and 9:15 AM)
            $clockInHour = 8 + rand(0, 1); // 8 or 9
            $clockInMinute = $clockInHour === 8 ? rand(45, 59) : rand(0, 15);
            $clockIn = sprintf('%02d:%02d:00', $clockInHour, $clockInMinute);
            
            // Generate realistic clock-out times (between 5:30 PM and 7:00 PM)
            $clockOutHour = rand(17, 19);
            $clockOutMinute = $clockOutHour === 19 ? 0 : rand(0, 59);
            if ($clockOutHour === 19) $clockOutHour = 18; // Keep it reasonable
            $clockOut = sprintf('%02d:%02d:00', $clockOutHour, $clockOutMinute);
            
            // Calculate late time (company start time is 9:00 AM)
            $companyStartTime = '09:00:00';
            $lateSeconds = strtotime($clockIn) - strtotime($date->format('Y-m-d') . ' ' . $companyStartTime);
            $late = $lateSeconds > 0 ? gmdate('H:i:s', $lateSeconds) : '00:00:00';
            
            // Calculate early leaving (company end time is 6:00 PM)
            $companyEndTime = '18:00:00';
            $earlyLeavingSeconds = strtotime($date->format('Y-m-d') . ' ' . $companyEndTime) - strtotime($date->format('Y-m-d') . ' ' . $clockOut);
            $earlyLeaving = $earlyLeavingSeconds > 0 ? gmdate('H:i:s', $earlyLeavingSeconds) : '00:00:00';
            
            // Calculate overtime
            $overtimeSeconds = strtotime($date->format('Y-m-d') . ' ' . $clockOut) - strtotime($date->format('Y-m-d') . ' ' . $companyEndTime);
            $overtime = $overtimeSeconds > 0 ? gmdate('H:i:s', $overtimeSeconds) : '00:00:00';
            
            // Random rest time between 30-60 minutes
            $restMinutes = rand(30, 60);
            $totalRest = sprintf('00:%02d:00', $restMinutes);

            DB::table('attendances')->insert([
                'employee_id'        => $employeeId,
                'date'               => $date->toDateString(),
                'status'             => 'Present',
                'clock_in'           => $clockIn,
                'clock_out'          => $clockOut,
                'late'               => $late,
                'early_leaving'      => $earlyLeaving,
                'overtime'           => $overtime,
                'total_rest'         => $totalRest,

                'clock_in_latitude'  => '18.5204',   // Pune sample lat
                'clock_in_longitude' => '73.8567',   // Pune sample long
                'clock_out_latitude' => '18.5204',
                'clock_out_longitude'=> '73.8567',
                'clock_in_image'     => null,
                'clock_out_image'    => null,

                'workspace'          => $userWorkspaceId,
                'site_id'            => $randomSite->id,
                'created_by'         => 1,

                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
        
        $this->command->info('Attendance records created for ninad.easy2it@gmail.com from Jan 1, 2026 to ' . $endDate->format('Y-m-d'));
    }
}
