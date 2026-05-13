# Purchase Invoice PDF Debugging Guide

## Issue
Purchase Invoices insert, edit, and view operations for mobile API and web PDF are not creating/debug properly.

## Debugging Changes Made

### 1. API Controller (`app/Http/Controllers/Api/PurchaseInvoiceApiController.php`)

#### Store Method (Lines 381-399)
Added comprehensive logging for PDF generation during invoice creation:
- Log entry when PDF generation starts
- Log the result of PDF generation (path or null)
- Log successful save with PDF path
- Log warning if PDF generation returns null
- Log error with full stack trace on exception

#### Update Method (Lines 574-597)
Added comprehensive logging for PDF regeneration during invoice update:
- Log entry when PDF generation starts
- Log old PDF path before deletion
- Log the result of PDF generation (path or null)
- Log successful save with PDF path
- Log warning if PDF generation returns null
- Log error with full stack trace on exception

#### generatePurchaseInvoicePdf Method (Lines 1119-1197)
Added detailed step-by-step logging:
- Loading relationships
- Loading company settings
- Initializing Dompdf
- Rendering Blade view
- Rendering PDF
- Outputting PDF content
- Uploading PDF to storage
- Upload result
- Success or failure with URL

### 2. Web Controller (`app/Http/Controllers/PurchaseInvoiceController.php`)

#### Store Method (Lines 206-223)
Added logging for PDF generation during web invoice creation:
- Log entry when PDF generation starts
- Log the result of PDF generation (path or null)
- Log successful save with PDF path
- Log warning if PDF generation returns null
- Log error with full stack trace on exception

#### Update Method (Lines 400-418)
Added logging for PDF regeneration during web invoice update:
- Log entry when PDF generation starts
- Log the result of PDF generation (path or null)
- Log successful save with PDF path
- Log warning if PDF generation returns null
- Log error with full stack trace on exception

#### storeFromGrn Method (Lines 812-830)
Added logging for PDF generation when creating invoice from GRN:
- Log entry when PDF generation starts
- Log the result of PDF generation (path or null)
- Log successful save with PDF path
- Log warning if PDF generation returns null
- Log error with full stack trace on exception

#### generatePurchaseInvoicePdf Method (Lines 856-940)
Added detailed step-by-step logging (same as API controller):
- Loading relationships
- Loading company settings
- Initializing Dompdf
- Rendering Blade view
- Rendering PDF
- Outputting PDF content
- Uploading PDF to storage
- Upload result
- Success or failure with URL

### 3. Helper Function (`app/Helper/helper.php`)

#### upload_pdf_content Function (Lines 3156-3253)
Added comprehensive logging for PDF upload process:
- Log function entry with path, filename, and content size
- Log storage disk selection (configured or fallback to local)
- Log S3/Wasabi configuration if applicable
- Log directory creation for each storage disk
- Log file save operation for each storage disk
- Log save results for each storage disk
- Log success with URL and all save results
- Log error if all saves fail
- Log exception with full stack trace

## How to Debug

### Step 1: Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### Step 2: Test PDF Generation

#### Test via Web Interface
1. Navigate to Purchase Invoice create page
2. Fill in required fields and submit
3. Check the logs for:
   - `PDF Generation Start - Web Store`
   - `PDF Generation - Loading relationships (Web)`
   - `PDF Generation - Loading settings (Web)`
   - `PDF Generation - Initializing Dompdf (Web)`
   - `PDF Generation - Rendering view (Web)`
   - `PDF Generation - Rendering PDF (Web)`
   - `PDF Generation - Outputting PDF content (Web)`
   - `upload_pdf_content - Start`
   - `upload_pdf_content - Success` or error messages

#### Test via Mobile API
```bash
# Create a new purchase invoice via API
curl -X POST http://your-domain/api/purchase-invoice \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_id": 1,
    "site_id": 1,
    "workspace_id": 1,
    "invoice_date": "2024-01-15",
    "invoice_type": "general_po",
    "items": [
      {
        "material_id": 1,
        "quantity": 10,
        "price": 100.00
      }
    ]
  }'
```

Check the logs for:
- `PDF Generation Start - API Store`
- Same detailed logs as web but marked with "API"

### Step 3: Identify the Failure Point

The logs will show exactly where the PDF generation fails:

1. **If logs show "PDF Generation Start" but no "Loading relationships"**
   - Issue: Exception before relationship loading
   - Check: Invoice ID validity, database connection

2. **If logs show "Loading relationships" but no "Loading settings"**
   - Issue: Relationship loading failed
   - Check: Related data existence (items, supplier, site, etc.)

3. **If logs show "Loading settings" but no "Initializing Dompdf"**
   - Issue: Settings retrieval failed
   - Check: Company settings in database

4. **If logs show "Initializing Dompdf" but no "Rendering view"**
   - Issue: Dompdf initialization failed
   - Check: Dompdf installation, PHP extensions

5. **If logs show "Rendering view" but no "Rendering PDF"**
   - Issue: Blade view rendering failed
   - Check: `resources/views/purchase-invoice/print.blade.php` exists and is valid

