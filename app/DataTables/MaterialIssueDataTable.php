<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\MaterialIssue;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MaterialIssueDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'material-issues-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'row-checkbox';
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
        return 'material_issues';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\MaterialIssue::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $checkboxClass = $this->getCheckboxClass();
        
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (MaterialIssue $issue) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$issue->id.'">';
            })
            ->editColumn('issue_number', function (MaterialIssue $issue) {
                return optional($issue)->issue_number ?? '';
            })
            ->editColumn('issue_date', function (MaterialIssue $issue) {
                return \Carbon\Carbon::parse($issue->issue_date)->format('d-m-Y');
            })
            ->editColumn('site_id', function (MaterialIssue $issue) {
                return optional($issue->site)->name ?? '';
            })
            ->addColumn('issue_to', function (MaterialIssue $issue) {
                return $issue->issue_to_name;
            })
            ->addColumn('items_count', function (MaterialIssue $issue) {
                return $issue->items_count ?? 0;
            })
            ->addColumn('total_quantity', function (MaterialIssue $issue) {
                return DB::table('material_issue_items')
                    ->where('issue_id', $issue->id)
                    ->sum('quantity') ?? 0;
            })
            ->editColumn('status', function (MaterialIssue $issue) {
                $status = $issue->status;
                $map = [
                    'Completed' => ['label' => 'Completed', 'class' => 'bg-success'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('created_by', function (MaterialIssue $issue) {
                return optional($issue->creator)->name ?? '';
            })
            ->editColumn('created_at', function (MaterialIssue $issue) {
                return $issue->created_at->format('d-m-Y, h:i A');
            });

        // Add action column if user has any material-issue permission
        if (\Laratrust::hasPermission('material-issue show') || 
            \Laratrust::hasPermission('material-issue edit') || 
            \Laratrust::hasPermission('material-issue delete')) {
            $dataTable->addColumn('action', function (MaterialIssue $issue) {
                return view('material-issues.action', compact('issue'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'status', 'action']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(MaterialIssue $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['site', 'creator'])
            ->withCount('items')
            ->where('workspace_id', getActiveWorkSpace())
            ->when(getActiveProject(), function ($q) {
                $q->where('site_id', getActiveProject());
            });

        // Issue to type filter
        if (!empty($request->issue_to_type)) {
            $query->where('issue_to_type', $request->issue_to_type);
        }

        // Date range filter
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('issue_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('issue_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('issue_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('material-issues-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.start_date = $("#start_date").val();
                    d.end_date = $("#end_date").val();
                    d.issue_to_type = $("#issue_to_type_filter").val();
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
                // Reload table when filters change
                $('#start_date,#end_date,#issue_to_type_filter').change(function(){
                    $('#material-issues-table').DataTable().ajax.reload();
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

    /**
     * Get the dataTable columns definition.
     */
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
            Column::make('issue_number')->title(__('Issue Number'))->orderable(true)->searchable(true),
            Column::make('issue_date')->title(__('Date')),
            Column::make('site_id')->title(__('Site')),
            Column::computed('issue_to')->title(__('Issue To'))->orderable(false)->searchable(false),
            Column::computed('items_count')->title(__('Items'))->orderable(false)->searchable(false),
            Column::computed('total_quantity')->title(__('Total Qty'))->orderable(false)->searchable(false),
            Column::make('status')->title(__('Status')),
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
     * Get export columns.
     */
    protected function getExportColumns(): array
    {
        return [
            'id',
            'issue_number',
            'issue_date',
            'site_id',
            'issue_to',
            'items_count',
            'total_quantity',
            'status',
            'created_by',
            'created_at',
        ];
    }

    /**
     * Get export column labels.
     */
    protected function getExportColumnLabels(): array
    {
        return [
            'ID',
            'Issue Number',
            'Date',
            'Site',
            'Issue To',
            'Items',
            'Total Qty',
            'Status',
            'Created By',
            'Created At',
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'MaterialIssue_' . date('YmdHis');
    }
}
