<?php

namespace App\DataTables\Traits;

use Yajra\DataTables\Html\Column;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SelectableExportTrait - Provides reusable checkbox selection and export functionality
 * for Yajra DataTables.
 * 
 * Usage:
 * 1. Use this trait in your DataTable class
 * 2. Implement the required abstract methods
 * 3. Call the trait methods in your DataTable methods
 * 
 * @package App\DataTables\Traits
 */
trait SelectableExportTrait
{
    /**
     * Get the unique table ID for this DataTable.
     * Must be implemented by the using class.
     * 
     * @return string Table ID (e.g., 'supplier-table')
     */
    abstract protected function getTableId(): string;

    /**
     * Get the checkbox class name for row selection.
     * Must be implemented by the using class.
     * 
     * @return string Checkbox class (e.g., 'supplier-checkbox')
     */
    abstract protected function getCheckboxClass(): string;

    /**
     * Get the export route name for this DataTable.
     * Must be implemented by the using class.
     * 
     * @return string Route name (e.g., 'export.selected')
     */
    abstract protected function getExportRouteName(): string;

    /**
     * Get the export filename prefix.
     * Must be implemented by the using class.
     * 
     * @return string Filename prefix (e.g., 'suppliers')
     */
    abstract protected function getExportFilePrefix(): string;

    /**
     * Get the model class for export functionality.
     * Must be implemented by the using class.
     * 
     * @return string Model class fully qualified name
     */
    abstract protected function getModelClass(): string;

    /**
     * Add checkbox column to the dataTable.
     * Call this in your dataTable() method.
     * 
     * @param \Yajra\DataTables\EloquentDataTable $dataTable
     * @param string|null $modelClass Optional model class for type hinting
     * @return \Yajra\DataTables\EloquentDataTable
     */
    protected function addCheckboxColumn($dataTable, $modelClass = null)
    {
        $checkboxClass = $this->getCheckboxClass();
        $modelClass = $modelClass ?? $this->getModelClass();
        
        return $dataTable->addColumn('checkbox', function ($model) use ($checkboxClass) {
            return '<input type="checkbox" class="' . $checkboxClass . ' form-check-input" value="' . $model->id . '">';
        });
    }

    /**
     * Add checkbox column definition to getColumns().
     * Call this in your getColumns() method.
     * 
     * @param array $columns Existing columns array
     * @param bool $addNoColumn Whether to add a No/index column
     * @return array Modified columns array with checkbox
     */
    protected function addCheckboxColumnDefinition(array $columns, bool $addNoColumn = true): array
    {
        $checkboxClass = $this->getCheckboxClass();
        
        // Add ID column (hidden)
        array_unshift($columns, Column::make('id')->searchable(false)->visible(false)->exportable(false)->printable(false));
        
        // Add checkbox column
        array_unshift($columns, Column::computed('checkbox')
            ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
            ->exportable(false)
            ->printable(false)
            ->orderable(false)
            ->searchable(false)
            ->width(20));

        // Add No column if requested
        if ($addNoColumn) {
            array_unshift($columns, Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false));
        }

