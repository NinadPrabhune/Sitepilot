<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProjectUserService;
use Illuminate\Support\Facades\Log;

class AssignAdminsToProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:assign-admins 
                            {--workspace= : Workspace ID to filter projects and users}
                            {--bulk : Use bulk assignment for better performance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign all Admin and Company users to all projects (existing and new)';

    /**
     * Execute the console command.
     */
    public function handle(ProjectUserService $projectUserService): int
    {
        $this->info('Starting project admin assignment...');
        
        $workspaceId = $this->option('workspace');
        $useBulk = $this->option('bulk');

        if ($workspaceId) {
            $this->info("Filtering by workspace ID: {$workspaceId}");
        }

        try {
            if ($useBulk) {
                $this->info('Using bulk assignment method for better performance...');
                $result = $projectUserService->bulkAssignAdminsToAllProjects($workspaceId);
            } else {
                $this->info('Using standard assignment method...');
                $result = $projectUserService->assignAdminsToAllProjects($workspaceId);
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Projects Processed', $result['projects_processed']],
                    ['Users Assigned', $result['users_assigned']],
                    ['Users Skipped (already assigned)', $result['users_skipped'] ?? 'N/A'],
                ]
            );

            $this->newLine();
            $this->info('✓ Project admin assignment completed successfully!');
            
            // Log the results
            Log::info('Project admin assignment completed', [
                'workspace_id' => $workspaceId,
                'bulk_mode' => $useBulk,
                'projects_processed' => $result['projects_processed'],
                'users_assigned' => $result['users_assigned'],
                'users_skipped' => $result['users_skipped'] ?? 0,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Error during project admin assignment: ' . $e->getMessage());
            
            Log::error('Project admin assignment failed', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}