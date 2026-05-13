# Phase 3 Data Migration Scripts - Review Ready

**Date:** April 10, 2026  
**Status:** READY FOR REVIEW  
**Risk Level:** CRITICAL

---

## Overview

Phase 3 is the CRITICAL data migration checkpoint that converts the payment system from PO-based to invoice-based. These scripts are ready for your review and approval before execution on staging.

---

## Migration Scripts

### 1. Main Data Migration
**File:** `database/migrations/2026_04_15_000004_migrate_po_based_payments_to_invoice_based.php`

**Purpose:** Converts PO-based payments to invoice-based payments

**Process:**
- Creates migration snapshot table for rollback
- Migrates payments with existing purchase_invoice_id
- Migrates payments using payment_module_allocations
- Handles edge cases (payments without invoice)
- Updates ledger entries
- Verifies migration integrity

**Safety Features:**
- Transaction-based (all-or-nothing)
- Comprehensive audit logging
- Snapshot for rollback
- Integrity checks before completion

### 2. purchase_invoice_id Validation
**File:** `database/migrations/2026_04_15_000005_make_purchase_invoice_id_not_null.php`

**Purpose:** Enforces that against_invoice payments must have purchase_invoice_id

**Process:**
- Validates all against_invoice payments have invoice_id
- Adds CHECK constraint (MySQL 8.0.16+) or TRIGGER (older versions)
- Prevents future PO-based payments without invoice

**Safety Features:**
- Pre-migration validation
- Database-level constraint
- Version-aware implementation

### 3. Drop Allocations Table
**File:** `database/migrations/2026_04_15_000006_drop_payment_module_allocations_table.php`

**Purpose:** Removes payment_module_allocations table after migration

**Process:**
- Creates backup table before drop
- Verifies all allocations migrated
- Drops original table
- Keeps backup for audit

**Safety Features:**
- Automatic backup creation
- Pre-drop verification
- Backup retained for rollback

### 4. Ledger Reconciliation
**File:** `database/migrations/2026_04_15_000007_recalculate_supplier_ledger_balances.php`

**Purpose:** Recalculates supplier ledger balances after migration

**Process:**
- Creates balance snapshot
- Recalculates running balances chronologically
- Verifies balance integrity
- Reports any differences

**Safety Features:**
- Before/after comparison
- Transaction-based
- Comprehensive verification

---

## Validation Script

**File:** `database/phase3_validation.sql`

**Purpose:** Validates migration success after execution

**Checks:**
1. No PO-based payments remain (except edge cases)
2. All against_invoice payments have purchase_invoice_id
3. payment_module_allocations table is dropped
4. Backup tables exist
5. Migration snapshots exist
6. Total payment amount preserved
7. Ledger balance integrity
8. Invoicing columns populated
9. Payments requiring manual intervention

**Usage:**
```bash
mysql -u root -p sitepilot_live < database/phase3_validation.sql
```

---

## Rollback Script

**File:** `database/rollback_phase3.sql`

**Purpose:** Reverses all Phase 3 changes if needed

**Process:**
- Restores payment_module_allocations from backup
- Restores original payment types
- Restores ledger entries
- Restores ledger balances
- Removes validation constraints
- Drops snapshot tables

**Usage:**
```bash
mysql -u root -p sitepilot_live < database/rollback_phase3.sql
```

---

## Execution Plan (STRICT SAFE MODE)

### Step 1: Pre-Migration (On Staging)
- [ ] Take full database backup
- [ ] Run baseline validation queries
- [ ] Populate AUDIT_BASELINE_REPORT.md
- [ ] Review migration scripts
- [ ] Get stakeholder approval

### Step 2: Migration Execution (On Staging)
```bash
# Run migrations in order
php artisan migrate --path=database/migrations/2026_04_15_000004_migrate_po_based_payments_to_invoice_based.php
php artisan migrate --path=database/migrations/2026_04_15_000005_make_purchase_invoice_id_not_null.php
php artisan migrate --path=database/migrations/2026_04_15_000006_drop_payment_module_allocations_table.php
php artisan migrate --path=database/migrations/2026_04_15_000007_recalculate_supplier_ledger_balances.php
```

### Step 3: Validation (On Staging)
- [ ] Run validation script: `mysql -u root -p sitepilot_live < database/phase3_validation.sql`
- [ ] Review validation results
- [ ] Check audit logs: `storage/logs/payment_audit.log`
- [ ] Test payment creation workflow
- [ ] Test payment request workflow
- [ ] Verify ledger balances

### Step 4: Approval Decision
- [ ] If validation passes: **PROCEED TO PRODUCTION**
- [ ] If validation fails: **USE ROLLBACK SCRIPT**

### Step 5: Production Execution (After Staging Approval)
- [ ] Take production database backup
- [ ] Run migrations on production (same order)
- [ ] Run validation on production
- [ ] Monitor for 24 hours
- [ ] Verify all workflows working

---

## Risk Assessment

### High Risk Areas
- **Data migration:** Converting payment types and allocations
- **Ledger recalculation:** Balance changes could affect accounting
- **Edge cases:** Payments without invoice_id

### Mitigation Strategies
- **Transaction-based:** All-or-nothing execution
- **Snapshots:** Complete rollback capability
- **Validation:** Comprehensive pre and post checks
- **Staging first:** Mandatory staging validation
- **Audit logging:** Complete traceability

---

## Known Limitations

1. **Edge case payments:** Some PO-based payments without invoice_id may require manual intervention
   - These are flagged in the migration snapshot
   - Review after migration required

2. **CHECK constraint limitation:** MySQL < 8.0.16 uses triggers instead
   - Functionality equivalent, but different implementation
   - Rollback requires manual table recreation for CHECK constraints

3. **Ledger balance differences:** Small differences (< ₹0.01) may occur due to rounding
   - These are expected and acceptable
   - Review significant differences only

---

## Post-Migration Tasks

After Phase 3 completion:

1. **Review migration snapshot** for any payments requiring manual intervention
2. **Update documentation** with actual migration results
3. **Monitor system** for 24-48 hours for any issues
4. **Train users** on any workflow changes
5. **Archive snapshot tables** after 30 days (keep for audit)

---

## Contact

For questions about Phase 3 migration scripts:
- Review the audit document: `PAYMENT_MODULE_AUDIT_AND_REDESIGN.md`
- Check migration logs: `storage/logs/payment_audit.log`
- Review validation results after execution

---

## Approval Required

Before executing Phase 3 migrations:

- [ ] Migration scripts reviewed and approved
- [ ] Staging environment ready
- [ ] Database backup completed
- [ ] Rollback plan documented
- [ ] Stakeholder sign-off obtained

**Approval Signature:** ___________________  
**Date:** ___________________
