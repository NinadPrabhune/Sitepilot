<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericSelectedExport;
use App\Exports\SupplierSelectedExport;

/**
 * ExportController - Handles export requests for DataTable rows
 * 
 * Supports both:
 * - Generic export with dynamic model (for all DataTables using SelectableExportTrait)
 * - Specific export for existing Supplier export functionality (backward compatibility)
 * 
 * @package App\Http\Controllers
 */
class ExportController extends Controller
{
    /**
     * Export selected rows to Excel (Generic handler for all DataTables)
     * 
     * Parameters:
     * - model: Fully qualified model class name (e.g., App\Models\Supplier)
     * - ids: Comma-separated IDs to export (optional)
     * - all: 'true' to export all records (optional)
     * - columns: Comma-separated column names to export (optional)
     * - labels: Comma-separated column labels for headings (optional)
     * - prefix: Export file prefix (optional)
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportSelected(Request $request)
    {
        Log::info("=== EXPORT REQUEST START ===", [
            'model' => $request->model,
            'ids' => $request->ids,
            'all' => $request->all,
            'columns' => $request->columns,
            'labels' => $request->labels,
            'prefix' => $request->prefix
        ]);

        Log::info("Generic export request received", [
            'model' => $request->model,
            'ids' => $request->ids,
            'all' => $request->all,
            'columns' => $request->columns,
            'labels' => $request->labels,
            'prefix' => $request->prefix
        ]);

        $modelClass = $request->model;
        $ids = $request->ids;
        $exportAll = $request->all === 'true' || $request->all === true;

        Log::info('Export model class check', [
            'modelClass' => $modelClass,
            'modelClass_type' => gettype($modelClass),
            'exportAll' => $exportAll
        ]);

        // Validate model class
        if (empty($modelClass)) {
            return response()->json(['error' => 'Model class is required.'], 400);
        }

        if (!class_exists($modelClass)) {
            Log::error('Model class does not exist: ' . $modelClass);
            return response()->json(['error' => 'Invalid model class.'], 400);
        }

        $idArray = [];

        // If 'all=true', export all records without pagination restriction
        if ($exportAll) {
            $model = new $modelClass;
            $records = $model->get();
            
            if ($records->isEmpty()) {
                return response()->json(['error' => 'No records available to export.'], 400);
            }
            
            $idArray = $records->pluck('id')->toArray();
            Log::info("Exporting all records", [
                'model' => $modelClass, 
                'count' => count($idArray)
            ]);
        } else {
            // Validate IDs
            if (empty($ids)) {
                return response()->json(['error' => 'Please select at least one record to export.'], 400);
            }
            
            // Parse IDs from comma-separated string
            $idArray = is_array($ids) ? $ids : explode(',', $ids);
            
            // Sanitize - only allow numeric IDs
            $idArray = array_filter($idArray, function($id) {
                return is_numeric($id) && $id > 0;
            });
            
            $idArray = array_values($idArray);
            
            if (empty($idArray)) {
                return response()->json(['error' => 'No valid IDs provided for export.'], 400);
            }
            
            Log::info("Exporting selected IDs:", $idArray);
        }

        // Parse optional columns (JSON array from DataTable)
        $columns = null;
        if ($request->columns) {
            $columnsParam = $request->columns;
            // Try to parse as JSON first
            $decoded = json_decode($columnsParam, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $columns = $decoded;
            } else {
                // Fallback to comma-separated
                $columns = is_array($columnsParam) 
                    ? $columnsParam 
                    : explode(',', $columnsParam);
            }
        }

        // Parse optional labels (JSON array from DataTable)
        $labels = null;
        if ($request->labels) {
            $labelsParam = $request->labels;
            // Try to parse as JSON first
            $decoded = json_decode($labelsParam, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $labels = $decoded;
            } else {
                // Fallback to comma-separated
                $labels = is_array($labelsParam) 
                    ? $labelsParam 
                    : explode(',', $labelsParam);
            }
        }

        // Get file prefix
        $prefix = $request->prefix ?? $this->generatePrefixFromModel($modelClass);

        $fileName = $prefix . '_export_' . date('YmdHis') . '.xlsx';

        // Check if this is an Employee export (User model with employee columns)
        // Employee exports need special handling with JOINs
        // We check for employee-specific columns that only exist in employee data
        $employeeColumns = ['employee_id', 'phone', 'gender', 'dob', 'address', 'city', 'state', 'country', 'zipcode', 'branch_id', 'department_id', 'designation_id', 'company_doj'];
        
        // Also check these additional employee fields
        $allEmployeeCheckColumns = array_merge($employeeColumns, ['name', 'email']);
        
        Log::info('========================================================');
        Log::info('EXPORT CONTROLLER - DETAILED DEBUG');
        Log::info('========================================================');
        Log::info('Export check - raw request params:', [
            'model' => $request->model,
            'ids' => $request->ids,
            'all' => $request->all,
            'columns_raw' => $request->columns,
            'labels_raw' => $request->labels
        ]);
        
        Log::info('Export check - parsed columns:', [
            'columns' => $columns,
            'columns_count' => is_array($columns) ? count($columns) : 0,
            'is_array' => is_array($columns)
        ]);
        
        // Debug: Check if model matches - be more flexible
        $isUserModel = in_array($modelClass, [
            'App\\Models\\User',
            'App\\Models\\User',
            'User',
            'App\\User',
            'User'
        ]);
        
        // Debug: Check columns content - more comprehensive
        $hasEmployeeCols = 0;
        $matchingColumns = [];
        if (!empty($columns) && is_array($columns)) {
            $matchingColumns = array_intersect($columns, $employeeColumns);
            $hasEmployeeCols = count($matchingColumns);
            
            // Also check if we have at least name and email (basic user info that should be in employee export)
            $hasBasicUserInfo = count(array_intersect($columns, ['name', 'email'])) > 0;
            
            Log::info('Export column analysis:', [
                'received_columns' => $columns,
                'employee_columns_to_check' => $employeeColumns,
                'matching_columns' => $matchingColumns,
                'match_count' => $hasEmployeeCols,
                'has_basic_user_info' => $hasBasicUserInfo
            ]);
            
            // If we have name and email but they're part of the expected employee columns, treat as employee export
            if ($hasEmployeeCols === 0 && $hasBasicUserInfo && count($columns) >= 10) {
                $hasEmployeeCols = 1; // Treat as employee export
                Log::info('Treating as employee export based on column count and basic info');
            }
        } else {
            Log::warning('Export columns are empty or not an array!', [
                'columns_value' => $columns,
                'columns_type' => gettype($columns)
            ]);
        }
        
        Log::info('Export debug - final check:', [
            'modelClass' => $modelClass,
            'isUserModel' => $isUserModel,
            'hasColumns' => !empty($columns),
            'columnsCount' => is_array($columns) ? count($columns) : 0,
            'hasEmployeeCols' => $hasEmployeeCols,
            'matchingColumns' => $matchingColumns
        ]);
        
        // Consider it an employee export if:
        // 1. Model is User AND we have employee columns, OR
        // 2. Model is User AND we have enough columns (more than 10) suggesting it's a DataTable export
        $isEmployeeExport = (
            $isUserModel && 
            (
                (!empty($columns) && $hasEmployeeCols > 0) ||
                (is_array($columns) && count($columns) > 10)
            )
        );

        // Check if this is a Spent export using class_exists
        $isSpentExport = false; // Disable SpentExport to use GenericSelectedExport instead

        Log::info('Export check - model class comparison', [
            'modelClass' => $modelClass,
            'isSpentExport' => $isSpentExport,
            'class_exists' => class_exists($modelClass),
            'ends_with_Spent' => str_ends_with($modelClass, 'Spent')
        ]);
        Log::info('Export check - isEmployeeExport:', ['isEmployeeExport' => $isEmployeeExport]);
        Log::info('Export check - isSpentExport:', ['isSpentExport' => $isSpentExport]);

        // If this is an Employee export with exportAll, we need to get IDs using proper query with JOINs
        if ($isEmployeeExport && $exportAll) {
            $workspaceId = getActiveWorkSpace();
            $siteId = getActiveProject();
            $creatorId = creatorId();
            
            // Get IDs using the same query logic as EmployeeDataTable
            $userQuery = \App\Models\User::where('workspace_id', $workspaceId)
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                ->where('users.created_by', $creatorId)
                ->emp()
                ->where('users.site_id', $siteId);
            
            $idArray = $userQuery->pluck('users.id')->toArray();
            
            Log::info('EmployeeExport: Getting all employee IDs with proper query', [
                'count' => count($idArray),
                'workspace' => $workspaceId,
                'site' => $siteId
            ]);
            
            if (empty($idArray)) {
                return response()->json(['error' => 'No employee records available to export.'], 400);
            }
        }

        // If this is a Spent export with exportAll, let SpentExport handle the filtering
        // Don't pass IDs - let the export class handle workspace/project filtering
        if ($isSpentExport && $exportAll) {
            $idArray = []; // Empty array for exportAll - let SpentExport handle filtering
            Log::info('SpentExport: Using exportAll mode, letting SpentExport handle filtering');
        }

        if ($isEmployeeExport) {
            // Use EmployeeExport for employee data with proper JOINs
            Log::info('Creating EmployeeExport with:', [
                'columns' => $columns,
                'labels' => $labels,
                'ids_count' => count($idArray),
                'exportAll' => $exportAll
            ]);
            
            $export = new \App\Exports\EmployeeExport(
                $columns,
                $labels,
                $idArray,
                $exportAll
            );
            
            Log::info('Using EmployeeExport for employee data with JOINs');
        } elseif ($isSpentExport) {
            // Use SpentExport for spent data with proper JOINs
            Log::info('Creating SpentExport with:', [
                'columns' => $columns,
                'labels' => $labels,
                'ids_count' => count($idArray),
                'exportAll' => $exportAll
            ]);
            
            $export = new \App\Exports\SpentExport(
                $columns,
                $labels,
                $idArray,
                $exportAll
            );
            
            Log::info('Using SpentExport for spent data with JOINs');
        } else {
            // Use GenericSelectedExport for dynamic exports
            $export = new GenericSelectedExport(
                $modelClass,
                $idArray,
                $exportAll,
                $columns,
                $labels,
                $prefix
            );
        }

        return Excel::download($export, $fileName);
    }

    /**
     * Generate a file prefix from the model class name.
     * 
     * @param string $modelClass
     * @return string
     */
    private function generatePrefixFromModel(string $modelClass): string
    {
        // Get short name without namespace
        $shortName = class_basename($modelClass);
        
        // Convert to snake_case and pluralize simply
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_', $shortName));
        
        return $snakeCase . 's';
    }

    /**
     * Legacy export method for backward compatibility with Supplier exports.
     * 
     * @deprecated Use exportSelected() instead for new implementations
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportSupplierSelected(Request $request)
    {
        // For backward compatibility, delegate to exportSelected
        $request->merge(['model' => 'App\Models\Supplier']);
        return $this->exportSelected($request);
    }
}
