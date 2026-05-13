# Database Backup Procedure

## Purpose
This document outlines the database backup procedure for the Payment Module Redesign project.

## Environment Details
- **Database Type:** MySQL
- **Database Name:** sitepilot_live
- **Host:** localhost
- **Port:** 3306

## Backup Types

### 1. Full Database Backup (Before Phase 2)
**When:** Before running any Phase 2 migrations  
**Purpose:** Complete snapshot of database before schema changes

```bash
mysqldump -u root -p sitepilot_live > database/backups/sitepilot_live_phase1_complete_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Key Tables Backup (Before Phase 3)
**When:** Before running Phase 3 data migration  
**Purpose:** Backup critical payment-related tables

```bash
mysqldump -u root -p sitepilot_live \
  payment_requests \
  payments_module \
  payment_module_allocations \
  purchase_invoices \
  purchase_orders \
  supplier_transactions \
  advance_adjustments \
  > database/backups/sitepilot_live_phase3_tables_$(date +%Y%m%d_%H%M%S).sql
```

### 3. Incremental Backup (Daily)
**When:** Daily during Phase 3-7  
**Purpose:** Capture ongoing changes

```bash
mysqldump -u root -p sitepilot_live \
  --single-transaction \
  --quick \
  --lock-tables=false \
  > database/backups/sitepilot_live_incremental_$(date +%Y%m%d_%H%M%S).sql
```

## Backup Verification

After each backup, verify:
1. File size is reasonable (not 0 bytes)
2. File can be opened and read
3. SQL syntax is valid
4. No error messages in backup output

```bash
# Check file size
ls -lh database/backups/sitepilot_live_*.sql

# Verify SQL syntax (basic check)
head -n 50 database/backups/sitepilot_live_*.sql
```

## Restore Procedure

### Full Restore
```bash
mysql -u root -p sitepilot_live < database/backups/sitepilot_live_phase1_complete_YYYYMMDD_HHMMSS.sql
```

### Tables-Only Restore
```bash
mysql -u root -p sitepilot_live < database/backups/sitepilot_live_phase3_tables_YYYYMMDD_HHMMSS.sql
```

## Backup Retention Policy
- **Phase 1-2 backups:** Keep until Phase 3 completion
- **Phase 3-7 backups:** Keep 30 days
- **Production backups:** Keep 90 days

## Automated Backup Script

See `database/backups/backup.sh` for automated backup script.

## Important Notes
- Always verify backup integrity before proceeding with changes
- Store backups in a secure location with appropriate permissions
- Test restore procedure on staging environment before production use
- Document all backup and restore operations
