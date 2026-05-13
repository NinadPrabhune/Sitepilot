<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\MaterialReturn;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MaterialReturnDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'material-returns-table';
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
        return 'material_returns';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\MaterialReturn::class;
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
            ->addColumn('checkbox', function (MaterialReturn $return) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$return->id.'">';
            })
            ->editColumn('return_number', function (MaterialReturn $return) {
                return optional($return)->return_number ?? '';
            })
            ->editColumn('return_date', function (MaterialReturn $return) {
                return \Carbon\Carbon::parse($return->return_date)->format('d-m-Y');
            })
            ->editColumn('site_id', function (MaterialReturn $return) {
                return optional($return->site)->name ?? '';
            })
            ->addColumn('return_from', function (MaterialReturn $return) {
                return optional($return->issue)->issue_number ?? 'N/A';
            })
            ->addColumn('items_count', function (MaterialReturn $return) {
                return $return->items_count ?? 0;
            })
            ->addColumn('total_quantity', function (MaterialReturn $return) {
                return DB::table('material_return_items')
                    ->where('return_id', $return->id)
                    ->sum('quantity') ?? 0;
            })
            ->editColumn('status', function (MaterialReturn $return) {
                $status = $return->status;
                $map = [
                    'Completed' => ['label' => 'Completed', 'class' => 'bg-success'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('created_by', function (MaterialReturn $return) {
                return optional($return->creator)->name ?? '';
            })
            ->editColumn('created_at', function (MaterialReturn $return) {
                return $return->created_at->format('d-m-Y, h:i A');
            });

        // Add action column if user has any material-return permission
        if (\Laratrust::hasPermission('material-return show') || 
            \Laratrust::hasPermission('material-return edit') || 
            \Laratrust::hasPermission('material-return delete')) {
            $dataTable->addColumn('action', function (MaterialReturn $return) {
                return view('material-returns.action', compact('return'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'status', 'action']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(MaterialReturn $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['site', 'creator', 'issue'])
            ->withCount('items')
            ->where('workspace_id', getActiveWorkSpace())
            ->when(getActiveProject(), function ($q) {
                $q->where('site_id', getActiveProject());
            });

        // Issue filter
        if (!empty($request->issue_id)) {
            $query->where('issue_id', $request->issue_id);
        }

        // Date range filter
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('return_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('return_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('return_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('material-returns-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.start_date = $("#start_date").val();
                    d.end_date = $("#end_date").val();
                    d.issue_id = $("#issue_filter").val();
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
                $('#start_date,#end_date,#issue_filter').change(function(){
                    $('#material-returns-table').DataTable().ajax.reload();
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
            Column::make('return_number')->title(__('Return Number'))->orderable(true)->searchable(true),
            Column::make('return_date')->title(__('Date')),
            Column::make('site_id')->title(__('Site')),
            Column::computed('return_from')->title(__('Return From'))->orderable(false)->searchable(false),
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
            'return_number',
            'return_date',
            'site_id',
            'return_from',
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
            'Return Number',
            'Date',
            'Site',
            'Return From',
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
        return 'MaterialReturn_' . date('YmdHis');
    }
}
