<?php

namespace App\Http\Controllers;

use App\Imports\SupplierImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SupplierImportController extends Controller
{
    /**
     * Download the supplier import template Excel file.
     */
    public function downloadTemplate()
    {
        try {
            $fileName = 'supplier_import_template.xlsx';
            
            return Excel::download(
                new \App\Exports\SupplierTemplateExport(),
                $fileName
            );
        } catch (\Exception $e) {
            Log::error('Error downloading supplier template: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to download template: ') . $e->getMessage());
        }
    }

    /**
     * Show the dedicated supplier import page.
     */
    public function showImportPage()
    {
        if (!\Auth::user()->isAbleTo('supplier create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return view('suppliers.import-page');
    }

    /**
     * Import suppliers from Excel file - dedicated page handler.
     */
    public function processImport(Request $request)
    {
        if (!\Auth::user()->isAbleTo('supplier create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            if ($validator->fails()) {
                return redirect()->route('suppliers.import.page')
                    ->with('error', $validator->errors()->first());
            }

            $file = $request->file('file');
            
            // Use database transaction for better performance
            DB::beginTransaction();
            
            try {
                $import = new SupplierImport();
                Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::XLSX, [
                    'chunk_size' => 1000,
                ]);
                
                DB::commit();
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                DB::rollBack();
                // Get validation errors from Excel import
                $failures = $e->failures();
                $errors = [];
                foreach ($failures as $failure) {
                    $errors[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
                }
                return redirect()->route('suppliers.import.page')
                    ->with('import_errors', $errors)
                    ->with('error', __('Import failed with validation errors.'));
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
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
                Log::warning('Supplier Import Errors', [
                    'errors' => $errors,
                    'total_errors' => count($errors)
                ]);

                // Return with errors displayed
                return redirect()->route('suppliers.import.page')
                    ->with('success', $message)
                    ->with('import_errors', $errors);
            }

            return redirect()->route('supplier.index')->with('success', $message);
        } catch (\Exception $e) {
            // Check if it's a validation error from Laravel Excel
            $errorMessage = $e->getMessage();
            $errors = [];
            
            // Try to extract validation errors from the exception message
            if (preg_match('/The (\d+)\.(\w+) (.+)/', $errorMessage, $matches)) {
                $errors[] = $errorMessage;
            }
            
            Log::error('Supplier Import Error', [
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString()
            ]);
            
            if (!empty($errors)) {
                return redirect()->route('suppliers.import.page')
                    ->with('import_errors', $errors)
                    ->with('error', __('Failed to import suppliers: ') . $errorMessage);
            }
            
            return redirect()->route('suppliers.import.page')
                ->with('error', __('Failed to import suppliers: ') . $errorMessage);
        }
    }

    /**
     * Import suppliers from Excel file.
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

            // Use database transaction for better performance
            DB::beginTransaction();
            
            try {
                $import = new SupplierImport();
                Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::XLSX, [
                    'chunk_size' => 1000,
                ]);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
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
                Log::warning('Supplier Import Errors', [
                    'errors' => $errors,
                    'total_errors' => count($errors)
                ]);
            }

            // Store errors in session for display
            if (!empty($errors)) {
                session()->flash('import_errors', $errors);
            }

            return redirect()->route('supplier.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Supplier Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', __('Failed to import suppliers: ') . $e->getMessage());
        }
    }

    /**
     * Show the import form using AJAX modal.
     */
    public function showImportForm()
    {
        if (\Auth::user()->isAbleTo('supplier create')) {
            return view('suppliers.import');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
