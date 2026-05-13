# Selectable Export Implementation Guide

## Overview

This document explains the reusable selectable export solution implemented for DataTable classes in SitePilot. The solution allows all DataTables to support:
- Checkbox column for row selection
- Export selected rows
- Export all rows
- Reusable export logic across all DataTables

## New Files Created

### 1. SelectableExportTrait (`app/DataTables/Traits/SelectableExportTrait.php`)

A reusable trait that provides all the functionality needed for selectable exports:

#### Abstract Methods (Must be implemented by using class)
- `getTableId(): string` - Returns the unique table ID (e.g., 'supplier-table')
- `getCheckboxClass(): string` - Returns the checkbox class name (e.g., 'supplier-checkbox')
- `getExportRouteName(): string` - Returns the export route name (e.g., 'export.selected')
- `getExportFilePrefix(): string` - Returns the export filename prefix (e.g., 'suppliers')
- `getModelClass(): string` - Returns the fully qualified model class name

#### Available Methods (Can be called from using class)
- `addCheckboxColumn($dataTable)` - Adds checkbox column to dataTable
- `addCheckboxColumnDefinition($columns)` - Adds checkbox column to getColumns()
- `configureSelectionBuilder($dataTable)` - Configures builder with selection options
- `getCheckboxInitScript()` - Returns JavaScript for checkbox handling
- `getExportButtonConfig()` - Returns export button configuration with custom JS
- `getSelectionConfig()` - Returns selection configuration for parameters
- `handleSelectedIdsFilter($query)` - Filters query by selected IDs from export request

### 2. GenericSelectedExport (`app/Exports/GenericSelectedExport.php`)

A dynamic export class that can handle any model:

#### Constructor Parameters
- `modelClass` - Fully qualified model class name
- `ids` - Array of IDs to export
- `exportAll` - Boolean to export all records
- `columns` - Optional array of columns to export
- `columnLabels` - Optional array of column labels for headings
- `filePrefix` - Optional file prefix

### 3. Updated ExportController (`app/Http/Controllers/ExportController.php`)

Now supports generic exports via the `exportSelected()` method.

#### Parameters
- `model` - Model class (e.g., `App\Models\Supplier`)
- `ids` - Comma-separated IDs to export
- `all` - Set to 'true' to export all records
- `columns` - Optional comma-separated columns
- `labels` - Optional comma-separated column labels
- `prefix` - Optional file prefix

#### Example Request
```
/export-selected?model=App\Models\Supplier&ids=1,2,3
/export-selected?model=App\Models\Invoice&all=true
```

## Updated DataTables

The following DataTables have been updated to use the SelectableExportTrait:

1. **SupplierDataTable** (`app/DataTables/SupplierDataTable.php`)
2. **InvoiceDataTable** (`app/DataTables/InvoiceDataTable.php`)

## How to Add Selectable Export to Other DataTables

### Step 1: Add the Trait

```php
use App\DataTables\Traits\SelectableExportTrait;

class YourDataTable extends DataTable
{
    use SelectableExportTrait;
    
    // ... rest of class
}
```

### Step 2: Implement Abstract Methods

```php
protected function getTableId(): string
{
    return 'your-table';
}

protected function getCheckboxClass(): string
{
    return 'your-checkbox';
}

protected function getExportRouteName(): string
{
    return 'export.selected';
}

protected function getExportFilePrefix(): string
{
    return 'your-export-prefix';
}

protected function getModelClass(): string
{
    return \App\Models\YourModel::class;
}
```

### Step 3: Add Checkbox Column in dataTable()

```php
public function dataTable(QueryBuilder $query): EloquentDataTable
{
    $rowColumn = ['checkbox', /* other raw columns */];
    
    return (new EloquentDataTable($query))
        ->addIndexColumn()
        ->addColumn('checkbox', function ($model) {
            return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $model->id . '">';
        })
        // ... other columns
        ->rawColumns($rowColumn);
}
```

