# Numbering Gap Policy for Financial Modules

## Overview

This document defines the policy for handling sequence gaps in financial module numbering (Invoices, Payments).

## Current Behavior

The auto-numbering system generates numbers sequentially, but gaps can occur when:

1. Number is generated → Insert fails (validation error, constraint violation, etc.)
2. Transaction is rolled back after number generation
3. Manual deletion of records

**Example:**
- INV-0005 generated → Insert fails → INV-0006 created
- Result: INV-0005 is missing from the sequence

## Policy Decision

### ✅ ACCEPTED: Allow Gaps (Option A)

**Rationale:**
- Industry standard for most ERP systems
- Simplifies implementation and performance
- Gaps are acceptable for non-strict accounting
- Documented and transparent

**Applicable Modules:**
- Purchase Orders (PO)
- Indents (IND)
- GRNs (GRN-)
- Invoices (INV-)
- Payments (PAY-)

**Audit Trail:**
- All number generation events are logged (in debug mode)
- Retry attempts are logged as warnings
- Can trace why a gap occurred if needed

## When Gaps Are Problematic

Gaps may be problematic for:

1. **Strict Accounting Systems** - Some jurisdictions require continuous sequences for invoices
2. **Regulatory Compliance** - Certain industries (banking, government) may require gap-free sequences
3. **Customer Expectations** - Some customers expect sequential invoice numbers

## Mitigation Strategies

### For Strict Accounting Requirements

If your organization requires gap-free sequences for invoices/payments, consider:

**Option B: Track Gaps (Recommended)**
- Add logging when a number is skipped
- Maintain a "skipped_numbers" table
- Generate gap reports for auditors

**Option C: Strict Sequence (Complex)**
- Reserve number before transaction
- Commit after successful insert
- Handle rollback and number release
- Requires additional infrastructure

## Implementation Notes

### Current Implementation

The current system uses:
- DB locking with `lockForUpdate()`
- Retry logic on duplicate key errors
- Unique constraints as final safety net

This approach prioritizes:
- **Performance** - Locking is minimal
- **Scalability** - Works under high concurrency
- **Simplicity** - Easy to maintain

### Gap Prevention

To minimize gaps:
1. Ensure all validations happen before number generation
2. Use DB transactions properly
3. Avoid manual deletion of records
4. Handle errors gracefully

## Gap Reporting

### SQL Query to Identify Gaps

```sql
-- Find gaps in invoice numbers for a specific site
SELECT 
    site_id,
    CONCAT('Gap between: ', 
        (SELECT invoice_number FROM purchase_invoices i2 
         WHERE i2.site_id = i1.site_id 
         AND i2.id < i1.id 
         ORDER BY i2.id DESC LIMIT 1),
        ' and ', i1.invoice_number) as gap_description
FROM purchase_invoices i1
WHERE site_id = 1
ORDER BY id;
```

### Laravel Code to Track Skipped Numbers

```php
// In NumberGeneratorService, after a failed insert:
Log::warning('Number skipped due to failed insert', [
    'module' => $module,
    'site_id' => $siteId,
    'skipped_number' => $generatedNumber,
    'reason' => $exception->getMessage(),
]);
```

## Recommendation

**For Current System:**
- Accept gaps as documented behavior
- Add gap reporting for audit purposes
- Document this policy clearly

**For Future Enhancement:**
- Consider adding gap tracking if strict accounting is required
- Evaluate business requirements before implementing strict sequences

## References

- Migration: `2026_04_24_000001_add_unique_number_per_site_constraints.php`
- Service: `app/Services/NumberGeneratorService.php`
- Documentation: `AUTO_NUMBERING_IMPLEMENTATION.md`
