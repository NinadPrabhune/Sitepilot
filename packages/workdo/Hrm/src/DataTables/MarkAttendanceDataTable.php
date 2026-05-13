<?php

namespace Workdo\Hrm\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Attendance;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MarkAttendanceDataTable extends DataTable
{
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'mark-attendance-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'attendance-checkbox';
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
        return 'attendance';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return Attendance::class;
    }

    /**
     * Get the ID column name for filtering.
     */
    protected function getIdColumnName(): string
    {
        return 'attendances.id';
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['checkbox', 'date', 'status', 'clock_in', 'clock_out', 'clock_in_image', 'clock_out_image', 'late', 'early_leaving', 'overtime'];

        // Keep addIndexColumn() for UI display (SR NO column in table)
        // The export will exclude it via getExportColumnsConfig() and exportable(false)
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Attendance $attendance) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $attendance->id . '">';
            });

        // Employee column
        if (\Laratrust::hasPermission('attendance create') || \Laratrust::hasPermission('attendance edit')) {
            $dataTable->editColumn('employee_id', function (Attendance $attendances) {
                return $attendances->employees ? $attendances->employees->name : '-';
            })
            ->filterColumn('employee_id', function ($query, $keyword) {
                $query->whereHas('employees', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                });
            });

            $rowColumn[] = 'employee_id';
        }

        // site column
        $dataTable->editColumn('site_id', function (Attendance $attendances) {
            return $attendances->site ? $attendances->site->name : '-';
        })
        ->filterColumn('site_id', function ($query, $keyword) {
            $query->whereHas('site', function ($q) use ($keyword) {
                $q->where('name', 'like', "%$keyword%");
            });
        });

        $rowColumn[] = 'site_id';

        // Other columns
        $dataTable->editColumn('date', function (Attendance $attendances) {
            return $attendances->date ? company_date_formate($attendances->date) : '-';
        })
        ->editColumn('status', fn(Attendance $attendances) => $attendances->status ?? '-')
        ->editColumn('clock_in_image', function (Attendance $attendances) {
            $clockIn = $attendances->clock_in ?? '-';

            if (!empty($attendances->clock_in_image)) {
                $clockIn .= '
                    <span>
                        <div class="action-btn me-2 d-inline-block">
                            <a class="mx-3 btn bg-secondary btn-sm align-items-center"
                               href="' . get_file($attendances->clock_in_image) . '"
                               target="_blank">
                                <i class="ti ti-crosshair text-white"
                                   data-bs-toggle="tooltip"
                                   data-bs-original-title="Preview"></i>
                            </a>
                        </div>
                    </span>';
            }

            return $clockIn;
        })
        ->editColumn('clock_out_image', function (Attendance $attendances) {
            $clockOut = $attendances->clock_out ?? '-';

            if (!empty($attendances->clock_out_image)) {
                $clockOut .= '
                    <span>
                        <div class="action-btn me-2 d-inline-block">
                            <a class="mx-3 btn bg-secondary btn-sm align-items-center"
                               href="' . get_file($attendances->clock_out_image) . '"
                               target="_blank">
                                <i class="ti ti-crosshair text-white"
                                   data-bs-toggle="tooltip"
                                   data-bs-original-title="Preview"></i>
                            </a>
                        </div>
                    </span>';
            }

            return $clockOut;
        })
        
        ->editColumn('late', fn(Attendance $attendances) => $attendances->late ?? '-')
        ->editColumn('early_leaving', fn(Attendance $attendances) => $attendances->early_leaving ?? '-')
        ->editColumn('overtime', fn(Attendance $attendances) => $attendances->overtime ?? '-');

        // Action buttons
        if (\Laratrust::hasPermission('attendance edit') || \Laratrust::hasPermission('attendance delete')) {
            $dataTable->addColumn('action', function (Attendance $attendances) {
                return view('hrm::attendance.button', compact('attendances'));
            });
            $rowColumn[] = 'action';
        }

        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Attendance $model, Request $request): QueryBuilder
    {
        $user = Auth::user();

        // Base query - show all attendance records across all projects
        $attendances = $model->with(['employees', 'site'])
            ->where('workspace', getActiveWorkSpace());

        // Company can filter employees
        if ($user->type === 'company') {
            if (!empty($request->employee)) {
                $attendances->where('employee_id', $request->employee);
            }
        }
        // Non-company users see only their own attendance
        else {
            $attendances->where('employee_id', $user->employee->employee_id);
        }

        // Date filters
        if ($request->type === 'monthly' && !empty($request->month)) {
            $date = new \DateTime($request->month . '-01');
            $start_date = $date->format('Y-m-d');
            $end_date   = $date->format('Y-m-t'); // Last day of month

            $attendances->whereBetween('date', [$start_date, $end_date]);
        } elseif ($request->type === 'daily' && !empty($request->date)) {
            $attendances->where('date', $request->date);
        } else {
            // Default: current month
            $date = new \DateTime(date('Y-m-01'));
            $start_date = $date->format('Y-m-d');
            $end_date   = $date->format('Y-m-t'); // Last day of month

            $attendances->whereBetween('date', [$start_date, $end_date]);
        }

        // Handle selected_ids from export request
        $this->handleSelectedIdsFilter($attendances);

        return $attendances;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    var type = $("input[name=type]:radio:checked").val();
                    d.type = type;
                    if (type == "monthly") {
                        var month = $("input[name=month]").val();
                        d.month = month;
                    } else {
                        var date = $("input[name=date]").val();
                        d.date = date;
                    }
                    var employee = $("select[name=employee]").val();
                    d.employee = employee;
                }',
            ])
            ->orderBy(0)
            ->select(['' . $this->getCheckboxClass() . ''])
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
                "$(\"body\").on(\"click\", \"#applyfilter\", function() {" .
                "if (!$(\"input[name=type]:radio:checked\").val() && !$(\"input[name=month]\").val() && !$(\"input[name=date]\").val()) {" .
                "toastrs(\"Error!\", \"Please select Atleast One Filter\", \"error\");" .
                "return;" .
                "}" .
                "$(\"#mark-attendance-table\").DataTable().draw();" .
                "});" .
                "$(\"body\").on(\"click\", \"#clearfilter\", function() {" .
                "$(\"input[name=type]:radio:checked\").prop(\"checked\", false);" .
                "$(\"input[name=month]\").val(\"\");" .
                "$(\"input[name=date]\").val(\"\");" .
                "$(\"#mark-attendance-table\").DataTable().draw();" .
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
        $column = [];

        // Hidden ID column
        $column[] = Column::make('id')->searchable(false)->visible(false)->exportable(false)->printable(false);

        // Checkbox column
        $column[] = Column::computed('checkbox')
            ->title('<input type="checkbox" id="select-all-' . $this->getCheckboxClass() . '" class="form-check-input">')
            ->exportable(false)
            ->printable(false)
            ->orderable(false)
            ->searchable(false)
            ->width(20);

        // Index column
        $column[] = Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->exportable(false)->printable(false)->searchable(false)->orderable(false);

       
        $column[] = Column::make('employee_id')->title(__('Employee'));
        $column[] = Column::make('site_id')->title(__('Project'));
       

        // Data columns
        $column[] = Column::make('date')->title(__('Date'));
        $column[] = Column::make('status')->title(__('Status'));
        $column[] = Column::make('clock_in')->title(__('Clock In'));
        $column[] = Column::make('clock_out')->title(__('Clock Out'));
        $column[] = Column::make('clock_in_image')->title(__('Clock In Image'));
        $column[] = Column::make('clock_out_image')->title(__('Clock Out Image'));
        $column[] = Column::make('late')->title(__('Late'));
        $column[] = Column::make('early_leaving')->title(__('Early Leaving'));
        $column[] = Column::make('overtime')->title(__('Overtime'));

        // Action column (if has permission)
        if (\Laratrust::hasPermission('attendance edit') || \Laratrust::hasPermission('attendance delete')) {
            $column[] = Column::computed('action')
                ->title(__('Action'))
                ->exportable(false)
                ->printable(false)
                ->width(60);
        }

        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Mark Attendance_' . date('YmdHis');
    }

    /**
     * Get export columns configuration.
     * Defines which columns to export and their aliases.
     */
    public function getExportColumnsConfig(): array
    {
        $columns = [];

        // Only add columns that should be exported (no id, checkbox, index, action)
        if (\Laratrust::hasPermission('attendance create') || \Laratrust::hasPermission('attendance edit')) {
            $columns[] = ['field' => 'employee_id', 'alias' => 'employee_name', 'title' => __('Employee')];
        }

        $columns[] = ['field' => 'site_id', 'alias' => 'site_name', 'title' => __('Project')];
        $columns[] = ['field' => 'date', 'alias' => 'date', 'title' => __('Date')];
        $columns[] = ['field' => 'status', 'alias' => 'status', 'title' => __('Status')];
        $columns[] = ['field' => 'clock_in', 'alias' => 'clock_in', 'title' => __('Clock In')];
        $columns[] = ['field' => 'clock_out', 'alias' => 'clock_out', 'title' => __('Clock Out')];
        $columns[] = ['field' => 'clock_in_image', 'alias' => 'clock_in_image', 'title' => __('Clock In Image')];
        $columns[] = ['field' => 'clock_out_image', 'alias' => 'clock_out_image', 'title' => __('Clock Out Image')];
        $columns[] = ['field' => 'late', 'alias' => 'late', 'title' => __('Late')];
        $columns[] = ['field' => 'early_leaving', 'alias' => 'early_leaving', 'title' => __('Early Leaving')];
        $columns[] = ['field' => 'overtime', 'alias' => 'overtime', 'title' => __('Overtime')];

        return $columns;
    }

    /**
     * Get export column labels for headings.
     */
    public function getExportColumnLabels(): array
    {
        $labels = [];

        if (\Laratrust::hasPermission('attendance create') || \Laratrust::hasPermission('attendance edit')) {
            $labels['employee_name'] = __('Employee');
        }

        $labels['site_name'] = __('Project');
        $labels['date'] = __('Date');
        $labels['status'] = __('Status');
        $labels['clock_in'] = __('Clock In');
        $labels['clock_out'] = __('Clock Out');
        $labels['clock_in_image'] = __('Clock In Image');
        $labels['clock_out_image'] = __('Clock Out Image');
        $labels['late'] = __('Late');
        $labels['early_leaving'] = __('Early Leaving');
        $labels['overtime'] = __('Overtime');

        return $labels;
    }
}
