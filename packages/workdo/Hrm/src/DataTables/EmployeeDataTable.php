<?php

namespace Workdo\Hrm\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Workdo\Hrm\Entities\Employee;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Database\Eloquent\Builder;


class EmployeeDataTable extends DataTable
{
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'employees-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'employee-checkbox';
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
        return 'employees';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * Get the ID column name for filtering.
     * Override for JOIN queries that use table-qualified ID column.
     */
    protected function getIdColumnName(): string
    {
        return 'users.id';
    }

    /**
     * Check if this is an export request.
     * Only triggers for actual export requests (not DataTable AJAX).
     * Export requests have 'model' parameter which DataTable doesn't send.
     * 
     * @return bool
     */
    protected function isExportRequest(): bool
    {
        // Export requests always have 'model' parameter
        // DataTable AJAX does not have this parameter
        return request()->has('model');
    }

    /**
     * Get exportable columns configuration from getColumns().
     * Returns array of column configurations with:
     * - 'field': database field (from getName(), e.g., 'users.name')
     * - 'alias': data key (from getData(), e.g., 'branch_id')
     * - 'title': Excel header (from getTitle(), e.g., 'Branch')
     * 
     * @return array
     */
    protected function getExportColumnsConfig(): array
    {
        $columns = $this->getColumns();
        $config = [];
        
        // Log::info('========================================================');
        // Log::info('EMPLOYEE DATATABLE - getExportColumnsConfig()');
        // Log::info('========================================================');
        // Log::info('EmployeeDataTable getExportColumnsConfig() - processing getColumns():', [
        //     'total_columns' => count($columns),
        //     'class' => get_class($this)
        // ]);
        
        foreach ($columns as $column) {
            if (!($column instanceof \Yajra\DataTables\Html\Column)) {
                Log::info('Skipping non-Column object');
                continue;
            }
            
            // Get data value - handle both string and Column object cases
            $data = $column->getData();
            $dataValue = null;
            
            if (is_string($data)) {
                $dataValue = $data;
            } elseif (is_object($data) && isset($data->data)) {
                // Computed columns like 'checkbox', 'action' return the Column object
                $dataValue = $data->data;
            }
            
            // Skip computed columns (like checkbox, action, No)
            if (in_array($dataValue, ['checkbox', 'action', 'No', 'DT_RowIndex'])) {
                Log::info('Skipping computed column:', ['data' => $dataValue]);
                continue;
            }
            
            // Skip if exportable is explicitly set to false
            $attributes = $column->getAttributes();
            if (isset($attributes['exportable']) && $attributes['exportable'] === false) {
                Log::info('Skipping non-exportable column:', [
                    'data' => $dataValue,
                    'attributes' => $attributes
                ]);
                continue;
            }
            
            // Get field (name) - this is the database column
            // Try getName() first, then fallback to attributes['name'], then fallback to data
            $field = null;
            
            // Method 1: Try getName()
            $name = $column->getName();
            if (!empty($name) && is_string($name)) {
                $field = $name;
            }
            
            // Method 2: Try attributes['name']
            if (empty($field) && isset($attributes['name']) && !empty($attributes['name'])) {
                $field = $attributes['name'];
            }
            
            // Method 3: Fallback to data value if it's a database field pattern
            if (empty($field) && !empty($dataValue) && strpos($dataValue, '_id') !== false) {
                // For fields like branch_id, department_id, designation_id, 
                // the field name should be the table.name format
                // We'll handle these specially in EmployeeExport
                $field = $dataValue;
            }
            
            // For now, if field is still empty but we have data, use data as field
            if (empty($field) && !empty($dataValue)) {
                $field = $dataValue;
            }
            
            // Get title - this is the Excel header
            $title = $column->getTitle();
            $titleValue = null;
            
            if (is_string($title)) {
                $titleValue = $title;
            } elseif (is_object($title) && isset($title->title)) {
                $titleValue = $title->title;
            }
            
            // Also try attributes['title']
            if (empty($titleValue) && isset($attributes['title']) && !empty($attributes['title'])) {
                $titleValue = $attributes['title'];
            }
            
            Log::info('Column analysis:', [
                'data' => $dataValue,
                'field' => $field,
                'title' => $titleValue,
                'attributes' => $attributes,
                'getName_result' => $name
            ]);
            
            if (!empty($field) && !empty($dataValue) && !empty($titleValue)) {
                $config[] = [
                    'field' => $field,
                    'alias' => $dataValue,
                    'title' => $titleValue,
                ];
                Log::info('Added column to export config:', [
                    'alias' => $dataValue,
                    'field' => $field,
                    'title' => $titleValue
                ]);
            } else {
                Log::info('Skipping column - missing required values:', [
                    'data' => $dataValue,
                    'field' => $field,
                    'title' => $titleValue
                ]);
            }
        }
        
        Log::info('EmployeeDataTable getExportColumnsConfig() - final config:', $config);
        
        return $config;
    }

