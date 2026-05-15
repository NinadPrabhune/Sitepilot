# Database Schema Comparison Report

## Overview

- **Database A (Source of Truth):** `sitepilot_local(21)`
- **Database B (Target):** `sitepilot_live(5)`
- **Database Engine:** MySQL / MariaDB compatible dump (phpMyAdmin export)
- **Charset:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`

## Executive Summary

| Metric | Count |
|---|---:|
| Tables in Local | 276 |
| Tables in Live | 235 |
| Missing Tables in Live | 46 |
| Extra Tables in Live | 5 |
| Tables With Structural Differences | 39 |

---

# Missing Tables in Database B (Live)

These tables exist in Local but are missing from Live.

| Missing Table |
|---|
| assets_tools_and_equipment_transfer |
| calculation_versions |
| daily_health_check_logs |
| destructive_command_attempts |
| dpr_anomalies |
| dpr_edit_history |
| escalation_requests |
| financial_escalations |
| financial_gate_blocks |
| financial_period_locks |
| financial_postings |
| invariant_logs |
| item_categories |
| items |
| journal_adjustments |
| ledger_entries |
| ledger_integrity_logs |
| machinery_billing_items |
| machinery_bills |
| machinery_ledger |
| machinery_ownerships |
| machinery_payment_allocations |
| machinery_payment_request_items |
| machinery_payment_requests |
| machinery_rate_histories |
| machinery_supplier_rates |
| machinery_usage_logs |
| material_consumption_audits |
| material_consumption_versions |
| payment_audit_logs |
| payment_calculation_snapshots |
| payment_health_logs |
| payment_request_histories |
| payment_request_status_logs |
| payment_reversals |
| posting_batches |
| posting_failures |
| reconciliation_logs |
| supplier_advances |
| supplier_ledger |
| supplier_ledger_entries |
| transaction_integrity_logs |
| usage_calculation_logs |
| workflow_audits |
| workflow_state_histories |
| workflow_transitions |

---

# Extra Tables Present Only in Database B (Live)

| Extra Table |
|---|
| chart_of_account_parents |
| marketplace_page_settings |
| payment_migration_map |
| payment_migration_snapshot |
| po_status_logs |

## Possible Notes

- `payment_migration_*` tables appear migration-related and may be temporary.
- `chart_of_account_parents` may represent an old accounting hierarchy implementation.

---

# Major Column Differences

## activities

| Difference Type | Details |
|---|---|
| Extra column in Live | `user_id bigint unsigned DEFAULT NULL` |
| Nullability mismatch | `start_date` is `NOT NULL` in Local but nullable in Live |
| Index difference | Live uses partial index: `assign_to(250)` |

### Suggested SQL

```sql
ALTER TABLE activities
MODIFY start_date DATETIME NOT NULL;
```

---

## announcements

| Difference Type | Details |
|---|---|
| Extra column in Live | `site_id bigint NOT NULL` |

### Risk

Dropping this column may break production logic.

---

## announcement_employees

| Difference Type | Details |
|---|---|
| Extra column in Live | `site_id bigint NOT NULL` |

---

## advance_audit_logs

| Difference Type | Details |
|---|---|
| Type mismatch | `old_value` = JSON in Local vs LONGTEXT in Live |
| Type mismatch | `new_value` = JSON in Local vs LONGTEXT in Live |
| Collation mismatch | ENUM/collation variations |

### Suggested SQL

```sql
ALTER TABLE advance_audit_logs
MODIFY old_value JSON NULL,
MODIFY new_value JSON NULL;
```

### Risk

MySQL JSON conversion can fail if existing LONGTEXT data contains invalid JSON.

---

## advance_utilizations

| Difference Type | Details |
|---|---|
| Missing indexes in Live | `idx_advance_status_amount` |
| Missing indexes in Live | `idx_invoice_status_amount` |
| Missing indexes in Live | `idx_flow_created` |

### Suggested SQL

```sql
ALTER TABLE advance_utilizations
ADD KEY idx_advance_status_amount (supplier_advance_id,status,utilized_amount),
ADD KEY idx_invoice_status_amount (purchase_invoice_id,status,utilized_amount),
ADD KEY idx_flow_created (transaction_flow_id,created_at);
```

---

## assets_tools_and_equipment

| Difference Type | Details |
|---|---|
| Default mismatch | `created_at` |
| Default mismatch | `updated_at` |

Local:

```sql
timestamp NULL DEFAULT NULL
```

Live:

```sql
timestamp NULL DEFAULT CURRENT_TIMESTAMP
```

---

## attendances

| Difference Type | Details |
|---|---|
| Type mismatch | `site_id` unsigned in Local but signed in Live |

### Suggested SQL

```sql
ALTER TABLE attendances
MODIFY site_id BIGINT UNSIGNED NULL;
```

---

## ch_notifications

| Difference Type | Details |
|---|---|
| Type mismatch | `message_arr` JSON in Local vs TEXT in Live |

### Suggested SQL

```sql
ALTER TABLE ch_notifications
MODIFY message_arr JSON NULL;
```

---

## daily_consumption_details

| Missing Columns in Live |
|---|
| total_price |
| unit_price |

### Suggested SQL

```sql
ALTER TABLE daily_consumption_details
ADD COLUMN unit_price DECIMAL(15,2) NULL,
ADD COLUMN total_price DECIMAL(15,2) NULL;
```

---

## daily_consumption_masters

### Missing Columns in Live

| Column |
|---|
| diesel_consumed_liters |
| diesel_rate |
| diesel_total_cost |
| ledger_entry_id |
| supplier_ledger_entry_id |
| version |
| warning_override_count |
| warning_overrides |

### Observation

Local schema contains enhanced financial/audit tracking not yet deployed to Live.

---

## daily_progress_reports

### Large Structural Drift Detected

The Live database is significantly behind Local.

### Missing Columns in Live

| Column |
|---|
| approved_at |
| approved_by |
| audit_log |
| billable_hours |
| billed_at |
| calculated_amount |
| calculation_hash |
| captured_at |
| captured_by |
| critical_drift_count |
| deleted_at |
| deleted_by |
| drift_count |
| hash_mismatch_count |
| is_billed |
| is_locked |
| ledger_entry_id |
| lifecycle_state |
| locked_at |
| locked_by |
| machine_idle_reading |
| manual_balance_check |
| manual_balance_matched |
| manual_balance_notes |
| notes |
| oldest_pending_age_hours |
| operator_names |
| orphan_count |
| override_at |
| override_by |
| override_rate |
| override_reason |
| paid_at |
| paid_by |
| payment_request_id |
| payment_status |
| pending_approvals |
| rate_snapshot |
| rejected_at |
| rejected_by |
| rejection_reason |
| reversal_rate_percent |
| snapshot_date |
| source_type |
| system_health_status |
| total_entries |
| total_reversals |
| verified_at |
| verified_by |
| warning_override_count |
| warning_overrides |

### Risk Level

HIGH — financial calculation, auditability, workflow state management, and DPR reconciliation logic may fail on Live.

---

# Index Differences

## Missing Indexes in Live

| Table | Missing Index |
|---|---|
| advance_utilizations | idx_advance_status_amount |
| advance_utilizations | idx_invoice_status_amount |
| advance_utilizations | idx_flow_created |
| calculation_versions | calculation_versions_type_is_active_index |
| calculation_versions | calculation_versions_effective_from_index |

---

# Constraint Differences

## Observed Differences

| Table | Difference |
|---|---|
| activities | Different index definition on `assign_to` |
| attendances | Unsigned mismatch can affect FK compatibility |
| advance_audit_logs | JSON conversion affects validation rules |

No major primary-key drift detected in inspected tables.

---

# Views / Triggers / Stored Procedures

## Result

No significant stored procedures or trigger definitions were found in the provided dumps.

Possible reasons:
- Schema-only export without routines
- `--routines` not included during dump
- Application relies mostly on Laravel/Eloquent logic

---

# Suggested Migration Order

## Phase 1 — Add Missing Tables

Deploy all missing financial + machinery tables first.

Priority:
1. ledger_entries
2. supplier_ledger
3. machinery_payment_requests
4. machinery_payment_request_items
5. machinery_ledger
6. daily_health_check_logs
7. workflow tables

---

## Phase 2 — Add Missing Columns

Focus on:
- daily_progress_reports
- daily_consumption_masters
- payment tracking tables

---

## Phase 3 — Add Indexes

Especially composite indexes used for:
- reconciliation
- payment calculation
- audit queries
- DPR reporting

---

## Phase 4 — Convert JSON/TEXT Fields

Carefully validate production data before:

```sql
LONGTEXT -> JSON
```

Migration can fail if malformed JSON exists.

---

# Safe Migration Script Examples

## Create Missing Table Example

```sql
CREATE TABLE machinery_payment_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Add Missing Column Example

