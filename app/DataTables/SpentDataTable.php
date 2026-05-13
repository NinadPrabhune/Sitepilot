<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Spent;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
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

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $checkboxClass = $this->getCheckboxClass();
        
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Spent $spent) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$spent->id.'">';
            })
            ->editColumn('name', function (Spent $spent) {
                return $spent->name ?? '';
            })
            ->editColumn('ledger_name', function (Spent $spent) {
                return optional($spent->spentLedger)->name ?? '';
            })
            ->editColumn('amount', function (Spent $spent) {
                return '₹' . format_indian_currency($spent->amount);
            })
            ->editColumn('project_id', function (Spent $spent) {
                return optional($spent->project)->name ?? '';
            })
            ->editColumn('created_by', function (Spent $spent) {
                return optional($spent->createdBy)->name ?? '';
            })
            ->editColumn('created_at', function (Spent $spent) {
                return $spent->created_at->format('d-m-Y, h:i A');
            });

        if (\Laratrust::hasPermission('spent edit') || \Laratrust::hasPermission('spent delete')) {
            $dataTable->addColumn('action', function (Spent $spent) {
                return view('spent.action', compact('spent'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'action']);
    }

    public function query(Spent $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['spentLedger', 'project', 'createdBy'])
            ->leftJoin('spent_ledgers', 'spents.spent_ledger_id', '=', 'spent_ledgers.id')
            ->select('spents.*', 'spent_ledgers.name as ledger_name')
            ->where('spents.workspace_id', getActiveWorkSpace())
            ->when(getActiveProject(), function ($q) {
                $q->where('spents.project_id', getActiveProject());
            });

        if (!empty($request->project_id)) {
            $query->where('spents.project_id', $request->project_id);
        }

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('spents.created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        } elseif (!empty($request->start_date)) {
            $query->where('spents.created_at', '>=', $request->start_date . ' 00:00:00');
        } elseif (!empty($request->end_date)) {
            $query->where('spents.created_at', '<=', $request->end_date . ' 23:59:59');
        }

        return $query;
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
