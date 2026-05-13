<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\PaymentRequest;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentRequestDataTable extends DataTable
{
    use SelectableExportTrait;

    protected function getTableId(): string
    {
        return 'payment-request-table';
    }

    protected function getCheckboxClass(): string
    {
        return 'payment-request-checkbox';
    }

    protected function getExportRouteName(): string
    {
        return 'export.selected';
    }

    protected function getExportFilePrefix(): string
    {
        return 'payment_requests';
    }

    protected function getModelClass(): string
    {
        return PaymentRequest::class;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (PaymentRequest $pr) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $pr->id . '">';
            })
            ->editColumn('invoice_number', function (PaymentRequest $pr) {
                if ($pr->isPoAdvance()) {
                    $poNumber = $pr->po?->po_number ?? '-';
                    if ($pr->po && $poNumber !== '-') {
                        return '<a href="' . route('purchase-order.show', $pr->po->id) . '">' . $poNumber . '</a>';
                    }
                    return $poNumber;
                }
                $invoiceNumber = $pr->invoice?->invoice_number ?? '-';
                if ($pr->invoice && $invoiceNumber !== '-') {
                    return '<a href="' . route('purchase-invoice.show', $pr->invoice->id) . '">' . $invoiceNumber . '</a>';
                }
                return $invoiceNumber;
            })
            ->editColumn('supplier', function (PaymentRequest $pr) {
                if ($pr->isPoAdvance()) {
                    return $pr->po?->supplier?->name ?? '-';
                }
                return $pr->invoice?->supplier?->name ?? '-';
            })
            ->editColumn('type', function (PaymentRequest $pr) {
                $typeMap = [
                    'invoice_payment' => ['label' => 'Invoice Payment', 'class' => 'bg-primary'],
                    'po_advance' => ['label' => 'PO Advance', 'class' => 'bg-primary'],
                ];
                $type = $pr->type;
                $label = $typeMap[$type]['label'] ?? ucfirst($type);
                $class = $typeMap[$type]['class'] ?? 'bg-secondary';
                return '<span class="badge ' . $class . '">' . $label . '</span>';
            })
            ->editColumn('requested_amount', function (PaymentRequest $pr) {
                return currency_format_with_sym($pr->requested_amount);
            })
            ->editColumn('approved_amount', function (PaymentRequest $pr) {
                if ($pr->isPending()) {
                    return '<span class="text-muted">-</span>';
                }
                return currency_format_with_sym($pr->approved_amount ?? 0);
            })
            ->editColumn('status', function (PaymentRequest $pr) {
                $statusMap = [
                    'pending' => ['label' => 'Pending', 'class' => 'bg-warning text-dark'],
                    'approved' => ['label' => 'Approved', 'class' => 'bg-success'],
                    'partially_approved' => ['label' => 'Partial', 'class' => 'bg-info text-dark'],
                    'rejected' => ['label' => 'Rejected', 'class' => 'bg-danger'],
                    'paid' => ['label' => 'Paid', 'class' => 'bg-success'],
                    'partially_paid' => ['label' => 'Partial Paid', 'class' => 'bg-primary'],
                ];
                $status = $pr->status;
                $label = $statusMap[$status]['label'] ?? ucfirst($status);
                $class = $statusMap[$status]['class'] ?? 'bg-secondary';
                return '<span class="badge ' . $class . '">' . $label . '</span>';
            })
            ->editColumn('requested_by', function (PaymentRequest $pr) {
                return $pr->requestedBy?->name ?? '-';
            })
            ->editColumn('created_at', function (PaymentRequest $pr) {
                return $pr->created_at->format('d M Y') . '<br><small class="text-muted">' . $pr->created_at->format('h:i A') . '</small>';
            })
            ->editColumn('requested_date', function (PaymentRequest $pr) {
                if ($pr->payment_date) {
                    return $pr->payment_date->format('d M Y');
                }
                return '-';
            })
            ->editColumn('overdue', function (PaymentRequest $pr) {
                if (!$pr->payment_date || in_array($pr->status, ['paid', 'rejected'])) {
                    return '<span class="text-muted" data-sort="4">-</span>';
                }
                
                $now = now();
                $paymentDate = \Carbon\Carbon::parse($pr->payment_date);
                
                if ($now->gt($paymentDate)) {
                    $daysOverdue = $now->diffInDays($paymentDate);
                    return '<span class="badge bg-danger" data-sort="2">Overdue (' . $daysOverdue . ' days)</span>';
                }
                
                if ($now->isSameDay($paymentDate)) {
                    return '<span class="badge bg-warning text-dark" data-sort="1">Today</span>';
                }
                
                return '<span class="badge bg-success" data-sort="3">On Time</span>';
            })
            ->editColumn('invoice_date', function (PaymentRequest $pr) {
                if ($pr->isPoAdvance()) {
                    return $pr->po?->po_date ? \Carbon\Carbon::parse($pr->po->po_date)->format('d-m-Y') : '-';
                }
                return $pr->invoice?->invoice_date ? \Carbon\Carbon::parse($pr->invoice->invoice_date)->format('d-m-Y') : '-';
            })
            ->filterColumn('invoice_number', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereRaw('LOWER(purchase_invoices.invoice_number) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(purchase_orders.po_number) LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->filterColumn('supplier', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereRaw('LOWER(invoice_suppliers.name) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(po_suppliers.name) LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->filterColumn('requested_by', function ($query, $keyword) {
                $query->whereRaw('LOWER(users.name) LIKE ?', ["%{$keyword}%"]);
            });

        if (\Laratrust::hasPermission('manage-payment create') || 
            \Laratrust::hasPermission('purchase-invoices show')) {
            $dataTable->addColumn('action', function (PaymentRequest $pr) {
                return view('payment-request.list-action', compact('pr'));
            });
        }

        return $dataTable->rawColumns(['checkbox', 'type', 'status', 'approved_amount', 'action', 'invoice_number', 'created_at', 'overdue']);
    }

    public function query(PaymentRequest $model): QueryBuilder
    {
        $request = request();

        $query = $model->with(['invoice.supplier', 'requestedBy', 'po.supplier'])
            ->leftJoin('purchase_invoices', 'payment_requests.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->leftJoin('purchase_orders', 'payment_requests.po_id', '=', 'purchase_orders.id')
            ->leftJoin('suppliers as invoice_suppliers', 'purchase_invoices.supplier_id', '=', 'invoice_suppliers.id')
            ->leftJoin('suppliers as po_suppliers', 'purchase_orders.supplier_id', '=', 'po_suppliers.id')
            ->leftJoin('users', 'payment_requests.requested_by', '=', 'users.id')
            ->select('payment_requests.*')
            ->where(function ($q) {
                // Include payment requests with invoice
                $q->whereHas('invoice', function ($q) {
                    $q->where('workspace_id', getActiveWorkSpace());
                })
                // OR include PO advance requests (type='po_advance' with null invoice)
                ->orWhere(function ($q) {
                    $q->where('payment_requests.type', PaymentRequest::TYPE_PO_ADVANCE)
                      ->whereNull('payment_requests.purchase_invoice_id');
                });
            })
            ->when(getActiveProject(), function ($q) {
                $q->where(function ($q) {
                    // Filter by project for invoice-based requests
                    $q->whereHas('invoice', fn($q) => $q->where('site_id', getActiveProject()))
                      // OR include PO advance requests (check PO's site_id)
                      ->orWhere(function ($q) {
                          $q->where('payment_requests.type', PaymentRequest::TYPE_PO_ADVANCE)
                            ->whereHas('po', fn($q) => $q->where('site_id', getActiveProject()));
                      });
                });
            });

        if ($request->status) {
            $query->where('payment_requests.status', $request->status);
        }

        if ($request->supplier_id) {
            $query->where(function ($q) {
                // Filter by supplier for invoice-based requests
                $q->whereHas('invoice', fn($q) => $q->where('supplier_id', request()->supplier_id))
                  // OR include PO advance requests (check PO's supplier)
                  ->orWhere(function ($q) {
                      $q->where('payment_requests.type', PaymentRequest::TYPE_PO_ADVANCE)
                        ->whereHas('po', fn($q) => $q->where('supplier_id', request()->supplier_id));
                  });
            });
        }

        if (!empty($request->start_date)) {
            $query->whereDate('payment_requests.created_at', '>=', $request->start_date);
        }

        if (!empty($request->end_date)) {
            $query->whereDate('payment_requests.created_at', '<=', $request->end_date);
        }

        if (!empty($request->requested_date)) {
            $query->whereDate('payment_requests.payment_date', '=', $request->requested_date);
        }

        $this->handleSelectedIdsFilter($query);

        $query->orderBy('payment_date', 'asc');

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->select(['selector' => 'td:first-child .' . $this->getCheckboxClass()])
            ->ajax([
                'data' => 'function(d) {
                    d.status = $("select[name=status]").val();
                    d.supplier_id = $("select[name=supplier_id]").val();
                    d.requested_date = $("input[name=requested_date]").val();
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
                "    $('#payment-request-table').DataTable().draw();\n" .
                "});\n\n" .
                "$('body').on('click', '#clearfilter', function() {\n" .
                "    $('select[name=status]').val('');\n" .
                "    $('select[name=supplier_id]').val('');\n" .
                "    $('input[name=requested_date]').val('');\n" .
                "    $('input[name=start_date]').val('');\n" .
                "    $('input[name=end_date]').val('');\n" .
                "    $('#payment-request-table').DataTable().draw();\n" .
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
                ->title('<input type="checkbox" id="select-all-payment-request-checkbox" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::computed('invoice_number')->title(__('Invoice/PO No'))->orderable(false)->searchable(true),
            Column::computed('supplier')->title(__('Supplier'))->orderable(false)->searchable(true),
            Column::make('type')->title(__('Type'))->orderable(false)->searchable(false),
            Column::make('requested_amount')->title(__('Requested'))->orderable(true),
            Column::make('approved_amount')->title(__('Approved'))->orderable(false),
            Column::make('status')->title(__('Status'))->orderable(false)->searchable(true),
            Column::computed('requested_by')->title(__('Created By'))->orderable(false)->searchable(true),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('requested_date')->title(__('Requested Date'))->orderable(false)->searchable(false),
            Column::computed('overdue')->title(__('Over Due'))->orderable(true)->searchable(false),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
        ];
    }

    protected function filename(): string
    {
        return 'PaymentRequest_' . date('YmdHis');
    }
}
