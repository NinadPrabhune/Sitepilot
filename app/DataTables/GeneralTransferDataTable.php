<?php

namespace App\DataTables;

use App\Models\GeneralTransfer;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class GeneralTransferDataTable extends DataTable {

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = [];
        $dataTable = (new EloquentDataTable($query))
                ->addIndexColumn()
                ->editColumn('transfer_type', fn($row) => ucfirst($row->transfer_type))
                ->addColumn('entity_name', function ($row) {
                    switch ($row->transfer_type) {
                        case 'machinery': return $row->machinery?->name ?? '-';
                        case 'tools_and_equipment': return $row->toolsAndEquipment?->material?->name ?? '-';
                        case 'employee': return $row->employee?->name ?? '-';
                        default: return '-';
                    }
                })
                ->editColumn('from_site_id', fn($row) => $row->fromSite->name ?? '-')
                ->editColumn('to_site_id', fn($row) => $row->toSite->name ?? '-')
                ->editColumn('transfer_date', fn($row) => $row->transfer_date ? \Carbon\Carbon::parse($row->transfer_date)->format('d-m-Y') : '-')

                // ✅ Created By
                ->editColumn('created_by', fn($row) => optional($row->creator)->name ?? '-')

                // ✅ Created At
                ->editColumn('created_at', fn($row) => $row->created_at->format('d M Y, h:i A'));

        $dataTable->addColumn('action', fn(GeneralTransfer $transfer) => view('general_transfer.action', compact('transfer')));

        $rowColumn[] = ['entity_name', 'action', 'created_by', 'created_at'];

        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(GeneralTransfer $model): QueryBuilder {
        return $model->newQuery()
                        ->with(['machinery', 'toolsAndEquipment', 'employee', 'fromSite', 'toSite', 'creator']) // ✅ add creator
                        ->where('workspace_id', getActiveWorkSpace())
                        ->when(getActiveProject(), function ($q) {
                            $q->where('from_site_id', getActiveProject());
                        })
                        ->when(request()->filled('start_date'), function ($q) {
                            $q->whereDate('transfer_date', '>=', request('start_date'));
                        })
                        ->when(request()->filled('end_date'), function ($q) {
                            $q->whereDate('transfer_date', '<=', request('end_date'));
                        })
                        ->when(request()->filled('transfer_type'), function ($q) {
                            $q->where('transfer_type', request('transfer_type'));
                        });
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder {
        $dataTable = $this->builder()
                ->setTableId('general-transfer-table')
                ->columns($this->getColumns())
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
        ]);

        // Export buttons
        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Export"></i>',
            'buttons' => [
                [
                    'extend' => 'print',
                    'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 2, 3, 4]],
                ],
                [
                    'extend' => 'csv',
                    'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 2, 3, 4]],
                ],
                [
                    'extend' => 'excel',
                    'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 2, 3, 4]],
                ],
            ],
        ];

        $buttonsConfig = array_merge([
            $exportButtonConfig,
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ]);

        $dataTable->parameters([
            "dom" => "
                <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex justify-content-end gap-2'Bf>>
                <'dataTable-container'<'col-sm-12'tr>>
                <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
        ]);

        return $dataTable;
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('transfer_type')->title(__('Transfer Type')),
            Column::make('entity_name')->title(__('Name')),
            Column::make('from_site_id')->title(__('From Site')),
            Column::make('to_site_id')->title(__('To Site')),
            Column::make('transfer_date')->title(__('Transfer Date')),
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string {
        return 'GeneralTransfer_' . date('YmdHis');
    }
}
