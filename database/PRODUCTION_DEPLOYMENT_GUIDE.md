# Production Deployment Guide: SitePilot Live DB Sync

## Overview

This guide covers the deployment of 6 migration files to synchronize the Live production database with the Local development database schema.

---

## Migration Files Summary

| Batch | File | Priority | Tables Created/Modified | Risk |
|-------|------|---------|------------------------|------|
| 1 | `2026_05_14_200001_create_financial_core_tables.php` | CRITICAL | 8 tables | LOW |
| 2 | `2026_05_14_200002_create_machinery_module_tables.php` | HIGH | 11 tables | LOW |
| 3 | `2026_05_14_200003_create_dpr_workflow_tables.php` | HIGH | 7 tables + DPR columns | MEDIUM |
| 4 | `2026_05_14_200004_create_audit_reconciliation_tables.php` | MEDIUM | 21 tables | LOW |
| 5 | `2026_05_14_200005_add_performance_indexes.php` | MEDIUM | Index additions + type fixes | MEDIUM |
| 6 | `2026_05_14_200006_add_dpr_performance_indexes.php` | MEDIUM | 5 tables get indexes | LOW |

---

## Pre-Deployment Checklist

### 1. Database Backup (REQUIRED)

```sql
-- Full database backup
mysqldump -u root -p sitepilot_live > backup_sitepilot_live_$(date +%Y%m%d_%H%M%S).sql

-- Verify backup size (should be several MB+)
ls -lh backup_sitepilot_live_*.sql
```

### 2. Staging Environment Testing

```bash
# Deploy to staging first
cd /var/www/sitepilot/staging
php artisan migrate --path=database/migrations/2026_05_14_200001_create_financial_core_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200002_create_machinery_module_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200003_create_dpr_workflow_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200004_create_audit_reconciliation_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200005_add_performance_indexes.php --force
php artisan migrate --path=database/migrations/2026_05_14_200006_add_dpr_performance_indexes.php --force
```

### 3. Verify Existing Tables

```sql
-- Check current table count
SELECT COUNT(*) as total_tables FROM information_schema.tables
WHERE table_schema = 'sitepilot_live' AND table_type = 'BASE TABLE';

-- Expected: 276 (current 235 + 41 new)
```

### 4. Check Large Tables

```sql
-- Identify large tables that may need maintenance window
SELECT table_name, table_rows, data_length + index_length as total_size
FROM information_schema.tables
WHERE table_schema = 'sitepilot_live'
AND table_type = 'BASE TABLE'
AND data_length + index_length > 100 * 1024 * 1024  -- > 100MB
ORDER BY data_length + index_length DESC;
```

**Expected large tables:**
- `daily_progress_reports` - Likely the largest
- `activities`
- `users`

---

## Deployment Sequence

### Step 1: Deploy Core Financial Tables

```bash
php artisan migrate --path=database/migrations/2026_05_14_200001_create_financial_core_tables.php --force
```

**Expected output:**
- `ledger_entries` table created
- `supplier_ledger` table created
- `supplier_ledger_entries` table created
- `financial_postings` table created
- `posting_batches` table created
- `posting_failures` table created
- `financial_period_locks` table created
- `financial_gate_blocks` table created

**Tables affected:** 8 NEW tables

---

### Step 2: Deploy Machinery Module Tables

```bash
php artisan migrate --path=database/migrations/2026_05_14_200002_create_machinery_module_tables.php --force
```

**Expected output:**
- `machinery_payment_requests` table created (if not exists)
- `machinery_payment_request_items` table created (if not exists)
- `machinery_payment_allocations` table created (if not exists)
- `machinery_ledger` table created (if not exists)
- `machinery_bills` table created (if not exists)
- `machinery_billing_items` table created (if not exists)
- `machinery_rate_histories` table created (if not exists)
- `machinery_supplier_rates` table created (if not exists)
- `machinery_ownerships` table created (if not exists)
- `machinery_usage_logs` table created (if not exists)
- `calculation_versions` table created (if not exists)

