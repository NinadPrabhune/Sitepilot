<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DefaultMasterDataService
 * 
 * Service class to manage global master data for the application.
 * Converts workspace-based default data into GLOBAL MASTER DATA.
 * 
 * This service ensures idempotent operations - multiple runs will not create duplicates.
 * 
 * @author Developer
 */
class DefaultMasterDataService
{
    /**
     * Default Document Types
     */
    protected $documentTypes = [
        'Employee Provident Fund Upload',
        'ESIC CARD Upload',
        'Bank Details Upload',
        'Aadhar Card Upload',
    ];

    /**
     * Default Leave Types
     */
    protected $leaveTypes = [
        ['title' => 'Casual', 'days' => 10],
    ];

    /**
     * Default Branches
     */
    protected $branches = [
        'Head Office (Corporate)',
        'Project Site Office',
    ];

    /**
     * Default Departments (mapped to branches)
     */
    protected $departments = [
        ['branch' => 'Head Office (Corporate)', 'name' => 'Administration & HR'],
        ['branch' => 'Head Office (Corporate)', 'name' => 'Finance & Accounts'],
        ['branch' => 'Project Site Office', 'name' => 'Project Management'],
    ];

    /**
     * Default Designations (mapped to branches and departments)
     */
    protected $designations = [
        ['branch' => 'Head Office (Corporate)', 'department' => 'Administration & HR', 'name' => 'HR Manager'],
        ['branch' => 'Head Office (Corporate)', 'department' => 'Finance & Accounts', 'name' => 'Account Manager'],
        ['branch' => 'Project Site Office', 'department' => 'Project Management', 'name' => 'Project Manager'],
    ];

