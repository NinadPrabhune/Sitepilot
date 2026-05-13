<?php

namespace App\DataTables;

use App\Models\MaterialTransfer;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MaterialTransferDataTable extends DataTable {

    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = ['record_file', 'from_site_id', 'to_site_id', 'total_amount', 'record_date'];

        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->editColumn('record_file', function (MaterialTransfer $transfer) {
                if ($transfer->record_file) {
                    $filePath = ltrim($transfer->record_file, '/');
                    $url = asset('storage/' . $filePath);
                    return '<a href="' . $url . '" target="_blank">Download</a>';
                }
                return 'N/A';
            })
            ->editColumn('from_site_id', fn(MaterialTransfer $transfer) => optional($transfer->fromSite)->name ?? '')
            ->editColumn('to_site_id', fn(MaterialTransfer $transfer) => optional($transfer->toSite)->name ?? '')
            ->editColumn('total_amount', fn(MaterialTransfer $transfer) => currency_format_with_sym($transfer->total_amount))
            ->editColumn('record_date', fn(MaterialTransfer $transfer) => \Carbon\Carbon::parse($transfer->record_date)->format('d-m-Y'))

            // ✅ Add Created By
            ->editColumn('created_by', function (MaterialTransfer $transfer) {
                return optional($transfer->creator)->name ?? '';
            })

            // ✅ Add Created At with clear date/time format
            ->editColumn('created_at', function (MaterialTransfer $transfer) {
                return $transfer->created_at->format('d M Y, h:i A');
            });

        $dataTable->addColumn('action', fn(MaterialTransfer $transfer) => view('material-transfer.action', compact('transfer')));

        $rowColumn[] = 'action';
        $rowColumn[] = 'created_by';
        $rowColumn[] = 'created_at';

        return $dataTable->rawColumns($rowColumn);
    }


    public function query(MaterialTransfer $model): QueryBuilder {
        $request = request();

        $query = $model->with(['fromSite', 'toSite'])
                ->where('from_site_id', getActiveProject())
                ->where('status', '!=', '1');

        // ✅ Default: current month transfers if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('record_date', \Carbon\Carbon::now()->month)
                    ->whereYear('record_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('record_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('record_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('record_date', '<=', $request->end_date);
        }

        return $query;
    }

    public function html(): HtmlBuilder {
        return $this->builder()
                        ->setTableId('material-transfer-table')
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
                    $('#material-transfer-table').DataTable().draw();
                });

                // Clear filter
                $('body').on('click', '#clearfilter', function() {
                    $('input[name=start_date]').val('');
                    $('input[name=end_date]').val('');
                    $('#material-transfer-table').DataTable().draw();
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
                    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
                }'
        ]);
    }

    public function getColumns(): array {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('record_number')->title(__('Record No')),
            Column::make('record_date')->title(__('Record Date')),
            Column::make('from_site_id')->title(__('From Site')),
            Column::make('to_site_id')->title(__('To Site')),
            Column::make('total_amount')->title(__('Total Amount')),
            Column::make('record_file')->title(__('Record File'))->orderable(false)->searchable(false),
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    protected function filename(): string {
        return 'MaterialTransfer_' . date('YmdHis');
    }
}
