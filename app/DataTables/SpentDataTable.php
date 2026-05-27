<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Spent;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SpentDataTable extends DataTable
{
    use SelectableExportTrait;

    protected function getTableId(): string
    {
        return 'spents-table';
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
        return 'spents';
    }

    protected function getModelClass(): string
    {
        return \App\Models\Spent::class;
    }

    public function dataTable(QueryBuilder $query): QueryDataTable
    {
        $checkboxClass = $this->getCheckboxClass();

        $dataTable = (new QueryDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function ($row) use ($checkboxClass) {
                if ($row->spent_type === 'manual') {
                    return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$row->id.'">';
                }
                return '';
            })
            ->editColumn('name', function ($row) {
                if ($row->spent_type === 'invoice') {
                    return '<a href="' . route('purchase-invoice.show', $row->id) . '" target="_blank">' . $row->name . '</a>';
                } elseif ($row->spent_type === 'machinery') {
                    return '<a href="' . route('machinery-payment.show', $row->id) . '" target="_blank">' . $row->name . '</a>';
                }
                return $row->name ?? '';
            })
            ->editColumn('ledger_name', function ($row) {
                return $row->ledger_name ?? '';
            })
            ->editColumn('amount', function ($row) {
                return currency_format_with_sym_indian($row->amount);
            })
            ->editColumn('project_id', function ($row) {
                return $row->project_name ?? '';
            })
            ->editColumn('created_by', function ($row) {
                return $row->creator_name ?? '';
            })
            ->editColumn('created_at', function ($row) {
                return \Carbon\Carbon::parse($row->created_at)->format('d-m-Y, h:i A');
            });

        $dataTable->addColumn('action', function ($row) {
            if ($row->spent_type === 'manual') {
                $spent = Spent::find($row->id);
                return view('spent.action', compact('spent'));
            }
            return '';
        });

        return $dataTable->rawColumns(['checkbox', 'action', 'name']);
    }

    public function query(Spent $model): QueryBuilder
    {
        $request = request();
        $workspaceId = getActiveWorkSpace();
        $projectId = !empty($request->project_id) ? $request->project_id : getActiveProject();
        $startDate = !empty($request->start_date) ? $request->start_date . ' 00:00:00' : null;
        $endDate = !empty($request->end_date) ? $request->end_date . ' 23:59:59' : null;

        // 1. Manual Spents
        $spentQuery = \DB::table('spents')
            ->select([
                'spents.id',
                'spents.name',
                'spents.amount',
                'spents.project_id',
                'spents.workspace_id',
                'spents.created_at',
                \DB::raw("'manual' as spent_type"),
                'spent_ledgers.name as ledger_name',
                'projects.name as project_name',
                'users.name as creator_name'
            ])
            ->leftJoin('spent_ledgers', 'spents.spent_ledger_id', '=', 'spent_ledgers.id')
            ->leftJoin('projects', 'spents.project_id', '=', 'projects.id')
            ->leftJoin('users', 'spents.created_by', '=', 'users.id')
            ->where('spents.workspace_id', $workspaceId);

        if ($projectId) {
            $spentQuery->where('spents.project_id', $projectId);
        }
        if ($startDate) {
            $spentQuery->where('spents.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $spentQuery->where('spents.created_at', '<=', $endDate);
        }

        // 2. Approved Purchase Invoices
        $invoiceQuery = \DB::table('purchase_invoices')
            ->select([
                'purchase_invoices.id',
                \DB::raw("CONCAT('Invoice: ', purchase_invoices.invoice_number) as name"),
                'purchase_invoices.grand_total as amount',
                'purchase_invoices.site_id as project_id',
                'purchase_invoices.workspace_id',
                'purchase_invoices.created_at',
                \DB::raw("'invoice' as spent_type"),
                \DB::raw("'Purchase Invoice' as ledger_name"),
                'projects.name as project_name',
                'users.name as creator_name'
            ])
            ->leftJoin('projects', 'purchase_invoices.site_id', '=', 'projects.id')
            ->leftJoin('users', 'purchase_invoices.created_by', '=', 'users.id')
            ->where('purchase_invoices.workspace_id', $workspaceId)
            ->where('purchase_invoices.status', 'Approved');

        if ($projectId) {
            $invoiceQuery->where('purchase_invoices.site_id', $projectId);
        }
        if ($startDate) {
            $invoiceQuery->where('purchase_invoices.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $invoiceQuery->where('purchase_invoices.created_at', '<=', $endDate);
        }

        // 3. Approved Machinery Payment Requests
        $machineryQuery = \DB::table('machinery_payment_requests')
            ->select([
                'machinery_payment_requests.id',
                \DB::raw("CONCAT('Machinery: ', machineries.name) as name"),
                'machinery_payment_requests.net_payable as amount',
                'machineries.site_id as project_id',
                'machinery_payment_requests.workspace_id',
                'machinery_payment_requests.created_at',
                \DB::raw("'machinery' as spent_type"),
                \DB::raw("'Machinery Rental' as ledger_name"),
                'projects.name as project_name',
                'users.name as creator_name'
            ])
            ->join('machineries', 'machinery_payment_requests.machinery_id', '=', 'machineries.id')
            ->leftJoin('projects', 'machineries.site_id', '=', 'projects.id')
            ->leftJoin('users', 'machinery_payment_requests.requested_by', '=', 'users.id')
            ->where('machinery_payment_requests.workspace_id', $workspaceId)
            ->whereIn('machinery_payment_requests.status', ['approved', 'locked', 'paid']);

        if ($projectId) {
            $machineryQuery->where('machineries.site_id', $projectId);
        }
        if ($startDate) {
            $machineryQuery->where('machinery_payment_requests.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $machineryQuery->where('machinery_payment_requests.created_at', '<=', $endDate);
        }

        // Combine using Union
        $combinedQuery = $spentQuery->unionAll($invoiceQuery)->unionAll($machineryQuery);

        // Convert to a base query that DataTables can use for sorting/filtering
        return \DB::table(\DB::raw("({$combinedQuery->toSql()}) as combined_spents"))
            ->mergeBindings($combinedQuery);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('spents-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.start_date = $("#start_date").val();
                    d.end_date = $("#end_date").val();
                    d.project_id = $("#project_filter").val();
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
            ->initComplete($this->getCombinedInitScript("
                $('#start_date,#end_date,#project_filter').change(function(){
                    $('#spents-table').DataTable().ajax.reload();
                });
            "))
            ->parameters([
                "dom" => "
                    <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>
                    <'dataTable-container'<'col-sm-12'tr>>
                    <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                'buttons' => $this->getExportButtonConfig(),
                "drawCallback" => 'function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }'
            ]);
    }

    public function getColumns(): array
    {
        $checkboxClass = $this->getCheckboxClass();

        return [
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::make('name')->title(__('Name'))->orderable(true)->searchable(true),
            Column::make('ledger_name')->title(__('Ledger Name'))->orderable(false)->searchable(false),
            Column::make('amount')->title(__('Amount'))->orderable(true)->searchable(false),
            Column::make('project_id')->title(__('Project'))->orderable(true)->searchable(false),
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->title(__('Action')),
        ];
    }

    /**
     * Get export columns configuration.
     * Defines which columns to export and their aliases.
     */
    protected function getExportColumnsConfig(): array
    {
        $columns = [];

        $columns[] = ['field' => 'id', 'alias' => 'id', 'title' => __('ID')];
        $columns[] = ['field' => 'name', 'alias' => 'name', 'title' => __('Name')];
        $columns[] = ['field' => 'spent_ledger_id', 'alias' => 'ledger_name', 'title' => __('Ledger Name')];
        $columns[] = ['field' => 'amount', 'alias' => 'amount', 'title' => __('Amount')];
        $columns[] = ['field' => 'project_id', 'alias' => 'project_name', 'title' => __('Project')];
        $columns[] = ['field' => 'created_by', 'alias' => 'created_by_name', 'title' => __('Created By')];
        $columns[] = ['field' => 'created_at', 'alias' => 'created_at', 'title' => __('Created At')];

        return $columns;
    }

    /**
     * Get export column labels for headings.
     */
    protected function getExportColumnLabels(): array
    {
        $labels = [];

        $labels['id'] = __('ID');
        $labels['name'] = __('Name');
        $labels['ledger_name'] = __('Ledger Name');
        $labels['amount'] = __('Amount');
        $labels['project_name'] = __('Project');
        $labels['created_by_name'] = __('Created By');
        $labels['created_at'] = __('Created At');

        return $labels;
    }

    protected function filename(): string
    {
        return 'Spent_' . date('YmdHis');
    }
}
