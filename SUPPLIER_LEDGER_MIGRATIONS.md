# Supplier Ledger Database Migrations

## Overview
This document describes all database migrations for the Supplier Ledger system implemented in 2026.

## Migration Files

### 1. Initial Table Creation
**File**: `2026_03_31_000001_create_supplier_transactions_table.php`

Creates the base `supplier_transactions` table.

```bash
php artisan migrate
```

**Columns**:
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| supplier_id | BIGINT | Foreign key to suppliers |
| site_id | BIGINT | Foreign key to sites (nullable) |
| reference_type | VARCHAR(255) | Type: po, grn, invoice, payment, advance, adjustment |
| reference_id | BIGINT | ID of referenced record |
| transaction_date | DATE | Date of transaction |
| debit | DECIMAL(15,2) | Debit amount |
| credit | DECIMAL(15,2) | Credit amount |
| balance | DECIMAL(15,2) | Running balance |
| description | TEXT | Description text |
| workspace_id | BIGINT | Workspace reference |
| created_by | BIGINT | User who created |

**Indexes**:
- `supplier_id`
- `site_id`
- `reference_id`
- `transaction_date`
- `(supplier_id, transaction_date)`
- `(reference_type, reference_id)`

---

### 2. Add Reference Amount Column
**File**: `2026_04_03_000001_add_reference_amount_to_supplier_transactions_table.php`

Adds columns for improved data storage and audit trail.

```bash
php artisan migrate
```

**New Columns**:
| Column | Type | Description |
|--------|------|-------------|
| reference_amount | DECIMAL(15,2) | Store original amount (e.g., invoice total) |
| updated_by | BIGINT | Track last modifier (nullable) |

**Purpose**:
- `reference_amount`: Stores the actual amount for invoices, POs, payments - useful for reporting without parsing description
- `updated_by`: Audit trail - tracks who last modified the transaction

---

### 3. Add Optimized Indexes
**File**: `2026_04_03_000002_add_optimized_indexes_to_supplier_transactions_table.php`

Adds performance-optimized indexes for common query patterns.

```bash
php artisan migrate
```

**New Indexes**:
| Index Name | Columns | Purpose |
|------------|---------|---------|
| supplier_transactions_supplier_site_index | (supplier_id, site_id) | Composite filter queries |
| supplier_transactions_reference_type_index | reference_type | Type-based filtering |
| supplier_transactions_date_index | transaction_date | Date range queries |

**Performance Benefits**:
- Faster filtered queries by supplier + site
- Improved aggregation by reference type
- Better date range performance

---

## Running Migrations

### Fresh Install
```bash
php artisan migrate
```

### Check Status
```bash
php artisan migrate:status
```

### Rollback (Caution!)
```bash
php artisan migrate:rollback
```

---

## Column Reference

### Reference Types
The `reference_type` column supports these values:

| Type | Debit | Credit | Balance Impact |
|------|-------|--------|----------------|
| `po` | PO Amount | 0 | Increases |
| `grn` | 0 | 0 | No impact |
| `invoice` | 0 | 0 | No impact |
| `payment` | 0 | Amount | Decreases |
| `advance` | 0 | Amount | Decreases |
| `adjustment` | Variable | Variable | Variable |

### Balance Formula
```
balance = previous_balance + debit - credit
```

---

## Database Schema Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                  supplier_transactions                      │
├─────────────────────────────────────────────────────────────┤
│ id                      BIGINT (PK)                         │
│ supplier_id             BIGINT (FK → suppliers)              │
│ site_id                 BIGINT (FK → sites)                │
│ reference_type          VARCHAR(50)                        │
│ reference_id            BIGINT                             │
│ reference_amount        DECIMAL(15,2)                      │
│ transaction_date         DATE                               │
│ debit                   DECIMAL(15,2) DEFAULT 0            │
│ credit                  DECIMAL(15,2) DEFAULT 0            │
│ balance                 DECIMAL(15,2) DEFAULT 0            │
│ description             TEXT                               │
│ workspace_id            BIGINT                             │
│ created_by              BIGINT                             │
│ updated_by              BIGINT (nullable)                  │
│ created_at              TIMESTAMP                           │
│ updated_at              TIMESTAMP                           │
├─────────────────────────────────────────────────────────────┤
│ INDEXES:                                                   │
│ - supplier_id                                    │
│ - site_id                                       │
│ - reference_id                                  │
│ - transaction_date                              │
│ - (supplier_id, transaction_date)              │
│ - (reference_type, reference_id)               │
│ - (supplier_id, site_id)        [NEW]          │
│ - reference_type                 [NEW]          │
└─────────────────────────────────────────────────────────────┘
```

---

## Troubleshooting

### Migration Fails - Duplicate Column
If you see "Duplicate column name" errors:
- The migrations check for existing columns before adding
- Safe to run multiple times

### Index Creation Fails
If index creation fails:
- Some database engines don't support concurrent index creation
- Migration includes error handling

### Data Migration Considerations
When migrating existing data:
1. Run `--dry-run` on ledger recalculation first
2. Backup database
3. Ensure `reference_amount` is populated for existing records
4. Run: `php artisan ledger:recalculate`

---

## Related Documentation

- [Ledger Recalculation Command](./LEDGER_RECALCULATION_COMMAND.md)
- [Supplier Ledger Report](./SUPPLIER_LEDGER_REPORT.md)

---

## Version
- **v1.0** - 2026-04-03 - Initial implementation
