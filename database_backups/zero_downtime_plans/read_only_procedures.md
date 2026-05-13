# Read-Only Mode Procedures

Generated: 2026-05-06 12:34:40

## Overview

Read-only mode prevents data modifications during critical migrations while keeping the application accessible for read operations.

## Implementation

### 1. Enable Read-Only Mode

```bash
# Enable read-only mode with custom message
php artisan app:mode read-only --message="Database maintenance in progress" --duration=30m

# Verify read-only mode is active
php artisan mode:status
```

### 2. Database Preparation

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check database connections
php artisan db:show-connections
```

### 3. Migration Execution

```bash
# Run migrations with extended timeout
php artisan migrate --force --timeout=600

# Monitor migration progress
php artisan migrate:status --watch
```

### 4. Verification

```bash
# Verify migration completion
php artisan migrate:status

# Test database connectivity
php artisan db:test --all-tables

# Check application health
php artisan health:check --comprehensive
```

### 5. Disable Read-Only Mode

```bash
# Disable read-only mode
php artisan app:mode production

# Verify normal operation
php artisan mode:status
```

## Monitoring During Read-Only Mode

- User authentication success/failure rates
- Database query performance
- Application response times
- Error log patterns
- Cache hit rates

## Emergency Procedures

### If Migration Fails

1. Keep read-only mode enabled
2. Investigate failure in separate terminal
3. Restore database from backup if needed
4. Fix migration issues
5. Retry migration process

### If Application Issues Occur

1. Check read-only mode status
2. Review recent changes
3. Check database connectivity
4. Consider temporary disable of read-only mode

