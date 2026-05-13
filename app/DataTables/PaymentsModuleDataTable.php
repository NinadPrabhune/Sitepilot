<?php

namespace App\DataTables;

use App\Models\PaymentsModule;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentsModuleDataTable extends DataTable {

    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = [];

        $dataTable = (new EloquentDataTable($query))
                ->addIndexColumn()
                ->editColumn('supplier_id', fn(PaymentsModule $model) => optional($model->supplier)->name ?? '')
                ->editColumn('purchase_order_id', fn(PaymentsModule $model) => optional($model->purchaseOrder)->po_number ?? '')
                ->editColumn('site_id', fn(PaymentsModule $model) => optional($model->site)->name ?? '')
                ->editColumn('amount', fn(PaymentsModule $model) => number_format($model->amount, 2))
                ->editColumn('payment_date', fn(PaymentsModule $model) => $model->payment_date ? \Carbon\Carbon::parse($model->payment_date)->format('Y-m-d') : '')
                ->editColumn('payment_type', fn(PaymentsModule $model) => ucfirst($model->payment_type))
                 // ✅ Add Created By
                ->editColumn('created_by', function (PaymentsModule $model) {
                    return optional($model->creator)->name ?? '';
                })
                // ✅ Add Created At with clear date/time format
                ->editColumn('created_at', function (PaymentsModule $model) {
                    return $model->created_at->format('d M Y, h:i A');
                });

        // Action column
        $dataTable->addColumn('action', fn(PaymentsModule $paymentsModule) => view('payments-module.action', compact('paymentsModule')));
        $rowColumn[] = 'action';

        return $dataTable->rawColumns($rowColumn);
    }

    public function query(PaymentsModule $model): QueryBuilder {
        $request = request();

        $query = $model->newQuery()
                ->with(['supplier', 'purchaseOrder', 'site','creator'])
                ->where('workspace_id', getActiveWorkSpace())
                ->when(getActiveProject(), fn($q) => $q->where('site_id', getActiveProject()));

        // ✅ Default: current month payments if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('payment_date', \Carbon\Carbon::now()->month)
                    ->whereYear('payment_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('payment_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('payment_date', '<=', $request->end_date);
        }

        return $query;
    }

    public function html(): HtmlBuilder {
        return $this->builder()
                        ->setTableId('payments-module-table')
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
                    $('#payments-module-table').DataTable().draw();
                });

                // Clear filter
                $('body').on('click', '#clearfilter', function() {
                    $('input[name=start_date]').val('');
                    $('input[name=end_date]').val('');
                    $('#payments-module-table').DataTable().draw();
                });

                var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');
                searchInput.removeClass('form-control form-control-sm').addClass('dataTable-input');
                var select = $(table.api().table().container()).find('.dataTables_length select')
                    .removeClass('custom-select custom-select-sm form-control form-control-sm')
                    .addClass('dataTable-selector');
            }")
                        ->parameters([
                            "dom" => " <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>> <'dataTable-container'<'col-sm-12'tr>> <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                            'buttons' => $this->buttonsConfig(),
                            "drawCallback" => 'function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
                }'
        ]);
    }

    protected function buttonsConfig(): array {
        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2"></i>',
            'buttons' => [
                ['extend' => 'print', 'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'), 'className' => 'btn btn-light text-primary dropdown-item'],
                ['extend' => 'csv', 'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'), 'className' => 'btn btn-light text-primary dropdown-item'],
                ['extend' => 'excel', 'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'), 'className' => 'btn btn-light text-primary dropdown-item'],
            ],
        ];

        return array_merge([$exportButtonConfig], [
            ['extend' => 'reset', 'className' => 'btn btn-light-danger'],
            ['extend' => 'reload', 'className' => 'btn btn-light-warning'],
        ]);
    }

    public function getColumns(): array {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('payment_number')->title(__('Payment Number')),
            Column::make('supplier_id')->title(__('Supplier')),
            Column::make('purchase_order_id')->title(__('Purchase Order')),
            Column::make('site_id')->title(__('Site')),
            Column::make('amount')->title(__('Amount')),
            Column::make('payment_date')->title(__('Payment Date')),
            Column::make('payment_type')->title(__('Type')),
//            Column::make('mode')->title(__('Mode')),
//            Column::make('reference_number')->title(__('Reference')),
             Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    protected function filename(): string {
        return 'PaymentsModule_' . date('YmdHis');
    }
}
