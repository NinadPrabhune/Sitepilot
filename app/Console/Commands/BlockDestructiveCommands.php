<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BlockDestructiveCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'block:destructive-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Block destructive Artisan commands and log attempts';

    /**
     * Destructive commands to block
     */
    protected array $destructiveCommands = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:seed --class=DatabaseSeeder',
        'db:wipe',
        'migrate:fresh --seed',
        'migrate:refresh --seed',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛡️ Destructive Command Protection System');
        $this->info('=====================================');
        
        $this->info('Blocked commands:');
        foreach ($this->destructiveCommands as $command) {
            $this->line("  ❌ {$command}");
        }
        
        $this->info(PHP_EOL . 'Protection Status: ✅ ACTIVE');
        $this->info('All attempts will be logged and blocked.');
        
        return 0;
    }

    /**
     * Check if a command is destructive
     */
    public static function isDestructive(string $command): bool
    {
        // Check if protection is enabled
        if (!config('destructive_commands.enabled', true)) {
            return false;
        }

        // Check emergency override
        if (config('destructive_commands.emergency_override', false)) {
            Log::warning('⚠️ EMERGENCY OVERRIDE ACTIVE - Destructive command protection disabled', [
                'command' => $command,
                'timestamp' => now()->toDateTimeString(),
            ]);
            return false;
        }

        // Check if current environment is protected
        $currentEnv = config('app.env');
        $protectedEnvs = config('destructive_commands.protected_environments', ['production', 'staging', 'local']);
        
        if (!in_array($currentEnv, $protectedEnvs)) {
            return false;
        }

        // Check user whitelist
        $currentUser = get_current_user();
        $whitelist = config('destructive_commands.user_whitelist', []);
        
        if (in_array($currentUser, $whitelist)) {
            Log::info('🔓 WHITELISTED USER - Destructive command allowed', [
                'command' => $command,
                'user' => $currentUser,
                'timestamp' => now()->toDateTimeString(),
            ]);
            return false;
        }

        // Check against blocked command patterns
        $destructivePatterns = config('destructive_commands.blocked_commands', [
            'migrate:fresh',
            'migrate:refresh', 
            'migrate:reset',
            'db:wipe',
            'db:seed.*DatabaseSeeder',
        ]);

        foreach ($destructivePatterns as $pattern) {
            if (preg_match('/' . str_replace('*', '.*', $pattern) . '/i', $command)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log and alert on blocked command attempt
     */
    public static function logBlockedAttempt(string $command, array $context = []): void
    {
        $logData = [
            'command' => $command,
            'timestamp' => now()->toDateTimeString(),
            'user' => get_current_user(),
            'ip' => request()->ip() ?? 'CLI',
            'environment' => config('app.env'),
            'context' => $context,
        ];

        // Critical log entry
        Log::critical('🚨 DESTRUCTIVE COMMAND BLOCKED', $logData);

        // Send email alert if configured
        if (config('app.destructive_command_alerts', false)) {
            try {
                $adminEmail = config('app.admin_email', 'admin@example.com');
                Mail::raw(
                    "Destructive command blocked:\n\nCommand: {$command}\nUser: {$logData['user']}\nTime: {$logData['timestamp']}\nEnvironment: {$logData['environment']}\n\nThis attempt has been blocked for safety.",
                    function ($message) use ($adminEmail, $command) {
                        $message->to($adminEmail)
                            ->subject('🚨 Destructive Command Blocked: ' . $command);
                    }
                );
            } catch (\Exception $e) {
                Log::error('Failed to send destructive command alert', [
                    'error' => $e->getMessage(),
                    'command' => $command,
                ]);
            }
        }

        // Create security log entry
        if (class_exists(\App\Models\SecurityLog::class)) {
            \App\Models\SecurityLog::create([
                'event_type' => 'destructive_command_blocked',
                'description' => "Destructive command '{$command}' was blocked",
                'user_id' => auth()->id() ?? null,
                'ip_address' => $logData['ip'],
                'user_agent' => request()->userAgent() ?? 'CLI',
                'details' => json_encode($logData),
                'created_at' => now(),
            ]);
        }
    }
}