```sql
ALTER TABLE daily_progress_reports
ADD COLUMN calculation_hash VARCHAR(255) NULL;
```

---

## Add Missing Index Example

```sql
ALTER TABLE advance_utilizations
ADD KEY idx_flow_created (transaction_flow_id, created_at);
```

---

# Risk Analysis

| Risk | Severity | Notes |
|---|---|---|
| Missing machinery billing tables | HIGH | Payment processing may fail |
| Missing DPR workflow fields | HIGH | Audit + reconciliation inconsistency |
| JSON conversion mismatch | HIGH | Migration failure possible |
| Missing financial ledgers | CRITICAL | Accounting integrity risk |
| Missing indexes | MEDIUM | Query performance degradation |
| Signed vs unsigned mismatch | MEDIUM | FK incompatibility risk |
| Timestamp default mismatch | LOW | Behavioral inconsistency |

---

# Final Assessment

The Live database is substantially behind the Local development schema.

Major gaps exist in:
- Machinery billing
- DPR lifecycle tracking
- Financial posting
- Ledger systems
- Audit infrastructure
- Workflow management
- Reconciliation systems

The migration should be performed in controlled phases with:
- Full backup
- Dry-run on staging
- Data validation for JSON conversions
- Foreign-key validation before enabling constraints

Recommended approach:
1. Deploy missing tables
2. Deploy non-breaking columns
3. Backfill data
4. Add indexes
5. Enable strict constraints
6. Run reconciliation checks

