<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Log;
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

        // 🛡️ DEV-ONLY SEEDERS DISABLED FOR DATA PROTECTION
        // All dev seeders use TRUNCATE and delete production data
        // They are now disabled even in local environment for safety
        
        if (app()->environment('local') && !config('app.safe_seed_only', true)) {
            $this->command->warn('⚠️  DEV seeders are disabled for data protection.');
            $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env (NOT RECOMMENDED)');
            
            // 🛡️ DANGEROUS SEEDERS COMMENTED OUT:
            // $this->call(SupplierSeeder::class);           // Uses TRUNCATE
            // $this->call(MachinerySeeder::class);          // Uses TRUNCATE  
            // $this->call(PurchaseInvoiceSeeder::class);    // Uses TRUNCATE
            // $this->call(ManPowerMasterSeeder::class);     // Uses TRUNCATE
            // $this->call(DailyProgressReportSeeder::class); // Uses TRUNCATE
            // $this->call(DailyConsumptionSeeder::class);    // Uses TRUNCATE
            // $this->call(AttendanceSeeder::class);          // Uses TRUNCATE
            // $this->call(MaterialTransferSeeder::class);    // Uses TRUNCATE
        }

        if(module_is_active('AIAssistant'))
        {
            $this->call(AIAssistantTemplateListTableSeeder::class);
        }
    }
}
