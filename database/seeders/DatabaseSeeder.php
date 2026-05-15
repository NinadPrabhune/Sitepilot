<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkSpace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 🛡️ SEEDER PROTECTION: Block seeders in safe mode (default)
        if (config('app.safe_seed_only', true)) {
            Log::critical('🚨 SEEDER BLOCKED IN SAFE MODE', [
                'time' => now()->toDateTimeString(),
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            ]);
            $this->command->error('❌ Seeders are blocked in safe mode. Set SAFE_SEED_ONLY=false in .env to enable.');
            return;
        }

        //  FORENSIC LOGGING: Track when database seeders run
        Log::critical('🚨 DATABASE SEEDER RUNNING', [
            'time' => now()->toDateTimeString(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ]);

        // 🧱 ENVIRONMENT-AWARE SEEDER LOADING
        // Prod seeders: Safe reference data (no truncate, uses upsert)
        $this->call(EmailTemplates::class);
        $this->call(NotificationsTableSeeder::class);
        $this->call(Plans::class);
        $this->call(PermissionTableSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(DefultSetting::class);
        $this->call(LanguageTableSeeder::class);
        $this->call(PackagesName::class);

        // Safe reference data seeders (using upsert, safe for production)
        $this->call(UnitSeeder::class);
        $this->call(MaterialCategorySeeder::class);
        $this->call(MaterialSeeder::class);
        $this->call(GstMasterSeeder::class);
        $this->call(SupplierCategorySeeder::class);
        $this->call(ManPowerTypeSeeder::class);
        $this->call(AssetsToolsAndEquipmentSeeder::class);

        // 🛡️ DANGEROUS SEEDERS REMOVED FOR DATA PROTECTION:
        // - MachineryCategorySeeder (uses TRUNCATE)
        // - PaymentsModuleSeeder (uses TRUNCATE)

        // Global Master Data (run once, not per workspace)
        $this->call(DefaultMasterDataSeeder::class);

        // DEV seeders (safe for local environments)
        if (app()->environment('local')) {
            $this->ensureMultiWorkspaceSetup();

            $this->call(SupplierSeeder::class);
            $this->call(MachinerySeeder::class);
            $this->call(PurchaseInvoiceSeeder::class);
            $this->call(ManPowerMasterSeeder::class);
            $this->call(DailyProgressReportSeeder::class);
            $this->call(DailyConsumptionSeeder::class);
            $this->call(AttendanceSeeder::class);
            $this->call(MaterialTransferSeeder::class);
        }

        if (module_is_active('AIAssistant')) {
            $this->call(AIAssistantTemplateListTableSeeder::class);
        }
    }

    /**
     * Create multiple workspaces with projects, users, and mappings.
     */
    private function ensureMultiWorkspaceSetup(): void
    {
        // Define workspaces
        $workspaces = [
            [
                'name' => 'WorkDo',
                'slug' => 'workdo',
                'created_by_user_type' => 'company',
            ],
            [
                'name' => 'Client Alpha',
                'slug' => 'client-alpha',
                'created_by_user_type' => 'company',
            ],
            [
                'name' => 'Client Beta',
                'slug' => 'client-beta',
                'created_by_user_type' => 'company',
            ],
        ];

        $projectDataByWorkspace = [
            1 => [ // WorkDo
                [
                    'name' => 'Head Office Construction',
                    'description' => 'Internal head office construction project',
                ],
                [
                    'name' => 'Project Site A',
                    'description' => 'Client A site project',
                ],
                [
                    'name' => 'Project Site B',
                    'description' => 'Client B site project',
                ],
            ],
            2 => [ // Client Alpha
                [
                    'name' => 'Alpha Commercial Complex',
                    'description' => 'Commercial complex project for Client Alpha',
                ],
                [
                    'name' => 'Alpha Residential Tower',
                    'description' => 'Residential tower project for Client Alpha',
                ],
            ],
            3 => [ // Client Beta
                [
                    'name' => 'Beta Industrial Park',
                    'description' => 'Industrial park project for Client Beta',
                ],
                [
                    'name' => 'Beta Warehouse Facility',
                    'description' => 'Warehouse facility project for Client Beta',
                ],
            ],
        ];

        // Step 1: Ensure workspaces exist
        foreach ($workspaces as $wsIndex => $wsData) {
            $existing = WorkSpace::where('slug', $wsData['slug'])->first();
            if ($existing) {
                continue;
            }

            // Find or create a company user for this workspace
            $companyUser = User::where('type', 'company')
                ->where('workspace_id', '!=', 0)
                ->where('workspace_id', function ($q) use ($wsData) {
                    $q->select('id')->from('work_spaces')->where('slug', $wsData['slug']);
                })
                ->first();

            if (!$companyUser) {
                $companyUser = new User();
                $companyUser->name = $wsData['name'] . ' Company';
                $companyUser->email = strtolower(str_replace(' ', '', $wsData['name'])) . '@example.com';
                $companyUser->password = Hash::make('1234');
                $companyUser->email_verified_at = now();
                $companyUser->type = 'company';
                $companyUser->active_status = 1;
                $companyUser->avatar = 'uploads/users-avatar/avatar.png';
                $companyUser->dark_mode = 0;
                $companyUser->lang = 'en';
                $companyUser->referral_code = rand(100000, 999999);
                $companyUser->workspace_id = 1; // temporary, will update
                $companyUser->created_by = 1;
                $companyUser->save();

                $role_r = \App\Models\Role::where('name', 'company')->first();
                if ($role_r) {
                    $companyUser->addRole($role_r);
                }
                $companyUser->MakeRole();
            }

            $workspace = WorkSpace::create([
                'name' => $wsData['name'],
                'slug' => $wsData['slug'],
                'created_by' => $companyUser->id,
                'status' => 1,
            ]);

            // Assign company user to workspace
            $companyUser->workspace_id = $workspace->id;
            $companyUser->active_workspace = $workspace->id;
            $companyUser->save();
            User::CompanySetting($companyUser->id);
        }

        // Step 2: Create projects for each workspace
        $allProjectIds = [];
        $allUserIds = DB::table('users')->pluck('id')->toArray();

        foreach ($workspaces as $wsIndex => $wsData) {
            $workspace = WorkSpace::where('slug', $wsData['slug'])->first();
            if (!$workspace) {
                continue;
            }

            $workspaceProjects = $projectDataByWorkspace[$wsIndex + 1] ?? [];
            foreach ($workspaceProjects as $projData) {
                $existing = DB::table('projects')
                    ->where('name', $projData['name'])
                    ->where('workspace', $workspace->id)
                    ->first();
                if ($existing) {
                    $allProjectIds[] = $existing->id;
                    continue;
                }

                $projectId = DB::table('projects')->insertGetId([
                    'name' => $projData['name'],
                    'description' => $projData['description'],
                    'status' => 'Ongoing',
                    'type' => 'project',
                    'start_date' => '2026-01-01',
                    'end_date' => '2027-12-31',
                    'budget' => rand(20000000, 100000000),
                    'currency' => 'INR',
                    'is_active' => 1,
                    'project_progress' => '0',
                    'progress' => '0',
                    'task_progress' => '0',
                    'estimated_hrs' => rand(1000, 5000),
                    'workspace' => $workspace->id,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $allProjectIds[] = $projectId;

                // Map all users to this project
                foreach ($allUserIds as $userId) {
                    DB::table('user_projects')->updateOrInsert(
                        ['user_id' => $userId, 'project_id' => $projectId],
                        ['user_id' => $userId, 'project_id' => $projectId, 'is_active' => 1]
                    );
                }
            }
        }
    }
}