    /**
     * Get exportable column aliases for data mapping.
     * Returns array of data keys (used in map function).
     * 
     * @return array
     */
    protected function getExportColumns(): array
    {
        $config = $this->getExportColumnsConfig();
        $columns = array_column($config, 'alias');
        
        Log::info('EmployeeDataTable getExportColumns() - returning aliases:', $columns);
        
        return $columns;
    }

    /**
     * Get export column titles for Excel headers.
     * Returns array of column titles.
     * 
     * @return array
     */
    protected function getExportColumnLabels(): array
    {
        $config = $this->getExportColumnsConfig();
        $labels = array_column($config, 'title');
        
        Log::info('EmployeeDataTable getExportColumnLabels() - returning titles:', $labels);
        
        return $labels;
    }

    /**
     * Get raw SQL select for export query.
     * Returns array suitable for selectRaw().
     * Format: ['users.id AS employee_id', 'branches.name AS branch_id', ...]
     * 
     * @return array
     */
    protected function getExportSelectRaw(): array
    {
        $config = $this->getExportColumnsConfig();
        $select = [];
        
        foreach ($config as $col) {
            $select[] = $col['field'] . ' AS ' . $col['alias'];
        }
        
        return $select;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['checkbox', 'employee_id', 'name', 'email', 'branch_id', 'department_id', 'designation_id', 'site_id', 'company_doj'];
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (User $employee) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $employee->id . '">';
            })
            ->editColumn('employee_id', function (User $employees) {
                if (!empty($employees->employee_id)) {
                    if (\Laratrust::hasPermission('employee show') && $employees->is_disable == 1) {
                        $url = route('employee.show', \Illuminate\Support\Facades\Crypt::encrypt($employees->id));
                        $emp_id = Employee::employeeIdFormat($employees->employee_id);
                        $html = '<a class="btn btn-outline-primary" href="' . $url . '">
                                        ' . $emp_id . '
                                    </a>';
                        return $html;
                    } else {
                        $emp_id = Employee::employeeIdFormat($employees->employee_id);
                        $html = '<a href="#" class="btn btn-outline-primary">' . $emp_id . '</a>';
                        return $html;
                    }
                } else {
                    $html = '--';
                    return $html;
                }
            })
            ->editColumn('name', function (User $employees) {
                return $employees->name ?? '-';
            })
            ->editColumn('email', function (User $employees) {
                return $employees->email ?? '-';
            })
            ->editColumn('branch_id', function (User $employees) {
                return $employees->branches_name ?? '-';
            })
            ->editColumn('department_id', function (User $employees) {
                return $employees->departments_name ?? '-';
            })
            ->editColumn('designation_id', function (User $employees) {
                return $employees->designations_name ?? '-';
            })
            ->editColumn('site_id', function (User $employees) {
                return $employees->sites_name ?? '-';
            })
            ->editColumn('company_doj', function (User $employees) {
                return $employees->company_doj ? company_date_formate($employees->company_doj ?? '-') : '-';
            });
        if (\Laratrust::hasPermission('employee show') || \Laratrust::hasPermission('employee edit') || \Laratrust::hasPermission('employee delete')) {
            $dataTable->addColumn('action', function (User $employees) {
                return view('hrm::employee.button', compact('employees'));
            });
            $rowColumn[] = 'action';
        }
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(User $model, Request $request): QueryBuilder
    {
        // Check if this is an export request
        $isExport = $this->isExportRequest();
        
        // Get logged-in user type
        $userType = Auth::user()->type;
        
        // Check if user is Admin or company - show all employees without restrictions
        $isAdminOrCompany = ($userType === 'Admin' || $userType === 'company');
        
        // Debug log for user type
        Log::info('EmployeeDataTable - User Type:', ['type' => $userType, 'isAdminOrCompany' => $isAdminOrCompany]);
        
        if ($isExport) {
            // For export, use selectRaw with proper column aliases
            $selectRaw = $this->getExportSelectRaw();
            $selectColumns = array_merge(['users.id'], $selectRaw);
            
            if ($isAdminOrCompany) {
                // Admin or company: show all employees without workspace/site restrictions
                $employees = $model
                    ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                    ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                    ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                    ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                    ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                    ->where('users.created_by', creatorId())
                    ->selectRaw(implode(', ', $selectColumns));
            } else {
                // Normal users: apply workspace_id and site_id restrictions
                $employees = $model->withoutGlobalScopes()
                    ->where('users.workspace_id', getActiveWorkSpace())
                    ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                    ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                    ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                    ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                    ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                    ->where('users.created_by', creatorId())
                    ->where('users.site_id', getActiveProject())
                    ->selectRaw(implode(', ', $selectColumns));
            }
            
            // Handle selected_ids from export request using trait method
            $this->handleSelectedIdsFilter($employees);
            
            return $employees;
        }
        
        // Regular DataTable query
        if ($isAdminOrCompany) {
            // Admin or company: show all employees without workspace/site restrictions
            $employees = $model
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                ->where('users.created_by', creatorId())
                ->select('users.*', 'users.id as ID', 'employees.*', 'users.name as name', 'users.email as email', 'users.id as id', 'branches.name as branches_name', 'departments.name as departments_name', 'designations.name as designations_name', 'projects.name as sites_name');
        } elseif (!in_array(Auth::user()->type, Auth::user()->not_emp_type)) {
            // Non-admin: show only own record
            $employees = $model->withoutGlobalScopes()
                ->where('users.workspace_id', getActiveWorkSpace())
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                ->where('users.id', Auth::user()->id)
                ->where('users.site_id', getActiveProject())
                ->select('users.*', 'users.id as ID', 'employees.*', 'users.name as name', 'users.email as email', 'users.id as id', 'branches.name as branches_name', 'departments.name as departments_name', 'designations.name as designations_name', 'projects.name as sites_name');
        } elseif (Auth::user()->isAbleTo('employee manage')) {
            // Has permission: show all in workspace/project
            $employees = $model->withoutGlobalScopes()
                ->where('users.workspace_id', getActiveWorkSpace())
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                ->where('users.created_by', creatorId())
                ->where('users.site_id', getActiveProject())
                ->select('users.*', 'users.id as ID', 'employees.*', 'users.name as name', 'users.email as email', 'users.id as id', 'branches.name as branches_name', 'departments.name as departments_name', 'designations.name as designations_name', 'projects.name as sites_name');
        } else {
            // Default: show own record
            $employees = $model->withoutGlobalScopes()
                ->where('users.workspace_id', getActiveWorkSpace())
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                ->leftJoin('projects', 'users.site_id', '=', 'projects.id')
                ->where('users.id', Auth::user()->id)
                ->select('users.*', 'users.id as ID', 'employees.*', 'users.name as name', 'users.email as email', 'users.id as id', 'branches.name as branches_name', 'departments.name as departments_name', 'designations.name as designations_name', 'projects.name as sites_name');
        }

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($employees);

        return $employees;
    }

    
   
