# DataTables & Action Buttons Implementation Guide

## Standard Patterns

### 1. Action Button Structure
```blade
<div class="action-btn me-2">
    <a href="#" class="mx-3 btn btn-sm align-items-center bg-[color]"
       data-bs-toggle="tooltip" title="{{ __('Action Name') }}">
        <i class="ti ti-[icon] text-white"></i>
    </a>
</div>
```

### 2. Button Color Standards
- **View**: `bg-warning`
- **Edit**: `bg-info` 
- **Delete**: `bg-danger`
- **Create**: `bg-primary`
- **Submit/Approve**: `bg-success`
- **Lock/Warning**: `bg-warning`

### 3. DataTable Configuration
```php
// Checkbox column
Column::computed('action')
      ->title('')
      ->exportable(false)
      ->printable(false)
      ->orderable(false)
      ->searchable(false)
      ->width(30)
      ->addClass('text-center select-checkbox');

// Actions column
Column::computed('actions')
      ->exportable(false)
      ->printable(false)
      ->width(120)
      ->addClass('text-center');
```

### 4. DataTable HTML Setup
```php
->selectStyleMulti()
->dom('Bfrtip')
->buttons([
    // Export collection with proper styling
])
```

### 5. Filter Layout Pattern
- Use `col-xl-2` for each filter (consistent sizing)
- Standard date labels: "Start Date", "End Date"
- Apply/Reset buttons with `bg-primary` and `bg-danger`

### 6. Icon Standards
- Use `ti` icons consistently
- Add `text-white` class for visibility
- Standard action icons: `ti-eye`, `ti-pencil`, `ti-trash`, `ti-plus`

### 7. Permission Structure
```blade
@permission('[module] [action]')
    // Action button
@endpermission
```

## Quick Reference

**File Structure:**
- `resources/views/[module]/index.blade.php` - Main listing page
- `resources/views/[module]/action.blade.php` - Action buttons
- `app/DataTables/[Module]DataTable.php` - DataTable configuration

**Key Classes:**
- `action-btn` - Action button container
- `bg-[color]` - Button background colors
- `ti ti-[icon]` - Tabler icons
- `select-checkbox` - Checkbox column styling
