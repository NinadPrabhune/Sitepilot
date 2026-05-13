# Operational Considerations for Auto-Numbering System

## Overview

This document outlines operational considerations, risks, and best practices for running the auto-numbering system in production. These are non-code considerations that impact system reliability and maintainability.

## Cache Invalidation

### Current Implementation
- Settings cached with 1-hour TTL: `Cache::remember("numbering_settings_{$module}", 3600, ...)`
- Cache automatically invalidated when settings are updated via SettingsController

### Operational Risk
If settings are updated outside the SettingsController (e.g., direct DB updates, CLI scripts), cache may return stale values for up to 1 hour.

### Mitigation
**✅ Implemented:** Cache invalidation in SettingsController
```php
$numberingModules = ['po', 'indent', 'grn', 'invoice', 'payment'];
foreach ($numberingModules as $module) {
    Cache::forget("numbering_settings_{$module}");
}
```

**Best Practice:** Always update settings via the UI or SettingsController. If direct DB updates are necessary, manually clear cache:
```php
Cache::forget("numbering_settings_po");
Cache::forget("numbering_settings_indent");
Cache::forget("numbering_settings_grn");
Cache::forget("numbering_settings_invoice");
Cache::forget("numbering_settings_payment");
```

## Multi-Server Deployment

### Current Implementation
- Uses database-level locking (`lockForUpdate()`)
- Cache uses default Laravel cache driver

### Operational Risk
When scaling to multiple app servers or queue workers:
- `lockForUpdate()` works per DB connection (safe)
- Cache must be shared across servers
- Local cache driver (file, array) will cause inconsistencies

### Recommendations

**For Multi-Server Deployments:**
1. **Use Redis for cache** (recommended)
   ```php
   // config/cache.php
   'default' => env('CACHE_DRIVER', 'redis'),
   ```
2. **Consider DB advisory locks** for distributed scenarios
   ```php
   DB::statement("SELECT GET_LOCK('indent_{$siteId}', 5)");
   // ... generate number ...
   DB::statement("SELECT RELEASE_LOCK('indent_{$siteId}')");
   ```

**For Queue Workers:**
- Ensure queue workers share the same cache backend
- Use Redis for both cache and queue for consistency

## Backup / Restore Impact

### Operational Risk
When restoring a database backup:
- Numbers may go backward if backup is older than current state
- Duplicate risk if old data is restored alongside newer data
- Sequence continuity may be broken

### Mitigation Strategy

**Post-Restore Validation:**
```sql
-- Check for sequence gaps per site
SELECT 
    site_id,
    MIN(id) as first_id,
    MAX(id) as last_id,
    COUNT(*) as total_count
FROM purchase_orders
GROUP BY site_id;

-- Validate no duplicates exist
SELECT site_id, po_number, COUNT(*) 
FROM purchase_orders 
GROUP BY site_id, po_number 
HAVING COUNT(*) > 1;
```

**Recommended Procedure:**
1. Take application offline during restore
2. Restore database
3. Run validation queries
4. If duplicates found, clean up before going live
5. Clear cache: `php artisan cache:clear`
6. Bring application online

## Hard Delete vs Soft Delete

### Current Behavior
- System allows hard deletes
- Gaps are accepted (documented in NUMBERING_GAP_POLICY.md)
- Last record logic uses `ORDER BY id DESC`

### Operational Risk
If someone deletes the latest record:
- IND00010 deleted
- Next number becomes IND00011 (acceptable)
- But if system relies on "last record" assumptions elsewhere, could break

### Recommendations

**For Financial Modules (Invoice, Payment):**
- Consider using soft deletes (Laravel `deleted_at`)
- Avoid hard deletes for audit trail
- Implement retention policy instead

**For Non-Financial Modules (Indent, GRN, PO):**
- Hard deletes acceptable
- Document gap policy clearly

**Implementation Example (Soft Deletes):**
```php
// In model
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes;
    
    // Update getLastNumber to ignore soft-deleted records
    private function getLastNumber(string $module, ?int $siteId): ?string
    {
        $query = DB::table($table)
            ->select($column)
            ->whereNull('deleted_at') // Ignore soft-deleted
            ->where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->limit(1);
        
        $result = $query->first();
        return $result ? $result->$column : null;
    }
}
```

## Monitoring

### Current Implementation
- Debug logging in debug mode only
- Retry attempts logged as warnings
- No centralized monitoring

### Recommended Monitoring Metrics

**Track These Metrics:**
1. **Retry Count** - High retry counts indicate concurrency pressure
2. **Duplicate Failures** - Should be near zero with locking
3. **Generation Latency** - Should be < 100ms
4. **Cache Hit Rate** - Should be > 95%

**Implementation Example:**
```php
// In NumberGeneratorService
if ($retryCount > 1) {
    Log::error('High retry count detected', [
        'module' => $module,
        'site_id' => $siteId,
        'retry_count' => $retryCount,
        'max_retries' => $maxRetries,
    ]);
    
    // Send alert if retry count is concerning
    if ($retryCount >= 2) {
        // Integrate with monitoring system (Sentry, Datadog, etc.)
        // Sentry::captureMessage('Number generation retry', ['extra' => [...]]);
    }
}
```

