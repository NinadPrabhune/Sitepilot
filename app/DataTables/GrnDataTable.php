<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Grn;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class GrnDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'grn-table';
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
        return 'grn';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Grn::class;
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
            ->addColumn('checkbox', function (Grn $grn) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$grn->id.'">';
            })
            ->editColumn('grn_number', function (Grn $grn) {
                return '<a href="'.route('grn.show', $grn->id).'" data-bs-toggle="tooltip" data-bs-original-title="'.__('View GRN').'">'.($grn->grn_number ?? '').'</a>';
            })
            ->editColumn('grn_date', function (Grn $grn) {
                return \Carbon\Carbon::parse($grn->grn_date)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($grn->created_at)->format('h:i A') . '</small>';
            })
            ->editColumn('po_id', function (Grn $grn) {
                return optional($grn->purchaseOrder)->po_number ?? '';
            })
            ->editColumn('grn_type', function (Grn $grn) {
                if ($grn->grn_type === 'direct') {
                    return '<span class="badge bg-success">' . __('Direct GRN') . '</span>';
                }
                return '<span class="badge bg-primary">' . __('Against PO') . '</span>';
            })
            ->editColumn('supplier_id', function (Grn $grn) {
                return optional($grn->supplier)->name ?? '';
            })
            ->editColumn('site_id', function (Grn $grn) {
                return optional($grn->site)->name ?? '';
            })
            // ->editColumn('received_by', function (Grn $grn) {
            //     return optional($grn)->received_by ?? '';
            // })
            ->editColumn('status', function (Grn $grn) {
                $status = $grn->status;
                $map = [
                    'Completed' => ['label' => 'Completed', 'class' => 'bg-success'],
                    'Partial' => ['label' => 'Partial', 'class' => 'bg-info'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('created_by', function (Grn $grn) {
                return optional($grn->creator)->name ?? '';
            })
            ->editColumn('created_at', function (Grn $grn) {
                return \Carbon\Carbon::parse($grn->created_at)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($grn->created_at)->format('h:i A') . '</small>';
            });

        // Add action column if user has any grn permission
        if (\Laratrust::hasPermission('grn show') || 
            \Laratrust::hasPermission('grn edit') || 
            \Laratrust::hasPermission('grn delete')) {
            $dataTable->addColumn('action', function (Grn $grn) {
                return view('grn.action', compact('grn'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'grn_number', 'grn_date', 'grn_type', 'status', 'created_at', 'action']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Grn $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['purchaseOrder', 'supplier', 'site', 'creator'])
            ->when(getActiveWorkSpace() > 0, function ($q) {
                $q->where('workspace_id', getActiveWorkSpace());
            })
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
            $query->whereBetween('grn_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('grn_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('grn_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('grn-table')
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
                    $('#grn-table').DataTable().ajax.reload();
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
            Column::make('grn_number')->title(__('GRN Number'))->orderable(true)->searchable(true),
            Column::make('grn_type')->title(__('GRN Type'))->orderable(true)->searchable(true),
            Column::make('grn_date')->title(__('GRN Date')),
            Column::make('po_id')->title(__('PO Number')),
            Column::make('supplier_id')->title(__('Supplier')),
            Column::make('site_id')->title(__('Site')),
            // Column::make('received_by')->title(__('Received By')),
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
            'grn_number',
            'grn_type',
            'grn_date',
            'po_id',
            'supplier_id',
            'site_id',
            'received_by',
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
            'GRN Number',
            'GRN Type',
            'GRN Date',
            'PO Number',
            'Supplier',
            'Site',
            'Received By',
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
        return 'Grn_' . date('YmdHis');
    }
}
