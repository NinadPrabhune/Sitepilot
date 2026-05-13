# Ledger Immutability Enforcement

## 🚨 Critical Finding from Scenario 8

**Issue:** System assumes ledger immutability, but test environment used DELETE+REINSERT which broke snapshot integrity.

**Root Cause:** Physical deletion of ledger entries breaks snapshot references and audit trail.

## ✅ Solution Implemented: Soft Delete Pattern

### Migration Applied
- **File:** `2026_04_28_000005_add_soft_delete_to_machinery_ledger.php`
- **Added:** `deleted_at` column with index
- **Status:** ✅ Migrated

### Model Updated
- **File:** `app/Domain/Machinery/Models/MachineryLedger.php`
- **Added:** `use SoftDeletes` trait
- **Status:** ✅ Implemented

## 🔧 Usage Guidelines

### ❌ NEVER Use Physical Delete
```php
// WRONG - Breaks snapshot integrity
DB::table('machinery_ledger')->where('id', 123)->delete();
```

### ✅ Always Use Soft Delete
```php
// CORRECT - Preserves audit trail
MachineryLedger::where('id', 123)->delete();
// OR
DB::table('machinery_ledger')->where('id', 123)->softDelete();
```

### ❌ NEVER Update Critical Fields
```php
// WRONG - Blocked by immutability enforcement
$ledger = MachineryLedger::find(123);
$ledger->amount = 999;
$ledger->save(); // Throws RuntimeException
```

### ✅ Use Correction Pattern
```php
// CORRECT - Create reversal entry
MachineryLedger::create([
    'machinery_id' => $original->machinery_id,
    'workspace_id' => $original->workspace_id,
    'entry_direction' => $original->entry_direction === 'credit' ? 'debit' : 'credit',
    'entry_type' => $original->entry_type,
    'amount' => $original->amount,
    'date' => $original->date,
    'description' => 'Correction for entry #' . $original->id,
    'reversed_entry_id' => $original->id,
    'is_reversal' => true,
]);

// Then soft delete original
$original->delete();
```

### ✅ Query with Soft Delete Awareness
```php
// Automatically excludes soft-deleted entries
$entries = MachineryLedger::where('machinery_id', 1)->get();

// Include soft-deleted entries (for audit)
$allEntries = MachineryLedger::withTrashed()->where('machinery_id', 1)->get();

// Only soft-deleted entries
$deletedEntries = MachineryLedger::onlyTrashed()->where('machinery_id', 1)->get();
```

## 🧪 Test Script Updates

All test scripts must use soft delete for cleanup:

```php
// BEFORE (WRONG)
DB::table('machinery_ledger')->where('machinery_id', 1)->delete();

// AFTER (CORRECT)
MachineryLedger::where('machinery_id', 1)->delete();
```

## 📋 Ledger Correction Pattern

For correcting ledger entries, use reversal entries instead of deletion:

```php
// Pattern: Create reversal entry
MachineryLedger::create([
    'machinery_id' => $original->machinery_id,
    'workspace_id' => $original->workspace_id,
    'entry_direction' => $original->entry_direction === 'credit' ? 'debit' : 'credit',
    'entry_type' => $original->entry_type,
    'amount' => $original->amount,
    'date' => $original->date,
    'description' => 'Correction for entry #' . $original->id,
    'reversed_entry_id' => $original->id,
    'is_reversal' => true,
]);

// Then soft delete original
$original->delete();
```

## 🎯 Benefits

1. **Snapshot Integrity:** Ledger IDs remain valid for snapshot references
2. **Audit Trail:** Complete history preserved, including corrections
3. **Compliance:** Financial audit requirements met
4. **Reversibility:** Can restore entries if needed
5. **Drift Detection:** Accurate recalculation without missing entries

## 📊 System Classification

**Before:** Hybrid mutable ledger + snapshot calculator  
**After:** Immutable ledger with soft delete + snapshot calculator

This aligns with financial system best practices (SAP-style design).