**Tables affected:** 11 NEW tables

---

### Step 3: Deploy DPR Workflow Tables (MEDIUM RISK)

```bash
# Schedule during maintenance window if daily_progress_reports > 100K rows
php artisan migrate --path=database/migrations/2026_05_14_200003_create_dpr_workflow_tables.php --force
```

**Expected output:**
- `dpr_edit_history` table created
- `dpr_anomalies` table created
- `daily_health_check_logs` table created
- `workflow_transitions` table created
- `workflow_state_histories` table created
- `workflow_audits` table created
- Columns added to `daily_progress_reports`
- Columns added to `daily_consumption_masters`
- Columns added to `daily_consumption_details`

**Risk Level:** MEDIUM
- ALTER TABLE on `daily_progress_reports` adds multiple nullable columns
- Table lock duration: O(seconds) for nullable column additions
- **Recommended:** Schedule during off-peak hours if table > 100K rows

---

### Step 4: Deploy Audit/Reconciliation Tables

```bash
php artisan migrate --path=database/migrations/2026_05_14_200004_create_audit_reconciliation_tables.php --force
```

**Expected output:** 21 NEW audit/logging tables created:
- `reconciliation_logs`
- `payment_audit_logs`
- `payment_health_logs`
- `payment_calculation_snapshots`
- `payment_request_status_logs`
- `payment_request_histories`
- `payment_reversals`
- `ledger_integrity_logs`
- `transaction_integrity_logs`
- `invariant_logs`
- `destructive_command_attempts`
- `item_categories`
- `items`
- `supplier_advances`
- `escalation_requests`
- `financial_escalations`
- `journal_adjustments`
- `material_consumption_audits`
- `material_consumption_versions`
- `usage_calculation_logs`
- `assets_tools_and_equipment_transfer`

**Tables affected:** 21 NEW tables

---

### Step 5: Deploy Performance Indexes (MEDIUM RISK)

```bash
php artisan migrate --path=database/migrations/2026_05_14_200005_add_performance_indexes.php --force
```

**Operations:**
- Index additions on `advance_utilizations`
- Index additions on `calculation_versions`
- JSON type conversions (with validation)
- Unsigned type fixes on `attendances`
- Nullability fixes on `activities`

**Risk Level:** MEDIUM
- Index creation may briefly lock tables
- JSON conversion includes data validation

---

### Step 6: Deploy DPR Performance Indexes

```bash
php artisan migrate --path=database/migrations/2026_05_14_200006_add_dpr_performance_indexes.php --force
```

**Tables affected:** 6 tables get new indexes
- `daily_progress_reports`
- `daily_consumption_masters`
- `ledger_entries`
- `machinery_ledger`
- `supplier_ledger`
- `machinery_payment_requests`

**Risk Level:** LOW
- Index additions only

---

## Post-Deployment Verification

### 1. Verify Table Count

```sql
SELECT COUNT(*) as total_tables FROM information_schema.tables
WHERE table_schema = 'sitepilot_live' AND table_type = 'BASE TABLE';

-- Expected: 276+ (was 235 before migration)
```

### 2. Verify Critical Tables Exist

```sql
-- Financial tables
SELECT 'ledger_entries' as tbl, COUNT(*) as cnt FROM ledger_entries
UNION ALL SELECT 'supplier_ledger', COUNT(*) FROM supplier_ledger
UNION ALL SELECT 'financial_postings', COUNT(*) FROM financial_postings
UNION ALL SELECT 'machinery_ledger', COUNT(*) FROM machinery_ledger
UNION ALL SELECT 'machinery_payment_requests', COUNT(*) FROM machinery_payment_requests;
```

### 3. Verify Column Additions

```sql
-- Check DPR columns added
SELECT COLUMN_NAME FROM information_schema.columns
WHERE table_schema = 'sitepilot_live'
AND table_name = 'daily_progress_reports'
AND COLUMN_NAME IN (
    'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason',
    'is_locked', 'locked_at', 'locked_by', 'payment_status', 'is_billed',
    'lifecycle_state', 'rate_snapshot', 'calculation_hash'
)
ORDER BY COLUMN_NAME;
```

