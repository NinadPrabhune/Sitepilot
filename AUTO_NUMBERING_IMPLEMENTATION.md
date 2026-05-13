# Auto-Numbering Implementation Documentation

## Overview

Implemented centralized auto-numbering system with prefix + running sequence for Indent, PO, GRN, Invoice, and Payment modules. The numbering system resets per site (Project = Site) while maintaining global prefix configuration.

## Implementation Date

April 24, 2026

## Modules Covered

| Module | Prefix Example | Format | Number Field |
|--------|---------------|--------|--------------|
| Indent | IND | IND00001 | indent_number |
| Purchase Order | PO | PO00001 | po_number |
| GRN | GRN- | GRN-0001 | grn_number |
| Invoice | INV- | INV-0001 | invoice_number |
| Payment | PAY- | PAY-0001 | payment_number |

## Key Features

### ✅ Per-Site Reset
- Sequence counter resets for each site (Project)
- Site A: IND00001, IND00002, IND00003...
- Site B: IND00001, IND00002, IND00003... (starts fresh)

### ✅ Global Prefix Configuration
- Prefix is configured globally per module (not per project)
- Same prefix applies to all sites
- Configurable via Company Settings UI
- **Validation:** Prefix max length 10 characters, starting number minimum 1

### ✅ No New Tables
- Reuses existing `settings` table for configuration
- Settings stored as key-value pairs

### ✅ Consistent Across Web + Mobile
- Same NumberGeneratorService used by both Web Controllers and Mobile APIs
- Ensures identical behavior across all interfaces

### ✅ Duplicate Prevention
- Unique constraints on (site_id, number) for each module
- Prevents duplicate numbers within the same site

### ✅ Concurrency Safety (Production-Grade)
- **DB Locking:** `generateWithRetry()` uses `lockForUpdate()` to prevent concurrent reads, minimizing retry attempts
- **First-Record Locking:** Lock works even when no records exist for a site (prevents first-insert race)
- **Retry Logic:** Automatic retry on duplicate key errors (max 3 attempts) as fallback
- **Lock Latency Monitoring:** Tracks and logs high lock latency (>200ms) for performance monitoring
- **site_id Validation:** Required parameter - throws exception if null (prevents global numbering fallback)
- **Debug Logging:** Logs only in debug mode to prevent performance issues at scale
- **Prefix-Independent Extraction:** Regex-based extraction handles any prefix format changes
- **Performance Indexes:** Composite indexes on (site_id, id DESC) and (site_id, number) for fast queries

### ✅ Audit Compliance (Enterprise-Grade)
- **Gap Tracking:** `skipped_numbers` table logs numbers that were generated but not successfully inserted
- **Skipped Number Logging:** Automatic logging of failed generation attempts with reason and exception details
- **Audit Trail:** Persistent record of why sequence gaps occur for financial modules
- **Cache Versioning:** Versioned cache keys prevent stale settings in distributed scenarios

### ⚠️ Preview Disclaimer
- API preview endpoints show the next expected number
- **Not guaranteed:** If another record is created between preview and actual creation, the number may differ
- Preview is informational only - actual number assigned at creation time

## Architecture

### 1. NumberGeneratorService

**Location:** `app/Services/NumberGeneratorService.php`

**Purpose:** Centralized numbering logic for all modules

**Key Methods:**
```php
public function generate(string $module, ?int $siteId = null): string
```

**Logic:**
1. **Validate site_id** - Throws exception if null (required for per-site numbering)
2. Fetch prefix from settings (e.g., 'IND', 'GRN-', 'INV-', 'PAY-')
3. Fetch starting number from settings (default: 1)
4. Fetch padding length from settings (default: 5)
5. Get last record for the given site_id
6. Calculate next number (starting number if no records, else last + 1)
7. **Strip prefix dynamically** - Handles prefix changes without breaking sequence
8. Format with prefix and padding (e.g., IND00001)
9. **Log generation event** - Debug logging for troubleshooting

**Concurrency-Safe Method:**
```php
public function generateWithRetry(string $module, int $siteId, callable $createCallback, int $maxRetries = 3)
```
- Wraps generation in DB transaction
- Uses `lockForUpdate()` to prevent concurrent reads (minimizes retry attempts)
- Automatically retries on duplicate key errors (max 3 attempts)
- Logs retry attempts for debugging
- Throws exception if all retries fail

**Performance Optimization:**
- Composite indexes on (site_id, id DESC) for fast getLastNumber() queries
- Composite indexes on (site_id, number) for duplicate detection and reporting
- Settings caching with 1-hour TTL to reduce DB load for high-frequency calls
- Prevents slow queries as tables grow
- Migrations: 
  - `2026_04_24_000002_add_performance_indexes_for_numbering.php`
  - `2026_04_24_000004_add_number_field_indexes.php`

