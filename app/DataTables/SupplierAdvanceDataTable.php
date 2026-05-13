<?php

namespace App\DataTables;

use App\Models\SupplierAdvance;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Http\Request;

class SupplierAdvanceDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('action', function ($advance) {
                $actionBtn = '<div class="btn-group">';
                
                // View button
                $actionBtn .= '<a href="' . route('supplier-advance.show', $advance->id) . '" class="btn btn-sm btn-primary" title="View">
                    <i class="fa fa-eye"></i>
                </a>';

                // Approve button (if pending)
                if ($advance->status === SupplierAdvance::STATUS_PENDING) {
                    $actionBtn .= '<a href="' . route('supplier-advance.approve', $advance->id) . '" class="btn btn-sm btn-success" title="Approve">
                        <i class="fa fa-check"></i>
                    </a>';
                }

                // Payment form button (if approved but not paid)
                if ($advance->status === SupplierAdvance::STATUS_APPROVED) {
                    $actionBtn .= '<a href="' . route('supplier-advance.payment-form', $advance->id) . '" class="btn btn-sm btn-info" title="Record Payment">
                        <i class="fa fa-money"></i>
                    </a>';
                }

                // Timeline button (if paid)
                if ($advance->status === SupplierAdvance::STATUS_PAID) {
                    $actionBtn .= '<a href="' . route('supplier-advance.timeline', $advance->id) . '" class="btn btn-sm btn-secondary" title="Timeline">
                        <i class="fa fa-history"></i>
                    </a>';
                }

                // Delete button (if pending or cancelled)
                if (in_array($advance->status, [SupplierAdvance::STATUS_PENDING, SupplierAdvance::STATUS_CANCELLED])) {
                    $actionBtn .= '<button type="button" class="btn btn-sm btn-danger delete-advance" data-id="' . $advance->id . '" title="Delete">
                        <i class="fa fa-trash"></i>
                    </button>';
                }

                $actionBtn .= '</div>';
                return $actionBtn;
            })
            ->editColumn('advance_number', function ($advance) {
                return '<span class="badge badge-info">' . $advance->advance_number . '</span>';
            })
            ->editColumn('status', function ($advance) {
                $badgeClass = match($advance->status) {
                    SupplierAdvance::STATUS_PENDING => 'badge-warning',
                    SupplierAdvance::STATUS_APPROVED => 'badge-info',
                    SupplierAdvance::STATUS_PAID => 'badge-success',
                    SupplierAdvance::STATUS_CANCELLED => 'badge-danger',
                    default => 'badge-secondary'
                };
                return '<span class="badge ' . $badgeClass . '">' . ucfirst($advance->status) . '</span>';
            })
            ->editColumn('amount', function ($advance) {
                return '₹' . number_format($advance->amount, 2);
            })
            ->editColumn('remaining_amount', function ($advance) {
                $available = $advance->getAvailableBalanceAttribute();
                $color = $available > 0 ? 'text-success' : 'text-muted';
                return '<span class="' . $color . '">₹' . number_format($available, 2) . '</span>';
            })
            ->addColumn('supplier_name', function ($advance) {
                return $advance->supplier->name ?? '-';
            })
            ->addColumn('po_number', function ($advance) {
                return $advance->po ? $advance->po->po_number : 'Manual';
            })
            ->addColumn('site_name', function ($advance) {
                return $advance->site ? $advance->site->name : '-';
            })
            ->addColumn('created_by_name', function ($advance) {
                return $advance->creator ? $advance->creator->name : '-';
            })
            ->rawColumns(['action', 'advance_number', 'status', 'remaining_amount']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\SupplierAdvance $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(SupplierAdvance $model)
    {
        $query = $model->newQuery()
            ->with(['supplier', 'po', 'site', 'creator']);

        // Filter by supplier
        if (request()->has('supplier_id') && request()->supplier_id) {
            $query->where('supplier_id', request()->supplier_id);
        }

        // Filter by status
        if (request()->has('status') && request()->status) {
            $query->where('status', request()->status);
        }

        // Filter by date range
        if (request()->has('start_date') && request()->start_date && request()->has('end_date') && request()->end_date) {
            $query->whereBetween('advance_date', [request()->start_date, request()->end_date]);
        }

        // Filter by workspace
        if (auth()->user()->workspace_id) {
            $query->where('workspace_id', auth()->user()->workspace_id);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
            ->setTableId('supplier-advance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(1)
            ->buttons(
                Button::make('export'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload')
            );
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
            Column::make('id')->hidden(),
            Column::make('advance_number')->title('Advance No.'),
            Column::make('advance_date')->title('Date'),
            Column::make('supplier_name')->title('Supplier'),
            Column::make('po_number')->title('PO No.'),
            Column::make('amount')->title('Amount'),
            Column::make('remaining_amount')->title('Available'),
            Column::make('status')->title('Status'),
            Column::make('site_name')->title('Site'),
            Column::make('created_by_name')->title('Created By'),
            Column::make('created_at')->title('Created At'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'SupplierAdvance_' . date('YmdHis');
    }
}
