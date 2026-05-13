# DataTable Export Debugging Guide

This guide provides step-by-step debugging procedures for resolving DataTable export issues where related model columns show empty values.

## Common Issue: Export Columns Showing Empty

**Symptoms:**
- Direct export (export all) works but related model columns (Project Name, Created By, etc.) show empty
- Checkbox-selected export may work correctly
- Ledger Name or similar columns work but others don't

## Root Cause Analysis

The most common cause is **soft deletes** on related models preventing relationships from loading. When Project, User, or other related models use soft deletes, Eloquent relationships won't load the related records unless `withTrashed()` is used.

## Debugging Steps

### Step 1: Verify Database Data
```sql
SELECT id, project_id, created_by FROM spents LIMIT 10;
SELECT * FROM projects WHERE id IN (...);
SELECT * FROM users WHERE id IN (...);
```
- Confirm foreign keys have valid values
- Confirm related records exist in database

### Step 2: Verify Model Relationships
Check the model has correct relationship definitions:
```php
public function project() {
    return $this->belongsTo(Project::class, 'project_id');
}

public function createdBy() {
    return $this->belongsTo(User::class, 'created_by');
}
```

### Step 3: Check Export Class Being Used
- Determine if using GenericSelectedExport or custom export class
- Check ExportController logic for export class selection
- Log which export class is instantiated

### Step 4: Verify Relationship Loading in Query
Ensure the export query loads relationships:
```php
// Basic relationship loading
$query = Model::with(['project', 'createdBy']);

// With soft delete handling (IMPORTANT)
$query = Model::with(['project', 'createdBy'])
    ->with(['project' => function($q) {
        $q->withTrashed();
    }])
    ->with(['createdBy' => function($q) {
        $q->withTrashed();
    }]);
```

### Step 5: Check Column Mapping
- Verify column names in export match virtual column handlers
- Check if using getExportColumnsConfig() with field/alias/title mapping
- Ensure virtual column handlers exist for the column names being exported

### Step 6: Add Debug Logging
Add surgical debug logs to identify the exact issue:
```php
// In export class constructor
Log::info('Export constructor', [
    'columns' => $columns,
    'columnLabels' => $columnLabels
]);

// In map() method
Log::info('Export debug', [
    'model_id' => $model->id,
    'project' => $model->project,
    'createdBy' => $model->createdBy,
    'project_name' => optional($model->project)->name,
    'created_by_name' => optional($model->createdBy)->name,
]);
```

## Common Solutions

### Solution 1: Add withTrashed to Query
If related models use soft deletes:
```php
public function query() {
    return Model::with(['relationship'])
        ->with(['relationship' => function($q) {
            $q->withTrashed();
        }]);
}
```

### Solution 2: Fix Virtual Column Handlers
Ensure getVirtualColumnValue() has handlers for the column names:
```php
protected function getVirtualColumnValue(Model $model, string $column): string {
    if ($model instanceof \App\Models\YourModel) {
        switch ($column) {
            case 'project_name':
                return optional($model->project)->name ?? '';
            case 'created_by_name':
                return optional($model->createdBy)->name ?? '';
        }
    }
    return '';
}
```

### Solution 3: Use getExportColumnsConfig()
Implement proper column configuration with aliases:
```php
protected function getExportColumnsConfig(): array {
    return [
        ['field' => 'project_id', 'alias' => 'project_name', 'title' => 'Project'],
        ['field' => 'created_by', 'alias' => 'created_by_name', 'title' => 'Created By'],
    ];
}
```

## Quick Reference Checklist

When encountering export column issues:
- [ ] Database: Verify foreign keys have valid values
- [ ] Model: Check relationship definitions are correct
- [ ] Export Class: Identify which class is being used
- [ ] Query: Verify relationships are loaded with with()
- [ ] Soft Deletes: Add withTrashed() if related models use soft deletes
- [ ] Column Names: Verify column names match handlers
- [ ] Virtual Handlers: Ensure getVirtualColumnValue() has handlers
- [ ] Debug Logs: Add surgical logs to identify exact issue

## Example Fix for Spent Export

**Problem:** Project and Created By columns showed empty in Spent export while Ledger Name worked.

**Root Cause:** Project and User models use soft deletes, preventing relationships from loading.

**Solution:** Added withTrashed to SpentExport query:
```php
public function query() {
    $query = Spent::with(['spentLedger', 'project', 'createdBy'])
        ->with(['project' => function($q) {
            $q->withTrashed();
        }])
        ->with(['createdBy' => function($q) {
            $q->withTrashed();
        }]);
    // ... rest of query logic
}
```

## Related Models Commonly Using Soft Deletes

- Project (Workdo\Taskly\Entities\Project)
- User (App\Models\User)
- Employee (Workdo\Hrm\Entities\Employee)
- Site/Project in HRM module

Always check if these models use soft deletes when debugging export issues.
