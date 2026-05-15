<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DailyProgressReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

use App\Models\WorkSpace;

use App\Models\User;
use Workdo\Taskly\Entities\Project;

class DailyProgressReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
     {
         DB::statement('SET FOREIGN_KEY_CHECKS=0;');
         DB::table('daily_progress_reports')->truncate();
         DB::statement('SET FOREIGN_KEY_CHECKS=1;');

         $user = User::first(); // or User::inRandomOrder()->first();
        $workspace = Workspace::first();
        $project = Project::first();

        $records = [
            [
                'date' => now()->subDays(4),
                'machine_start_reading' => 1200,
                'machine_end_reading' => 1300,
                'number_of_operators' => 3,
                'work_details' => 'Excavation and leveling work completed.',
                'diesel_consumption' => 45.50,
                'maintenance_notes' => 'Engine oil checked.',
                'machinery_advances' => 'Excavator moved to sector B.',
                'status' => 1,
            ],
            [
                'date' => now()->subDays(3),
                'machine_start_reading' => 1300,
                'machine_end_reading' => 1380,
                'number_of_operators' => 2,
                'work_details' => 'Foundation trenching started.',
                'diesel_consumption' => 38.20,
                'maintenance_notes' => 'Hydraulic pipe replaced.',
                'machinery_advances' => 'Bulldozer deployed.',
                'status' => 1,
            ],
            [
                'date' => now()->subDays(2),
                'machine_start_reading' => 1380,
                'machine_end_reading' => 1450,
                'number_of_operators' => 4,
                'work_details' => 'Material shifting and compaction.',
                'diesel_consumption' => 50.00,
                'maintenance_notes' => 'Air filter cleaned.',
                'machinery_advances' => 'Roller moved to zone C.',
                'status' => 0,
            ],
            [
                'date' => now()->subDays(1),
                'machine_start_reading' => 1450,
                'machine_end_reading' => 1520,
                'number_of_operators' => 3,
                'work_details' => 'Concrete mixing and pouring.',
                'diesel_consumption' => 42.75,
                'maintenance_notes' => 'Fuel injector checked.',
                'machinery_advances' => 'Mixer truck serviced.',
                'status' => 1,
            ],
            [
                'date' => now(),
                'machine_start_reading' => 1520,
                'machine_end_reading' => 1600,
                'number_of_operators' => 2,
                'work_details' => 'Site cleanup and finishing.',
                'diesel_consumption' => 30.10,
                'maintenance_notes' => 'Brake pads replaced.',
                'machinery_advances' => 'Excavator returned to base.',
                'status' => 1,
            ],
        ];

         $siteIds = DB::table('projects')->select('id', 'workspace')->get();
        foreach ($records as $record) {
            
            $randomSite = $siteIds->random();
            
            DailyProgressReport::create(array_merge($record, [
                'created_by' => $user->id,
                'site_id' => $randomSite->id,
                'workspace_id' => $randomSite->workspace,
            ]));
        }
    }
}
