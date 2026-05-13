<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class GenericSelectedExport implements FromQuery, WithHeadings, WithMapping
{
    protected ?string $modelClass;
    protected array $ids;
    protected bool $exportAll;
    protected ?array $columns;
    protected ?array $columnLabels;
    protected ?string $filePrefix;

    protected array $relationMap = [
        'site_id' => 'site',
        'site_name' => 'site',
        'workspace_id' => 'workspace',
        'category_id' => 'category',
        'supplier_id' => 'supplier',
        'user_id' => 'user',
        'customer_id' => 'customer',
        'vendor_id' => 'vendor',
        'employee_id' => 'employees',
        'employee_name' => 'employees',
        'spent_ledger_id' => 'spentLedger',
        'ledger_name' => 'spentLedger',
    ];

    protected array $confidentialColumns = [
        'password','remember_token','email_verified_at','token','api_key',
        'secret_key','client_secret','client_id','secret',
        'two_factor_secret','two_factor_recovery_codes',
    ];

    protected array $exportExcludeColumns = [
        'dt_rowindex','row_index','sr_no','srno','serial_no','serial_number',
        'id','checkbox','action','actions','0',
        'DT_RowIndex', 'RowIndex', 'rowindex'
    ];

    public function __construct(
        string $modelClass,
        array $ids = [],
        bool $exportAll = false,
        ?array $columns = null,
        ?array $columnLabels = null,
        ?string $filePrefix = null
    ) {
        $this->modelClass = $modelClass;
        $this->ids = $ids;
        $this->exportAll = $exportAll;
        $this->columns = $columns;
        $this->columnLabels = $columnLabels;
        $this->filePrefix = $filePrefix;

        Log::info('GenericSelectedExport constructor - columns received', [
            'modelClass' => $modelClass,
            'columns' => $columns,
            'columnLabels' => $columnLabels
        ]);

        // Log for debugging export differences
        Log::info('=== GenericSelectedExport DEBUG ===', [
            'modelClass' => $modelClass,
            'ids' => $ids,
            'exportAll' => $exportAll,
            'columns_input' => $columns,
            'columns_type' => gettype($columns),
            'columnLabels_input' => $columnLabels,
            'request_columns' => request()->get('columns'),
            'request_labels' => request()->get('labels'),
            'request_all' => request()->get('all'),
        ]);
        
        // Filter and clean columns - ensure only valid string columns
        $this->columns = $columns ? array_values(array_filter($columns, function($col) {
            return is_string($col) && trim($col) !== '';
        })) : [];
        
        $this->columnLabels = $columnLabels;
        $this->filePrefix = $filePrefix;
        
        Log::info('=== GenericSelectedExport CLEANED ===', [
            'columns_cleaned' => $this->columns,
            'columns_count' => count($this->columns)
        ]);
    }

    protected function filterColumns(array $columns, ?Model $model = null): array
    {
        $modelHidden = $model ? $model->getHidden() : [];

        $excluded = array_unique(array_merge(
            array_map('strtolower', $this->confidentialColumns),
            array_map('strtolower', $modelHidden),
            array_map('strtolower', $this->exportExcludeColumns)
        ));

        return array_values(array_filter($columns, function ($col) use ($excluded) {
            return is_string($col)
                && trim($col) !== ''
                && !in_array(strtolower($col), $excluded);
        }));
    }

    public function query()
    {
        $model = new $this->modelClass;
        $query = $model->newQuery();

        // Special handling for Spent model - add workspace/project filtering and load all relationships
        if (class_exists($this->modelClass) && str_ends_with($this->modelClass, 'Spent')) {
            $query->where('workspace_id', getActiveWorkSpace())
                ->when(getActiveProject(), function ($q) {
                    $q->where('project_id', getActiveProject());
                })
                ->with(['spentLedger', 'project', 'createdBy']); // Force load all Spent relationships
        }

        if (!empty($this->columns)) {
            foreach ($this->relationMap as $column => $relation) {
                if (in_array($column, $this->columns) && method_exists($model, $relation)) {
                    $query->with($relation);
                }
            }

            // Special cases for columns that need relationship loading but aren't in relationMap
            if (in_array('ledger_name', $this->columns) && method_exists($model, 'spentLedger')) {
                $query->with('spentLedger');
            }

            // Load project relationship for both project_id and project_name
            if ((in_array('project_id', $this->columns) || in_array('project_name', $this->columns)) && method_exists($model, 'project')) {
                $query->with('project');
            }

            // Load createdBy relationship for both created_by and created_by_name
            if ((in_array('created_by', $this->columns) || in_array('created_by_name', $this->columns)) && method_exists($model, 'createdBy')) {
                $query->with('createdBy');
            }
        }

        if (!$this->exportAll && !empty($this->ids)) {
            $query->whereIn('id', $this->ids);
        }

        return $query;
    }

    public function headings(): array
    {
        Log::info('GenericSelectedExport headings() - START', [
            'columns_raw' => $this->columns,
            'columnLabels' => $this->columnLabels,
        ]);
        
        // CRITICAL: If columns is empty but we have a model, try to get columns from model
        if (empty($this->columns) && $this->modelClass && class_exists($this->modelClass)) {
            Log::warning('GenericSelectedExport: Empty columns, falling back to model fillable', [
                'modelClass' => $this->modelClass
            ]);
            $model = new $this->modelClass;
            $this->columns = $model->getFillable();
        }
        
        $columns = $this->filterColumns($this->columns);
        
        Log::info('GenericSelectedExport headings() - after filter', [
            'columns_filtered' => $columns,
        ]);

        if ($this->columnLabels) {
            $headings = array_map(fn($col) =>
                $this->columnLabels[$col] ?? ucwords(str_replace('_', ' ', $col)),
                $columns
            );
            Log::info('GenericSelectedExport headings() - FINAL', ['headings' => $headings]);
            return $headings;
        }

        $headings = array_map(fn($col) =>
            ucwords(str_replace('_', ' ', $col)),
            $columns
        );
        Log::info('GenericSelectedExport headings() - FINAL (no labels)', ['headings' => $headings]);
        return $headings;
    }

    public function map($model): array
    {
        Log::info('GenericSelectedExport map() - START', [
            'model_id' => $model->id,
            'model_class' => get_class($model),
            'columns_raw' => $this->columns,
        ]);

        // CRITICAL: If columns is empty but we have a model, use model fillable as fallback
        // This ensures even if no columns passed, we have something to export
        if (empty($this->columns) && $this->modelClass && class_exists($this->modelClass)) {
            $modelInstance = new $this->modelClass;
            $this->columns = $modelInstance->getFillable();
            Log::info('GenericSelectedExport map(): Using fallback columns from model fillable', [
                'columns' => $this->columns
            ]);
        }

        // STRICT: only use defined columns
        $columns = $this->filterColumns($this->columns, $model);

        Log::info('GenericSelectedExport map() - columns after filter', [
            'columns_filtered' => $columns,
        ]);

        // If still empty after filtering, return empty array
        if (empty($columns)) {
            Log::warning('GenericSelectedExport: No columns to export after filtering', [
                'modelClass' => get_class($model)
            ]);
            return [];
        }

        $data = [];
        $columnLog = [];

        foreach ($columns as $column) {
            $value = null;
            $source = 'unknown';

            // Relation mapping
            $mapped = $this->getRelationValue($model, $column);
            if ($mapped !== null) {
                $value = $mapped;
                $source = 'relation';
                $data[] = $value;
                $columnLog[] = ['column' => $column, 'value' => $value, 'source' => $source];
                continue;
            }

            // Direct attribute
            if (isset($model->{$column})) {
                $value = $model->{$column} ?? '';
                $source = 'direct';
                $data[] = $value;
                $columnLog[] = ['column' => $column, 'value' => $value, 'source' => $source];
                continue;
            }

            // Virtual columns
            $value = $this->getVirtualColumnValue($model, $column);
            $source = 'virtual';
            $data[] = $value;
            $columnLog[] = ['column' => $column, 'value' => $value, 'source' => $source];
        }

        // Surgical debug log for Spent model to check relationship data
        if ($model instanceof \App\Models\Spent) {
            Log::info('EXPORT DEBUG - Spent model relationship data', [
                'model_id' => $model->id,
                'project' => $model->project,
                'createdBy' => $model->createdBy,
                'project_name' => optional($model->project)->name,
                'created_by_name' => optional($model->createdBy)->name,
                'spentLedger' => $model->spentLedger,
                'ledger_name' => optional($model->spentLedger)->name,
            ]);
        }

        Log::info('GenericSelectedExport map() - data built', [
            'model_id' => $model->id,
            'column_log' => $columnLog,
            'data' => $data,
        ]);

        // FINAL CLEAN (important)
        // Handle DT_RowIndex from addIndexColumn() - strip any numeric index values
        $data = array_values($data);

        // If first item is numeric (likely DT_RowIndex leak), remove it
        // This handles case where addIndexColumn() adds row index to model
        if (!empty($data) && is_numeric($data[0]) && $data[0] > 0 && $data[0] == floor($data[0])) {
            Log::info('GenericSelectedExport: Removing DT_RowIndex leak', ['removed_value' => $data[0]]);
            array_shift($data);
            $data = array_values($data);
        }

        Log::info('GenericSelectedExport map() - FINAL', [
            'model_id' => $model->id,
            'data' => $data,
        ]);

        return $data;
    }

    protected function getVirtualColumnValue(Model $model, string $column): string
    {
        if ($model instanceof \Workdo\Hrm\Entities\Attendance) {
            if ($column === 'employee_name') {
                return optional($model->employees)->name ?? '';
            }

            if ($column === 'site_name') {
                return optional($model->site)->name ?? '';
            }

            if ($column === 'clock_in_image_url') {
                return $model->clock_in_image ? get_file($model->clock_in_image) : '';
            }

            if ($column === 'clock_out_image_url') {
                return $model->clock_out_image ? get_file($model->clock_out_image) : '';
            }
        }

        if ($model instanceof \App\Models\Spent) {
            switch ($column) {
                case 'project_name':
                    return optional($model->project)->name ?? '';
                case 'created_by_name':
                    return optional($model->createdBy)->name ?? '';
                case 'ledger_name':
                    return optional($model->spentLedger)->name ?? '';
            }
        }

        return '';
    }

    protected function getRelationValue(Model $model, string $column): ?string
    {
        if (!isset($this->relationMap[$column])) {
            return null;
        }

        $relationName = $this->relationMap[$column];

        if (!method_exists($model, $relationName)) {
            return null;
        }

        try {
            $relation = $model->{$relationName};

            if ($relation instanceof Model) {
                $value = $relation->name
                    ?? $relation->title
                    ?? $relation->company_name
                    ?? trim(($relation->first_name ?? '') . ' ' . ($relation->last_name ?? ''));
                
                // DO NOT fall back to ID - this causes numeric "1" to appear in exports
                return $value ?: '';
            }
        } catch (\Exception $e) {
            Log::warning('Relation error', ['error' => $e->getMessage()]);
        }

        return '';
    }

    public function getFilePrefix(): string
    {
        return $this->filePrefix ?? 'export';
    }
}