# Feature Flag Migration Procedures

Generated: 2026-05-06 12:34:40

## Overview

Feature flags allow gradual rollout of migration changes and instant rollback if issues are detected.

## Implementation

### 1. Feature Flag Setup

```php
// In config/migration_flags.php
return [
    'enable_new_table_creation' => env('MIGRATION_ENABLE_NEW_TABLES', false),
    'enable_column_addition' => env('MIGRATION_ENABLE_COLUMNS', false),
    'enable_index_changes' => env('MIGRATION_ENABLE_INDEXES', false),
    'enable_constraint_changes' => env('MIGRATION_ENABLE_CONSTRAINTS', false),
    'migration_mode' => env('MIGRATION_MODE', 'disabled'),
];
```

### 2. Migration Code with Feature Flags

```php
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

public function up()
{
    if (Config::get('migration_flags.enable_new_table_creation')) {
        Schema::create('new_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    if (Config::get('migration_flags.enable_column_addition')) {
        Schema::table('existing_table', function (Blueprint $table) {
            $table->string('new_column')->nullable();
        });
    }
}

public function down()
{
    if (Config::get('migration_flags.enable_new_table_creation')) {
        Schema::dropIfExists('new_table');
    }

    if (Config::get('migration_flags.enable_column_addition')) {
        Schema::table('existing_table', function (Blueprint $table) {
            $table->dropColumn('new_column');
        });
    }
}
```

### 3. Deployment Commands

```bash
# Enable specific migration feature
MIGRATION_ENABLE_NEW_TABLES=true php artisan migrate --force

# Enable all migration features
MIGRATION_MODE=enabled php artisan migrate --force

# Disable migration features (rollback)
MIGRATION_MODE=disabled php artisan migrate --force

# Check current flag status
php artisan migration:flags --status
```

### 4. Gradual Rollout

```bash
# 10% of users
MIGRATION_ENABLE_NEW_TABLES=true MIGRATION_USER_PERCENTAGE=10 php artisan migrate --force

# 50% of users
MIGRATION_ENABLE_NEW_TABLES=true MIGRATION_USER_PERCENTAGE=50 php artisan migrate --force

# 100% of users
MIGRATION_ENABLE_NEW_TABLES=true MIGRATION_USER_PERCENTAGE=100 php artisan migrate --force
```

## Monitoring with Feature Flags

- User behavior differences between groups
- Performance metrics by flag status
- Error rates by user percentage
- Database query patterns
- Rollback trigger events

## Emergency Rollback

```bash
# Instant rollback by disabling flags
MIGRATION_MODE=disabled php artisan migrate --force

# Complete rollback
php artisan migration:rollback --all

# Verify rollback
php artisan migrate:status
```

