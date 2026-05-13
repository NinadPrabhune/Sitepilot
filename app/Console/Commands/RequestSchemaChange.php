<?php

namespace App\Console\Commands;

use App\Services\ProductionSafetyService;
use Illuminate\Console\Command;

class RequestSchemaChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:request {command_type} {command_details} {--environment= : Target environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Request approval for a schema change';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $commandType = $this->argument('command_type');
        $commandDetails = $this->argument('command_details');
        $environment = $this->option('environment') ?? config('app.env');

        // Validate command type
        $validTypes = ['migrate', 'seed', 'schema_change', 'destructive_command'];
        if (!in_array($commandType, $validTypes)) {
            $this->error("❌ Invalid command type. Valid types: " . implode(', ', $validTypes));
            return 1;
        }

        // Check if this environment requires approval
        if (!ProductionSafetyService::areDestructiveCommandsLocked($environment)) {
            $this->info("ℹ️  Environment '{$environment}' does not require approval for this operation");
            $this->info("You can run the command directly:");
            $this->line("  php artisan {$commandDetails}");
            return 0;
        }

        // Create approval request
        $requestId = ProductionSafetyService::createApprovalRequest(
            $commandType,
            $commandDetails,
            get_current_user(),
            $environment
        );

        $this->info("✅ Schema change request created successfully");
        $this->info("Request ID: {$requestId}");
        $this->info("Command Type: {$commandType}");
        $this->info("Command: {$commandDetails}");
        $this->info("Environment: {$environment}");
        $this->info("Requested by: " . get_current_user());
        $this->info("");
        $this->info("Next steps:");
        $this->info("1. An admin must approve this request:");
        $this->line("   php artisan schema:approve {$requestId} --reason=\"Production deployment\"");
        $this->info("2. Once approved, you can run the command:");
        $this->line("   php artisan {$commandDetails} --approval={$requestId}");
        $this->info("");
        $this->info("Request expires in 24 hours");

        return 0;
    }
}
