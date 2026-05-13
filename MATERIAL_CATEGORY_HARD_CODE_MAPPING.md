# Material & Supplier Category Hard Code Mapping

This document maps the hardcoded category IDs used throughout the codebase to their actual names and the modules/tables that reference them.

## Overview

The system uses hardcoded category IDs in multiple locations. This mapping helps identify which categories are being referenced and where they are used.

---

## Material Categories (Table: `material_categories`)

### Category ID 1: Building Materials
- **Seeder Reference**: `MaterialCategorySeeder.php` line 20
- **Usage**: Not currently hardcoded in controllers (dynamic lookup used)

### Category ID 2: Fuels ⚠️ **HEAVILY HARDCODED**
- **Seeder Reference**: `MaterialCategorySeeder.php` line 21
- **Purpose**: Fuel materials for machinery and vehicles
- **Hardcoded in 14 locations**:

#### Controllers (4 files)
1. **DailyProgressReportController.php**
   - Line 64: `->where('category_id',2)->get()`
   - Line 97: `->where('category_id',2)->get()`
   - Line 318: `if ((int)$item->category_id === 2)`

2. **DailyConsumptionController.php**
   - Line 47: `->where('category_id',2)->get()`
   - Line 55: `->where('category_id','!=',2)->get()`
   - Line 225: `if ((int)$item->category_id === 2)`

3. **ActivityController.php**
   - Line 244: `->where('category_id',2)->get()`
   - Line 256: `->where('category_id','!=',2)->get()`

#### API Controllers (2 files)
4. **DailyConsumptionApiController.php**
   - Line 44: `->where('category_id', 2)`
   - Line 56: `->where('category_id', '!=', 2)`

5. **DailyProgressReportApiController.php**
   - Line 194: `->where('category_id', 2)`

#### Blade Views (4 files)
6. **daily-progress-reports/create.blade.php**
   - Line 195: `if (parseInt(item.category_id) === 2)`

7. **daily-progress-reports/create-new.blade.php**
   - Line 241: `if (parseInt(item.category_id) === 2)`

8. **daily-consumption/create.blade.php**
   - Line 267: `if (parseInt(item.category_id) === 2)`

9. **daily-consumption/edit.blade.php**
   - Line 314: `if (parseInt(item.category_id) === 2)`

#### Helper (1 file)
10. **helper.php**
    - Line 2650: `//if ((int)$item->category_id === 2)` (commented)

---

### Category ID 3: Tools & Equipment ⚠️ **HARDCODED**
- **Seeder Reference**: `MaterialCategorySeeder.php` line 22
- **Purpose**: Tools, equipment, and assets
- **Hardcoded in 5 locations**:

#### Controllers (3 files)
1. **PurchaseInvoiceController.php**
   - Line 73: `->where('category_id', '!=', 3)` (EXCLUDES this category)

2. **PurchaseInvoiceController_1.php**
   - Line 53: `->where('category_id', '!=', 3)` (EXCLUDES this category)

3. **AssetsToolsAndEquipmentController.php**
   - Line 25: `->where('category_id', 3)->pluck('name', 'id')`
   - Line 133: `->where('category_id', 3)->pluck('name', 'id')`

#### API Controllers (1 file)
4. **PurchaseInvoiceApiController.php**
   - Line 211: `->where('category_id', '!=', 3)` (EXCLUDES this category)

---

### Other Material Categories (Not Hardcoded)
- **ID 4**: Lubricants
- **ID 5**: Tools & Equipment (duplicate entry in seeder)
- **ID 6**: Plumbing Materials
- **ID 7**: Electrical Items
- **ID 8**: Finishing Materials
- **ID 9**: Doors & Windows
- **ID 10**: Exterior & Landscaping

*Note: These categories are defined in the seeder but are not referenced with hardcoded IDs in the codebase.*

---

## Supplier Categories (Table: `supplier_categories`)

### Category ID 1: Subcontractors ⚠️ **HARDCODED**
- **Seeder Reference**: `SupplierCategorySeeder.php` line 20
- **Purpose**: Specialized construction service providers (used for ManPower)
- **Hardcoded in 8 locations**:

#### Controllers (3 files)
1. **ManPowerController.php**
   - Line 35: `->where('category_id', 1)->pluck('name', 'id')`
   - Line 160: `->where('category_id', 1)->pluck('name', 'id')`

