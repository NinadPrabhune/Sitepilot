<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\File;

/**
 * SupplierDataTable - DataTable with selectable export functionality
 * 
 * Uses SelectableExportTrait for reusable checkbox selection and export features.
 * 
 * @package App\DataTables
 */
class SupplierDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'supplier-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'supplier-checkbox';
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
        return 'suppliers';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Supplier::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['checkbox', 'created_by'];
        
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Supplier $supplier) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $supplier->id . '">';
            })
            ->editColumn('category_id', function (Supplier $supplier) {
                return optional($supplier->category)->name ?? '';
            })
            ->editColumn('created_by', function (Supplier $supplier) {
                return optional($supplier->creator)->name ?? '';
            })
            ->editColumn('type', function (Supplier $supplier) {
                return $supplier->type ?? '';
            })
            ->editColumn('phone', function (Supplier $supplier) {
                return $supplier->phone ?? '';
            })
            ->editColumn('email', function (Supplier $supplier) {
                return $supplier->email ?? '';
            })
            ->editColumn('address', function (Supplier $supplier) {
                return $supplier->address ?? '';
            })
            ->editColumn('city', function (Supplier $supplier) {
                return $supplier->city ?? '';
            })
            ->editColumn('state', function (Supplier $supplier) {
                return $supplier->state ?? '';
            })
            ->editColumn('pincode', function (Supplier $supplier) {
                return $supplier->pincode ?? '';
            })
            ->editColumn('country', function (Supplier $supplier) {
                return $supplier->country ?? '';
            });
        
        $dataTable->addColumn('action', function (Supplier $supplier) {
            return view('suppliers.action', compact('supplier'));
        });
        $rowColumn[] = 'action';
        
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Supplier $model): QueryBuilder
    {
        $supplierQuery = $model->where('is_active', '=', 1)->orderBy('id', 'desc');
        $supplierQuery = $supplierQuery->with(['category', 'creator']);

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($supplierQuery);

        return $supplierQuery;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0)
            ->select(['' . $this->getCheckboxClass() . ''])
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
        $column = [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $this->getCheckboxClass() . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),            
            Column::make('name')->title(__('Name')),
            Column::make('category_id')->title(__('Category')),
            Column::make('type')->title(__('Type')),
            Column::make('contact_person')->title(__('Contact Person')),
            Column::make('phone')->title(__('Phone')),
            Column::make('city')->title(__('City')),
            Column::make('state')->title(__('State')),
            Column::make('pincode')->title(__('Pincode')),
            Column::make('country')->title(__('Country')),
        ];
        
        $action = [
            Column::computed('action')
            ->exportable(false)
            ->printable(false)
            ->width(60)
            
        ];
        $column = array_merge($column,$action);
        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Supplier_' . date('YmdHis');
    }
}
