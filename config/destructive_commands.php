<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Destructive Command Protection
    |--------------------------------------------------------------------------
    |
    | This configuration controls the protection system for destructive Artisan
    | commands that could cause data loss or system damage.
    |
    */

    'enabled' => env('DESTRUCTIVE_COMMAND_PROTECTION', true),

    /*
    |--------------------------------------------------------------------------
    | Protected Environments
    |--------------------------------------------------------------------------
    |
    | Environments where destructive commands should be blocked. Set to empty
    | array to disable protection in all environments.
    |
    */
    'protected_environments' => [
        'production',
        'staging', 
        'local',  // Protect local too (after today's incident!)
        'testing',
        'development',
    ],

    /*
    |--------------------------------------------------------------------------
    | Destructive Command Patterns
    |--------------------------------------------------------------------------
    |
    | List of command patterns that should be blocked. These support regex
    | patterns for flexible matching.
    |
    */
    'blocked_commands' => [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'db:seed.*DatabaseSeeder',
        'migrate:fresh.*--seed',
        'migrate:refresh.*--seed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Configuration
    |--------------------------------------------------------------------------
    |
    | Configure email alerts when destructive commands are blocked.
    |
    */
    'alerts' => [
        'enabled' => env('DESTRUCTIVE_COMMAND_ALERTS', false),
        'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'slack_webhook' => env('SLACK_WEBHOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how destructive command attempts are logged.
    |
    */
    'logging' => [
        'channel' => 'destructive_commands',
        'level' => 'critical',
        'max_files' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Alternatives
    |--------------------------------------------------------------------------
    |
    | Messages to show users when commands are blocked, providing safe
    | alternatives to accomplish their goals.
    |
    */
    'safe_alternatives' => [
        'migrate:fresh' => [
            'Use specific migrations: php artisan migrate --path=database/migrations/YOUR_MIGRATION.php',
            'Check migration status: php artisan migrate:status',
            'Rollback specific migration: php artisan migrate:rollback --step=1',
        ],
        'migrate:refresh' => [
            'Use specific migrations: php artisan migrate --path=database/migrations/YOUR_MIGRATION.php',
            'Check migration status: php artisan migrate:status',
            'Rollback specific migration: php artisan migrate:rollback --step=1',
        ],
        'db:seed' => [
            'Run individual seeders: php artisan db:seed --class=SpecificSeeder',
            'Check available seeders: php artisan db:seed --help',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Override
    |--------------------------------------------------------------------------
    |
    | In case of emergency, you can temporarily disable protection by setting
    | DESTRUCTIVE_COMMAND_EMERGENCY_OVERRIDE=true in your environment.
    | USE WITH EXTREME CAUTION!
    |
    */
    'emergency_override' => env('DESTRUCTIVE_COMMAND_EMERGENCY_OVERRIDE', false),

    /*
    |--------------------------------------------------------------------------
    | User Whitelist
    |--------------------------------------------------------------------------
    |
    | Users who are allowed to run destructive commands. This is an additional
    | layer of protection - even if someone bypasses the system, they must be
    | in this whitelist.
    |
    */
    'user_whitelist' => [
        // 'admin_user',  // Add specific usernames here
        // 'system_user',
    ],
];
