# Update DPR Controller for Clean Template

## Controller Changes Needed

In your `DailyProgressReportController.php`, update the `edit` method to use the clean template:

```php
public function edit($id)
{
    $report = DailyProgressReport::findOrFail($id);
    $machineryList = Machinery::all();
    $materials = Material::all()->keyBy('id')->map(function($material) {
        return [
            'name' => $material->name,
            'unit' => $material->unit,
            'price' => $material->price,
            'total_qty' => $material->total_qty,
            'category_id' => $material->category_id,
            'category_name' => $material->category->name ?? ''
        ];
    });
    
    $machinery = $machineryList->where('id', $report->machinery_id)->first();
    $isRental = $machinery->owned_by === 'rental' ?? false;
    
    // Use clean template instead of original
    return view('daily-progress-reports.edit-clean', compact(
        'report',
        'machineryList', 
        'materials',
        'machinery',
        'isRental'
    ));
}
```

## Route Update (if needed)

Make sure your routes point to the correct controller method:

```php
Route::get('/daily-progress-reports/{id}/edit', [DailyProgressReportController::class, 'edit'])->name('daily-progress-reports.edit');
```

## Testing

1. Clear browser cache
2. Test: `GET /daily-progress-reports/1/edit`
3. Should load clean template without syntax errors
4. All functionality should work via external JavaScript