### Step 4: Update getColumns()

```php
public function getColumns(): array
{
    $checkboxClass = $this->getCheckboxClass();
    
    return [
        Column::make('id')->searchable(false)->visible(false)->exportable(false)->printable(false),
        Column::computed('checkbox')
            ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
            ->exportable(false)
            ->printable(false)
            ->orderable(false)
            ->searchable(false)
            ->width(20),
        Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
        // ... other columns
    ];
}
```

### Step 5: Update html() Method

```php
public function html(): HtmlBuilder
{
    $dataTable = $this->builder()
        ->setTableId($this->getTableId())
        ->columns($this->getColumns())
        ->minifiedAjax()
        ->orderBy(0)
        ->select(['' . $this->getCheckboxClass() . ''])
        // ... other configurations
        ->initComplete($this->getCheckboxInitScript());

    // Use trait method for export buttons
    $buttonsConfig = $this->getExportButtonConfig();

    $dataTable->parameters([
        // ... other parameters
        'buttons' => $buttonsConfig,
        'select' => [
            "style" => "multi",
            "selector" => "td:first-child ." . $this->getCheckboxClass()
        ],
    ]);

    return $dataTable;
}
```

### Step 6: Add ID Filter in query()

```php
public function query(YourModel $model): QueryBuilder
{
    $query = $model->newQuery();
    
    // Add your existing filters here
    
    // Handle selected_ids from export request
    $this->handleSelectedIdsFilter($query);
    
    return $query;
}
```

### Step 7: Update View File

Add the export route to your view's JavaScript:

```blade
<script>
    window.yourTableExportUrl = "{{ route('export.selected') }}";
</script>
```

## DataTables That Should Be Updated (32 remaining)

- ActivityDataTable
- AssetsToolsAndEquipmentDataTable
- BankTransferDataTable
- CouponDataTable
- CustomDomainRequestDataTable
- DailyConsumptionDataTable
- DailyProgressReportDataTable
- EmailTemplateDataTable
- GeneralTransferDataTable
- MachineryCategoryDataTable
- MachineryDataTable
- ManPowerDataTable
- ManPowerTypeDataCategoryDataTable
Table
- Material- MaterialDataTable
- MaterialTransferDataTable
- NotificationDataTable
- OrderDataTable
- PaymentRequestDataTable
- PaymentsModuleDataTable
- ProposalDataTable
- PurchaseDataTable
- PurchaseInvoiceDataTable
- StockReportDataTable
- SupplierCategoryDataTable
- SupplierDataTable1
- SupportDataTable
- UnitDataTable
- UsersDataTable
- WarehouseDataTable
- WarehouseTransferDataTable

## Backward Compatibility

The implementation maintains backward compatibility:
- Existing exports using `SupplierSelectedExport` still work
- The `export.selected` route handles both old and new formats
- Standard Yajra DataTable export buttons still work alongside the new selection export

## Testing

Test the export functionality by:
1. Opening a DataTable page
2. Selecting rows using checkboxes
3. Clicking the export button
4. Verifying the downloaded Excel file contains only selected rows
5. Testing "Export All" when no rows are selected

## Troubleshooting

### Issue: Checkbox not selecting rows
- Ensure `->select(['' . $this->getCheckboxClass() . ''])` is called in html()
- Ensure `select` parameter is set in parameters with correct selector

### Issue: Export returns 400 error
- Check that the model class parameter is correctly passed
- Ensure model class exists and is valid

### Issue: Export downloads but is empty
- Check that the query is filtering correctly by IDs
- Verify the GenericSelectedExport is receiving the IDs correctly

## Benefits of This Implementation

1. **DRY Principle**: Code is not duplicated across DataTables
2. **Consistency**: All DataTables have the same export UX
3. **Maintainability**: Changes only need to be made in one place
4. **Flexibility**: Each DataTable can customize its own table ID, checkbox class, etc.
5. **Scalability**: Easy to add to new DataTables
