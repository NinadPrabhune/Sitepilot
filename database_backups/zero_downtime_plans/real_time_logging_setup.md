# Real-Time Migration Logging Setup

Generated: 2026-05-06 12:34:40

## Enhanced Logging Configuration

### 1. Laravel Configuration

```php
// config/logging.php
'channels' => [
    'migration' => [
        'driver' => 'daily',
        'path' => storage_path('logs/migration.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],
    'migration_metrics' => [
        'driver' => 'single',
        'path' => storage_path('logs/migration_metrics.log'),
        'level' => 'info',
    ],
],
```

### 2. Migration Logging Implementation

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MigrationLogger
{
    public static function logMigrationStart($migration)
    {
        Log::channel('migration')->info('Migration started: ' . $migration);
        Log::channel('migration_metrics')->info([
            'event' => 'migration_start',
            'migration' => $migration,
            'timestamp' => now(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
    }

    public static function logMigrationEnd($migration, $duration)
    {
        Log::channel('migration')->info('Migration completed: ' . $migration . ' (' . $duration . 's)');
        Log::channel('migration_metrics')->info([
            'event' => 'migration_end',
            'migration' => $migration,
            'duration' => $duration,
            'timestamp' => now(),
        ]);
    }

    public static function logQuery($query, $duration)
    {
        if ($duration > 1000) { // Log slow queries
            Log::channel('migration_metrics')->warning([
                'event' => 'slow_query',
                'query' => $query,
                'duration' => $duration,
                'timestamp' => now(),
            ]);
        }
    }

    public static function logError($migration, $error)
    {
        Log::channel('migration')->error('Migration error: ' . $migration . ' - ' . $error);
        Log::channel('migration_metrics')->error([
            'event' => 'migration_error',
            'migration' => $migration,
            'error' => $error,
            'timestamp' => now(),
        ]);
    }
}
```

### 3. Real-Time Log Monitoring

```bash
# Monitor migration logs in real-time
tail -f storage/logs/migration.log | grep -E '(started|completed|error)'

# Monitor metrics
tail -f storage/logs/migration_metrics.log | jq '.'

# Monitor for errors
tail -f storage/logs/migration.log | grep -i error | while read line; do
    echo "[ALERT] Migration error detected: $line"
    # Send alert notification
done
```

## Log Analysis Commands

```bash
# Analyze migration performance
grep 'Migration completed' storage/logs/migration.log | awk '{print $NF}' | sort -n | awk '{sum+=$1} END {print "Average duration: " sum/NR "s"}'

# Count slow queries
grep 'slow_query' storage/logs/migration_metrics.log | wc -l

# Find errors
grep -i error storage/logs/migration.log | tail -10
```

