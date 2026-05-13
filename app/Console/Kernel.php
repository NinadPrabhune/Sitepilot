<?php

namespace App\Console;

use App\Jobs\CheckBirthdayNotification;
use App\Jobs\CheckEventNotification;
use App\Jobs\CheckHolidayNotification;
use App\Jobs\CheckLowStockNotification;
use App\Console\Commands\BlockDestructiveCommands;
use App\Console\Commands\RequestSchemaChange;
use App\Console\Commands\ApproveSchemaChange;
use App\Services\ProductionSafetyService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
{
        
//     $schedule->job(new \App\Jobs\CheckLowStockNotification) ->everyMinute() ->onQueue('notifications'); 
        
        
    // Check for low stock materials - Every hour
    // 
    
//    $schedule->job(new CheckLowStockNotification)->everyMinute();

//    $schedule->job(new CheckLowStockNotification)
//        ->everyMinute()        
//        ->onSuccess(function () {
//            \Log::info('Low stock notification job completed successfully at ' . now()->format('Y-m-d H:i:s'));
//        })
//        ->onFailure(function () {
//            \Log::error('Low stock notification job failed at ' . now()->format('Y-m-d H:i:s'));
//        });

    
    $schedule->job(new CheckLowStockNotification)
        ->hourly()
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('Low stock notification job completed successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('Low stock notification job failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // Check for employee birthdays - Every day at 8 AM
    $schedule->job(new CheckBirthdayNotification)
        ->dailyAt('08:00')
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('Birthday notification job completed successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('Birthday notification job failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // Check for upcoming holidays - Every day at 9 AM
    $schedule->job(new CheckHolidayNotification)
        ->dailyAt('09:00')
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('Holiday notification job completed successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('Holiday notification job failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // Check for upcoming events - Every day at 10 AM
    $schedule->job(new CheckEventNotification)
        ->dailyAt('10:00')
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('Event notification job completed successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('Event notification job failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // System health check - Every 6 hours
    $schedule->command('system:health-check')
        ->everySixHours()
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('System health check completed successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('System health check failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // DPR/Ledger integrity check - Daily at 2 AM
    $schedule->command('dpr:integrity-check')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->onOneServer()
        ->onSuccess(function () {
            \Log::info('DPR integrity check completed at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('DPR integrity check failed at ' . now()->format('Y-m-d H:i:s'));
        });

    // Generate API documentation - Every hour (if enabled via AUTO_GENERATE_API_DOCS env)
    // DISABLED: User will create API docs manually
    /*
    $schedule->command('api:generate-docs')
        ->hourly()
        ->withoutOverlapping()
        ->onSuccess(function () {
            \Log::info('API documentation generated successfully at ' . now()->format('Y-m-d H:i:s'));
        })
        ->onFailure(function () {
            \Log::error('API documentation generation failed at ' . now()->format('Y-m-d H:i:s'));
        });
    */
}


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    /**
     * 🚨 MULTI-LAYER DESTRUCTIVE COMMAND PROTECTION: Block dangerous commands
     */
    protected function call($command, array $parameters = [])
    {
        // Build full command string for checking
        $fullCommand = $command;
        if (!empty($parameters)) {
            foreach ($parameters as $key => $value) {
                if (is_string($key)) {
                    $fullCommand .= " --{$key}={$value}";
                } else {
                    $fullCommand .= " {$value}";
                }
            }
        }

        $currentUser = get_current_user();
        $currentEnvironment = config('app.env');
        $ipAddress = request()->ip() ?? 'CLI';

        // Check for approval token override
        $approvalToken = $parameters['approval'] ?? null;

        // Multi-layer protection checks
        $isDestructive = BlockDestructiveCommands::isDestructive($fullCommand);
        $areCommandsLocked = ProductionSafetyService::areDestructiveCommandsLocked($currentEnvironment);
        $isSeedingLocked = ProductionSafetyService::isSeedingLocked($currentEnvironment);
        $isSchemaLocked = ProductionSafetyService::isSchemaLocked($currentEnvironment);

        // Check if this is a seeding operation
        $isSeedingOperation = str_contains($command, 'db:seed') || str_contains($fullCommand, '--seed');

        // Determine if command should be blocked
        $shouldBlock = false;
        $blockReason = '';

        if ($isDestructive && $areCommandsLocked) {
            $shouldBlock = true;
            $blockReason = "Destructive commands are locked in {$currentEnvironment} environment";
        } elseif ($isSeedingOperation && $isSeedingLocked) {
            $shouldBlock = true;
            $blockReason = "Seeding is locked in {$currentEnvironment} environment";
        } elseif (($isDestructive || $isSeedingOperation) && $isSchemaLocked) {
            $shouldBlock = true;
            $blockReason = "Schema changes are locked in {$currentEnvironment} environment";
        }

        // Check for approval override
        if ($shouldBlock && $approvalToken) {
            if (ProductionSafetyService::isRequestApproved($approvalToken)) {
                $shouldBlock = false;
                $blockReason = '';
                Log::warning('⚠️ DESTRUCTIVE COMMAND APPROVED VIA TOKEN', [
                    'command' => $fullCommand,
                    'approval_token' => $approvalToken,
                    'user' => $currentUser,
                    'environment' => $currentEnvironment,
                ]);
            } else {
                $blockReason = "Invalid or expired approval token: {$approvalToken}";
            }
        }

        // Block the command if needed
        if ($shouldBlock) {
            // Log the blocked attempt with full context
            ProductionSafetyService::logDestructiveAttempt(
                $command,
                $fullCommand,
                $currentUser,
                $ipAddress,
                'artisan_kernel',
                true,
                $blockReason,
                [
                    'parameters' => $parameters,
                    'working_directory' => getcwd(),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'environment' => $currentEnvironment,
                    'destructive_detected' => $isDestructive,
                    'seeding_detected' => $isSeedingOperation,
                    'commands_locked' => $areCommandsLocked,
                    'seeding_locked' => $isSeedingLocked,
                    'schema_locked' => $isSchemaLocked,
                ]
            );

            // Display comprehensive error message
            $this->getArtisan()->error('🚨 COMMAND BLOCKED - PRODUCTION SAFETY LOCK');
            $this->getArtisan()->error('Command: ' . $fullCommand);
            $this->getArtisan()->error('Environment: ' . $currentEnvironment);
            $this->getArtisan()->error('Reason: ' . $blockReason);
            $this->getArtisan()->error('User: ' . $currentUser);
            $this->getArtisan()->error('');
            $this->getArtisan()->info('🔐 To proceed, you have these options:');
            $this->getArtisan()->info('');
            
            if ($isDestructive) {
                $this->getArtisan()->info('1. Request approval for this operation:');
                $this->line("   php artisan schema:request destructive_command \"{$fullCommand}\" --environment={$currentEnvironment}");
                $this->getArtisan()->info('');
                $this->getArtisan()->info('2. Use safer alternatives:');
                $this->getArtisan()->info('   • Specific migrations: php artisan migrate --path=database/migrations/YOUR_FILE.php');
                $this->getArtisan()->info('   • Check status: php artisan migrate:status');
                $this->getArtisan()->info('   • Rollback specific: php artisan migrate:rollback --step=1');
            }
            
            if ($isSeedingOperation) {
                $this->getArtisan()->info('3. For seeding, use individual seeders:');
                $this->getArtisan()->info('   • php artisan db:seed --class=SpecificSeeder');
                $this->getArtisan()->info('   • Request approval if needed: php artisan schema:request seed \"db:seed --class=SpecificSeeder\"');
            }
            
            $this->getArtisan()->info('');
            $this->getArtisan()->info('⚠️  This attempt has been logged for security audit.');
            $this->getArtisan()->info('📞 Contact your system administrator for emergency access.');
            
            exit(1); // Terminate with error code
        }

        // Log all command executions (forensic logging)
        Log::critical('🚨 ARTISAN COMMAND EXECUTED', [
            'command' => $command,
            'parameters' => $parameters,
            'full_command' => $fullCommand,
            'user' => $currentUser,
            'environment' => $currentEnvironment,
            'working_directory' => getcwd(),
            'timestamp' => now()->toDateTimeString(),
            'destructive_detected' => $isDestructive,
            'approval_token_used' => $approvalToken,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ]);

        return parent::call($command, $parameters);
    }
}