6. **If logs show "Rendering PDF" but no "Outputting PDF content"**
   - Issue: PDF rendering failed
   - Check: Dompdf configuration, memory limits

7. **If logs show "Outputting PDF content" but no "upload_pdf_content - Start"**
   - Issue: PDF content generation failed
   - Check: PDF content size, memory limits

8. **If logs show "upload_pdf_content - Start" but no "Success"**
   - Issue: File upload failed
   - Check: Storage permissions, disk space, storage configuration

### Step 4: Check Storage Configuration

Verify filesystem configuration in `config/filesystems.php`:
- `public_folder` disk points to `public_path('uploads')`
- `local` disk points to `base_path('uploads')`
- Directory permissions are correct (755 for directories, 644 for files)

### Step 5: Verify Directory Permissions

```bash
# Check if directories exist and are writable
ls -la uploads/pdf/
ls -la public/uploads/pdf/

# Create directories if they don't exist
mkdir -p uploads/pdf/purchase-invoice
mkdir -p public/uploads/pdf/purchase-invoice

# Set permissions
chmod -R 755 uploads/pdf
chmod -R 755 public/uploads/pdf
```

### Step 6: Check Storage Settings

Verify storage settings in the database (`settings` table):
- Check if `storage_setting` is configured
- If using S3/Wasabi, verify credentials are correct
- If not configured, system will fallback to local storage

## Common Issues and Solutions

### Issue 1: Directory Not Writable
**Symptom**: `upload_pdf_content - Creating directory` followed by exception
**Solution**: Ensure `uploads/pdf/purchase-invoice` and `public/uploads/pdf/purchase-invoice` exist and are writable

### Issue 2: Dompdf Memory Limit
**Symptom**: `Rendering PDF` followed by memory error
**Solution**: Increase PHP memory limit in `php.ini`:
```ini
memory_limit = 256M
```

### Issue 3: Blade View Missing
**Symptom**: `Rendering view` followed by "View not found" error
**Solution**: Ensure `resources/views/purchase-invoice/print.blade.php` exists

### Issue 4: Storage Disk Not Configured
**Symptom**: `upload_pdf_content - Using local storage (fallback)`
**Solution**: Configure storage setting in admin panel or accept local storage fallback

### Issue 5: PDF Content Empty
**Symptom**: `Outputting PDF content` with content_size = 0
**Solution**: Check Blade view for errors, ensure data is passed correctly

## Log Examples

### Successful PDF Generation
```
[2024-01-15 10:00:00] local.INFO: PDF Generation Start - Web Store {"invoice_id": 123, "invoice_number": "INV-0123"}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading relationships (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading settings (Web) {"invoice_id": 123, "workspace_id": 1}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Initializing Dompdf (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering view (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering PDF (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Outputting PDF content (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Uploading PDF (Web) {"invoice_id": 123, "filename": "123_INV-0123.pdf", "path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Start {"path": "pdf/purchase-invoice", "fileName": "123_INV-0123.pdf", "content_size": 45678}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Using local storage (fallback)
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Creating directory {"disk": "local", "path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Saving to configured storage {"disk": "local", "fullPath": "pdf/purchase-invoice/123_INV-0123.pdf"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Configured storage save result {"saved": true}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Creating local directory {"path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Local storage save result {"saved": true}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Creating public_folder directory {"path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Public folder save result {"saved": true}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Success {"url": "uploads/pdf/purchase-invoice/123_INV-0123.pdf", "storage": true, "local": true, "public": true}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Upload result (Web) {"invoice_id": 123, "result": {"flag": 1, "msg": "File uploaded successfully", "url": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Success (Web) {"invoice_id": 123, "url": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}
[2024-01-15 10:00:00] local.INFO: PDF Generation Result - Web Store {"invoice_id": 123, "pdf_path": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}
[2024-01-15 10:00:00] local.INFO: PDF saved successfully - Web Store {"invoice_id": 123, "pi_pdf": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}
```

### Failed PDF Generation (Storage Error)
```
[2024-01-15 10:00:00] local.INFO: PDF Generation Start - Web Store {"invoice_id": 123, "invoice_number": "INV-0123"}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading relationships (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading settings (Web) {"invoice_id": 123, "workspace_id": 1}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Initializing Dompdf (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering view (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering PDF (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Outputting PDF content (Web) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Uploading PDF (Web) {"invoice_id": 123, "filename": "123_INV-0123.pdf", "path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Start {"path": "pdf/purchase-invoice", "fileName": "123_INV-0123.pdf", "content_size": 45678}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Using local storage (fallback)
[2024-01-15 10:00:00] local.ERROR: upload_pdf_content - Exception {"message": "Directory not writable: uploads/pdf/purchase-invoice", "trace": "..."}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Upload result (Web) {"invoice_id": 123, "result": {"flag": 0, "msg": "Directory not writable: uploads/pdf/purchase-invoice", "url": null}}
[2024-01-15 10:00:00] local.WARNING: PDF Generation - Upload failed (Web) {"invoice_id": 123, "result": {"flag": 0, "msg": "Directory not writable: uploads/pdf/purchase-invoice", "url": null}}
[2024-01-15 10:00:00] local.INFO: PDF Generation Result - Web Store {"invoice_id": 123, "pdf_path": null}
[2024-01-15 10:00:00] local.WARNING: PDF generation returned null - Web Store {"invoice_id": 123}
```

