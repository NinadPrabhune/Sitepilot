<?php

namespace App\DataTables;

use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StockReportDataTable extends DataTable
{
    /**
     * Build DataTable class.
     */
    public function dataTable($query)
    {
        return datatables()
            ->collection($query) // ✅ use collection since helper returns a Collection
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return view('stock-reports.action', compact('row'));
            })
            ->rawColumns(['action']);
    }

    /**
     * Get query source of dataTable.
     */
    public function query()
    {
        $request     = request();
        $siteId      = $request->input('site_id');
        $material_id = $request->input('material_id');
//        $startDate   = $request->input('start_date');
//        $endDate     = $request->input('end_date');

        // ✅ Ensure siteId is set
        if (empty($siteId)) {
            $siteId = getActiveProject();
        }

        return getCurrentStockBySiteId($siteId, null, null, null, null, $material_id);
        // Returns a Collection of stock items
//        return getCurrentStockBySiteId($siteId, null, null, $startDate, $endDate, $material_id);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('stock-report-table')
            ->columns($this->getColumns())            
            ->orderBy(0)
            ->language([
                "paginate" => [
                    "next" => '<i class="ti ti-chevron-right"></i>',
                    "previous" => '<i class="ti ti-chevron-left"></i>'
                ],
                'lengthMenu' => "_MENU_" . __("Entries Per Page"),
                "searchPlaceholder" => __("Search..."),
                "search" => "",
                "info" => __("Showing _START_ to _END_ of _TOTAL_ entries")
            ])
            ->initComplete("function() {
                var table = this;

                // Apply filter
$('body').on('click', '#applyfilter', function() {
    $('#stock-report-table').DataTable().draw();
});

// Clear filter
$('body').on('click', '#clearfilter', function() {
    $('select[name=material_id]').val('');
    $('#stock-report-table').DataTable().draw();
});


                var searchInput = $('#'+table.api().table().container().id+' label input[type=\"search\"]');
                searchInput.removeClass('form-control form-control-sm').addClass('dataTable-input');
                var select = $(table.api().table().container()).find('.dataTables_length select')
                    .removeClass('custom-select custom-select-sm form-control form-control-sm')
                    .addClass('dataTable-selector');
            }")
            ->parameters([
                            "dom" => "
                    <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>>
                    <'dataTable-container'<'col-sm-12'tr>>
                    <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
                            'buttons' => [
                                [
                                    'extend' => 'collection',
                                    'className' => 'btn btn-light-secondary dropdown-toggle',
                                    'text' => '<i class="ti ti-download me-2"></i>',
                                    'buttons' => ['print', 'csv', 'excel'],
                                ],
                                ['extend' => 'reset', 'className' => 'btn btn-light-danger'],
                                ['extend' => 'reload', 'className' => 'btn btn-light-warning'],
                            ],
                            "drawCallback" => 'function(settings) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
                }'
        ]);
    }

    /**
     * Get columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('No')
                ->title(__('No'))
                ->data('DT_RowIndex')
                ->name('DT_RowIndex')
                ->searchable(false)
                ->orderable(false),
            Column::make('material_name')->title(__('Material')),
            Column::make('category_name')->title(__('Category')),
            Column::make('total_qty')->title(__('Available Qty')),
            Column::make('unit_name')->title(__('Unit')),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60),
        ];
    }

    /**
     * Get filename for export.
     */
    protected function filename(): string
    {
        return 'StockReport_' . date('YmdHis');
    }
}
