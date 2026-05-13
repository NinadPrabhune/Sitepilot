<?php

namespace App\Console\Commands;

use App\Services\ProductionSafetyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApproveSchemaChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:approve {request_id} {--reason= : Approval reason}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a schema change request';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requestId = $this->argument('request_id');
        $reason = $this->option('reason');

        // Verify this is an admin operation
        if (!$this->isAdmin()) {
            $this->error('❌ Admin privileges required to approve schema changes');
            return 1;
        }

        // Get request details
        $request = DB::table('schema_change_approvals')
            ->where('request_id', $requestId)
            ->first();

        if (!$request) {
            $this->error("❌ Request '{$requestId}' not found");
            return 1;
        }

        if ($request->status !== 'pending') {
            $this->error("❌ Request '{$requestId}' is not pending (current status: {$request->status})");
            return 1;
        }

        if ($request->expires_at && $request->expires_at < now()) {
            $this->error("❌ Request '{$requestId}' has expired");
            return 1;
        }

        // Get current user ID (simplified for CLI)
        $userId = 1; // In real implementation, get from authenticated user

        // Approve the request
        if (ProductionSafetyService::approveRequest($requestId, $userId, $reason)) {
            $this->info("✅ Schema change request '{$requestId}' approved successfully");
            $this->info("Command: {$request->command_details}");
            $this->info("Environment: {$request->environment}");
            $this->info("Requested by: {$request->requested_by}");
            
            if ($reason) {
                $this->info("Reason: {$reason}");
            }
        } else {
            $this->error("❌ Failed to approve request '{$requestId}'");
            return 1;
        }

        return 0;
    }

    private function isAdmin(): bool
    {
        // In a real implementation, check actual admin permissions
        // For now, assume CLI access is admin-level
        return true;
    }
}
