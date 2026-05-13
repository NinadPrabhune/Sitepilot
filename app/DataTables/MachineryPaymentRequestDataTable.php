<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MachineryPaymentRequestDataTable extends DataTable
{
    use SelectableExportTrait;

    protected function getTableId(): string
    {
        return 'payment-requests-table';
    }

    protected function getCheckboxClass(): string
    {
        return 'row-checkbox';
    }

    protected function getExportRouteName(): string
    {
        return 'export.selected';
    }

    protected function getExportFilePrefix(): string
    {
        return 'payment-requests';
    }

    protected function getModelClass(): string
    {
        return MachineryPaymentRequest::class;
    }

    public function dataTable(QueryBuilder $query)
    {
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (MachineryPaymentRequest $request) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $request->id . '">';
            })
            ->editColumn('machinery_id', function (MachineryPaymentRequest $request) {
                return $request->machinery->name ?? 'N/A';
            })
            ->addColumn('period', function (MachineryPaymentRequest $request) {
                return \Carbon\Carbon::parse($request->period_start)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($request->period_end)->format('d M Y');
            })
            ->editColumn('net_payable', function (MachineryPaymentRequest $request) {
                return '<div class="text-end">' . number_format($request->net_payable, 2) . '</div>';
            })
            ->editColumn('status', function (MachineryPaymentRequest $request) {
                $status = $request->status;
                $map = [
                    'draft' => ['label' => 'Draft', 'class' => 'bg-secondary'],
                    'submitted' => ['label' => 'Submitted', 'class' => 'bg-info'],
                    'verified' => ['label' => 'Verified', 'class' => 'bg-warning'],
                    'approved' => ['label' => 'Approved', 'class' => 'bg-success'],
                    'locked' => ['label' => 'Locked', 'class' => 'bg-primary'],
                    'paid' => ['label' => 'Paid', 'class' => 'bg-success'],
                    'rejected' => ['label' => 'Rejected', 'class' => 'bg-danger'],
                    'hold' => ['label' => 'Hold', 'class' => 'bg-warning']
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge ' . $class . '">' . $label . '</span>';
            })
            ->editColumn('requested_by_name', function (MachineryPaymentRequest $request) {
                return $request->requested_by_name ?? 'N/A';
            })
            ->addColumn('payment_status', function (MachineryPaymentRequest $request) {
                $totalPosted = $request->payments()->sum('amount');
                $remainingBalance = $request->net_payable - $totalPosted;
                
                if ($totalPosted <= 0) {
                    $status = '<span class="badge bg-secondary">Unpaid</span>';
                } elseif ($remainingBalance <= 0) {
                    $status = '<span class="badge bg-success">Fully Paid</span>';
                } else {
                    $status = '<span class="badge bg-warning">Partially Paid</span>';
                }
                
                $details = '<small class="text-muted d-block">Paid: ' . number_format($totalPosted, 2) . '<br>Balance: ' . number_format($remainingBalance, 2) . '</small>';
                
                return $status . $details;
            })
            ->editColumn('created_at', function (MachineryPaymentRequest $request) {
                return $request->created_at->format('d M Y') . '<br><small class="text-muted">' . $request->created_at->format('h:i A') . '</small>';
            });

        $dataTable->addColumn('action', function (MachineryPaymentRequest $request) {
            if (\Laratrust::hasPermission('machinery-payment manage')) {
                return view('machinery-payment.action', compact('request'))->render();
            }
            return '';
        });

        return $dataTable->rawColumns(['checkbox', 'net_payable', 'status', 'payment_status', 'action', 'created_at']);
    }

    public function query(MachineryPaymentRequest $model): QueryBuilder
    {
        return $model->newQuery()->with(['machinery', 'supplier', 'period', 'payments']);
    }

    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->select(['selector' => 'td:first-child .' . $this->getCheckboxClass()])
            ->ajax([
                'data' => 'function(d) {
                    d.status = $("select[name=status_filter]").val();
                    d.machinery = $("select[name=machinery_filter]").val();
                    d.start_date = $("input[name=start_date]").val();
                    d.end_date = $("input[name=end_date]").val();
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
                "    $('#payment-requests-table').DataTable().draw();\n" .
                "});\n\n" .
                "$('body').on('click', '#clearfilter', function() {\n" .
                "    $('select[name=status_filter]').val('');\n" .
                "    $('select[name=machinery_filter]').val('');\n" .
                "    $('input[name=start_date]').val('');\n" .
                "    $('input[name=end_date]').val('');\n" .
                "    $('#payment-requests-table').DataTable().draw();\n" .
                "});"
            ));

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
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }'
        ]);

        return $dataTable;
    }

    public function getColumns(): array
    {
        $checkboxClass = $this->getCheckboxClass();
        
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('machinery_id')->title(__('Machinery'))->orderable(false)->searchable(true),
            Column::computed('period')->title(__('Period'))->orderable(false)->searchable(false),
            Column::make('net_payable')->title(__('Net Payable'))->orderable(true),
            Column::make('status')->title(__('Status'))->orderable(false)->searchable(true),
            Column::computed('payment_status')->title(__('Payment Status'))->orderable(false)->searchable(false),
            Column::make('requested_by_name')->title(__('Created By'))->orderable(false)->searchable(true),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
        ];
    }

    protected function filename(): string
    {
        return 'PaymentRequests_' . date('YmdHis');
    }
}
