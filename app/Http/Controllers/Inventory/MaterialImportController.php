<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Imports\MaterialImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MaterialImportController extends Controller
{
    /**
     * Download the material import template Excel file.
     */
    public function downloadTemplate()
    {
        try {
            $fileName = 'material_import_template.xlsx';
            
            return Excel::download(
                new \App\Exports\MaterialTemplateExport(),
                $fileName
            );
        } catch (\Exception $e) {
            Log::error('Error downloading material template: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to download template: ') . $e->getMessage());
        }
    }

    /**
     * Show the dedicated material import page.
     */
    public function showImportPage()
    {
        if (!\Auth::user()->isAbleTo('material create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return view('materials.import-page');
    }

    /**
     * Process material import from dedicated page.
     */
    public function processImport(Request $request)
    {
        if (!\Auth::user()->isAbleTo('material create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // 5MB max
            ]);

            if ($validator->fails()) {
                return redirect()->route('materials.import.page')
                    ->with('error', $validator->errors()->first());
            }

            $file = $request->file('file');
            
            // Use database transaction for better performance
            DB::beginTransaction();
            
            try {
                $import = new MaterialImport();
                Excel::import($import, $file);
                
                DB::commit();
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                DB::rollBack();
                // Get validation errors from Excel import
                $failures = $e->failures();
                $errors = [];
                foreach ($failures as $failure) {
                    $errors[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
                }
                return redirect()->route('materials.import.page')
                    ->with('import_errors', $errors)
                    ->with('error', __('Import failed with validation errors.'));
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Check for column not found errors
                $errorMessage = $e->getMessage();
                $errors = [];
                
                if (str_contains($errorMessage, 'Column not found') || str_contains($errorMessage, 'Unknown column')) {
                    $errors[] = 'Database error - GST lookup failed. Please remove gst_rate from import file.';
                } else {
                    $errors[] = $errorMessage;
                }
                
                Log::error('Material Import Error', [
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString()
                ]);
                
                return redirect()->route('materials.import.page')
                    ->with('import_errors', $errors)
                    ->with('error', __('Failed to import materials: ') . $errorMessage);
            }

            // Get results
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            // Prepare summary message
            $totalRows = $importedCount + $skippedCount;
            $message = __("Total rows: {$totalRows}, Imported: {$importedCount}, Skipped: {$skippedCount}");

            // Log errors if any
            if (!empty($errors)) {
                Log::warning('Material Import Errors', [
                    'errors' => $errors,
                    'total_errors' => count($errors)
                ]);

                // Return with errors displayed
                return redirect()->route('materials.import.page')
                    ->with('success', $message)
                    ->with('import_errors', $errors);
            }

            return redirect()->route('material.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Material Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('materials.import.page')
                ->with('error', __('Failed to import materials: ') . $e->getMessage());
        }
    }

    /**
     * Import materials from Excel file.
     */
    public function import(Request $request)
    {
        try {
            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,csv',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            $file = $request->file('file');

            // Import the file
            $import = new MaterialImport();
            
            Excel::import($import, $file);

            // Get results
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            // Prepare summary message
            $totalRows = $importedCount + $skippedCount;
            $message = __("Total rows: {$totalRows}, Imported: {$importedCount}, Skipped: {$skippedCount}");

            // Log errors if any
            if (!empty($errors)) {
                Log::warning('Material Import Errors', [
                    'errors' => $errors,
                    'total_errors' => count($errors)
                ]);
            }

            // Store errors in session for display
            if (!empty($errors)) {
                session()->flash('import_errors', $errors);
            }

            return redirect()->route('material.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Material Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', __('Failed to import materials: ') . $e->getMessage());
        }
    }

    /**
     * Show the import form using AJAX modal.
     */
    public function showImportForm()
    {
        if (\Auth::user()->isAbleTo('material create')) {
            return view('materials.import');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Process the import with database transaction for better performance.
     */
    public function importWithTransaction(Request $request)
    {
        try {
            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,csv',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $file = $request->file('file');

            // Use database transaction for better performance
            DB::beginTransaction();
            
            try {
                $import = new MaterialImport();
                Excel::import($import, $file);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Get results
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            $totalRows = $importedCount + $skippedCount;

            return response()->json([
                'success' => true,
                'message' => "Total rows: {$totalRows}, Imported: {$importedCount}, Skipped: {$skippedCount}",
                'summary' => [
                    'total_rows' => $totalRows,
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                ],
                'errors' => array_slice($errors, 0, 10) // Return first 10 errors
            ]);
        } catch (\Exception $e) {
            Log::error('Material Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => __('Failed to import materials: ') . $e->getMessage()
            ], 500);
        }
    }
}
