# Diesel Responsibility Implementation Summary

## Overview
Successfully implemented diesel cost responsibility logic across billing and payment workflows to respect the `diesel_by_company` flag on machinery records.

---

## Files Created

### 1. New Service Helper
**File:** `app/Domain/Machinery/Services/DieselResponsibilityService.php`
- **Purpose:** Centralized rule helper for diesel payment decisions
- **Methods:**
  - `companyPaysDiesel(Machinery $machinery): bool`
  - `shouldDeductDieselFromPayment(Machinery $machinery): bool`
  - `getDeductibleDieselAmount(Machinery $machinery, float $dieselCost): float`

### 2. New Migration
**File:** `database/migrations/2026_05_06_000001_add_diesel_audit_fields_to_billing_items.php`
- **Purpose:** Add transparency fields for audit trail
- **New Fields:**
  - `diesel_cost_actual` (decimal 12,2) - Full calculated diesel cost
  - `diesel_cost_deducted` (decimal 12,2) - Amount actually deducted from bill
  - `diesel_responsibility` (string 20) - 'company' or 'supplier'

### 3. Unit Test
**File:** `tests/Unit/DieselResponsibilityServiceTest.php`
- **Purpose:** Verify helper logic works correctly
- **Test Cases:**
  - Company pays diesel scenario
  - Supplier pays diesel scenario
  - Zero diesel cost handling
  - Null value handling

---

## Files Modified

### 1. BillingService.php
**File:** `app/Services/BillingService.php`

**Changes:**
- Added import for `DieselResponsibilityService`
- Updated `calculateAmount()` to accept `$dieselCost` (already converted from liters)
- Added logic to only include diesel in bill when `diesel_by_company = true`
- Added audit fields (`diesel_cost_actual`, `diesel_cost_deducted`, `diesel_responsibility`) to billing item creation

**Key Logic:**
```php
$dieselCost = $totalDieselLiters * ($machine->diesel_rate ?? 0);
$dieselAmount = DieselResponsibilityService::getDeductibleDieselAmount($machine, $dieselCost);
return round($hourlyAmount + $dieselAmount, 2);
```

### 2. MachineryPaymentRequestService.php
**File:** `app/Domain/Machinery/Services/MachineryPaymentRequestService.php`

**Changes:**
- Added imports for `Machinery` model
- Updated `calculatePayable()` signature to accept `Machinery $machinery` parameter
- Added diesel filtering logic in `calculatePayable()`:
  - Always include non-diesel debits (maintenance, advances)
  - Only include diesel debits if company pays
- Added audit fields to calculation result
- Updated `reverifyCalculation()` to use same diesel filtering logic
- **CRITICAL:** `lockLedgerEntries()` remains unchanged (pure locking, no filtering)

**Key Logic:**
```php
$debits = $entries->where('entry_direction', 'debit')
    ->filter(function ($entry) use ($machinery) {
        if ($entry->entry_type !== 'diesel') {
            return true; // Always include non-diesel
        }
        return DieselResponsibilityService::companyPaysDiesel($machinery);
    })
    ->sum('amount');
```

### 3. MachineryBillingItem.php Model
**File:** `app/Models/MachineryBillingItem.php`

**Changes:**
- Added new fields to `$fillable` array:
  - `diesel_cost_actual`
  - `diesel_cost_deducted`
  - `diesel_responsibility`
- Added decimal casts for new fields

---

## Calculation Flow

### Billing Layer (DPR → Bill)
```
DPR.diesel_consumption (liters)
         ↓
× machinery.diesel_rate (₹/liter)
         ↓
= dieselCost (₹)
         ↓
DieselResponsibilityService::getDeductibleDieselAmount()
         ↓
if diesel_by_company = true  → include full cost
if diesel_by_company = false   → include ₹0
         ↓
Final Bill Amount = (hours × hourly_rate) + dieselAmount
```

### Payment Layer (Ledger → Payment Request)
```
Ledger Entries (all types)
         ↓
Credits: Always included (DPR work)
Debits: Filtered
  ├─ Non-diesel (maintenance, advances) → Always included
  └─ Diesel → Only if company pays
         ↓
Net Payable = Credits - Filtered Debits
```

---

## Test Scenarios

### Scenario 1: Supplier Pays Diesel
```
Machinery: diesel_by_company = false
DPR Work: ₹10,000 (credit)
Diesel: 50L @ ₹80 = ₹4,000 (debit)

Expected:
┌──────────────────────┬──────────┐
│ Billing Amount       │ ₹10,000  │
│ Payment Amount       │ ₹10,000  │
│ Diesel Deducted      │ ₹0       │
│ Diesel Responsibility│ supplier │
└──────────────────────┴──────────┘
```

### Scenario 2: Company Pays Diesel
```
Machinery: diesel_by_company = true
DPR Work: ₹10,000 (credit)
Diesel: 50L @ ₹80 = ₹4,000 (debit)

Expected:
┌──────────────────────┬─────────┐
│ Billing Amount       │ ₹6,000  │
│ Payment Amount       │ ₹6,000  │
│ Diesel Deducted      │ ₹4,000  │
│ Diesel Responsibility│ company │
└──────────────────────┴─────────┘
```

### Scenario 3: Cross-Verification (Critical)
```
For any period:
Bill Total == Payment Request Total ✅ MUST MATCH
```

---

## Database Migration

Run the following to apply the new audit fields:
```bash
php artisan migrate --path=database/migrations/2026_05_06_000001_add_diesel_audit_fields_to_billing_items.php
```

---

## Rollback Plan

If issues arise:
1. Revert `BillingService.php` changes
2. Revert `MachineryPaymentRequestService.php` changes
3. Rollback migration:
   ```bash
   php artisan migrate:rollback --path=database/migrations/2026_05_06_000001_add_diesel_audit_fields_to_billing_items.php
   ```
4. Revert `MachineryBillingItem.php` model changes
5. Delete `DieselResponsibilityService.php` (optional - harmless if unused)

---

## Verification Checklist

- [x] `DieselResponsibilityService` created and loads correctly
- [x] BillingService uses helper for diesel calculation
- [x] PaymentRequestService filters diesel debits based on flag
- [x] `lockLedgerEntries()` unchanged (no modifications)
- [x] Migration created for audit fields
- [x] Model updated with new fillable fields
- [x] Unit tests created for helper service
- [ ] Integration tests run successfully
- [ ] Manual test: Supplier pays scenario
- [ ] Manual test: Company pays scenario
- [ ] Verify billing == payment totals match

---

## Key Design Decisions

1. **Single Source of Truth:** `DieselResponsibilityService` provides centralized logic
2. **Data Source Separation:** Billing uses DPR (liters), Payment uses Ledger (₹)
3. **Locking Integrity:** `lockLedgerEntries()` remains pure, filtering happens in calculation layer
4. **Audit Transparency:** New fields show diesel breakdown for finance team visibility
5. **No Schema Changes to Core Tables:** Only added fields to billing items (additive only)

---

## Implementation Date
May 6, 2026

## Status
✅ **IMPLEMENTATION COMPLETE**
Pending testing and validation.
