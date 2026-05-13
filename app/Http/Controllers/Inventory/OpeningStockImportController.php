<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Imports\OpeningStockImport;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Workdo\Taskly\Entities\Project;

class OpeningStockImportController extends Controller
{
    /**
     * Download the opening stock template Excel file.
     */
    public function downloadTemplate()
    {
        try {
            $fileName = 'opening_stock_template_' . date('YmdHis') . '.xlsx';
            
            return Excel::download(
                new \App\Exports\OpeningStockTemplateExport(),
                $fileName
            );
        } catch (\Exception $e) {
            Log::error('Error downloading opening stock template: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to download template: ') . $e->getMessage());
        }
    }

    /**
     * Import opening stock from Excel file.
     */
    public function import(Request $request)
    {
        try {
            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
                'file' => 'required|file|mimes:xlsx,csv',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            $projectId = $request->project_id;
            $file = $request->file('file');

            // Check if project belongs to current workspace
            $project = Project::where('id', $projectId)
                ->where('workspace', getActiveWorkSpace())
                ->first();

            if (!$project) {
                return redirect()->back()->with('error', __('Invalid project selected.'));
            }

            // Import the file
            $import = new OpeningStockImport($projectId);
            
            Excel::import($import, $file);

            // Get results
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            // Prepare summary message
            $totalRows = $importedCount + $skippedCount;
            $message = __("Total rows: {$totalRows}, Imported: {$importedCount}, Skipped: {$skippedCount}");

            if (!empty($errors)) {
                Log::warning('Opening stock import errors: ', $errors);
            }

            return redirect()->route('opening-stock.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error importing opening stock: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to import opening stock: ') . $e->getMessage());
        }
    }

    /**
     * Show the import form.
     */
    public function showImportForm()
    {
        if (\Auth::user()->isAbleTo('opening-stock create')) {
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->get()
                ->pluck('name', 'id');

            return view('opening-stock.import', compact('projects'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
