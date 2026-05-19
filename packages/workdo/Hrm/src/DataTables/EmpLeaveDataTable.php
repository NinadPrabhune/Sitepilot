<?php

namespace Workdo\Hrm\DataTables;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Entities\Leave;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmpLeaveDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = [
            'leave_type_id',
            'applied_on',
            'start_date',
            'end_date',
            'total_leave_days',
            'approved_days',     // ✅ new
            'leave_reason',
            'status',
            'status_reason',     // ✅ new
            'workspace_name',
            'site_name'
        ];

        $dataTable = (new EloquentDataTable($query))->addIndexColumn();

        if (in_array(\Auth::user()->type, \Auth::user()->not_emp_type)) {
            $dataTable->editColumn('employee_name', function (Leave $leaves) {
                return $leaves->employee_name ?? '-';
            });
            $rowColumn[] = 'employee_name';
        }

        if (\Laratrust::hasPermission('leave edit') || \Laratrust::hasPermission('leave delete') || \Laratrust::hasPermission('leave approver manage')) {
            $dataTable->addColumn('action', function (Leave $leaves) {
                return view('hrm::leave.button', compact('leaves'));
            });
            $rowColumn[] = 'action';
        }

        $dataTable->editColumn('leave_type_id', fn($leaves) => $leaves->leaveType ? $leaves->leaveType->title : '-')
            ->editColumn('applied_on', fn($leaves) => $leaves->applied_on ? company_date_formate($leaves->applied_on) : '-')
            ->editColumn('start_date', fn($leaves) => $leaves->start_date ? company_date_formate($leaves->start_date) : '-')
            ->editColumn('end_date', fn($leaves) => $leaves->end_date ? company_date_formate($leaves->end_date) : '-')
            ->editColumn('total_leave_days', fn($leaves) => $leaves->total_leave_days ?? '-')
            ->editColumn('approved_days', fn($leaves) => $leaves->approved_days ?? '-') // ✅ numeric
            ->editColumn('leave_reason', function ($leaves) {
                $url = route('leave.description', $leaves->id);
                return '<a class="action-item" data-url="' . $url . '" data-ajax-popup="true" data-bs-toggle="tooltip" 
                        title="' . __('Leave Reason') . '" data-title="' . __('Leave Reason') . '">
                        <i class="fa fa-comment"></i></a>';
            })
            ->editColumn('status', function ($leaves) {
                $status = $leaves->status ?? '-';
                if ($status === 'Pending') {
                    return '<div class="badge bg-warning p-2 px-3 status-badge5">' . $status . '</div>';
                } elseif ($status === 'Approved') {
                    return '<div class="badge bg-success p-2 px-3 status-badge5">' . $status . '</div>';
                } else {
                    return '<div class="badge bg-danger p-2 px-3 status-badge5">' . $status . '</div>';
                }
            })
            ->editColumn('status_reason', function ($leaves) { // ✅ clickable icon
                if (!empty($leaves->status_reason)) {
                    $url = route('leave.status_reason', $leaves->id); // define this route
                    return '<a class="action-item" data-url="' . $url . '" data-ajax-popup="true" data-bs-toggle="tooltip" 
                            title="' . __('Status Reason') . '" data-title="' . __('Status Reason') . '">
                            <i class="fa fa-comment"></i></a>';
                }
                return '-';
            })
            ->editColumn('workspace_name', fn($leaves) => $leaves->workspace_name ?? '-')
            ->editColumn('site_name', fn($leaves) => $leaves->site_name ?? '-');

        return $dataTable->rawColumns($rowColumn);
    }

    public function query(Leave $model, Request $request): QueryBuilder
    {
        // Get logged-in user type
        $userType = Auth::user()->type;
        
        // Check if user is Admin or company - show all leaves without restrictions
        $isAdminOrCompany = ($userType === 'Admin' || $userType === 'company');
        
        if ($isAdminOrCompany) {
            // Admin or company: show all leave records without workspace/site restrictions
            return $model
                ->with(['leaveType', 'EmployeeName'])
                ->leftJoin('employees', 'employees.user_id', '=', 'leaves.user_id')
                ->leftJoin('users', 'users.id', '=', 'leaves.user_id')
                ->leftJoin('work_spaces', 'work_spaces.id', '=', 'leaves.workspace')
                ->leftJoin('projects', 'projects.id', '=', 'leaves.site_id')
                ->select('leaves.*', 'work_spaces.name as workspace_name', 'projects.name as site_name', 'users.name as employee_name')
                ->orderBy('leaves.id', 'desc');
        } elseif (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
            // Regular employee: show only their own leaves
            return $model->where('leaves.user_id', Auth::user()->id)
                ->where('leaves.workspace', getActiveWorkSpace())
                ->where('leaves.site_id', getActiveProject())
                ->leftJoin('work_spaces', 'work_spaces.id', '=', 'leaves.workspace')
                ->leftJoin('projects', 'projects.id', '=', 'leaves.site_id')
                ->select('leaves.*', 'work_spaces.name as workspace_name', 'projects.name as site_name')
                ->orderBy('leaves.id', 'desc');
        } else {
            // Other users (HR, etc.): show leaves in active workspace and site
            return $model->where('leaves.workspace', getActiveWorkSpace())
                ->where('leaves.site_id', getActiveProject())
                ->with(['leaveType', 'EmployeeName'])
                ->leftJoin('employees', 'employees.user_id', '=', 'leaves.user_id')
                ->leftJoin('users', 'users.id', '=', 'leaves.user_id')
                ->leftJoin('work_spaces', 'work_spaces.id', '=', 'leaves.workspace')
                ->leftJoin('projects', 'projects.id', '=', 'leaves.site_id')
                ->select('leaves.*', 'work_spaces.name as workspace_name', 'projects.name as site_name', 'users.name as employee_name')
                ->orderBy('leaves.id', 'desc');
        }
    }

   public function html(): HtmlBuilder {
        $dataTable = $this->builder()
                ->setTableId('emp-leave-table')
                ->columns($this->getColumns())
                ->minifiedAjax()
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
                ->initComplete('function() {
                var table = this;
                var searchInput = $(\'#\'+table.api().table().container().id+\' label input[type="search"]\');
                searchInput.removeClass(\'form-control form-control-sm\');
                searchInput.addClass(\'dataTable-input\');
                var select = $(table.api().table().container()).find(".dataTables_length select").removeClass(\'custom-select custom-select-sm form-control form-control-sm\').addClass(\'dataTable-selector\');
            }');

        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Export"></i>',
            'buttons' => [
                [
                    'extend' => 'print',
                    'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'csv',
                    'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'excel',
                    'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
            ],
        ];

        $buttonsConfig = array_merge([
            $exportButtonConfig,
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ]);

        $dataTable->parameters([
            "dom" => "
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

    public function getColumns(): array
    {
        $column = [
            Column::make('leave_type_id')->title(__('Leave Type')),
            Column::make('applied_on')->title(__('Applied On'))->name('leaves.applied_on'),
            Column::make('start_date')->title(__('Start Date'))->name('leaves.start_date'),
            Column::make('end_date')->title(__('End Date'))->name('leaves.end_date'),
            Column::make('total_leave_days')->title(__('Total days')),
            Column::make('approved_days')->title(__('Approved Days')),   // ✅ new
//            Column::make('leave_reason')->title(__('Leave Reason')),
            Column::make('status')->title(__('Status')),
//            Column::make('status_reason')->title(__('Admin Reason')),   // ✅ new
            Column::make('workspace_name')->title(__('Workspace')),
            Column::make('site_name')->title(__('Site')),
        ];

        if (in_array(\Auth::user()->type, \Auth::user()->not_emp_type)) {
            $employee = [
                Column::make('id')->name('leaves.id')->searchable(false)->visible(false)->exportable(false)->printable(false),
                Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
                Column::make('employee_name')->title(__('Employee')),
            ];
            $column = array_merge($employee, $column);
        } else {
            $employee = [
                Column::make('id')->name('leaves.id')->searchable(false)->visible(false)->exportable(false)->printable(false),
                Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            ];
            $column = array_merge($employee, $column);
        }

        if (\Laratrust::hasPermission('leave edit') || \Laratrust::hasPermission('leave delete') || \Laratrust::hasPermission('leave approver manage')) {
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

    protected function filename(): string
    {
        return 'Leaves_' . date('YmdHis');
    }
}
