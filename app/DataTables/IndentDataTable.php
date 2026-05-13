<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Indent;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class IndentDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'indents-table';
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
        return 'indents';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Indent::class;
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
            ->orderColumn('No', 'id desc')
            ->addColumn('checkbox', function (Indent $indent) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$indent->id.'">';
            })
            ->editColumn('indent_number', function (Indent $indent) {
                return optional($indent)->indent_number ?? '';
            })
            ->editColumn('indent_date', function (Indent $indent) {
                return \Carbon\Carbon::parse($indent->indent_date)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($indent->created_at)->format('h:i A') . '</small>';
            })
            ->editColumn('site_id', function (Indent $indent) {
                return optional($indent->site)->name ?? '';
            })
            ->editColumn('total_amount', function (Indent $indent) {
                return '<div class="text-end">' . currency_format_with_sym_indian($indent->total_amount) . '</div>';
            })
            ->editColumn('status', function (Indent $indent) {
                $status = $indent->status;
                $map = [
                    'Open' => ['label' => 'Open', 'class' => 'bg-success'],
                    'Partially Closed' => ['label' => 'Partially Closed', 'class' => 'bg-warning'],
                    'Closed' => ['label' => 'Closed', 'class' => 'bg-danger'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('created_by', function (Indent $indent) {
                return optional($indent->creator)->name ?? '';
            })
            ->editColumn('created_at', function (Indent $indent) {
                return \Carbon\Carbon::parse($indent->created_at)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($indent->created_at)->format('h:i A') . '</small>';
            });

        // Add action column if user has any indent permission
        if (\Laratrust::hasPermission('indent show') || 
            \Laratrust::hasPermission('indent edit') || 
            \Laratrust::hasPermission('indent delete')) {
            $dataTable->addColumn('action', function (Indent $indent) {
                return view('indent.action', compact('indent'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'indent_date', 'total_amount', 'status', 'created_at', 'action']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Indent $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['supplier', 'creator', 'site'])
            ->where('workspace_id', getActiveWorkSpace())
            ->when(getActiveProject(), function ($q) {
                $q->where('site_id', getActiveProject());
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // Supplier filter
        if (!empty($request->supplier_id)) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Date range filter
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('indent_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('indent_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('indent_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('indents-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.start_date = $("#start_date").val();
                    d.end_date = $("#end_date").val();
                    d.supplier_id = $("#supplier_filter").val();
                }',
            ])
            ->orderBy(0, 'desc')
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
                $('#start_date,#end_date,#supplier_filter').change(function(){
                    $('#indents-table').DataTable().ajax.reload();
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
            Column::make('indent_number')->title(__('Indent Number'))->orderable(true)->searchable(true),
            Column::make('indent_date')->title(__('Date')),
            Column::make('site_id')->title(__('Site')),
            Column::make('total_amount')->title(__('Total Amount'))->className('text-end'),
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
            'indent_number',
            'indent_date',
            'site_id',
            'total_amount',
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
            'Indent Number',
            'Date',
            'Site',
            'Total Amount',
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
        return 'Indent_' . date('YmdHis');
    }
}
