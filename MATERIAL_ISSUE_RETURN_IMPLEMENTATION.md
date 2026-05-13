# Material Issue and Material Return Implementation

## Overview

This document provides a comprehensive summary of the Material Issue and Material Return modules implemented for the SitePilot ERP system.

## Implementation Summary

### Database Migrations

Created 4 new database tables:

1. **material_issues** - Stores material issue records
   - id, issue_number (unique), site_id, issue_to_type (user/supplier), issue_to_id, issue_date, status (Completed), remarks, created_by, workspace_id, timestamps

2. **material_issue_items** - Stores items for each material issue
   - id, issue_id, material_id, quantity, rate (optional), amount (optional), remarks (nullable)

3. **material_returns** - Stores material return records
   - id, return_number (unique), issue_id (nullable), site_id, return_date, status (Completed), remarks, created_by, workspace_id, timestamps

4. **material_return_items** - Stores items for each material return
   - id, return_id, material_id, quantity, remarks (nullable)

### Models

Created 4 Eloquent models:

1. **MaterialIssue** - Main model for material issues
   - Relationships: site, user, supplier, creator, workspace, items
   - Methods: generateIssueNumber(), getIssueToAttribute(), getIssueToNameAttribute()
   - Scopes: forWorkspace(), forSite(), forIssueToType(), latestFirst()

2. **MaterialIssueItem** - Model for issue items
   - Relationships: issue, material
   - Auto-calculates amount on save

3. **MaterialReturn** - Main model for material returns
   - Relationships: site, issue, creator, workspace, items
   - Methods: generateReturnNumber()
   - Scopes: forWorkspace(), forSite(), latestFirst()

4. **MaterialReturnItem** - Model for return items
   - Relationships: return, material

### Controllers

Created 4 controllers:

1. **MaterialIssueController** - Web controller for material issues
   - index() - List all issues with DataTable
   - create() - Show create form
   - store() - Create new issue with stock validation and deduction
   - show() - Show issue details
   - getAvailableStock() - AJAX endpoint for stock check

2. **MaterialReturnController** - Web controller for material returns
   - index() - List all returns with DataTable
   - create() - Show create form
   - store() - Create new return with stock addition
   - show() - Show return details
   - getIssueDetails() - AJAX endpoint for issue details

3. **MaterialIssueApiController** - API controller for material issues
   - index() - List all issues
   - create() - Get create data
   - store() - Create new issue
   - show() - Show issue details
   - getAvailableStock() - Get available stock

4. **MaterialReturnApiController** - API controller for material returns
   - index() - List all returns
   - create() - Get create data
   - store() - Create new return
   - show() - Show return details
   - getIssueDetails() - Get issue details

### Views

Created 6 Blade views:

1. **material-issues/index.blade.php** - List all material issues
2. **material-issues/create.blade.php** - Create new material issue
3. **material-issues/show.blade.php** - Show material issue details
4. **material-returns/index.blade.php** - List all material returns
5. **material-returns/create.blade.php** - Create new material return
6. **material-returns/show.blade.php** - Show material return details

### Routes

Added routes in web.php:
- Route::resource('material-issues', MaterialIssueController::class)
- Route::post('material-issues/get-available-stock', [MaterialIssueController::class, 'getAvailableStock'])
- Route::resource('material-returns', MaterialReturnController::class)
- Route::post('material-returns/get-issue-details', [MaterialReturnController::class, 'getIssueDetails'])

Added routes in api.php:
- Route::apiResource('material-issues', MaterialIssueApiController::class)
- Route::post('/material-issues/create-data', [MaterialIssueApiController::class, 'createData'])
- Route::post('/material-issues/get-available-stock', [MaterialIssueApiController::class, 'getAvailableStock'])
- Route::apiResource('material-returns', MaterialReturnApiController::class)
- Route::post('/material-returns/create-data', [MaterialReturnApiController::class, 'createData'])
- Route::post('/material-returns/get-issue-details', [MaterialReturnApiController::class, 'getIssueDetails'])

