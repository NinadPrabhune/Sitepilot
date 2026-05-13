<?php

namespace App\DataTables;

use App\Models\DailyConsumptionMaster;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class DailyConsumptionDataTable extends DataTable {

    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = ['consumption_file', 'site_id', 'consumption_date'];

        $dataTable = (new EloquentDataTable($query))
                ->addIndexColumn()
                ->editColumn('consumption_date', function (DailyConsumptionMaster $master) {
                    return \Carbon\Carbon::parse($master->consumption_date)->format('d-m-Y');
                })
                ->editColumn('consumption_file', function (DailyConsumptionMaster $master) {
                    if ($master->consumption_file) {
                        $url = asset('storage/' . ltrim($master->consumption_file, '/'));
                        return '<a href="' . $url . '" target="_blank">Download</a>';
                    }
                    return 'N/A';
                })
                ->editColumn('site_id', fn($master) => optional($master->site)->name ?? '')
                        
                ->editColumn('created_by', function (DailyConsumptionMaster $master) {
                    return optional($master->creator)->name ?? '';
                })
                ->editColumn('created_at', function (DailyConsumptionMaster $master) {
                    return $master->created_at->format('d-m-Y, h:i A');
                });
                $dataTable->addColumn('action', function (DailyConsumptionMaster $master) {
                    return view('daily-consumption.action', compact('master'));
                });
        $rowColumn[] = 'action';

        return $dataTable->rawColumns($rowColumn);
    }

    public function query(DailyConsumptionMaster $model): QueryBuilder {
        $request = request();

        $query = $model->with(['site','creator'])
                ->where('site_id', getActiveProject())
                ->where('status', '!=', '1');

        // ✅ Default: current month records if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('consumption_date', \Carbon\Carbon::now()->month)
                    ->whereYear('consumption_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('consumption_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('consumption_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('consumption_date', '<=', $request->end_date);
        }

        return $query;
    }

    public function html(): HtmlBuilder {
        $exportButtons = [
            ['extend' => 'print', 'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'), 'className' => 'btn btn-light text-primary dropdown-material'],
            ['extend' => 'csv', 'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'), 'className' => 'btn btn-light text-primary dropdown-material'],
            ['extend' => 'excel', 'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'), 'className' => 'btn btn-light text-primary dropdown-material'],
        ];

        return $this->builder()
                        ->setTableId('daily-consumption-table')
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
                            "paginate" => ["next" => '<i class="ti ti-chevron-right"></i>', "previous" => '<i class="ti ti-chevron-left"></i>'],
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
                    $('#daily-consumption-table').DataTable().draw();
                });

                // Clear filter
                $('body').on('click', '#clearfilter', function() {
                    $('input[name=start_date]').val('');
                    $('input[name=end_date]').val('');
                    $('#daily-consumption-table').DataTable().draw();
                });

                var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');
                searchInput.removeClass('form-control form-control-sm').addClass('dataTable-input');
                var select = $(table.api().table().container()).find('.dataTables_length select')
                    .removeClass('custom-select custom-select-sm form-control form-control-sm')
                    .addClass('dataTable-selector');
            }")
                        ->parameters([
                            "dom" => "<'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>" .
                            "<'dataTable-container'<'col-sm-12'tr>>" .
                            "<'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                            'buttons' => [
                                ['extend' => 'collection', 'className' => 'btn btn-light-secondary dropdown-toggle', 'text' => '<i class="ti ti-download me-2"></i>', 'buttons' => $exportButtons],
                                ['extend' => 'reset', 'className' => 'btn btn-light-danger'],
                                ['extend' => 'reload', 'className' => 'btn btn-light-warning'],
                            ],
                            "drawCallback" => "function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=tooltip]'));
                    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
                }"
        ]);
    }

    public function getColumns(): array {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('consumption_number')->title(__('Consumption No')),
            Column::make('consumption_date')->title(__('Consumption Date')),
            Column::make('consumption_type')->title(__('Consumption Type')),
            Column::make('site_id')->title(__('Site')),
            Column::make('consumption_file')->title(__('Consumption File'))->orderable(false)->searchable(false),
             Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    protected function filename(): string {
        return 'DailyConsumptionMaster_' . date('YmdHis');
    }
}
