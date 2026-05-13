<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class MaterialDataTable extends DataTable {

    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'material-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'material-checkbox';
    }

    /**
     * Get the export route name for this DataTable.
     */
    protected function getExportRouteName(): string
    {
        return 'export.selected';
    }

    /**
     * Get the export filename prefix.
     */
    protected function getExportFilePrefix(): string
    {
        return 'materials';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Material::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = ['image', 'price', 'category_id', 'unit_id', 'checkbox'];
        $dataTable = (new EloquentDataTable($query))
                ->addIndexColumn()
                ->addColumn('checkbox', function (Material $Material) {
                    return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $Material->id . '">';
                })
                ->editColumn('image', function (Material $Material) {
                    // Optimized: Avoid File::exists() per row - use asset URL directly
                    // Browser will handle 404 for missing images, much faster than filesystem check
                    $imageUrl = !empty($Material->image) ? asset($Material->image) : asset('images/material/No_Image_Available.jpeg');

                    $html = '<a href="' . $imageUrl . '" target="_blank">
                            <img src="' . $imageUrl . '" class="rounded border-2 border border-primary
                            " style="width:100px;" id="blah3" onerror="this.src=\'' . asset('images/material/No_Image_Available.jpeg') . '\'">
                        </a>';

                    return $html;
                })
                ->editColumn('category_id', function (Material $Material) {
                    return optional($Material->category)->name ?? '';
                })->editColumn('unit_id', function (Material $Material) {
                    return optional($Material->unit)->name ?? '';
                })
                ->editColumn('price', function (Material $Material) {
                    return currency_format_with_sym($Material->price);
                });

        if (\Laratrust::hasPermission('material show') || \Laratrust::hasPermission('material edit') || \Laratrust::hasPermission('material delete')) {
            $dataTable->addColumn('action', function (Material $material) {
                return view('materials.action', compact('material'));
            });
            $rowColumn[] = 'action';
        }
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Material $model): QueryBuilder {
        $materialQuery = $model->where('status', '=', 'active')->orderBy('id', 'desc');
        $materialQuery = $materialQuery->with(['category', 'unit']);

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($materialQuery);

        return $materialQuery;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder {
        $dataTable = $this->builder()
                ->setTableId($this->getTableId())
                ->columns($this->getColumns())
                ->select(['' . $this->getCheckboxClass() . ''])
                ->minifiedAjax()
                ->orderBy(0)
                ->language([
                    "paginate" => [
                        "next" => '<i class="ti ti-chevron-right"></i>',
                        "previous" => '<i class="ti ti-chevron-left"></i>'
                    ],
                    'lengthMenu' => "_MENU_" . __('Entries Per Page'),
                    "searchPlaceholder" => __('Search...'),
                    "search" => "",
                    "info" => __('Showing _START_ to _END_ of _TOTAL_ entries')
                ])
                ->initComplete($this->getCheckboxInitScript());

        // Use the export button config from trait
        $buttonsConfig = $this->getExportButtonConfig();

        $dataTable->parameters([
            "dom" => "
                            <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex justify-content-end gap-2'Bf>>
                            <'dataTable-container'<'col-sm-12'tr>>
                            <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "select" => [
                "style" => "multi",
                "selector" => "td:first-child ." . $this->getCheckboxClass()
            ],
            "drawCallback" => 'function( settings ) {
                                    var tooltipTriggerList = [].slice.call(
                                        document.querySelectorAll("[data-bs-toggle=tooltip]")
                                      );
                                      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                        return new bootstrap.Tooltip(tooltipTriggerEl);
                                      });
                                      var popoverTriggerList = [].slice.call(
                                        document.querySelectorAll("[data-bs-toggle=popover]")
                                      );
                                      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                                        return new bootstrap.Popover(popoverTriggerEl);
                                      });
                                      var toastElList = [].slice.call(document.querySelectorAll(".toast"));
                                      var toastList = toastElList.map(function (toastEl) {
                                        return new bootstrap.Toast(toastEl);
                                      });
                                }'
        ]);

        $dataTable->language([
            'buttons' => [
                'create' => __('Create'),
                'export' => __('Export'),
                'print' => __('Print'),
                'reset' => __('Reset'),
                'reload' => __('Reload'),
                'excel' => __('Excel'),
                'csv' => __('CSV'),
            ]
        ]);

        return $dataTable;
    }

    /**
     * Get export column labels from getColumns() - must match the exact order.
     * This ensures headers come from the title attribute and match getColumns() order.
     *
     * @return array Array of column labels in export order
     */
    protected function getExportColumnLabels(): array
    {
        $columns = $this->getColumns();
        $labels = [];

        foreach ($columns as $column) {
            // Skip non-Column objects
            if (!($column instanceof \Yajra\DataTables\Html\Column)) {
                continue;
            }

            // Get attributes which contains data, title, exportable, etc.
            $attributes = $column->getAttributes();
            
            // Get data value from attributes
            $dataValue = $attributes['data'] ?? null;

            // Skip computed columns (checkbox, action) and non-exportable
            if (in_array($dataValue, ['checkbox', 'action', 'DT_RowIndex'])) {
                continue;
            }

            // Skip if exportable is explicitly set to false
            if (isset($attributes['exportable']) && $attributes['exportable'] === false) {
                continue;
            }

            // Get title from the attributes
            $title = $attributes['title'] ?? null;
            if (!empty($title) && is_string($title)) {
                // Skip HTML checkbox title
                if (strpos($title, '<input') !== false) {
                    continue;
                }
                $labels[] = $title;
            } elseif (!empty($dataValue)) {
                // Fallback to data value if no title
                $labels[] = $dataValue;
            }
        }

        return $labels;
    }

    /**
     * Get export columns configuration - returns field/alias mapping.
     * This ensures only columns defined in getColumns() are exported, in exact order.
     *
     * @return array Array of column configs with 'field' and 'alias' keys
     */
    protected function getExportColumnsConfig(): array
    {
        $columns = $this->getColumns();
        $config = [];

        foreach ($columns as $column) {
            // Skip non-Column objects
            if (!($column instanceof \Yajra\DataTables\Html\Column)) {
                continue;
            }

            // Get attributes which contains data, title, exportable, etc.
            $attributes = $column->getAttributes();
            
            // Get data value from attributes
            $dataValue = $attributes['data'] ?? null;

            // Skip computed columns (checkbox, action) and non-exportable
            if (in_array($dataValue, ['checkbox', 'action', 'DT_RowIndex'])) {
                continue;
            }

            // Skip if exportable is explicitly set to false
            if (isset($attributes['exportable']) && $attributes['exportable'] === false) {
                continue;
            }

            // Add to config with field and alias
            if (!empty($dataValue)) {
                $config[] = [
                    'field' => $dataValue,
                    'alias' => $dataValue
                ];
            } elseif (!empty($attributes['name'])) {
                // Fallback to name attribute
                $config[] = [
                    'field' => $attributes['name'],
                    'alias' => $attributes['name']
                ];
            }
        }

        return $config;
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array {
        $checkboxClass = $this->getCheckboxClass();
        
        $column = [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('image')->title(__('Image'))->orderable(false)->searchable(false),
            Column::make('name')->title(__('Name')),
            Column::make('category_id')->title(__('Category')),
            Column::make('unit_id')->title(__('Unit')),
            Column::make('price')->title(__('Price')),
            Column::make('reorder_level')->title(__('Reorder Level')),
                    Column::make('status')->title(__('Status'))
                    ->exportable(false)
                    ->printable(false)
                    ->width(60)
        ];
        if (\Laratrust::hasPermission('material show') || \Laratrust::hasPermission('material edit') || \Laratrust::hasPermission('material delete')) {
            $action = [
                        Column::computed('action')
                        ->exportable(false)
                        ->printable(false)
                        ->width(60)
            ];
            $column = array_merge($column, $action);
        }
        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string {
        return 'Material_' . date('YmdHis');
    }
}
