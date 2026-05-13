<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PurchaseOrderDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'purchase-orders-table';
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
        return 'purchase_orders';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\PurchaseOrder::class;
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
            ->addColumn('checkbox', function (PurchaseOrder $po) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$po->id.'">';
            })
            ->editColumn('po_number', function (PurchaseOrder $po) {
                return '<a href="'.route('purchase-order.show', $po->id).'" data-bs-toggle="tooltip" data-bs-original-title="'.__('View PO').'">'.($po->po_number ?? '').'</a>';
            })
            ->editColumn('po_date', function (PurchaseOrder $po) {
                return \Carbon\Carbon::parse($po->po_date)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($po->created_at)->format('h:i A') . '</small>';
            })
            ->editColumn('indent_id', function (PurchaseOrder $po) {
                return optional($po->indent)->indent_number ?? '';
            })
            ->editColumn('supplier_id', function (PurchaseOrder $po) {
                return optional($po->supplier)->name ?? '';
            })
            ->editColumn('site_id', function (PurchaseOrder $po) {
                return optional($po->site)->name ?? '';
            })
            ->editColumn('grand_total', function (PurchaseOrder $po) {
                return '<div class="text-end">' . currency_format_with_sym_indian($po->grand_total) . '</div>';
            })
            ->editColumn('status', function (PurchaseOrder $po) {
                $status = $po->display_status;
                $map = [
                    'Draft' => ['label' => 'Draft', 'class' => 'bg-secondary'],
                    'Approved' => ['label' => 'Approved', 'class' => 'bg-primary'],
                    'Partial Received' => ['label' => 'Partial Received', 'class' => 'bg-warning'],
                    'Completed' => ['label' => 'Completed', 'class' => 'bg-success'],
                    'Rejected' => ['label' => 'Rejected', 'class' => 'bg-danger'],
                    'Flagged - Corrected' => ['label' => 'Flagged - Corrected', 'class' => 'bg-info'],
                    'Flagged' => ['label' => 'Flagged', 'class' => 'bg-info'],
                    'Short Closed' => ['label' => 'Short Closed', 'class' => 'bg-dark'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('payment_flag', function (PurchaseOrder $po) {
                // Use invoicing_status instead of payment_flag
                $status = $po->invoiced_status ?? 'not_invoiced';
                $map = [
                    'not_invoiced' => ['label' => 'Not Invoiced', 'class' => 'bg-secondary'],
                    'partially_invoiced' => ['label' => 'Partially Invoiced', 'class' => 'bg-info'],
                    'fully_invoiced' => ['label' => 'Fully Invoiced', 'class' => 'bg-success'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('created_by', function (PurchaseOrder $po) {
                return optional($po->creator)->name ?? '';
            })
            ->editColumn('created_at', function (PurchaseOrder $po) {
                return \Carbon\Carbon::parse($po->created_at)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($po->created_at)->format('h:i A') . '</small>';
            })
            ->editColumn('payment_request_status', function (PurchaseOrder $po) {
                $latestRequest = $po->paymentRequests->first();
                if (!$latestRequest) {
                    return '<span class="badge bg-secondary">-</span>';
                }

                $status = $latestRequest->status;
                $map = [
                    'pending' => ['label' => 'Requested', 'class' => 'bg-warning'],
                    'approved' => ['label' => 'Approved', 'class' => 'bg-success'],
                    'partially_approved' => ['label' => 'Partially Approved', 'class' => 'bg-info'],
                    'rejected' => ['label' => 'Rejected', 'class' => 'bg-danger'],
                    'partially_paid' => ['label' => 'Partially Paid', 'class' => 'bg-info'],
                    'paid' => ['label' => 'Paid', 'class' => 'bg-success'],
                ];
                $label = $map[$status]['label'] ?? ucfirst($status);
                $class = $map[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge '.$class.' payment-request-status-btn cursor-pointer" 
                            data-po-id="'.$po->id.'" 
                            data-request-id="'.$latestRequest->id.'"
                            data-bs-toggle="tooltip" 
                            title="Click to view details">'.$label.'</span>';
            });

        // Add action column if user has any purchase order permission
        if (\Laratrust::hasPermission('purchase-order show') || 
            \Laratrust::hasPermission('purchase-order edit') || 
            \Laratrust::hasPermission('purchase-order delete')) {
            $dataTable->addColumn('action', function (PurchaseOrder $po) {
                return view('purchase-order.action', compact('po'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'po_number', 'po_date', 'grand_total', 'status', 'payment_flag', 'payment_request_status', 'created_at', 'action']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(PurchaseOrder $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['supplier', 'creator', 'indent', 'site'])
            ->with(['paymentRequests' => function($q) {
                $q->where('type', 'po_advance')->latest();
            }])
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
            $query->whereBetween('po_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('po_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('po_date', '<=', $request->end_date);
        }

        // Invoicing status filter (replaces payment_flag)
        if (!empty($request->invoicing_status)) {
            $query->where('invoiced_status', $request->invoicing_status);
        }

        // Legacy payment_flag filter for backward compatibility
        if (!empty($request->payment_flag)) {
            $query->where('payment_flag_deprecated', $request->payment_flag);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('purchase-orders-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.start_date = $("#start_date").val();
                    d.end_date = $("#end_date").val();
                    d.supplier_id = $("#supplier_filter").val();
                    d.invoicing_status = $("#invoicing_status_filter").val();
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
                $('#start_date,#end_date,#supplier_filter,#invoicing_status_filter').change(function(){
                    $('#purchase-orders-table').DataTable().ajax.reload();
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
            Column::make('po_number')->title(__('PO Number'))->orderable(true)->searchable(true),
            Column::make('po_date')->title(__('Date')),
            Column::make('indent_id')->title(__('Indent')),
            Column::make('supplier_id')->title(__('Supplier')),
            Column::make('site_id')->title(__('Site')),
            Column::make('grand_total')->title(__('Total Amount'))->className('text-end'),
            Column::make('status')->title(__('Status')),
            Column::make('payment_flag')->title(__('Invoicing Status')),
            Column::computed('payment_request_status')->title(__('Payment Request Status'))->orderable(false)->searchable(false),
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
            'po_number',
            'po_date',
            'indent_id',
            'supplier_id',
            'site_id',
            'grand_total',
            'status',
            'payment_flag', // Column name stays same but shows invoicing_status
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
            'PO Number',
            'Date',
            'Indent',
            'Supplier',
            'Site',
            'Total Amount',
            'Status',
            'Invoicing Status',
            'Created By',
            'Created At',
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'PurchaseOrder_' . date('YmdHis');
    }
}