### 4. Verify Indexes

```sql
-- Check new indexes exist
SELECT DISTINCT INDEX_NAME, TABLE_NAME
FROM information_schema.statistics
WHERE table_schema = 'sitepilot_live'
AND INDEX_NAME IN (
    'idx_advance_status_amount', 'idx_advance_status_amount',
    'idx_flow_created', 'idx_dpr_status', 'idx_dpr_payment_status'
)
ORDER BY TABLE_NAME, INDEX_NAME;
```

### 5. Run Application Health Check

```bash
# Clear config cache
php artisan config:clear
php artisan cache:clear

# Run application tests
php artisan test --filter=MigrationTest

# Check for errors in logs
tail -100 storage/logs/laravel.log | grep -i error
```

---

## Rollback Procedures

### Full Database Rollback

If complete rollback is needed:

```bash
# Restore from backup
mysql -u root -p sitepilot_live < backup_sitepilot_live_YYYYMMDD_HHMMSS.sql

# Verify restoration
php artisan migrate:status
```

### Selective Rollback (Per Migration)

```bash
# Rollback in reverse order
php artisan migrate:rollback --path=database/migrations/2026_05_14_200006_add_dpr_performance_indexes.php
php artisan migrate:rollback --path=database/migrations/2026_05_14_200005_add_performance_indexes.php
php artisan migrate:rollback --path=database/migrations/2026_05_14_200004_create_audit_reconciliation_tables.php
php artisan migrate:rollback --path=database/migrations/2026_05_14_200003_create_dpr_workflow_tables.php
php artisan migrate:rollback --path=database/migrations/2026_05_14_200002_create_machinery_module_tables.php
php artisan migrate:rollback --path=database/migrations/2026_05_14_200001_create_financial_core_tables.php
```

---

## Artisan Command Sequence

```bash
# Full deployment command sequence
php artisan migrate --path=database/migrations/2026_05_14_200001_create_financial_core_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200002_create_machinery_module_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200003_create_dpr_workflow_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200004_create_audit_reconciliation_tables.php --force
php artisan migrate --path=database/migrations/2026_05_14_200005_add_performance_indexes.php --force
php artisan migrate --path=database/migrations/2026_05_14_200006_add_dpr_performance_indexes.php --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## Monitoring Recommendations

### Watch for:
1. **Query Performance:** Some queries may be slow initially during index build
2. **Database Lock:** Monitor for table locks on `daily_progress_reports`
3. **Disk Space:** New tables will consume additional disk space
4. **Memory Usage:** Index creation may temporarily increase memory usage

### Recommended Monitoring Queries:

```sql
-- Check for long-running queries
SHOW FULL PROCESSLIST WHERE Time > 30 AND Command != 'Sleep';

-- Check table sizes
SELECT table_name, data_length + index_length as size_mb
FROM information_schema.tables
WHERE table_schema = 'sitepilot_live'
AND data_length + index_length > 10 * 1024 * 1024
ORDER BY size_mb DESC;

-- Verify no failed postings
SELECT COUNT(*) as failures FROM posting_failures WHERE status = 'pending';
```

---

## Disk Space Requirements

**Estimated new table size:** 50-200 MB (initial, empty tables)

**Additional considerations:**
- Audit tables will grow over time
- Consider implementing archival policy for:
  - `workflow_audits`
  - `payment_audit_logs`
  - `reconciliation_logs`
  - `invariant_logs`

---

## Contact & Emergency

- **DBA On-Call:** [Contact info]
- **Rollback Window:** First 4 hours post-deployment
- **Backup Location:** `/backups/mysql/sitepilot_live/`

---

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Deployer | | | |
| DBA Review | | | |
| QA Sign-off | | | |
| Manager Approval | | | |