### Menu Integration

Updated CompanyMenuListener to add menu items under Inventory section:
- Material Issue (icon: arrow-down, route: material-issues.index)
- Material Return (icon: arrow-up, route: material-returns.index)

## Stock Logic

### Material Issue
1. Validates stock availability before creating issue
2. Uses StockService::issueMaterial() to deduct stock
3. Creates stock_transactions entry with type 'issue' (negative quantity)
4. Updates material_project_stock current_stock

### Material Return
1. Uses StockService::adjustStock() to add stock back
2. Creates stock_transactions entry with type 'adjustment' (positive quantity)
3. Updates material_project_stock current_stock

## Key Features

- **No Approval Workflow**: Direct "Completed" status
- **Not Linked to Indent**: Independent module
- **Direct Stock Update**: Real-time stock deduction/addition
- **Issue To Types**: User (employee) or Supplier (subcontractor)
- **Stock Validation**: Prevents over-issue with available stock check
- **Dynamic Forms**: Add/remove rows for items
- **AJAX Integration**: Real-time stock check and issue details loading

## Usage

### Creating a Material Issue
1. Navigate to Inventory > Material Issue
2. Click "Create" button
3. Select Issue To Type (User or Supplier)
4. Select Issue To (employee or supplier)
5. Select Issue Date
6. Add material items with quantities
7. System validates stock availability
8. Submit to create issue and deduct stock

### Creating a Material Return
1. Navigate to Inventory > Material Return
2. Click "Create" button
3. Optionally select a Material Issue to link
4. Select Return Date
5. Add material items with quantities
6. Submit to create return and add stock back

## API Endpoints

### Material Issues
- GET /api/material-issues - List all issues
- GET /api/material-issues/create - Get create data
- POST /api/material-issues - Create new issue
- GET /api/material-issues/{id} - Show issue details
- POST /api/material-issues/get-available-stock - Get available stock

### Material Returns
- GET /api/material-returns - List all returns
- GET /api/material-returns/create - Get create data
- POST /api/material-returns - Create new return
- GET /api/material-returns/{id} - Show return details
- POST /api/material-returns/get-issue-details - Get issue details

## Permissions

The following permissions are required:
- material-issue manage - View material issues list
- material-issue create - Create new material issue
- material-issue show - View material issue details
- material-return manage - View material returns list
- material-return create - Create new material return
- material-return show - View material return details

## Database Tables

### material_issues
- Primary table for material issue records
- Links to projects (site_id), users (created_by), workspaces (workspace_id)
- Supports soft deletes

### material_issue_items
- Stores individual items for each material issue
- Links to material_issues and materials tables
- Auto-calculates amount from rate and quantity

### material_returns
- Primary table for material return records
- Links to material_issues (optional), projects (site_id), users (created_by), workspaces (workspace_id)
- Supports soft deletes

### material_return_items
- Stores individual items for each material return
- Links to material_returns and materials tables

## Stock Transactions

All stock movements are recorded in the stock_transactions table:
- Type: 'issue' for material issues (negative quantity)
- Type: 'adjustment' for material returns (positive quantity)
- Reference type: 'material_issue' or 'material_return'
- Reference ID: ID of the issue or return record

## Future Enhancements

Potential improvements for future versions:
1. Bulk issue/return functionality
2. Issue/return templates
3. Advanced reporting and analytics
4. Email notifications
5. Mobile app integration
6. Barcode scanning support
7. Multi-currency support
8. Advanced approval workflows (if needed)

## Conclusion

The Material Issue and Material Return modules have been successfully implemented with:
- Complete database structure
- Full CRUD operations
- Stock management integration
- User-friendly interfaces
- API support
- Menu integration
- Proper validation and error handling

The system is ready for production use and provides a simple, efficient way to track material consumption and returns without the complexity of approval workflows.
