<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class UpdateUserNamesToCamelCase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-names-camelcase {--dry-run : Preview changes without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all user names to camel case (proper capitalization)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating user names to camel case...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $users = User::all();
        $updatedCount = 0;
        $changes = [];

        foreach ($users as $user) {
            $originalName = $user->name;
            $camelCaseName = $this->toCamelCase($originalName);

            if ($originalName !== $camelCaseName) {
                $changes[] = [
                    'ID' => $user->id,
                    'Original' => $originalName,
                    'Updated' => $camelCaseName,
                ];

                if (!$dryRun) {
                    $user->name = $camelCaseName;
                    $user->save();
                }

                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->info("Found {$updatedCount} user(s) with names to update:");
            $this->table(
                ['ID', 'Original Name', 'Updated Name'],
                $changes
            );

            if (!$dryRun) {
                $this->newLine();
                $this->info("✓ Successfully updated {$updatedCount} user name(s) to camel case");
            } else {
                $this->newLine();
                $this->info("To apply these changes, run: php artisan users:update-names-camelcase");
            }
        } else {
            $this->info('✓ All user names are already in camel case format');
        }

        $this->newLine();
        $this->info("Total users processed: {$users->count()}");

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
