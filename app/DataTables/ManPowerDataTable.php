<?php

namespace App\DataTables;

use App\Models\ManPowerMaster;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ManPowerDataTable extends DataTable {

    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = ['supplier_id', 'site_id', 'total_count', 'work_date'];

        $dataTable = (new EloquentDataTable($query))
                ->addIndexColumn()
                ->editColumn('supplier_id', fn($row) => optional($row->supplier)->name ?? '')
                ->editColumn('site_id', fn($row) => optional($row->site)->name ?? '')
                ->editColumn('total_count', fn($row) => $row->total_count)
                ->editColumn('work_date', fn($row) => \Carbon\Carbon::parse($row->work_date)->format('d-m-Y'))                
                ->editColumn('created_by', function ($row) {
                    return optional($row->creator)->name ?? '';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->format('d-m-Y, h:i A');
                });

        
        
        $dataTable->addColumn('action', function ($row) {
            return view('manpower.action', compact('row'));
        });

        $rowColumn[] = 'action';

        return $dataTable->rawColumns($rowColumn);
    }

    public function query(ManPowerMaster $model): QueryBuilder {
        $request = request();

        $query = $model->with(['supplier', 'site'])
                ->where('workspace_id', getActiveWorkSpace())
                ->where('site_id', getActiveProject())
                ->orderBy('id', 'desc');

        // ✅ Default: current month records if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('work_date', \Carbon\Carbon::now()->month)
                    ->whereYear('work_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('work_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('work_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('work_date', '<=', $request->end_date);
        }

        return $query;
    }

    public function html(): HtmlBuilder {
        return $this->builder()
                        ->setTableId('manpower-table')
                        ->columns($this->getColumns())
                        ->ajax([
                            'data' => 'function(d) {
                    var start_date = $("input[name=start_date]").val();
                    d.start_date = start_date;

                    var end_date = $("input[name=end_date]").val();
                    d.end_date = end_date;
                }',
                        ])
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
                        ->initComplete("function() {
                var table = this;

                // Apply filter
                $('body').on('click', '#applyfilter', function() {
                    if (!$('input[name=start_date]').val() && !$('input[name=end_date]').val()) {
                        toastrs('Error!', 'Please select at least one filter', 'error');
                        return;
                    }
                    $('#manpower-table').DataTable().draw();
                });

                // Clear filter
                $('body').on('click', '#clearfilter', function() {
                    $('input[name=start_date]').val('');
                    $('input[name=end_date]').val('');
                    $('#manpower-table').DataTable().draw();
                });

                var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');
                searchInput.removeClass('form-control form-control-sm').addClass('dataTable-input');
                var select = $(table.api().table().container()).find('.dataTables_length select')
                    .removeClass('custom-select custom-select-sm form-control form-control-sm')
                    .addClass('dataTable-selector');
            }")
                        ->parameters([
                            "dom" => "
                    <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>
                    <'dataTable-container'<'col-sm-12'tr>>
                    <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                            'buttons' => [
                                [
                                    'extend' => 'collection',
                                    'className' => 'btn btn-light-secondary dropdown-toggle',
                                    'text' => '<i class="ti ti-download me-2"></i>',
                                    'buttons' => ['print', 'csv', 'excel'],
                                ],
                                ['extend' => 'reset', 'className' => 'btn btn-light-danger'],
                                ['extend' => 'reload', 'className' => 'btn btn-light-warning'],
                            ],
                            "drawCallback" => 'function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
                }'
        ]);
    }

    public function getColumns(): array {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('work_date')->title(__('Work Date')),
            Column::make('supplier_id')->title(__('Supplier')),
            Column::make('site_id')->title(__('Site')),
            Column::make('total_count')->title(__('Total Count')),
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    protected function filename(): string {
        return 'ManPower_' . date('YmdHis');
    }
}
