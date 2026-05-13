<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Illuminate\Http\Request;
use Yajra\DataTables\Services\DataTable;

class ProposalDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'proposal-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'proposal-checkbox';
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
        return 'proposals';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Proposal::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['proposal_id', 'issue_date', 'status', 'action', 'checkbox'];
        
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Proposal $proposal) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $proposal->id . '">';
            })
            ->editColumn('proposal_id', function (Proposal $proposal) {
                $url = route('proposal.show', \Crypt::encrypt($proposal->id));
                return '<a href="' . $url . '" class="btn btn-outline-primary">' . \App\Models\Proposal::proposalNumberFormat($proposal->proposal_id) . '</a>';
            })
            ->editColumn('issue_date', function (Proposal $proposal) {
                return company_date_formate($proposal->issue_date);
            })
            ->editColumn('status', function (Proposal $proposal) {
                if ($proposal->status == 0) {
                    return '<span class="badge fix_badge bg-primary p-2 px-3">' . __(\App\Models\Proposal::$statues[$proposal->status]) . '</span>';
                } elseif ($proposal->status == 1) {
                    return '<span class="badge fix_badge bg-info p-2 px-3">' . __(\App\Models\Proposal::$statues[$proposal->status]) . '</span>';
                } elseif ($proposal->status == 2) {
                    return '<span class="badge fix_badge bg-secondary p-2 px-3">' . __(\App\Models\Proposal::$statues[$proposal->status]) . '</span>';
                } elseif ($proposal->status == 3) {
                    return '<span class="badge fix_badge bg-warning p-2 px-3">' . __(\App\Models\Proposal::$statues[$proposal->status]) . '</span>';
                } elseif ($proposal->status == 4) {
                    return ' <span class="badge fix_badge bg-danger p-2 px-3">' . __(\App\Models\Proposal::$statues[$proposal->status]) . '</span>';
                }
            })
            ->addColumn('action', function (Proposal $proposal) {
                return view('proposal.action', compact('proposal'));
            });
        if (Auth::user()->type != 'client') {
            $dataTable = $dataTable->editColumn('customer_id', function (Proposal $proposal) {
                return optional($proposal->customer)->name ?? '';
            });
            $rowColumn[] = 'customer_id';
        }
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Proposal $model, Request $request): QueryBuilder
    {
        if (Auth::user()->type != 'company') {
            $query = $model->join('users', 'proposals.customer_id', '=', 'users.id')
                ->where('users.id', Auth::user()->id)->select('proposals.*')
                ->where('proposals.workspace', getActiveWorkSpace());
        } else {
            $query = $model->where('workspace', getActiveWorkSpace());
        }

        if (!empty($request->customer)) {
            $query->where('customer_id', '=', $request->customer);
        }
        if (!empty($request->issue_date)) {
            $date_range = explode('to', $request->issue_date);
            if (count($date_range) == 2) {
                $query->whereBetween('issue_date', $date_range);
            } else {
                $query->where('issue_date', $date_range[0]);
            }
        }

        if (!empty($request->status)) {

            $query->where('status', $request->status);
        }

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($query);

        return $query->with('customers');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->select(['' . $this->getCheckboxClass() . ''])
            ->ajax([
                'data' => 'function(d) {
                    var issue_date = $("input[name=issue_date]").val();
                    d.issue_date = issue_date

                    var customer = $("select[name=customer]").val();
                    d.customer = customer

                    var status = $("select[name=status]").val();
                    d.status = status
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
                "$(\"body\").on(\"click\", \"#applyfilter\", function() {\n" .
                "    if (!$(\"input[name=issue_date]\").val() && !$(\"select[name=customer]\").val() && !$(\"select[name=status]\").val()) {\n" .
                "        toastrs(\"Error!\", \"Please select Atleast One Filter \", \"error\");\n" .
                "        return;\n" .
                "    }\n" .
                "    $(\"#proposal-table\").DataTable().draw();\n" .
                "});\n\n" .
                "$(\"body\").on(\"click\", \"#clearfilter\", function() {\n" .
                "    $(\"input[name=issue_date]\").val(\"\");\n" .
                "    $(\"select[name=customer]\").val(\"\");\n" .
                "    $(\"select[name=status]\").val(\"\");\n" .
                "    $(\"#proposal-table\").DataTable().draw();\n" .
                "});"
            ));

        // Use the export button config from trait
        $buttonsConfig = $this->getExportButtonConfig();

        $dataTable->parameters([
            "dom" =>  "
                            <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex justify-content-end gap-2'Bf>>
                            <'dataTable-container'<'col-sm-12'tr>>
                            <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "select" => [
                "style" => "multi",
                "selector" => "td:first-child ." . $this->getCheckboxClass()
            ],
            "drawCallback" => 'function( settings ) {
                                    var tooltipTriggerList = [].slice.call(
                                        document.querySelectorAll("[data-bs-toggle=tooltip]")
                                      );
                                      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                        return new bootstrap.Tooltip(tooltipTriggerEl);
                                      });
                                      var popoverTriggerList = [].slice.call(
                                        document.querySelectorAll("[data-bs-toggle=popover]")
                                      );
                                      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                                        return new bootstrap.Popover(popoverTriggerEl);
                                      });
                                      var toastElList = [].slice.call(document.querySelectorAll(".toast"));
                                      var toastList = toastElList.map(function (toastEl) {
                                        return new bootstrap.Toast(toastEl);
                                      });
                                }'
        ]);

        $dataTable->language([
            'buttons' => [
                'create' => __('Create'),
                'export' => __('Export'),
                'print' => __('Print'),
                'reset' => __('Reset'),
                'reload' => __('Reload'),
                'excel' => __('Excel'),
                'csv' => __('CSV'),
            ]
        ]);

        return $dataTable;
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        $checkboxClass = $this->getCheckboxClass();
        
        $column = [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('proposal_id')->title(__('Proposal')),

        ];
        if (Auth::user()->type != 'client') {
            $column[] = Column::make('customer_id')->title(__('Customer'));
        }
        $column[] = Column::make('account_type')->title(__('Account Type'));
        $column[] = Column::make('issue_date')->title(__('Issue Date'));
        $column[] = Column::make('status')->title(__('Status'));
        $column[] = Column::computed('action')
            ->exportable(false)
            ->printable(false)
            ->width(60)
            ;
        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Proposal_' . date('YmdHis');
    }
}