## Root Cause Identified and Fixed

Based on the logs provided, the issue has been identified:

**Problem**: Purchase Invoices are being created (INV-0020, INV-0021) but PDF generation is NOT being triggered at all. The logs show:
- GRN PDF generation: Working successfully (GRN-0016, GRN-0017 PDFs uploaded)
- Purchase Invoice PDF generation: NO logs at all (not even "PDF Generation Start")

**Root Cause**: Purchase Invoices are being created through model events (likely automatically from GRN or other processes), which bypass the controller's `store()` method. The PDF generation code was only in the controllers, so it was never executed when invoices were created through observers or other code paths.

**Solution**: Moved PDF generation to `PurchaseInvoiceObserver` to ensure PDFs are generated whenever a Purchase Invoice is created or updated, regardless of how it's created (API, web, or automatic creation from GRN).

## Implementation Details

### Observer-Based PDF Generation

PDF generation is now handled entirely by the `PurchaseInvoiceObserver`:

1. **Created Event**: Automatically generates PDF when invoice is created (deletes existing PDF if present)
2. **Updated Event**: Automatically regenerates PDF when relevant fields change (invoice_date, supplier_id, site_id, total_amount, invoice_type)
3. **Deletion Before Creation**: If a PDF already exists, it is deleted before creating a new one to ensure clean regeneration

### Controller Changes

Removed duplicate PDF generation code from:
- `app/Http/Controllers/Api/PurchaseInvoiceApiController.php` (store and update methods)
- `app/Http/Controllers/PurchaseInvoiceController.php` (store, update, and storeFromGrn methods)

This prevents conflicts and ensures PDF generation is handled consistently through the observer.

## Next Steps

1. Test creating a new Purchase Invoice (via web, API, or from GRN)
2. Check the Laravel logs for "PDF Generation Start - Observer"
3. Verify that PDF is generated and saved to `uploads/pdf/purchase-invoice/`
4. Test updating an invoice to verify PDF regeneration works
5. Verify that existing PDFs are deleted before creating new ones

## Files Modified

1. `app/Observers/PurchaseInvoiceObserver.php` - Added PDF generation to created() and updated() events
2. `app/Http/Controllers/Api/PurchaseInvoiceApiController.php` - Removed duplicate PDF generation code
3. `app/Http/Controllers/PurchaseInvoiceController.php` - Removed duplicate PDF generation code
4. `app/Helper/helper.php` - Added debugging logs to `upload_pdf_content()`

## Log Patterns to Look For

### Successful PDF Generation (Observer)
```
[2024-01-15 10:00:00] local.INFO: Invoice created notification - Debug {"invoice_id": 123, "invoice_number": "INV-0123"}
[2024-01-15 10:00:00] local.INFO: PDF Generation Start - Observer {"invoice_id": 123, "invoice_number": "INV-0123"}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading relationships (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Loading settings (Observer) {"invoice_id": 123, "workspace_id": 1}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Initializing Dompdf (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering view (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Rendering PDF (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Outputting PDF content (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Uploading PDF (Observer) {"invoice_id": 123, "filename": "123_INV-0123.pdf", "path": "pdf/purchase-invoice"}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Start {"path": "pdf/purchase-invoice", "fileName": "123_INV-0123.pdf", "content_size": 45678}
[2024-01-15 10:00:00] local.INFO: upload_pdf_content - Success {"url": "uploads/pdf/purchase-invoice/123_INV-0123.pdf", "storage": true, "local": true, "public": true}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Success (Observer) {"invoice_id": 123, "url": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}
```

### PDF Regeneration on Update
```
[2024-01-15 10:00:00] local.INFO: Invoice updated notification sent {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: Invoice has relevant changes - regenerating PDF (Observer) {"invoice_id": 123}
[2024-01-15 10:00:00] local.INFO: PDF Generation Start - Observer {"invoice_id": 123, "invoice_number": "INV-0123"}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Deleting existing PDF (Observer) {"invoice_id": 123, "old_path": "uploads/pdf/purchase-invoice/123_INV-0123.pdf"}
[2024-01-15 10:00:00] local.INFO: PDF Generation - Existing PDF deleted (Observer) {"invoice_id": 123}
... (rest of PDF generation logs)
```

## Additional Notes

- The PDF generation is wrapped in try-catch blocks to prevent invoice creation/update failure even if PDF generation fails
- PDFs are saved to multiple storage locations for redundancy:
  - Configured storage (S3/Wasabi if configured, otherwise local)
  - Local storage (project root/uploads)
  - Public folder (public/uploads) for web access
- The logs include invoice IDs and invoice numbers to correlate with specific invoices
- All exceptions include full stack traces for detailed debugging
