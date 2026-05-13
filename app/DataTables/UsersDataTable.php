<?php

namespace App\DataTables;

use App\DataTables\Traits\SelectableExportTrait;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    // Use the selectable export trait
    use SelectableExportTrait;

    /**
     * Get the unique table ID for this DataTable.
     */
    protected function getTableId(): string
    {
        return 'users-table';
    }

    /**
     * Get the checkbox class name for row selection.
     */
    protected function getCheckboxClass(): string
    {
        return 'user-checkbox';
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
        return 'users';
    }

    /**
     * Get the model class for export functionality.
     */
    protected function getModelClass(): string
    {
        return \App\Models\User::class;
    }

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('checkbox', function (User $user) {
                return '<input type="checkbox" class="' . $this->getCheckboxClass() . ' form-check-input" value="' . $user->id . '">';
            })
            ->editColumn('avatar', function (User $user) {
                $avatarUrl = check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png');
                $html = '<a>
                            <img src="' . $avatarUrl . '" class="rounded border-2 border border-primary" width="40">
                        </a>';
                return $html;
            })
            ->editColumn('type', function (User $user) {
                $html = '<span class="badge bg-primary p-2 px-3">
                            ' . $user->type . '
                        </span>';
                return $html;
            })
            ->addColumn('action', function (User $user) {
                return view('users.action', compact('user'));
            })
            ->rawColumns(['avatar', 'type', 'action', 'checkbox']);
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(User $model, Request $request): QueryBuilder
    {
        if (Auth::user()->type == 'super admin') {
            $users = $model->where('type', 'company');
        } else {
            if (Auth::user()->isAbleTo('workspace manage')) {
                $users = $model->where('created_by', creatorId())->where('workspace_id', getActiveWorkSpace());
            } else {
                $users = $model->where('created_by', creatorId());
            }

            if ($request->name) {
                $users->where('name', 'like', '%' . $request->name . '%');
            }
            if($request->email)
            {
                $users->where('email', 'like', '%' . $request->email . '%');
            }
            if ($request->role) {
                $role = Role::find($request->role);
                $users = $users->where('type', $role->name);
            }
        }

        // Handle selected_ids from export request using trait method
        $this->handleSelectedIdsFilter($users);

        return $users;
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
                    var name = $("input[name=name]").val();
                    d.name = name

                    var email = $("input[name=email]").val();
                    d.email = email

                    var role = $("select[name=role]").val();
                    d.role = role
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
                "    if (!$(\"input[name=name]\").val() && !$(\"input[name=email]\").val() && !$(\"select[name=role]\").val()) {\n" .
                "        toastrs(\"Error!\", \"Please select Atleast One Filter \", \"error\");\n" .
                "        return;\n" .
                "    }\n" .
                "    $(\"#users-table\").DataTable().draw();\n" .
                "});\n\n" .
                "$(\"body\").on(\"click\", \"#clearfilter\", function() {\n" .
                "    $(\"input[name=name]\").val(\"\");\n" .
                "    $(\"select[name=role]\").val(\"\");\n" .
                "    $(\"input[name=email]\").val(\"\");\n" .
                "    $(\"#users-table\").DataTable().draw();\n" .
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
            Column::make('avatar')->title(__('Avatar')),
            Column::make('name')->title(__('Name')),
            Column::make('email')->title(__('Email')),
            Column::make('type')->title(__('Role'))->addClass('text-capitalize'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)

        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Users_' . date('YmdHis');
    }
}
