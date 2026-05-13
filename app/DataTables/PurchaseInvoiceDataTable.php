<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PurchaseInvoiceDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'purchase-invoice-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'purchase-invoice-checkbox';
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
        return 'purchase_invoices';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\PurchaseInvoice::class;
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
            ->addColumn('checkbox', function (PurchaseInvoice $invoice) use ($checkboxClass) {
                return '<input type="checkbox" class="'.$checkboxClass.' form-check-input" value="'.$invoice->id.'">';
            })
            ->editColumn('invoice_number', function (PurchaseInvoice $invoice) {
                return '<a href="'.route('purchase-invoice.show', $invoice->id).'" data-bs-toggle="tooltip" data-bs-original-title="'.__('View Invoice').'">'.($invoice->invoice_number ?? '').'</a>';
            })
            ->editColumn('invoice_file', function (PurchaseInvoice $invoice) {
                if ($invoice->invoice_file) {
                    $filePath = ltrim($invoice->invoice_file, '/');
                    $url = asset('storage/' . $filePath);
                    return '<a href="' . $url . '" target="_blank">Download</a>';
                }
                return 'N/A';
            })
            ->editColumn('supplier_id', function (PurchaseInvoice $invoice) {
                return optional($invoice->supplier)->name ?? '';
            })
            ->editColumn('invoice_type', function (PurchaseInvoice $invoice) {
                $type = $invoice->invoice_type;

                $map = [
                    'minor_misc_service' => ['label' => 'Minor/Misc Service', 'class' => 'bg-warning text-dark'],
                    'general_po' => ['label' => 'General PO', 'class' => 'bg-primary'],
                ];

                $label = $map[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
                $class = $map[$type]['class'] ?? 'bg-secondary';

                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->editColumn('site_id', function (PurchaseInvoice $invoice) {
                return optional($invoice->site)->name ?? '';
            })
            ->editColumn('created_by', function (PurchaseInvoice $invoice) {
                return optional($invoice->creator)->name ?? '';
            })

            ->editColumn('total_amount', function (PurchaseInvoice $invoice) {
                return '<div class="text-end">' . currency_format_with_sym_indian($invoice->total_amount) . '</div>';
            })
            ->editColumn('created_at', function (PurchaseInvoice $invoice) {
                return \Carbon\Carbon::parse($invoice->created_at)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($invoice->created_at)->format('h:i A') . '</small>';
            })
            ->editColumn('invoice_date', function (PurchaseInvoice $invoice) {
                return \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') . '<br><small class="text-muted">' . \Carbon\Carbon::parse($invoice->created_at)->format('h:i A') . '</small>';
            });

        if (\Laratrust::hasPermission('purchase-invoices show') || 
            \Laratrust::hasPermission('purchase-invoices edit') || 
            \Laratrust::hasPermission('purchase-invoices delete') || 
            \Laratrust::hasPermission('purchase-invoices payment') || 
            \Laratrust::hasPermission('manage-payment create')) {
             $dataTable->addColumn('action', function (PurchaseInvoice $invoice) {
                return view('purchase-invoice.action', compact('invoice'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'invoice_number', 'invoice_file', 'total_amount', 'invoice_date', 'created_at', 'action', 'invoice_type']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(PurchaseInvoice $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['supplier', 'site','creator'])
            ->where('workspace_id', getActiveWorkSpace())
            ->when(getActiveProject(), function ($q) {
                $q->where('site_id', getActiveProject());
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // ✅ Supplier filter
        if (!empty($request->supplier_id)) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // ✅ Default: current month invoices if no filter applied
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('invoice_date', \Carbon\Carbon::now()->month)
                  ->whereYear('invoice_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Inclusive overlap filtering when filters are applied
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('invoice_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('invoice_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('invoice_date', '<=', $request->end_date);
        }

        return $query;
    }



        /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('purchase-invoice-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    var start_date = $("input[name=start_date]").val();
                    d.start_date = start_date;

                    var end_date = $("input[name=end_date]").val();
                    d.end_date = end_date;
                    
                    var supplier_id = $("#supplier_filter").val();
                    d.supplier_id = supplier_id;
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
                // Apply filter
                $('body').on('click', '#applyfilter', function() {
                    if (!$('input[name=start_date]').val() && !$('input[name=end_date]').val()) {
                        toastrs('Error!', 'Please select at least one filter', 'error');
                        return;
                    }
                    $('#purchase-invoice-table').DataTable().draw();
                });

                // Clear filter
                $('body').on('click', '#clearfilter', function() {
                    $('input[name=start_date]').val('');
                    $('input[name=end_date]').val('');
                    $('#supplier_filter').val('');
                    $('#purchase-invoice-table').DataTable().draw();
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
        
        // Define columns manually to have checkbox at first position
        $columns = [
            // Checkbox at first position
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            
            // No/Row Index column
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            
            // ID column (hidden in UI, but available for export)
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            
            // Data columns
            Column::make('invoice_number')->title(__('Invoice No'))->orderable(true)->searchable(true),
            Column::make('invoice_date')->title(__('Invoice Date')),
            Column::make('invoice_type')->title(__('Invoice Type')),
            Column::make('supplier_id')->title(__('Supplier')),
            Column::make('site_id')->title(__('Site')),
            Column::make('total_amount')->title(__('Total Amount'))->className('text-end'),
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::make('invoice_file')->title(__('Invoice File'))->orderable(false)->searchable(false),
        ];

        // Add action column only if user has relevant permissions (matching dataTable logic)
        if (\Laratrust::hasPermission('purchase-invoices show') || 
            \Laratrust::hasPermission('purchase-invoices edit') || 
            \Laratrust::hasPermission('purchase-invoices delete') || 
            \Laratrust::hasPermission('purchase-invoices payment') || 
            \Laratrust::hasPermission('manage-payment create')) {
            $columns[] = Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60);
        }

        return $columns;
    }

    /**
     * Get export columns - override trait to include limited columns in export.
     * Returns indexed array of column names (without labels).
     */
    protected function getExportColumns(): array
    {
        return [
            'id',
            'invoice_number',
            'invoice_date',
            'invoice_type',
            'supplier_id',
            'site_id',
            'created_by',
            'created_at',
        ];
    }

    /**
     * Get export column labels - returns the labels for export columns.
     */
    protected function getExportColumnLabels(): array
    {
        return [
            'ID',
            'Invoice Number',
            'Invoice Date',
            'Invoice Type',
            'Supplier',
            'Site',
            'Created By',
            'Created At',
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'PurchaseInvoice_' . date('YmdHis');
    }
}
