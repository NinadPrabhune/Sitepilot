<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProductionSafetyService
{
    /**
     * Check if schema changes are locked for the environment
     */
    public static function isSchemaLocked(string $environment = null): bool
    {
        $environment = $environment ?? config('app.env');
        
        $lock = Cache::remember("safety_lock:schema_changes:{$environment}", 300, function () use ($environment) {
            return DB::table('production_safety_locks')
                ->where('lock_type', 'schema_changes')
                ->where('environment', $environment)
                ->where('is_locked', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->first();
        });

        return $lock !== null;
    }

    /**
     * Check if seeding is locked for the environment
     */
    public static function isSeedingLocked(string $environment = null): bool
    {
        $environment = $environment ?? config('app.env');
        
        $lock = Cache::remember("safety_lock:seeding:{$environment}", 300, function () use ($environment) {
            return DB::table('production_safety_locks')
                ->where('lock_type', 'seeding')
                ->where('environment', $environment)
                ->where('is_locked', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->first();
        });

        return $lock !== null;
    }

    /**
     * Check if destructive commands are locked for the environment
     */
    public static function areDestructiveCommandsLocked(string $environment = null): bool
    {
        $environment = $environment ?? config('app.env');
        
        $lock = Cache::remember("safety_lock:destructive_commands:{$environment}", 300, function () use ($environment) {
            return DB::table('production_safety_locks')
                ->where('lock_type', 'destructive_commands')
                ->where('environment', $environment)
                ->where('is_locked', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->first();
        });

        return $lock !== null;
    }

    /**
     * Log a destructive command attempt
     */
    public static function logDestructiveAttempt(
        string $command,
        string $fullCommand,
        string $user,
        string $ipAddress,
        string $blockSource,
        bool $wasBlocked = true,
        string $blockReason = null,
        array $context = []
    ): void {
        DB::table('destructive_command_attempts')->insert([
            'command' => $command,
            'full_command' => $fullCommand,
            'user' => $user,
            'ip_address' => $ipAddress,
            'environment' => config('app.env'),
            'block_source' => $blockSource,
            'was_blocked' => $wasBlocked,
            'block_reason' => $blockReason,
            'context' => json_encode($context),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear cache to refresh locks
        Cache::forget("safety_lock:destructive_commands:" . config('app.env'));
    }

    /**
     * Create a schema change approval request
     */
    public static function createApprovalRequest(
        string $commandType,
        string $commandDetails,
        string $requestedBy,
        string $environment = null
    ): string {
        $environment = $environment ?? config('app.env');
        $requestId = 'REQ-' . strtoupper(uniqid());
        
        DB::table('schema_change_approvals')->insert([
            'request_id' => $requestId,
            'command_type' => $commandType,
            'command_details' => $commandDetails,
            'requested_by' => $requestedBy,
            'environment' => $environment,
            'status' => 'pending',
            'expires_at' => now()->addHours(24), // Requests expire in 24 hours
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::critical('🔐 SCHEMA CHANGE APPROVAL REQUESTED', [
            'request_id' => $requestId,
            'command_type' => $commandType,
            'command_details' => $commandDetails,
            'requested_by' => $requestedBy,
            'environment' => $environment,
        ]);

        return $requestId;
    }

    /**
     * Check if an approval request is valid and approved
     */
    public static function isRequestApproved(string $requestId): bool
    {
        $request = DB::table('schema_change_approvals')
            ->where('request_id', $requestId)
            ->where('status', 'approved')
            ->where('expires_at', '>', now())
            ->first();

        return $request !== null;
    }

    /**
     * Approve a schema change request
     */
    public static function approveRequest(
        string $requestId,
        int $approvedBy,
        string $reason = null
    ): bool {
        $updated = DB::table('schema_change_approvals')
            ->where('request_id', $requestId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'approval_reason' => $reason,
                'updated_at' => now(),
            ]);

        if ($updated) {
            Log::critical('✅ SCHEMA CHANGE APPROVED', [
                'request_id' => $requestId,
                'approved_by' => $approvedBy,
                'reason' => $reason,
            ]);
        }

        return $updated > 0;
    }

    /**
     * Create an emergency override token
     */
    public static function createEmergencyOverride(
        string $lockType,
        string $environment,
        string $reason,
        int $createdBy,
        int $durationMinutes = 30
    ): string {
        $token = 'OVERRIDE-' . strtoupper(uniqid());
        
        DB::table('production_safety_locks')
            ->where('lock_type', $lockType)
            ->where('environment', $environment)
            ->update([
                'override_token' => $token,
                'expires_at' => now()->addMinutes($durationMinutes),
                'updated_at' => now(),
            ]);

        Log::critical('⚠️ EMERGENCY OVERRIDE CREATED', [
            'lock_type' => $lockType,
            'environment' => $environment,
            'token' => $token,
            'reason' => $reason,
            'created_by' => $createdBy,
            'expires_at' => now()->addMinutes($durationMinutes),
        ]);

        return $token;
    }

    /**
     * Validate emergency override token
     */
    public static function validateOverrideToken(
        string $lockType,
        string $environment,
        string $token
    ): bool {
        $lock = DB::table('production_safety_locks')
            ->where('lock_type', $lockType)
            ->where('environment', $environment)
            ->where('override_token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if ($lock) {
            Log::warning('⚠️ EMERGENCY OVERRIDE USED', [
                'lock_type' => $lockType,
                'environment' => $environment,
                'token' => $token,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get safety lock status for all environments
     */
    public static function getSafetyStatus(): array
    {
        $locks = DB::table('production_safety_locks')
            ->get()
            ->groupBy('environment');

        $status = [];
        foreach ($locks as $environment => $envLocks) {
            $status[$environment] = [
                'schema_changes_locked' => false,
                'seeding_locked' => false,
                'destructive_commands_locked' => false,
            ];

            foreach ($envLocks as $lock) {
                if ($lock->is_locked && (!$lock->expires_at || $lock->expires_at > now())) {
                    $status[$environment][$lock->lock_type . '_locked'] = true;
                }
            }
        }

        return $status;
    }

    /**
     * Get recent destructive command attempts
     */
    public static function getRecentAttempts(int $limit = 50): array
    {
        return DB::table('destructive_command_attempts')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
