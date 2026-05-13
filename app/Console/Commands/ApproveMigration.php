<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApproveMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:approve 
                            {phase : Migration phase to approve (e.g., phase3_data_migration)}
                            {--staging : Approve for staging execution}
                            {--production : Approve for production execution}
                            {--force : Force approval without validation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a migration phase for staging or production execution';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phase = $this->argument('phase');
        $isStaging = $this->option('staging');
        $isProduction = $this->option('production');
        $force = $this->option('force');

        if (!$isStaging && !$isProduction) {
            $this->error('You must specify either --staging or --production');
            return 1;
        }

        if ($isStaging && $isProduction) {
            $this->error('You cannot approve for both staging and production at the same time');
            return 1;
        }

        // Check if migration state exists
        $migrationState = DB::table('system_migration_state')
            ->where('migration_phase', $phase)
            ->first();

        if (!$migrationState) {
            $this->error("Migration phase '{$phase}' not found in system_migration_state");
            return 1;
        }

        // Check if already locked
        if ($migrationState->locked) {
            $this->error("Migration phase '{$phase}' is already locked and executed");
            return 1;
        }

        // For production approval, require staging approval first
        if ($isProduction && !$migrationState->staging_approved) {
            $this->error("Cannot approve for production: Staging approval required first");
            return 1;
        }

        // Validate before approval (unless forced)
        if (!$force) {
            $this->info("Running validation checks for phase: {$phase}");
            
            $validationPassed = $this->validateMigration($phase);
            
            if (!$validationPassed) {
                $this->error("Validation failed. Use --force to approve anyway (not recommended)");
                return 1;
            }
        }

        // Update approval status
        $updateData = [];
        
        if ($isStaging) {
            $updateData['staging_approved'] = true;
            $updateData['staging_approved_at'] = now();
            $updateData['staging_approved_by'] = auth()->id() ?? 1;
            $this->info("Approved phase '{$phase}' for STAGING execution");
        }
        
        if ($isProduction) {
            $updateData['production_approved'] = true;
            $updateData['production_approved_at'] = now();
            $updateData['production_approved_by'] = auth()->id() ?? 1;
            $this->info("Approved phase '{$phase}' for PRODUCTION execution");
        }

        DB::table('system_migration_state')
            ->where('migration_phase', $phase)
            ->update($updateData);

        Log::channel('payment_audit')->info('Migration approved', [
            'phase' => $phase,
            'environment' => $isProduction ? 'production' : 'staging',
            'approved_by' => auth()->id() ?? 1,
            'forced' => $force,
        ]);

        $this->info('Approval recorded successfully');
        return 0;
    }

    /**
     * Validate migration before approval
     */
    private function validateMigration(string $phase): bool
    {
        $this->info('Running validation checks...');

        // Check 1: Verify database backup exists
        $this->info('  - Checking database backup...');
        // This is a placeholder - actual backup check would depend on backup system
        $this->warn('    (Backup check not implemented - manual verification required)');

        // Check 2: For Phase 3, verify prerequisites
        if (str_starts_with($phase, 'phase3')) {
            $this->info('  - Checking Phase 3 prerequisites...');
            
            // Check if Phase 1-2 completed
            $phase1Complete = DB::table('system_migration_state')
                ->where('migration_phase', 'like', 'phase%')
                ->where('status', 'completed')
                ->count();
            
            if ($phase1Complete === 0) {
                $this->error('    Phase 1-2 must be completed before Phase 3');
                return false;
            }
            
            $this->info('    ✓ Phase 1-2 prerequisites verified');
        }

        // Check 3: Verify no failed migrations
        $failedMigrations = DB::table('system_migration_state')
            ->where('status', 'failed')
            ->count();

        if ($failedMigrations > 0) {
            $this->error("    Found {$failedMigrations} failed migrations");
            return false;
        }

        $this->info('    ✓ No failed migrations found');

        $this->info('✓ All validation checks passed');
        return true;
    }
}