    /**
     * Seed all default master data
     * 
     * If $forceReset is true, truncates all tables and reseeds.
     * Otherwise, only inserts data if it doesn't already exist.
     * 
     * @param bool $forceReset If true, truncate tables before seeding
     * @return array Result message and status
     */
    public function seedAll($forceReset = false)
    {
        try {
            DB::beginTransaction();

            if ($forceReset) {
                $this->truncateAndReset();
            }

            // Seed all data (idempotent - uses firstOrCreate/updateOrInsert)
            $this->seedDocumentTypes();
            $this->seedLeaveTypes();
            $this->seedBranches();
            $this->seedDepartments();
            $this->seedDesignations();

            DB::commit();

            return [
                'success' => true,
                'message' => $forceReset 
                    ? 'Master data reset and seeded successfully.' 
                    : 'Master data seeded successfully (existing data preserved).',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to seed master data: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Truncate all related tables and reset IDs
     * 
     * Note: Order matters due to foreign key constraints.
     * Designations depend on departments, departments depend on branches.
     * 
     * @return void
     */
    public function truncateAndReset()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Truncate in correct order (child tables first)
        // Note: document_types and leave_types don't have foreign keys from our tables
        // but we truncate them first for clean slate
        $this->truncateTable('designations');
        $this->truncateTable('departments');
        $this->truncateTable('branches');
        $this->truncateTable('leave_types');
        $this->truncateTable('document_types');

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Helper method to truncate a table safely
     * 
     * @param string $table
     * @return void
     */
    protected function truncateTable($table)
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
        }
    }

    /**
     * Seed document types (idempotent)
     * 
     * @return void
     */
    protected function seedDocumentTypes()
    {
        foreach ($this->documentTypes as $docName) {
            // Check if exists before inserting
            $exists = DB::table('document_types')
                ->where('name', $docName)
                ->exists();

            if (!$exists) {
                DB::table('document_types')->insert([
                    'name'        => $docName,
                    'is_required' => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    /**
     * Seed leave types (idempotent)
     * 
     * @return void
     */
    protected function seedLeaveTypes()
    {
        foreach ($this->leaveTypes as $leave) {
            // Check if exists before inserting
            $exists = DB::table('leave_types')
                ->where('title', $leave['title'])
                ->exists();

            if (!$exists) {
                DB::table('leave_types')->insert([
                    'title'      => $leave['title'],
                    'days'       => $leave['days'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed branches (idempotent)
     * 
     * @return array Branch IDs indexed by name
     */
    protected function seedBranches()
    {
        $branchIds = [];

        foreach ($this->branches as $branchName) {
            // Check if exists before inserting
            $existing = DB::table('branches')
                ->where('name', $branchName)
                ->first();

            if ($existing) {
                $branchIds[$branchName] = $existing->id;
            } else {
                $branchIds[$branchName] = DB::table('branches')->insertGetId([
                    'name'       => $branchName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $branchIds;
    }

    /**
     * Seed departments (idempotent)
     * 
     * @param array|null $branchIds Branch IDs from seedBranches
     * @return array Department IDs indexed by name
     */
    protected function seedDepartments($branchIds = null)
    {
        // If no branchIds provided, get them from database
        if ($branchIds === null) {
            $branchIds = [];
            foreach ($this->branches as $branchName) {
                $branch = DB::table('branches')
                    ->where('name', $branchName)
                    ->first();
                if ($branch) {
                    $branchIds[$branchName] = $branch->id;
                }
            }
        }

        $departmentIds = [];

        foreach ($this->departments as $dept) {
            // Get branch_id
            $branchId = $branchIds[$dept['branch']] ?? null;
            
            if (!$branchId) {
                continue;
            }

            // Check if exists before inserting (match by name and branch_id)
            $existing = DB::table('departments')
                ->where('name', $dept['name'])
                ->where('branch_id', $branchId)
                ->first();

            if ($existing) {
                $departmentIds[$dept['name']] = $existing->id;
            } else {
                $departmentIds[$dept['name']] = DB::table('departments')->insertGetId([
                    'branch_id'  => $branchId,
                    'name'       => $dept['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $departmentIds;
    }

    /**
     * Seed designations (idempotent)
     * 
     * @param array|null $branchIds Branch IDs from seedBranches
     * @param array|null $departmentIds Department IDs from seedDepartments
     * @return void
     */
    protected function seedDesignations($branchIds = null, $departmentIds = null)
    {
        // If no branchIds provided, get them from database
        if ($branchIds === null) {
            $branchIds = [];
            foreach ($this->branches as $branchName) {
                $branch = DB::table('branches')
                    ->where('name', $branchName)
                    ->first();
                if ($branch) {
                    $branchIds[$branchName] = $branch->id;
                }
            }
        }

        // If no departmentIds provided, get them from database
        if ($departmentIds === null) {
            $departmentIds = [];
            foreach ($this->departments as $dept) {
                $branchId = $branchIds[$dept['branch']] ?? null;
                if ($branchId) {
                    $department = DB::table('departments')
                        ->where('name', $dept['name'])
                        ->where('branch_id', $branchId)
                        ->first();
                    if ($department) {
                        $departmentIds[$dept['name']] = $department->id;
                    }
                }
            }
        }

        foreach ($this->designations as $designation) {
            $branchId = $branchIds[$designation['branch']] ?? null;
            $departmentId = $departmentIds[$designation['department']] ?? null;

            if (!$branchId || !$departmentId) {
                continue;
            }

            // Check if exists before inserting (match by name, branch_id, and department_id)
            $exists = DB::table('designations')
                ->where('name', $designation['name'])
                ->where('branch_id', $branchId)
                ->where('department_id', $departmentId)
                ->exists();

            if (!$exists) {
                DB::table('designations')->insert([
                    'branch_id'    => $branchId,
                    'department_id'=> $departmentId,
                    'name'         => $designation['name'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    /**
     * Get all branches
     * 
     * @return array
     */
    public function getBranches()
    {
        return DB::table('branches')->get();
    }

    /**
     * Get all departments
     * 
     * @return array
     */
    public function getDepartments()
    {
        return DB::table('departments')->get();
    }

    /**
     * Get all designations
     * 
     * @return array
     */
    public function getDesignations()
    {
        return DB::table('designations')->get();
    }

    /**
     * Get all document types
     * 
     * @return array
     */
    public function getDocumentTypes()
    {
        return DB::table('document_types')->get();
    }

    /**
     * Get all leave types
     * 
     * @return array
     */
    public function getLeaveTypes()
    {
        return DB::table('leave_types')->get();
    }
}
