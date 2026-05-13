<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\SupplierCategory;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class SupplierCategoryDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'supplier-categories-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'supplier-category-checkbox';
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
        return 'supplier_categories';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\SupplierCategory::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['checkbox'];
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (SupplierCategory $SupplierCategory) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $SupplierCategory->id . '">';
            });

        $dataTable->addColumn('action', function (SupplierCategory $SupplierCategory) {
            return view('supplier-categories.action', compact('SupplierCategory'));
        });
        $rowColumn[] = 'action';
        
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(SupplierCategory $model): QueryBuilder
    {
        $query = $model->where('is_active', '=', 1)->orderBy('id', 'desc');

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($query);

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
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
            "dom" =>  "
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
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
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
            Column::make('name')->title(__('Name')),
            Column::make('description')->title(__('Description')),
        ];

        $action = [
            Column::computed('action')
            ->exportable(false)
            ->printable(false)
            ->width(60)
        ];
        $column = array_merge($column, $action);

        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'SupplierCategory_' . date('YmdHis');
    }
}
