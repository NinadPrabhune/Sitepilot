<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Facades\ModuleFacade as Module;

class ModuleSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:setup {module} {--dry-run : Preview changes without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely setup a module with migrations and seeders via CLI only';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $module = $this->argument('module');
        $isDryRun = $this->option('dry-run');

        // 🔐 PRODUCTION WARNING + TYPED CONFIRMATION
        if (app()->environment('production') && !$isDryRun) {
            $this->warn('⚠️  You are running in PRODUCTION environment');
            $this->warn('⚠️  This will modify your database');
            $confirm = $this->ask('⚠️  Type MODULE NAME to confirm ('.$module.')');
            if ($confirm !== $module) {
                $this->error('❌ Confirmation failed. Aborting.');
                return self::FAILURE;
            }
        }

        if ($isDryRun) {
            $this->info("🔍 DRY RUN MODE - Preview only, no changes will be executed");
            $this->newLine();
        } else {
            $this->info("🛠 Setting up module: {$module}");
        }

        // 🚨 FORENSIC LOGGING
        Log::critical('🛠 MODULE SETUP STARTED', [
            'module' => $module,
            'dry_run' => $isDryRun,
            'executed_by' => 'CLI',
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Check if module exists
        $moduleObj = Module::find($module);
        if (!$moduleObj || !$moduleObj->name) {
            $this->error("❌ Module '{$module}' not found.");
            Log::error('❌ MODULE SETUP FAILED - Module not found', ['module' => $module]);
            return self::FAILURE;
        }

        // 🔒 LOCKING MECHANISM: Prevent double execution
        $lock = Cache::lock('module_setup_' . $module, 60);
        if (!$lock->get()) {
            $this->error('⚠️ Module setup already running for this module');
            Log::warning('⚠️ MODULE SETUP LOCKED - Already running', ['module' => $module]);
            return self::FAILURE;
        }

        try {
            // 🧱 MODULE VERSION CHECK: Prevent duplicate executions
            // Check if module is already installed in add_ons table
            $existingAddon = DB::table('add_ons')->where('module', $module)->first();
            if ($existingAddon && $existingAddon->is_enable == 1) {
                $this->info("ℹ️ Module '{$module}' is already installed and enabled.");
                $this->info("🎯 You can skip setup or force reinstall with --force flag.");
                Log::info('ℹ️ MODULE ALREADY INSTALLED', [
                    'module' => $module,
                    'addon_id' => $existingAddon->id,
                ]);
                return self::SUCCESS;
            }

            // 🧪 PRE-FLIGHT CHECK: Ensure database is properly initialized
            if (!Schema::hasTable('users') || !Schema::hasTable('migrations')) {
                $this->error("❌ Database not initialized properly.");
                $this->error("⚠️ Required tables (users, migrations) are missing.");
                $this->error("💡 Please run: php artisan migrate");
                Log::error('❌ MODULE SETUP FAILED - Database not initialized', [
                    'module' => $module,
                    'has_users_table' => Schema::hasTable('users'),
                    'has_migrations_table' => Schema::hasTable('migrations'),
                ]);
                return self::FAILURE;
            }

            // 🔍 DRY RUN MODE: Preview changes without executing
            if ($isDryRun) {
                $this->info("📋 DRY RUN PREVIEW");
                $this->newLine();
                $this->info("Migrations to run:");
                $this->info("  - Module migrations from: /packages/workdo/{$module}/src/Database/Migrations");
                $this->newLine();
                $this->info("Seeders to run:");
                $this->info("  - Package seeder: package:seed {$module}");
                $this->newLine();
                $this->info("⚠️ No changes will be executed in dry-run mode");
                $this->newLine();
                return self::SUCCESS;
            }

            // 📦 DB BACKUP SNAPSHOT: Ultimate safety before any changes
            $this->info("📦 Creating database backup snapshot...");
            $backupFile = $this->createDatabaseBackup($module);
            if ($backupFile) {
                $this->info("✅ Backup created: {$backupFile}");
                Log::critical('📦 DB BACKUP CREATED', [
                    'module' => $module,
                    'backup_file' => $backupFile,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            } else {
                $this->warn("⚠️ Backup failed, but proceeding with caution...");
                Log::warning('⚠️ DB BACKUP FAILED - Proceeding without backup', [
                    'module' => $module,
                ]);
            }

            // 🧱 SEPARATE EXECUTION: Migrations (non-transactional) + Seeders (transactional)
            // This prevents inconsistent DB state when migrations auto-commit but seeders rollback

            // STEP 1: Run migrations WITHOUT transaction (schema changes are not rollback-safe)
            $this->info("📦 Running migrations for {$module}...");
            Artisan::call("migrate --path=/packages/workdo/{$module}/src/Database/Migrations", [
                '--force' => true
            ]);
            $this->info("✅ Migrations completed for {$module}");

            // STEP 2: Wrap ONLY seeders in transaction (data changes are rollback-safe)
            DB::beginTransaction();

            try {
                // Run seeders
                $this->info("🌱 Running seeders for {$module}...");
                Artisan::call("package:seed {$module}");
                $this->info("✅ Seeders completed for {$module}");

                // Commit transaction if seeders succeeded
                DB::commit();

                Log::critical('✅ MODULE SETUP COMPLETED', [
                    'module' => $module,
                    'timestamp' => now()->toDateTimeString(),
                ]);

                $this->info("✅ Module {$module} setup completed successfully.");
                $this->info("🎯 You can now enable the module from the UI.");

                return self::SUCCESS;

            } catch (\Throwable $e) {
                // Rollback only seeders on error (migrations already applied)
                DB::rollBack();

                Log::critical('🚨 SEEDER FAILED - ROLLED BACK', [
                    'module' => $module,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("❌ Seeder failed: " . $e->getMessage());
                $this->error("🔄 Seeder changes rolled back (migrations preserved).");
                $this->error("💡 You can re-run: php artisan package:seed {$module}");

                return self::FAILURE;
            }

        } catch (\Throwable $e) {
            Log::critical('🚨 MODULE SETUP FAILED', [
                'module' => $module,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("❌ Module setup failed: " . $e->getMessage());
            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Create database backup snapshot before module setup
     *
     * @param string $module
     * @return string|null Backup file path or null if failed
     */
    protected function createDatabaseBackup(string $module): ?string
    {
        try {
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');

            // Create backup directory if it doesn't exist
            $backupDir = storage_path('backups');
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Generate backup filename
            $filename = "db_backup_{$module}_" . now()->format('Ymd_His') . '.sql';
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // Build mysqldump command with consistency flags
            // --single-transaction: Ensures consistent snapshot even during active writes
            // --quick: Retrieves rows one at a time (memory efficient)
            // --lock-tables=false: Prevents table locking (non-blocking)
            $command = sprintf(
                'mysqldump --single-transaction --quick --lock-tables=false -h %s -u %s %s %s > %s',
                escapeshellarg($host),
                escapeshellarg($username),
                $password ? '-p' . escapeshellarg($password) : '',
                escapeshellarg($database),
                escapeshellarg($backupPath)
            );

            // Execute backup command
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($backupPath)) {
                return $backupPath;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('❌ DB BACKUP CREATION FAILED', [
                'error' => $e->getMessage(),
                'module' => $module,
            ]);
            return null;
        }
    }
}
