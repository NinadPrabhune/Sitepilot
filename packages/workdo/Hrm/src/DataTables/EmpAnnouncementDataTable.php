<?php

namespace Workdo\Hrm\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Announcement;
use Workdo\Hrm\Entities\Employee;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class EmpAnnouncementDataTable extends DataTable
{
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'emp-announcement-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'announcement-checkbox';
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
        return 'announcements';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return Announcement::class;
    }

    /**
     * Get the ID column name for filtering.
     */
    protected function getIdColumnName(): string
    {
        return 'announcements.id';
    }
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['checkbox', 'title', 'start_date', 'end_date', 'description'];
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (Announcement $announcement) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $announcement->id . '">';
            })
            ->editColumn('title', function (Announcement $announcements) {
                return $announcements->title ?? '-';
            })
            ->editColumn('start_date', function (Announcement $announcements) {
                return $announcements->start_date ? company_date_formate($announcements->start_date) ?? '-' : '-';
            })
            ->editColumn('end_date', function (Announcement $announcements) {
                return $announcements->end_date ? company_date_formate($announcements->end_date) ?? '-' : '-';
            })
            ->editColumn('description', function (Announcement $announcements) {
                $url = route('announcement.description', $announcements->id);
                $html = '<a class="action-item" data-url="' . $url . '" data-ajax-popup="true" data-bs-toggle="tooltip" title="' . __('Description') . '" data-title="' . __('Description') . '"><i class="fa fa-comment"></i></a>';
                return $html;
            });
            if (\Laratrust::hasPermission('announcement edit') || \Laratrust::hasPermission('announcement delete')) {
                $dataTable->addColumn('action', function (Announcement $announcements) {
                    return view('hrm::announcement.button', compact('announcements'));
                });
                $rowColumn[] = 'action';
            }
            return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    
    public function query(Announcement $model, Request $request): QueryBuilder
{
    if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
        $employee = Employee::where('user_id', Auth::user()->id)->first();

        if (!empty($employee)) {
            $announcements = $model->select('announcements.*')
                ->orderBy('announcements.id', 'desc')
                ->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where(function ($q) use ($employee) {
                    $q->where('announcement_employees.employee_id', $employee->id)
                      ->orWhere(function ($q2) {
                          $q2->where('announcements.department_id', '["0"]')
                             ->where('announcements.employee_id', '["0"]')
                             ->where('announcements.workspace', getActiveWorkSpace());
                      });
                });
        } else {
            $announcements = $model->where('workspace', getActiveWorkSpace())
                                   ->orderBy('announcements.id', 'desc');
        }
    } else {
        $announcements = $model->where('workspace', getActiveWorkSpace())
                               ->orderBy('announcements.id', 'desc');
    }

    // Handle selected_ids from export request
    $this->handleSelectedIdsFilter($announcements);

    return $announcements;
}

    
//    public function query(Announcement $model, Request $request): QueryBuilder
//    {
//        if (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
//            $employee = Employee::where('user_id', Auth::user()->id)->first();
//            $announcements = [];
//            if (!empty($employee)) {
//                $announcements    = $model->select('announcements.*')->orderBy('announcements.id', 'desc')->leftjoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')->where('announcement_employees.employee_id', '=', $employee->id)->orWhere(
//                    function ($q) {
//                        $q->where('announcements.department_id', '["0"]')
//                            ->where('announcements.employee_id', '["0"]')
//                            ->where('announcements.workspace', getActiveWorkSpace())
//                                ;
//                    }
//                );
//            }else {
//                $announcements    = $model->where('workspace', getActiveWorkSpace());
//            }
//        } else {
//            $announcements    = $model->where('workspace', getActiveWorkSpace());
//        }
//
//        return $announcements;
//    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId($this->getTableId())
            ->columns($this->getColumns())
            ->minifiedAjax()
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
            ->initComplete($this->getCheckboxInitScript());

        // Use the export button config from trait
        $buttonsConfig = $this->getExportButtonConfig();

        $dataTable->parameters([
            "dom" =>  "
        <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex justify-content-end gap-2'Bf>>
        <'dataTable-container'<'col-sm-12'tr>>
        <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
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
        $column = $this->addCheckboxColumnDefinition([
            Column::make('title')->title(__('Title')),
            Column::make('start_date')->title(__('Start Date')),
            Column::make('end_date')->title(__('End Date')),
            Column::make('description')->title(__('Description')),
        ], false);

        if (
            \Laratrust::hasPermission('announcement edit') ||
            \Laratrust::hasPermission('announcement delete')
        ) {
            $action = [
                Column::computed('action')
                    ->title(__('Action'))
                    ->exportable(false)
                    ->printable(false)
                    ->width(60)
                    
            ];

            $column = array_merge($column, $action);
        }

        return $column;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Announcements_' . date('YmdHis');
    }
}
