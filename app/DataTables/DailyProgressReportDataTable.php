<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\DailyProgressReport;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class DailyProgressReportDataTable extends DataTable {
    use SelectableExportTrait;

    protected function getTableId(): string
    {
        return 'daily-progress-report-table';
    }

    protected function getCheckboxClass(): string
    {
        return 'daily-progress-checkbox';
    }

    protected function getExportRouteName(): string
    {
        return 'export.selected';
    }

    protected function getExportFilePrefix(): string
    {
        return 'daily_progress_reports';
    }

    protected function getModelClass(): string
    {
        return DailyProgressReport::class;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable {
        return (new EloquentDataTable($query))
                        ->addIndexColumn()
                        ->addColumn('checkbox', function (DailyProgressReport $item) {
                            return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $item->id . '">';
                        })
                        ->editColumn('date', fn(DailyProgressReport $item) => \Carbon\Carbon::parse($item->date)->format('d-m-Y'))
                        ->editColumn('machinery_id', fn(DailyProgressReport $item) => optional($item->machinery)->name ?? '-')
                        ->editColumn('site_id', fn(DailyProgressReport $item) => optional($item->site)->name ?? '')
                        ->addColumn('machine_hours', fn(DailyProgressReport $item) => $item->machine_hours ?? '-')
                        ->addColumn('action', fn(DailyProgressReport $item) => view('daily-progress-reports.action', compact('item')))
                        // ✅ Add Created By
                       ->editColumn('created_by', function (DailyProgressReport $item) {
                           return optional($item->creator)->name ?? '';
                       })
                       // ✅ Add Created At with clear date/time format
                       ->editColumn('created_at', function (DailyProgressReport $item) {
                           return $item->created_at->format('d M Y, h:i A');
                       })
                        ->rawColumns(['action', 'checkbox']);
    }

    public function query(DailyProgressReport $model): QueryBuilder {
        $request = request();

        $query = $model->with(['site', 'machinery','creator'])
                ->where('workspace_id', getActiveWorkSpace())
                ->where('site_id', getActiveProject());

        // ✅ Default: current month reports if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('date', \Carbon\Carbon::now()->month)
                    ->whereYear('date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('date', '<=', $request->end_date);
        }

        $this->handleSelectedIdsFilter($query);

        return $query;
    }

    public function html(): HtmlBuilder {
        $dataTable = $this->builder()
                        ->setTableId($this->getTableId())
                        ->columns($this->getColumns())
                        ->select(['selector' => 'td:first-child .' . $this->getCheckboxClass()])
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
                        ->initComplete($this->getCombinedInitScript(
                "var table = this;\n\n" .
                "$('body').on('click', '#applyfilter', function() {\n" .
                "    if (!$('input[name=start_date]').val() && !$('input[name=end_date]').val()) {\n" .
                "        toastrs('Error!', 'Please select at least one filter', 'error');\n" .
                "        return;\n" .
                "    }\n" .
                "    $('#daily-progress-report-table').DataTable().draw();\n" .
                "});\n\n" .
                "$('body').on('click', '#clearfilter', function() {\n" .
                "    $('input[name=start_date]').val('');\n" .
                "    $('input[name=end_date]').val('');\n" .
                "    $('#daily-progress-report-table').DataTable().draw();\n" .
                "});\n\n" .
                "var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');\n" .
                "searchInput.removeClass('form-control form-control-sm').addClass('dataTable-input');\n" .
                "var select = $(table.api().table().container()).find('.dataTables_length select')\n" .
                "    .removeClass('custom-select custom-select-sm form-control form-control-sm')\n" .
                "    .addClass('dataTable-selector');"
            ))
                        ->parameters([
                            "dom" => "
                    <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>
                    <'dataTable-container'<'col-sm-12'tr>>
                    <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                            'buttons' => $this->getExportButtonConfig(),
                            "select" => [
                                "style" => "multi",
                                "selector" => "td:first-child ." . $this->getCheckboxClass()
                            ],
                            "drawCallback" => 'function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }'
        ]);

        return $dataTable;
    }

    public function getColumns(): array {
        $checkboxClass = $this->getCheckboxClass();
        
        return [
            Column::make('id')->visible(false)->searchable(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-daily-progress-checkbox" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->data('DT_RowIndex')->name('DT_RowIndex')->title(__('No'))->orderable(false)->searchable(false),
            Column::make('date')->title(__('Date')),
            Column::make('machinery_id')->title(__('Machinery Name')),
            Column::make('machine_start_reading')->title(__('Start Reading')),
            Column::make('machine_end_reading')->title(__('End Reading')),
            Column::make('machine_hours')->title(__('Machine Hours')),
            Column::make('number_of_operators')->title(__('Operators')),
            Column::make('diesel_consumption')->title(__('Diesel (L)')),
            Column::make('site_id')->title(__('Site')),
             Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60)->title(__('Action')),
        ];
    }

    protected function filename(): string {
        return 'DailyProgressReport_' . date('YmdHis');
    }
}