        return $columns;
    }

    /**
     * Configure the builder with selection and export options.
     * Call this in your html() method after builder().
     * 
     * @param \Yajra\DataTables\Html\Builder $dataTable
     * @return \Yajra\DataTables\Html\Builder
     */
    protected function configureSelectionBuilder($dataTable)
    {
        $tableId = $this->getTableId();
        $checkboxClass = $this->getCheckboxClass();
        
        return $dataTable
            ->setTableId($tableId)
            ->select(['' . $checkboxClass . '']);
    }

    /**
     * Get the initComplete JavaScript for checkbox handling.
     * Call this in your html() method.
     * 
     * @return string JavaScript code
     */
    protected function getCheckboxInitScript(): string
    {
        return $this->getCombinedInitScript('');
    }

    /**
     * Get the combined initComplete JavaScript for checkbox handling and optional custom init code.
     * This method properly combines both scripts into a single function.
     * 
     * @param string $additionalInitCode Additional JavaScript code to include inside the initComplete function
     * @return string Combined JavaScript code
     */
    protected function getCombinedInitScript(string $additionalInitCode = ''): string
    {
        $tableId = $this->getTableId();
        $checkboxClass = $this->getCheckboxClass();
        
        $additionalInitCode = trim($additionalInitCode);
        $additionalBlock = !empty($additionalInitCode) ? "\n\n                " . $additionalInitCode . "\n" : "";
        
        return "function() {
            var table = this;
            var api = this.api();
            var tableId = '{$tableId}';

            var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');
            searchInput.removeClass('form-control form-control-sm');
            searchInput.addClass('dataTable-input');
            var select = $(table.api().table().container()).find('.dataTables_length select').removeClass('custom-select custom-select-sm form-control form-control-sm').addClass('dataTable-selector');

            // Handle row checkbox change - select/deselect row using DataTables Select extension
            $('#' + tableId).on('change', '.{$checkboxClass}', function(e) {
                var row = $(this).closest('tr');
                var rowIndex = api.row(row).index();
                
                if (this.checked) {
                    api.row(rowIndex).select();
                } else {
                    api.row(rowIndex).deselect();
                }
            });

            // Handle Select All checkbox
            $('#select-all-{$checkboxClass}').on('click', function() {
                if (this.checked) {
                    api.rows().select();
                    $('.{$checkboxClass}').prop('checked', true);
                } else {
                    api.rows().deselect();
                    $('.{$checkboxClass}').prop('checked', false);
                }
            });

            // Sync header checkbox with selection state
            api.on('select deselect', function(e, dt, type, indexes) {
                var allSelected = api.rows({selected: true}).count();
                var totalVisible = api.rows({selected: null}).count();
                
                if (allSelected === totalVisible && totalVisible > 0) {
                    $('#select-all-{$checkboxClass}').prop('checked', true);
                } else {
                    $('#select-all-{$checkboxClass}').prop('checked', false);
                }
            });{$additionalBlock}
        }";
    }

    /**
     * Get export button configuration.
     * Call this in your html() method to get the buttons array.
     * 
     * @return array Buttons configuration array
     */
    protected function getExportButtonConfig(): array
    {
        $tableId = $this->getTableId();
        $checkboxClass = $this->getCheckboxClass();
        $exportUrl = route($this->getExportRouteName());
        $filePrefix = $this->getExportFilePrefix();
        // Use json_encode to properly escape the model class for JavaScript
        $modelClass = json_encode($this->getModelClass());
        
        // Get exportable columns from getColumns()
        $exportColumns = $this->getExportColumns();
        $columnsJson = json_encode($exportColumns);
        
        // Log::info('========================================================');
        // Log::info('SELECTABLE EXPORT TRAIT - BUTTON CONFIG');
        // Log::info('========================================================');
        // Log::info('SELECTABLE EXPORT BUTTON CONFIG - START', [
        //     'class' => get_class($this)
        // ]);
        // Log::info('DataTable class:', [
        //     'class' => get_class($this),
        //     'hasGetExportColumnsConfig' => method_exists($this, 'getExportColumnsConfig')
        // ]);
        // Log::info('Export columns being sent to frontend:', [
        //     'columns' => $exportColumns,
        //     'columnsJson' => $columnsJson,
        //     'count' => count($exportColumns)
        // ]);
        
        // Get column labels if the method exists
        $columnLabels = method_exists($this, 'getExportColumnLabels') 
            ? $this->getExportColumnLabels() 
            : [];
        $labelsJson = json_encode($columnLabels);
        
        // Log::info('Export labels being sent to frontend:', [
        //     'labels' => $columnLabels,
        //     'labelsJson' => $labelsJson,
        //     'count' => count($columnLabels)
        // ]);
        
        $jsAction = <<<JS
function(e, dt, node, config) {
    console.log('=== EXPORT BUTTON CLICKED ===');
    var tableId = '{$tableId}';
    var checkboxClass = '.{$checkboxClass}';
    var exportUrl = '{$exportUrl}';
    var filePrefix = '{$filePrefix}';
    var modelClass = {$modelClass};
    var exportColumns = {$columnsJson};
    var columnLabels = {$labelsJson};

    console.log('Export columns from PHP:', exportColumns);
    console.log('Column labels from PHP:', columnLabels);

    // Get selected checkbox values
    var selectedIds = [];
    $('#' + tableId + ' tbody ' + checkboxClass + ':checked').each(function() {
        selectedIds.push($(this).val());
    });

    console.log('Selected IDs:', selectedIds);

    var exportUrlFinal;
    var urlParams = [];

    // Add model parameter
    urlParams.push('model=' + encodeURIComponent(modelClass));

    // Add columns parameter if we have columns
    if (exportColumns && exportColumns.length > 0) {
        urlParams.push('columns=' + encodeURIComponent(JSON.stringify(exportColumns)));
    }

    // Add labels parameter if we have them (for export headings)
    if (columnLabels && Object.keys(columnLabels).length > 0) {
        urlParams.push('labels=' + encodeURIComponent(JSON.stringify(columnLabels)));
    }

    // Add IDs or all parameter
    if (selectedIds.length === 0) {
        urlParams.push('all=true');
        console.log('Direct export (all=true) - no selection');
    } else {
        urlParams.push('ids=' + selectedIds.join(','));
        console.log('Selected export with IDs:', selectedIds);
    }

    exportUrlFinal = exportUrl + '?' + urlParams.join('&');
    console.log('Final export URL:', exportUrlFinal);

    fetch(exportUrlFinal, {
        method: 'GET',
        headers: {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        }
    })
    .then(response => {
        console.log('Export response status:', response.status);
        if (!response.ok) {
            return response.json().then(err => Promise.reject(err));
        }
        return response.blob();
    })
    .then(blob => {
        console.log('Export blob received, size:', blob.size);
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filePrefix + '_export_' + new Date().toISOString().slice(0,10) + '.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Export error: ' + JSON.stringify(error));
    });
}
JS;

        return [
            [
                'text' => '<i class="ti ti-download me-2"></i> ',
                'className' => 'btn btn-default buttons-collection btn-light-secondary export-selected-btn',
                'attr' => ['id' => 'exportSelected'],
                'action' => $jsAction
            ],
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger me-2',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ];
    }

    /**
     * Get exportable columns from getColumns() method.
     * Returns array of column names that should be exported.
     * 
     * Uses getExportColumnsConfig() if available (for proper field/alias/title mapping),
     * otherwise falls back to direct processing of getColumns().
     * 
     * @return array
     */
    protected function getExportColumns(): array
    {
        // Log::info('========================================================');
        // Log::info('SELECTABLE EXPORT TRAIT - getExportColumns()');
        // Log::info('========================================================');
        // Log::info('SELECTABLE EXPORT TRAIT - getExportColumns() START', [
        //     'class' => get_class($this)
        // ]);
        // Log::info('Current class:', [
        //     'class' => get_class($this),
        //     'hasGetExportColumnsConfig' => method_exists($this, 'getExportColumnsConfig'),
        //     'hasGetColumns' => method_exists($this, 'getColumns')
        // ]);
        
        // Check if the child class has overridden getExportColumnsConfig()
        // If so, use it to get proper alias extraction
        if (method_exists($this, 'getExportColumnsConfig')) {
            // Log::info('Using getExportColumnsConfig() method');
            $config = $this->getExportColumnsConfig();
            $exportableColumns = array_column($config, 'alias');
            
            // Log::info('SelectableExportTrait getExportColumns - using getExportColumnsConfig():', $exportableColumns);
            
            return $exportableColumns;
        }
        
        // Fallback to original logic for classes without getExportColumnsConfig()
        // Get columns from getColumns()
        $columns = $this->getColumns();
        $exportableColumns = [];
        
        // Log::info('SelectableExportTrait getExportColumns - raw columns from getColumns():', [
        //     'count' => count($columns)
        // ]);
        
        foreach ($columns as $column) {
            // Skip non-Column objects
            if (!($column instanceof \Yajra\DataTables\Html\Column)) {
                // Log::info('Skipping non-Column object');
                continue;
            }
            
            // Get data value - handle both string and Column object cases
            $data = $column->getData();
            $dataValue = null;
            
            if (is_string($data)) {
                $dataValue = $data;
            } elseif (is_object($data) && isset($data->data)) {
                // Computed columns like 'checkbox', 'action' return the Column object
                $dataValue = $data->data;
            }
            
            // Skip computed columns (like checkbox, action)
            if (in_array($dataValue, ['checkbox', 'action', 'No', 'DT_RowIndex'])) {
                // Log::info('Skipping computed column', ['data' => $dataValue]);
                continue;
            }
            
            // Skip if exportable is explicitly set to false
            $attributes = $column->getAttributes();
            if (isset($attributes['exportable']) && $attributes['exportable'] === false) {
                // Log::info('Skipping non-exportable column', ['data' => $dataValue, 'attributes' => $attributes]);
                continue;
            }
            
            // Get the data attribute
            if (!empty($dataValue)) {
                if (!in_array($dataValue, $exportableColumns)) {
                    $exportableColumns[] = $dataValue;
                    // Log::info('Added column', ['data' => $dataValue, 'name' => $column->getName(), 'title' => $column->getTitle()]);
                }
            } elseif (is_string($column->getName()) && !empty($column->getName())) {
                // Fallback to name attribute
                if (!in_array($column->getName(), $exportableColumns)) {
                    $exportableColumns[] = (string) $column->getName();
                }
            }
        }
        
        // Log::info('SelectableExportTrait getExportColumns - final exportable columns:', $exportableColumns);
        
        return $exportableColumns;
    }

    /**
     * Get the selection configuration for DataTables parameters.
     * Call this in your html() method when setting parameters.
     * 
     * @return array Selection configuration
     */
    protected function getSelectionConfig(): array
    {
        $checkboxClass = $this->getCheckboxClass();
        
        return [
            "style" => "multi",
            "selector" => "td:first-child ." . $checkboxClass
        ];
    }

    /**
     * Get the ID column name for filtering.
     * Override this method in child class if the ID column is different (e.g., 'users.id' for JOIN queries).
     * 
     * @return string
     */
    protected function getIdColumnName(): string
    {
        return 'id';
    }

    /**
     * Handle selected_ids from export request in query.
     * Call this in your query() method to filter by selected IDs.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function handleSelectedIdsFilter($query)
    {
        $selectedIds = request()->get('selected_ids');
        $ids = request()->get('ids');
        
        // Support both selected_ids and ids parameters
        $idParam = $selectedIds ?? $ids;
        
        if ($idParam) {
            $ids = is_array($idParam) ? $idParam : explode(',', $idParam);
            $idColumn = $this->getIdColumnName();
            $query->whereIn($idColumn, $ids);
            // Log::info('Exporting selected ' . class_basename($this->getModelClass()) . ' IDs: ' . implode(',', $ids));
        }
        
        return $query;
    }

    /**
     * Get raw columns array for checkbox and action columns.
     * 
     * @return array Array of column names that should be raw (HTML)
     */
    protected function getRawColumns(): array
    {
        return ['checkbox'];
    }

    /**
     * Add action column support - can be overridden by child class.
     * 
     * @param \Yajra\DataTables\EloquentDataTable $dataTable
     * @return \Yajra\DataTables\EloquentDataTable
     */
    protected function addActionColumn($dataTable)
    {
        // Override in child class if action column is needed
        return $dataTable;
    }

    /**
     * Get action column definition - can be overridden by child class.
     * 
     * @return array|null Action column definition or null
     */
    protected function getActionColumnDefinition(): ?array
    {
        // Override in child class if action column is needed
        return null;
    }
}
