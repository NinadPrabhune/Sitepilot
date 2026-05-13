<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ActivityDataTable extends DataTable 
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'activity-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'activity-checkbox';
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
        return 'activities';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\Activity::class;
    }

    /**
     * Build the DataTable class.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable {
        $rowColumn = ['scope', 'quantity', 'unit', 'priority', 'created_at', 'assign_to', 'checkbox'];

        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Activity $activity) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $activity->id . '">';
            })
            ->editColumn('start_date', fn(Activity $activity) => \Carbon\Carbon::parse($activity->start_date)->format('d-m-Y H:i'))
            ->editColumn('due_date', fn(Activity $activity) => $activity->due_date ? \Carbon\Carbon::parse($activity->due_date)->format('d-m-Y H:i') : '-')
            ->editColumn('assign_to', function (Activity $activity) {
                // assign_to is stored as comma-separated IDs
                $ids = $activity->assign_to ? explode(',', $activity->assign_to) : [];
                $names = \App\Models\User::whereIn('id', $ids)->pluck('name')->toArray();
                return !empty($names) ? implode(', ', $names) : '-';
            })
            ->editColumn('completed_quantity', function (Activity $activity) {
                return $activity->completeds->sum('completed_quantity');
            })
            ->editColumn('progress', function (Activity $activity) {
                $completed = $activity->completeds->sum('completed_quantity');
                $total = $activity->quantity;
                $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
                return "{$completed} / {$total} ({$percentage}%)";
            })
            ->editColumn('priority', function (Activity $activity) {
                return ucfirst($activity->priority);
            })   
            ->editColumn('created_by', function (Activity $activity) {
                return optional($activity->creator)->name ?? '';
            })
            ->editColumn('created_at', function (Activity $activity) {
                return $activity->created_at->format('d-m-Y, h:i A');
            });

        $dataTable->addColumn('action', function (Activity $activity) {
            return view('activities.action', compact('activity'));
        });

        $rowColumn[] = 'action';

        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Activity $model): QueryBuilder {
        $request = request();

        $query = $model->newQuery()
            ->leftJoin('activities_completed', 'activities.id', '=', 'activities_completed.activity_id')
            ->selectRaw('activities.*, COALESCE(SUM(activities_completed.completed_quantity),0) as completed_quantity')
            ->where('workspace_id', getActiveWorkSpace())
            ->where('site_id', getActiveProject())
            ->groupBy('activities.id');

        // ✅ Default: current month activities (based on start_date)
        if (empty($request->start_date) && empty($request->end_date)) {
            $query->whereMonth('activities.start_date', \Carbon\Carbon::now()->month)
                  ->whereYear('activities.start_date', \Carbon\Carbon::now()->year);
        }

        // ✅ Filtering by Start Date / End Date
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('activities.start_date', [$request->start_date, $request->end_date]);
        } elseif (!empty($request->start_date)) {
            $query->where('activities.start_date', '>=', $request->start_date);
        } elseif (!empty($request->end_date)) {
            $query->where('activities.start_date', '<=', $request->end_date);
        }

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($query);

        return $query;
    }

    /**
     * Optional: HTML builder
     */
    public function html(): HtmlBuilder {
        $dataTable = $this->builder()
                ->setTableId($this->getTableId())
                ->columns($this->getColumns())
                ->select(['' . $this->getCheckboxClass() . ''])
                ->ajax([
                    'data' => 'function(d) {
                    var start_date = $("input[name=start_date]").val();
                    d.start_date = start_date;

                    var end_date = $("input[name=end_date]").val();
                    d.end_date = end_date;
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
                    "// Apply filter\n" .
                    "$('body').on('click', '#applyfilter', function() {\n" .
                    "    if (!$('input[name=start_date]').val() && !$('input[name=end_date]').val()) {\n" .
                    "        toastrs('Error!', 'Please select at least one filter', 'error');\n" .
                    "        return;\n" .
                    "    }\n" .
                    "    $('#activity-table').DataTable().draw();\n" .
                    "});\n\n" .
                    "// Clear filter\n" .
                    "$('body').on('click', '#clearfilter', function() {\n" .
                    "    $('input[name=start_date]').val('');\n" .
                    "    $('input[name=end_date]').val('');\n" .
                    "    $('#activity-table').DataTable().draw();\n" .
                    "});"
                ));

        // Use the export button config from trait
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
            "drawCallback" => 'function(settings) { }'
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
    public function getColumns(): array {
        $checkboxClass = $this->getCheckboxClass();
        
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(true)->printable(false)->title(__('ID')),
            Column::computed('checkbox')
                ->title('<input type="checkbox" id="select-all-' . $checkboxClass . '" class="form-check-input">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(20),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('assign_to')->title(__('Assigned Employees')),
            Column::make('title')->title(__('Activity Title')),
            Column::make('start_date')->title(__('Start Date')),
            Column::make('due_date')->title(__('Due Date')),
            Column::make('unit')->title(__('Unit')),
            Column::make('priority')->title(__('Priority')),
            Column::make('progress')->title(__('Progress'))->orderable(false)->searchable(false),            
            Column::make('created_by')->title(__('Created By')),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->exportable(false)->printable(false)->width(60),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string {
        return 'Activity_' . date('YmdHis');
    }
}