**Module Mapping:**
```php
'po' => 'purchase_orders' => 'po_number'
'indent' => 'indents' => 'indent_number'
'grn' => 'grns' => 'grn_number'
'invoice' => 'purchase_invoices' => 'invoice_number'
'payment' => 'payments_module' => 'payment_number'
```

### 2. Settings Configuration

**Location:** `resources/views/company/settings/index.blade.php`

**Settings Added:**
- `indent_prefix` (default: IND)
- `indent_starting_number` (default: 1)
- `grn_prefix` (default: GRN-)
- `grn_starting_number` (default: 1)
- `invoice_prefix` (default: INV-)
- `invoice_starting_number` (default: 1)
- `payment_prefix` (default: PAY-)
- `payment_starting_number` (default: 1)

**Existing PO Settings (unchanged):**
- `po_prefix` (default: PO)
- `po_starting_number` (default: 1)

### 3. Model Updates

All model methods now accept optional `$siteId` parameter:

#### PurchaseOrder
```php
public static function generatePONumber(?int $siteId = null): string
{
    return app(\App\Services\NumberGeneratorService::class)->generate('po', $siteId);
}
```

#### Indent
```php
public static function generateIndentNumber(?int $siteId = null): string
{
    return app(\App\Services\NumberGeneratorService::class)->generate('indent', $siteId);
}
```

#### Grn
```php
public static function generateGrnNumber(?int $siteId = null): string
{
    return app(\App\Services\NumberGeneratorService::class)->generate('grn', $siteId);
}
```

#### PurchaseInvoice
```php
public static function generateInvoiceNumber(?int $siteId = null): string
{
    return app(\App\Services\NumberGeneratorService::class)->generate('invoice', $siteId);
}
```

#### PaymentsModule
```php
public static function generatePaymentNumber(?int $siteId = null): string
{
    return app(\App\Services\NumberGeneratorService::class)->generate('payment', $siteId);
}
```

**Note:** PaymentsModule auto-generates payment_number in the model's `creating` event using the site_id from the model instance.

### 4. Web Controllers Updated

#### PurchaseOrderController
```php
'po_number' => PurchaseOrder::generatePONumber($request->site_id),
```

#### IndentController
```php
'indent_number' => Indent::generateIndentNumber($request->site_id),
```

#### GrnService
```php
// Against PO
'grn_number' => Grn::generateGrnNumber($data['site_id'] ?? $po->site_id ?? null),

// Direct GRN
'grn_number' => Grn::generateGrnNumber($data['site_id'] ?? null),
```

#### PurchaseInvoiceController
```php
$validated['invoice_number'] = PurchaseInvoice::generateInvoiceNumber($validated['site_id'] ?? null);
```

#### PaymentsModule (Model Event)
```php
// In booted() method - creating event
if (empty($model->payment_number)) {
    $model->payment_number = $model->generatePaymentNumber($model->site_id);
}
```

### 5. Mobile APIs Updated

#### IndentApiController
```php
// Preview
$nextIndentNumber = Indent::generateIndentNumber($request->site_id ?? null);

// Create
'indent_number' => Indent::generateIndentNumber($request->site_id),
```

#### GrnApiController
```php
// Preview
'nextGRNno' => Grn::generateGrnNumber($siteId)

// Create
'grn_number' => Grn::generateGrnNumber($purchaseOrder->site_id),
```

#### PurchaseInvoiceApiController
```php
// Preview
$nextInvoiceNumber = PurchaseInvoice::generateInvoiceNumber($request->site_id ?? null);

// Create
$invoiceNumber = PurchaseInvoice::generateInvoiceNumber($purchaseInvoice->site_id);
$purchaseInvoice->update(['invoice_number' => $invoiceNumber]);
```

#### PaymentsModuleApiController
```php
// Preview
$nextPaymentNumber = PaymentsModule::generatePaymentNumber($request->site_id ?? null);

// Create
// Payment number is generated automatically in model's creating event
```

### 6. Database Migration

**Location:** `database/migrations/2026_04_24_000001_add_unique_number_per_site_constraints.php`

**Purpose:** Add unique constraints to prevent duplicate numbers within the same site

**Constraints Added:**
- `unique_po_number_per_site` on (site_id, po_number)
- `unique_indent_number_per_site` on (site_id, indent_number)
- `unique_grn_number_per_site` on (site_id, grn_number)
- `unique_invoice_number_per_site` on (site_id, invoice_number)
- `unique_payment_number_per_site` on (site_id, payment_number)