2. **MaterialIssueController.php**
   - Line 45: `->where('category_id', 1)->orderBy('name')->get()`
   - Line 166: `->where('category_id', 1)->orderBy('name')->get()`

3. **ActivityController.php**
   - Line 208: `->where('category_id', 1)->pluck('name', 'id')`

#### API Controllers (2 files)
4. **MaterialIssueApiController.php**
   - Line 128: `->where('category_id', 1)->orderBy('name')->get()`
   - Line 511: `->where('category_id', 1)->orderBy('name')->get()`

5. **ManPowerApiController.php**
   - Line 78: `->where('category_id', 1)->pluck('name', 'id')`

---

### Other Supplier Categories (Not Hardcoded)
- **ID 2**: Material Suppliers
- **ID 3**: Equipment Suppliers
- **ID 4**: Interior & Finishing Suppliers
- **ID 5**: Service Providers
- **ID 6**: Technology Vendors
- **ID 7**: Fuel & Lubricant Suppliers
- **ID 8**: Fleet Fueling Services

*Note: These categories are defined in the seeder but are not referenced with hardcoded IDs in the codebase.*

---

## Summary Table

| Category Type | ID | Name | Hardcoded Count | Primary Modules |
|--------------|----|--------------|-----------------|-----------------|
| Material | 2 | Fuels | 14 | Daily Progress Reports, Daily Consumption, Activity |
| Material | 3 | Tools & Equipment | 5 | Purchase Invoice, Assets Tools & Equipment |
| Supplier | 1 | Subcontractors | 8 | ManPower, Material Issue, Activity |

**Total Hardcoded References: 27**

---

## Recommended Actions

### 1. Create Configuration Constants
Create a config file or constants class to define these category IDs:

```php
// config/categories.php
return [
    'material' => [
        'fuels' => 2,
        'tools_equipment' => 3,
    ],
    'supplier' => [
        'subcontractors' => 1,
    ],
];
```

### 2. Replace Hardcoded IDs
Replace all hardcoded category IDs with the configuration constants:

**Before:**
```php
$materials = Material::where('category_id', 2)->get();
```

**After:**
```php
$materials = Material::where('category_id', config('categories.material.fuels'))->get();
```

### 3. Use Dynamic Lookups
For better maintainability, consider using dynamic lookups by name:

```php
$fuelCategoryId = MaterialCategory::where('name', 'Fuels')->value('id');
$materials = Material::where('category_id', $fuelCategoryId)->get();
```

### 4. Add Database Constraints
Consider adding unique constraints or indexes to ensure category names remain consistent.

### 5. Documentation
Update this document whenever new categories are added or existing ones are modified.

---

## Related Files

### Seeders
- `database/seeders/MaterialCategorySeeder.php`
- `database/seeders/SupplierCategorySeeder.php`

### Models
- `app/Models/MaterialCategory.php`
- `app/Models/SupplierCategory.php`

### Controllers with Hardcoded References
- `app/Http/Controllers/DailyProgressReportController.php`
- `app/Http/Controllers/DailyConsumptionController.php`
- `app/Http/Controllers/ActivityController.php`
- `app/Http/Controllers/AssetsToolsAndEquipmentController.php`
- `app/Http/Controllers/PurchaseInvoiceController.php`
- `app/Http/Controllers/ManPowerController.php`
- `app/Http/Controllers/MaterialIssueController.php`

### API Controllers with Hardcoded References
- `app/Http/Controllers/Api/DailyConsumptionApiController.php`
- `app/Http/Controllers/Api/DailyProgressReportApiController.php`
- `app/Http/Controllers/Api/PurchaseInvoiceApiController.php`
- `app/Http/Controllers/Api/MaterialIssueApiController.php`
- `app/Http/Controllers/Api/ManPowerApiController.php`

### Blade Views with Hardcoded References
- `resources/views/daily-progress-reports/create.blade.php`
- `resources/views/daily-progress-reports/create-new.blade.php`
- `resources/views/daily-consumption/create.blade.php`
- `resources/views/daily-consumption/edit.blade.php`

### Helper
- `app/Helper/helper.php`

---

## Last Updated

- **Date**: 2026-04-24
- **Version**: 1.0
- **Total Hardcoded References**: 27
