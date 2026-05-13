# PO-Locked Advance System - Deployment Guide

## Pre-Deployment Checklist
- [ ] All code changes committed to version control
- [ ] Feature flag is set to `false` in `.env` file
- [ ] Shadow mode is set to `false` in `.env` file
- [ ] Financial period locking is set to `false` in `.env` file
- [ ] Database backup completed
- [ ] Testing guide reviewed and ready
- [ ] Rollback plan documented
- [ ] Team notified about deployment

## Deployment Steps

### Step 0: Pre-flight System Health Check
```bash
# Ensure Laravel boot state is clean
php artisan config:cache
php artisan route:list
php artisan optimize:check
```

### Step 1: Database Backup
```bash
# Backup database
mysqldump -u username -p database_name > backup_before_po_locked_advance_$(date +%Y%m%d_%H%M%S).sql

# Verify backup file exists
ls -lh backup_before_po_locked_advance_*.sql
```

### Step 2: Deploy Code
```bash
# Pull latest code
git pull origin main

# Install dependencies (if needed)
composer install --no-dev --optimize-autoloader

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 3: Run Migrations
```bash
# Run new migrations
php artisan migrate

# Verify migration status
php artisan migrate:status

# CRITICAL: Restart queue workers to prevent outdated logic execution
php artisan queue:restart
```

Expected new migrations:
- `2026_04_15_000007_add_transaction_flow_system.php`
- `2026_04_15_000008_make_amounts_derived_and_add_financial_periods.php`
- `2026_04_15_000009_create_advance_audit_logs_table.php`
- `2026_04_15_000010_add_financial_lock_and_safety_columns.php`
- `2026_04_15_000011_add_performance_covering_indexes.php`

### Step 3.5: DB Index Validation (Critical for ERP Performance)
```bash
# Verify indexes were created correctly
mysql -u username -p database_name -e "SHOW INDEX FROM advance_utilizations;"
mysql -u username -p database_name -e "SHOW INDEX FROM supplier_advances;"
mysql -u username -p database_name -e "SHOW INDEX FROM ledger_entries;"
```

### Step 4: Verify Configuration
```bash
# Check .env file
cat .env | grep PO_LOCKED_ADVANCE_ENABLED
# Expected: PO_LOCKED_ADVANCE_ENABLED=false

cat .env | grep SHADOW_MODE_ENABLED
# Expected: SHADOW_MODE_ENABLED=false

cat .env | grep FINANCIAL_PERIOD_LOCKING_ENABLED
# Expected: FINANCIAL_PERIOD_LOCKING_ENABLED=false

# CRITICAL: Clear and cache config to ensure feature flags reflect correctly
php artisan config:clear
php artisan config:cache
```

### Step 5: Verify Deployment
```bash
# Check application is running
php artisan serve

# Verify logs are working
tail -f storage/logs/laravel.log
tail -f storage/logs/finance.log
```

### Step 6: Smoke Test
1. Login to application
2. Navigate to Purchase Invoices
3. Create a test invoice (without advance allocation)
4. Verify invoice creation works normally
5. Verify no errors in logs

### Step 7: Post-Deployment Financial Integrity Check (Critical ERP Validation)
```bash
# Golden ERP validation check - ledger must always balance
mysql -u username -p database_name -e "SELECT SUM(debit) - SUM(credit) FROM ledger_entries;"
# Expected: 0

# Reservation table sanity check - verify no unexpected states
mysql -u username -p database_name -e "SELECT status, COUNT(*) FROM advance_utilizations GROUP BY status;"
# Expected: reserved, applied, reversed (no unexpected states)
```

## Post-Deployment Checklist
- [ ] Application is accessible
- [ ] No errors in application logs
- [ ] No errors in finance logs
- [ ] Feature flag remains OFF
- [ ] Database tables created successfully
- [ ] Indexes created successfully
- [ ] Basic invoice creation/payment flow works

## Rollback Procedure

### If Migration Fails
```bash
# Rollback last migration batch
php artisan migrate:rollback

# Restore database from backup
mysql -u username -p database_name < backup_before_po_locked_advance_YYYYMMDD_HHMMSS.sql
```

### If Application Errors Occur
```bash
# Disable feature flag immediately
echo "PO_LOCKED_ADVANCE_ENABLED=false" >> .env

# Clear config cache
php artisan config:clear

# Restart application
php artisan serve
```

### Full Rollback
```bash
# Restore database
mysql -u username -p database_name < backup_before_po_locked_advance_YYYYMMDD_HHMMSS.sql

# Rollback migrations
php artisan migrate:rollback --step=5

# Restore previous code
git checkout previous_commit

# Clear caches
php artisan cache:clear
php artisan config:clear
```

## Next Steps (After Successful Deployment)

### Day 1-3: Shadow Mode (Optional)
```bash
# Enable shadow mode for validation
echo "SHADOW_MODE_ENABLED=true" >> .env
echo "PO_LOCKED_ADVANCE_ENABLED=true" >> .env

# Clear config cache
php artisan config:clear
```

Monitor logs for validation results without affecting production.

### Day 4-7: Limited Rollout (Optional)
```bash
# Disable shadow mode
echo "SHADOW_MODE_ENABLED=false" >> .env

# Keep feature flag enabled for limited users
# (Implement user/role-based feature flag logic if needed)
```

### Day 8+: Full Rollout (After Validation)
```bash
# Enable feature flag for all users
echo "PO_LOCKED_ADVANCE_ENABLED=true" >> .env

# Enable financial period locking (optional)
echo "FINANCIAL_PERIOD_LOCKING_ENABLED=true" >> .env

# Clear config cache
php artisan config:clear
```

## Monitoring Metrics
- Number of advance allocations per day
- Failed allocation attempts
- Cross-PO allocation attempts (should be 0)
- Direct GRN allocation attempts (should be 0)
- Transaction flow ID generation errors
- Database lock timeouts
- Finance log errors

## Contact Information
- Development Team: [Contact details]
- Finance Team: [Contact details]
- Database Team: [Contact details]