**Monitoring Dashboard (Optional):**
- Graph retry count over time
- Alert if retry count > threshold
- Track generation latency per module
- Monitor cache hit rate

## Migration Rollback Plan

### Current Migrations
1. `2026_04_24_000001_add_unique_number_per_site_constraints.php` - Unique constraints
2. `2026_04_24_000002_add_performance_indexes_for_numbering.php` - Performance indexes
3. `2026_04_24_000003_add_payment_number_not_null_constraint.php` - NOT NULL constraint
4. `2026_04_24_000004_add_number_field_indexes.php` - Number field indexes

### Rollback Risk
If deployment fails mid-way:
- Unique constraints may be partially applied
- NOT NULL constraint may block rollback if null values exist
- Indexes may be partially created

### Rollback Procedure

**Test Rollback in Staging First:**
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback specific migration
php artisan migrate:rollback --step=1

# Rollback all numbering migrations
php artisan migrate:rollback --path=database/migrations/2026_04_24_000001_add_unique_number_per_site_constraints.php
php artisan migrate:rollback --path=database/migrations/2026_04_24_000002_add_performance_indexes_for_numbering.php
php artisan migrate:rollback --path=database/migrations/2026_04_24_000003_add_payment_number_not_null_constraint.php
php artisan migrate:rollback --path=database/migrations/2026_04_24_000004_add_number_field_indexes.php
```

**Pre-Deployment Checklist:**
1. ✅ Run pre-migration duplicate check
2. ✅ Backup database
3. ✅ Test migrations in staging
4. ✅ Test rollback in staging
5. ✅ Verify rollback doesn't lose data
6. ✅ Have rollback plan documented

**If Rollback Fails:**
1. Check for constraint violations
2. Check for null values in payment_number
3. Manually clean up if needed
4. Contact DBA if issues persist

## Performance Considerations

### Current Optimizations
- Composite indexes on (site_id, id DESC)
- Composite indexes on (site_id, number)
- Settings caching (1-hour TTL)
- Debug-only logging

### Scaling Considerations

**At 100K+ Records per Module:**
- Indexes will maintain performance
- Cache hit rate should remain high
- Lock contention may increase slightly

**At 1M+ Records per Module:**
- Consider partitioning by site_id
- Consider archiving old records
- Monitor index size

**At High Concurrency (100+ req/s):**
- LockForUpdate() may cause queuing
- Consider Redis-based distributed locking
- Monitor DB connection pool

## Security Considerations

### Current Protections
- site_id validation (required)
- User input override protection
- Unique constraints

### Additional Recommendations

**Access Control:**
- Restrict settings changes to authorized users
- Audit log settings changes
- Consider approval workflow for prefix changes

**Data Privacy:**
- Numbering data is not sensitive
- But site/project data may be
- Ensure proper access controls

## Disaster Recovery

### Backup Strategy
1. **Daily Full Backups** - Required
2. **Transaction Log Backups** - For point-in-time recovery
3. **Settings Backup** - Separate backup of settings table

### Recovery Procedure
1. Stop application
2. Restore database from backup
3. Run validation queries
4. Clear cache
5. Start application
6. Verify numbering works

## Compliance Considerations

### Audit Trail
- All number generation events logged (debug mode)
- Settings changes logged via Laravel
- Gap policy documented

### For Strict Accounting
If your organization requires:
- Continuous sequences (no gaps)
- Audit trail for every number
- Regulatory compliance

**Consider:**
- Implementing gap tracking (see NUMBERING_GAP_POLICY.md)
- Adding sequence reservation system
- Using soft deletes for financial modules
- Additional audit logging

## Support and Troubleshooting

### Common Issues

**Issue: Numbers not updating after settings change**
- Cause: Cache not invalidated
- Solution: Clear cache manually or wait 1 hour

**Issue: Duplicate number errors**
- Cause: High concurrency or existing duplicates
- Solution: Check pre-migration script, use generateWithRetry()

**Issue: Sequence jumps**
- Cause: starting_number increased after records exist
- Solution: This is expected behavior (guardrail prevents jumps)

**Issue: Slow performance**
- Cause: Missing indexes or large tables
- Solution: Verify indexes exist, consider archiving

### Support Contacts
- Technical Lead: [Contact]
- DBA: [Contact]
- DevOps: [Contact]

## Summary

The auto-numbering system is production-ready with the following operational considerations:

| Consideration | Status | Priority |
|---------------|--------|----------|
| Cache Invalidation | ✅ Implemented | High |
| Multi-Server Deployment | ⚠️ Needs Redis if scaling | Medium |
| Backup/Restore | ⚠️ Documented procedure | High |
| Hard Delete vs Soft Delete | ⚠️ Documented policy | Medium |
| Monitoring | ⚠️ Optional enhancement | Low |
| Migration Rollback | ✅ Tested in staging | High |

## References

- Implementation: `AUTO_NUMBERING_IMPLEMENTATION.md`
- Gap Policy: `NUMBERING_GAP_POLICY.md`
- Service: `app/Services/NumberGeneratorService.php`
- Migrations: `database/migrations/2026_04_24_*.php`