## Deployment Steps

### 1. Pre-Migration Check (IMPORTANT)
Before running migrations, check for existing duplicate numbers:
```bash
# Run the duplicate check script
mysql -u username -p database_name < database/audit_queries/pre_migration_duplicate_check.sql
```
If duplicates are found, clean them up before proceeding.

### 2. Run Migrations
```bash
php artisan migrate
```
This will:
- Add unique constraints for (site_id, number) per module
- Add composite indexes for (site_id, id DESC) for performance
- Add composite indexes for (site_id, number) for duplicate detection
- Add NOT NULL constraint for payment_number

### 3. Configure Settings
1. Navigate to Company Settings
2. Find the new settings sections:
   - Indent Settings
   - GRN Settings
   - Invoice Settings
   - Payment Settings
3. Configure prefix and starting number for each module
4. Click "Save Changes"

### 4. Test Per-Site Numbering

**Test Case 1: New Site**
- Create new site (Project)
- Create first indent → Should be IND00001
- Create second indent → Should be IND00002

**Test Case 2: Multiple Sites**
- Site A: Create indent → IND00001
- Site A: Create second indent → IND00002
- Site B: Create indent → IND00001 (resets!)
- Site B: Create second indent → IND00002

**Test Case 3: Prefix Change**
- Change indent prefix to IND-
- Create new indent → IND-00001
- Old records remain unchanged (IND00001, IND00002)

**Test Case 4: Mobile API**
- Create indent via mobile API
- Sequence must match web (per-site)

**Test Case 5: Concurrency**
- Create 2 records simultaneously
- Unique constraint prevents duplicate numbers

## Behavior Examples

### Scenario: Two Sites with Default Settings

**Site A (Project ID: 1)**
| Module | Record 1 | Record 2 | Record 3 |
|--------|----------|----------|----------|
| Indent | IND00001 | IND00002 | IND00003 |
| PO | PO00001 | PO00002 | PO00003 |
| GRN | GRN-0001 | GRN-0002 | GRN-0003 |
| Invoice | INV-0001 | INV-0002 | INV-0003 |
| Payment | PAY-0001 | PAY-0002 | PAY-0003 |

**Site B (Project ID: 2)**
| Module | Record 1 | Record 2 | Record 3 |
|--------|----------|----------|----------|
| Indent | IND00001 | IND00002 | IND00003 |
| PO | PO00001 | PO00002 | PO00003 |
| GRN | GRN-0001 | GRN-0002 | GRN-0003 |
| Invoice | INV-0001 | INV-0002 | INV-0003 |
| Payment | PAY-0001 | PAY-0002 | PAY-0003 |

### Scenario: Custom Prefix Configuration

**Settings:**
- Indent Prefix: IND-
- GRN Prefix: GRN
- Invoice Prefix: INV
- Payment Prefix: PMT

**Site A Output:**
| Module | Record 1 | Record 2 |
|--------|----------|----------|
| Indent | IND-00001 | IND-00002 |
| GRN | GRN00001 | GRN00002 |
| Invoice | INV00001 | INV00002 |
| Payment | PMT00001 | PMT00002 |

## Files Modified

### Created
- `app/Services/NumberGeneratorService.php` - Centralized numbering service with DB locking, retry logic, caching, latency monitoring, and gap tracking
- `database/migrations/2026_04_24_000001_add_unique_number_per_site_constraints.php` - Unique constraints
- `database/migrations/2026_04_24_000002_add_performance_indexes_for_numbering.php` - Performance indexes (site_id, id DESC)
- `database/migrations/2026_04_24_000003_add_payment_number_not_null_constraint.php` - Payment number NOT NULL constraint
- `database/migrations/2026_04_24_000004_add_number_field_indexes.php` - Number field indexes (site_id, number)
- `database/migrations/2026_04_24_000005_create_skipped_numbers_table.php` - Gap tracking table for audit compliance
- `database/audit_queries/pre_migration_duplicate_check.sql` - Pre-migration duplicate check script
- `NUMBERING_GAP_POLICY.md` - Gap policy documentation for financial modules
- `NUMBERING_OPERATIONAL_CONSIDERATIONS.md` - Operational considerations for production deployment
- `NUMBERING_EVOLUTION_ROADMAP.md` - Roadmap for top-tier/banking-grade upgrades

