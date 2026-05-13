<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workdo\Hrm\Entities\Employee;
use Illuminate\Support\Facades\DB;

class UpdateEmployeeNamesToCamelCase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:update-names-camelcase {--dry-run : Preview changes without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all employee names to camel case (proper capitalization)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating employee names to camel case...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $employees = Employee::all();
        $updatedCount = 0;
        $changes = [];

        foreach ($employees as $employee) {
            $originalName = $employee->name;
            $camelCaseName = $this->toCamelCase($originalName);

            if ($originalName !== $camelCaseName) {
                $changes[] = [
                    'ID' => $employee->id,
                    'Original' => $originalName,
                    'Updated' => $camelCaseName,
                ];

                if (!$dryRun) {
                    $employee->name = $camelCaseName;
                    $employee->save();
                }

                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->info("Found {$updatedCount} employee(s) with names to update:");
            $this->table(
                ['ID', 'Original Name', 'Updated Name'],
                $changes
            );

            if (!$dryRun) {
                $this->newLine();
                $this->info("✓ Successfully updated {$updatedCount} employee name(s) to camel case");
            } else {
                $this->newLine();
                $this->info("To apply these changes, run: php artisan employees:update-names-camelcase");
            }
        } else {
            $this->info('✓ All employee names are already in camel case format');
        }

        $this->newLine();
        $this->info("Total employees processed: {$employees->count()}");

        return Command::SUCCESS;
    }

    /**
     * Convert string to camel case (proper capitalization)
     * 
     * @param string $string
     * @return string
     */
    private function toCamelCase($string)
    {
        // Convert to title case (first letter of each word uppercase, rest lowercase)
        return ucwords(strtolower(trim($string)));
    }
}
