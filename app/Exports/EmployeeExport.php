<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Log;

/**
 * EmployeeExport - Custom export for HRM Employee DataTable
 * Uses proper JOIN queries to get employee data with relations
 * Only exports columns defined in EmployeeDataTable::getColumns()
 */
class EmployeeExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @var array Column data keys from DataTable (e.g., ['employee_id', 'name', 'email', ...])
     */
    protected array $columns;

    /**
     * @var array Column labels for headers
     */
    protected array $labels;

    /**
     * @var array IDs to export (empty = all)
     */
    protected array $ids;

    /**
     * @var bool Export all records
     */
    protected bool $exportAll;

    /**
     * Create a new export instance.
     *
     * @param array $columns Column data keys
     * @param array $labels Column headers
     * @param array $ids IDs to export
     * @param bool $exportAll Export all records
     */
    public function __construct(
        array $columns = [],
        array $labels = [],
        array $ids = [],
        bool $exportAll = false
    ) {
        $this->columns = $columns;
        $this->labels = $labels;
        $this->ids = $ids;
        $this->exportAll = $exportAll;

        // Log::info('===========================================');
        // Log::info('EmployeeExport INITIALIZED');
        // Log::info('===========================================');
        // Log::info('Columns (data keys):', $columns);
        // Log::info('Labels (headers):', $labels);
        // Log::info('IDs count:', ['count' => count($ids)]);
        // Log::info('ExportAll:', ['value' => $exportAll]);
    }

    /**
     * Build the query for export with proper JOINs
     */
    public function query()
    {
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();
        $creatorId = creatorId();

        // Log::info('------------------------------------------');
        // Log::info('EmployeeExport query() - Building query');
        // Log::info('------------------------------------------');
        // Log::info('Query params:', [
        //     'workspace' => $workspaceId,
        //     'site' => $siteId,
        //     'creator' => $creatorId
        // ]);

        // Build query with proper JOINs matching EmployeeDataTable
        $query = \App\Models\User::where('workspace_id', $workspaceId)
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
            ->where('users.created_by', $creatorId)
            ->emp()
            ->where('users.site_id', $siteId);

        // Filter by IDs if provided
        if (!$this->exportAll && !empty($this->ids)) {
            $query->whereIn('users.id', $this->ids);
            // Log::info('Filtering by IDs:', $this->ids);
        }

        // Select only the columns we need
        $selectFields = $this->getSelectFields();
        // Log::info('Final SELECT fields:', $selectFields);
        
        $query->selectRaw(implode(', ', $selectFields));

        // Log::info('Query built successfully');
        return $query;
    }

    /**
     * Get select fields for the query based on columns configuration
     * Maps data keys to database fields with proper aliases
     */
    protected function getSelectFields(): array
    {
        // Log::info('getSelectFields() - Mapping columns to database fields');
        
        // Map data key -> database field with table prefix
        // data: 'employee_id', name: 'users.id' -> 'users.id AS employee_id'
        // data: 'branch_id', name: 'branches.name' -> 'branches.name AS branch_id'
        $fieldMap = [
            'employee_id' => 'users.id',           // data=employee_id, name=users.id
            'name' => 'users.name',                 // data=name, name=users.name
            'email' => 'users.email',               // data=email, name=users.email
            'phone' => 'employees.phone',           // data=phone, name=employees.phone
            'gender' => 'employees.gender',        // data=gender, name=employees.gender
            'dob' => 'employees.dob',               // data=dob, name=employees.dob
            'address' => 'employees.address',       // data=address, name=employees.address
            'city' => 'employees.city',             // data=city, name=employees.city
            'state' => 'employees.state',           // data=state, name=employees.state
            'country' => 'employees.country',       // data=country, name=employees.country
            'zipcode' => 'employees.zipcode',       // data=zipcode, name=employees.zipcode
            'branch_id' => 'branches.name',         // data=branch_id, name=branches.name
            'department_id' => 'departments.name',  // data=department_id, name=departments.name
            'designation_id' => 'designations.name', // data=designation_id, name=designations.name
            'company_doj' => 'employees.company_doj', // data=company_doj, name=employees.company_doj
        ];

        $select = ['users.id'];

        // Log::info('Processing columns:', $this->columns);

        foreach ($this->columns as $column) {
            if (isset($fieldMap[$column])) {
                $dbField = $fieldMap[$column];
                $select[] = $dbField . ' AS ' . $column;
                Log::info("Mapped: $column -> $dbField AS $column");
            } else {
                Log::warning("Unknown column in export: $column");
            }
        }

        // Log::info('Final SELECT array:', $select);
        return $select;
    }

    /**
     * Get column headings for the export
     */
    public function headings(): array
    {
        // Log::info('------------------------------------------');
        // Log::info('EmployeeExport headings()');
        // Log::info('------------------------------------------');
        // Log::info('Labels received:', $this->labels);
        // Log::info('Columns received:', $this->columns);
        
        // Use provided labels if available
        if (!empty($this->labels)) {
            // Log::info('Using provided labels from DataTable');
            return $this->labels;
        }

        // Default headings based on columns - map to proper titles
        $titleMap = [
            'employee_id' => 'Employee ID',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'gender' => 'Gender',
            'dob' => 'Date of Birth',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'zipcode' => 'Zipcode',
            'branch_id' => 'Branch',
            'department_id' => 'Department',
            'designation_id' => 'Designation',
            'company_doj' => 'Date Of Joining',
        ];
        
        $headings = [];
        foreach ($this->columns as $column) {
            $headings[] = $titleMap[$column] ?? $column;
        }
        
        // Log::info('Generated headings:', $headings);
        return $headings;
    }

    /**
     * Map each row to an array
     */
    public function map($model): array
    {
        $data = [];

        foreach ($this->columns as $column) {
            // Get the value using the column alias (which is the data key)
            // The query uses 'field AS alias', so $model->alias should work
            $value = $model->{$column} ?? '';
            $data[] = $value;
        }

        return $data;
    }
}