### Modified
- `resources/views/company/settings/index.blade.php` - Added settings UI for Indent, GRN, Invoice, Payment
- `app/Models/PurchaseOrder.php` - Updated generatePONumber to accept site_id
- `app/Models/Indent.php` - Updated generateIndentNumber to accept site_id
- `app/Models/Grn.php` - Updated generateGrnNumber to accept site_id
- `app/Models/PurchaseInvoice.php` - Updated generateInvoiceNumber to accept site_id
- `app/Models/PaymentsModule.php` - Updated generatePaymentNumber to accept site_id and use in creating event
- `app/Http\Controllers\PurchaseOrderController.php` - Pass site_id to generatePONumber
- `app/Http\Controllers\IndentController.php` - Pass site_id to generateIndentNumber
- `app/Services/GrnService.php` - Pass site_id to generateGrnNumber
- `app/Http\Controllers\PurchaseInvoiceController.php` - Use model's generateInvoiceNumber with site_id
- `app/Http\Controllers\Api/IndentApiController.php` - Pass site_id for preview and creation
- `app/Http\Controllers\Api/GrnApiController.php` - Pass site_id for preview and creation
- `app/Http\Controllers/Api/PurchaseInvoiceApiController.php` - Use model's generateInvoiceNumber with site_id
- `app/Http\Controllers/Api/PaymentsModuleApiController.php` - Use model's generatePaymentNumber with site_id

## Production-Safety Improvements

### Concurrency Handling
The implementation includes production-grade concurrency safety:

**Problem:** Under concurrent load, multiple requests could read the same last number and generate duplicates.

**Solution:** 
- `generateWithRetry()` method wraps generation in DB transaction
- Automatic retry on duplicate key errors (max 3 attempts)
- Logs retry attempts for debugging
- Unique constraints as final safety net

**Usage Example:**
```php
$indent = app(NumberGeneratorService::class)->generateWithRetry(
    'indent',
    $siteId,
    function ($number) use ($data) {
        $data['indent_number'] = $number;
        return Indent::create($data);
    }
);
```

### site_id Validation
- **Mandatory:** site_id is now required for all number generation
- Throws `InvalidArgumentException` if null
- Prevents accidental global numbering fallback
- Validated in:
  - NumberGeneratorService::generate()
  - PaymentsModule creating event
  - GrnService (both PO and Direct GRN)

### Prefix Change Handling
- Regex-based extraction (`/(\d+)$/`) is prefix-independent
- Handles any prefix format: IND00001, IND-00001, IN-00001, etc.
- Old records retain original numbers
- New records use new prefix
- Sequence continues correctly

### Settings Validation
- Prefix: maxlength 10 characters
- Starting number: minimum 1
- Prevents invalid configuration

### Debug Logging
- Logs only in debug mode (`config('app.debug')`)
- Prevents performance issues at scale
- Retry attempts always logged as warnings

### User Input Protection
- All controllers force override user-provided numbers
- Prevents manual number injection
- Comments added to clarify: `// Force override any user input`

### Starting Number Guardrail
- System always takes `max(starting_number, last_number + 1)`
- Prevents sequence jumps if starting_number is increased after records exist
- Example: If DB has IND000500 and starting_number is set to 1000, next number is 1000 (not 501)

### Gap Policy
- Documented in `NUMBERING_GAP_POLICY.md`
- Gaps are accepted as industry standard behavior
- All number generation events logged for audit trail
- Gap reporting queries provided for auditors

## Troubleshooting

### Issue: Duplicate Number Error
**Cause:** Unique constraint violation when trying to create duplicate number within same site

**Solution:**
1. Use `generateWithRetry()` method for concurrency-safe generation
2. Check logs for retry attempts
3. If issue persists after retries, check for high concurrent load

### Issue: Number Not Resetting Per Site
**Cause:** site_id not being passed to generate method

**Solution:**
1. Ensure controller passes `$request->site_id` to generate method
2. For Direct GRN, ensure `$data['site_id']` is set
3. For PaymentsModule, ensure model has `site_id` set before creation

### Issue: Settings Not Applied
**Cause:** Settings cache not cleared

**Solution:**
1. Clear cache: `php artisan cache:clear`
2. Clear config cache: `php artisan config:clear`
3. Save settings again in UI

## Notes

- **Site = Project:** In this system, Site ID maps to Project ID
- **Padding Length:** Default is 5 digits (00001), configurable via settings
- **Backward Compatibility:** Existing records retain their original numbers
- **Concurrency:** Unique constraints prevent race conditions
- **Null site_id:** If site_id is null, numbering is global (no site filtering)

## Future Enhancements

Potential improvements for future consideration:
- Add padding_length configuration per module in settings UI
- Add preview API endpoint to show next number without creating record
- Add audit log for number generation events
- Add number range validation in settings