//public function query(User $model, Request $request): Builder
//{
//    if (Auth::user()->type === 'company') {
//        // Company type: show all employees in active workspace/project
//        $employees = $model->where('workspace_id', getActiveWorkSpace())
//            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
//            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
//            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
//            ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
//            ->where('users.site_id', getActiveProject())
//            ->select(
//                'users.*',
//                'employees.*',
//                'users.name as name',
//                'users.email as email',
//                'branches.name as branches_name',
//                'departments.name as departments_name',
//                'designations.name as designations_name'
//            );
//    }
//
//    return $employees;
//}


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
        $company_settings = getCompanyAllSetting();
        $columns = $this->addCheckboxColumnDefinition([
            Column::make('employee_id')->title(__('Employee ID'))->name('users.id'),
            Column::make('name')->title(__('Name'))->name('users.name'),
            Column::make('branch_id')->title(!empty($company_settings['hrm_branch_name']) ? $company_settings['hrm_branch_name'] : __('Branch'))->name('branches.name'),
            Column::make('department_id')->title(!empty($company_settings['hrm_department_name']) ? $company_settings['hrm_department_name'] : __('Department'))->name('departments.name'),
            Column::make('designation_id')->title(!empty($company_settings['hrm_designation_name']) ? $company_settings['hrm_designation_name'] : __('Designation'))->name(('designations.name')),
            Column::make('site_id')->title(__('Site'))->name('projects.name'),
            Column::make('company_doj')->title(__('Date Of Joining'))->name('employees.company_doj'),
        ], false);
        if (
            \Laratrust::hasPermission('employee show') ||
            \Laratrust::hasPermission('employee edit') ||
            \Laratrust::hasPermission('employee delete')
        ) {
            $action = [
                Column::computed('action')
                    ->title(__('Action'))
                    ->exportable(false)
                    ->printable(false)
                    ->width(60)

            ];

            $columns = array_merge($columns, $action);
        }

        return $columns;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Employees_' . date('YmdHis');
    }
}
