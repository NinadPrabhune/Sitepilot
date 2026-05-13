<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\AssetsToolsAndEquipment;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AssetsToolsAndEquipmentDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'assets-tools-and-equipment-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'assets-checkbox';
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
        return 'assets_tools_equipment';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\AssetsToolsAndEquipment::class;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['action', 'checkbox'];

        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (AssetsToolsAndEquipment $item) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $item->id . '">';
            })
            ->addColumn('material_name', function (AssetsToolsAndEquipment $item) {
                return optional($item->material)->name;
            })
            ->editColumn('site_id', function (AssetsToolsAndEquipment $item) {
                return optional($item->site)->name ?? '';
            })
            ->editColumn('available_qty', function (AssetsToolsAndEquipment $item) {
                return $item->available_qty;
            });

       
            $dataTable->addColumn('action', function (AssetsToolsAndEquipment $item) {
                return view('assets_tools_and_equipment.action', compact('item'));
            });
            $rowColumn[] = 'action';
       

        return $dataTable->rawColumns($rowColumn);
    }

    public function query(AssetsToolsAndEquipment $model): QueryBuilder
    {
        $query = $model->with('material', 'site')
            ->where('workspace_id', getActiveWorkSpace())
            ->where('site_id', getActiveProject())
            ->orderBy('id', 'desc')
            ->select([
                'assets_tools_and_equipment.*',
                \DB::raw('assets_tools_and_equipment.quantity as available_qty'),
            ]);

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($query);

        return $query;
    }

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
            "dom" => "
                <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>
                <'dataTable-container'<'col-sm-12'tr>>
                <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "select" => [
                "style" => "multi",
                "selector" => "td:first-child ." . $this->getCheckboxClass()
            ],
            "drawCallback" => 'function(settings) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
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

    public function getColumns(): array
    {
        $checkboxClass = $this->getCheckboxClass();
        
        $columns = [
            Column::make('id')->visible(false)->searchable(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->data('DT_RowIndex')->name('DT_RowIndex')->title(__('No'))->orderable(false)->searchable(false),
            Column::make('material_name')->title(__('Material Name')),
            Column::make('available_qty')->title(__('Available Qty')),
            Column::make('site_id')->title(__('Current Site')),
            Column::make('operational_status')->title(__('Operational Status')),
        ];

        
            $columns[] = Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->title(__('Action'));
      

        return $columns;
    }

    protected function filename(): string
    {
        return 'AssetsToolsAndEquipment_' . date('YmdHis');
    }
}
