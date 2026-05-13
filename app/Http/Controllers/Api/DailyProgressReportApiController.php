<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\Machinery;   // ✅ Correct import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


/**
 * @group Daily Progress Report
 * Endpoints for daily progress report management including machinery tracking and consumption
 */
class DailyProgressReportApiController extends Controller
{
    /**
     * List all reports (filtered by workspace & project).
     */
    
    public function index(Request $request)
{
    try {
        $siteId = $request->input('site_id');
        $workspaceId = $request->input('workspace_id');

        // Base query
        $query = DailyProgressReport::where('status', 0)
            ->with([
                'machinery:id,name', // Only id and name
                'consumptionMaster.details' => function ($q) {
                    $q->select('id', 'daily_consumption_master_id', 'material_id', 'quantity') // Only needed columns
                      ->with(['material' => function ($mq) {
                          $mq->select('id', 'name', 'unit_id') // Only id, name, unit_id
                             ->with(['unit:id,name']); // Only unit id and name
                      }]);
                }
            ]);

        // Apply workspace filter
        if (!empty($workspaceId) && $workspaceId != 0) {
            $query->where('workspace_id', $workspaceId);
        }

        // Apply site filter
        if (!empty($siteId) && $siteId != 0) {
            $query->where('site_id', $siteId);
        }

        // Get results ordered by date
        $reports = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daily progress reports fetched successfully.',
            'data' => $reports
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error fetching daily progress reports: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch reports.',
            'error' => $e->getMessage()
        ], 500);
    }
}
    
//    public function index(Request $request)
//    {
//        try {
//            $siteId = $request->input('site_id');
//            $workspaceId = $request->input('workspace_id');
//
//            // Base query
//            $query = DailyProgressReport::where('status', 0)
//                ->with([
//                    // Only select id and name for machinery
//                    'machinery:id,name',
//                    // Nested eager load for consumption master -> details -> material -> unit
//                    'consumptionMaster.details.material.unit'
//                ]);
//
//            // Apply workspace filter
//            if (!empty($workspaceId) && $workspaceId != 0) {
//                $query->where('workspace_id', $workspaceId);
//            }
//
//            // Apply site filter
//            if (!empty($siteId) && $siteId != 0) {
//                $query->where('site_id', $siteId);
//            }
//
//            // Get results ordered by date
//            $reports = $query->orderBy('date', 'desc')->get();
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Daily progress reports fetched successfully.',
//                'data' => $reports
//            ], 200);
//
//        } catch (\Exception $e) {
//            \Log::error('Error fetching daily progress reports: ' . $e->getMessage());
//
//            return response()->json([
//                'success' => false,
//                'message' => 'Unable to fetch reports.',
//                'error' => $e->getMessage()
//            ], 500);
//        }
//    }
    
    
    
//    public function index(Request $request)
//    {
//        try {
//            $siteId = $request->input('site_id');
//            $workspaceId = $request->input('workspace_id');  
//
//            $query = DailyProgressReport::where('status', 0) ->with(['machinery:id,name']); // only id and name
//
//            if (!empty($workspaceId) && $workspaceId != 0) {
//                $query->where('workspace_id', $workspaceId);
//            }
//
//            if (!empty($siteId) && $siteId != 0) {
//                $query->where('site_id', $siteId);
//            }
//
//            $reports = $query->orderBy('date', 'desc')->get();
//
//            return response()->json([
//                'success' => true,
//                'data' => $reports
//            ], 200);
//
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Unable to fetch reports.',
//                'error' => $e->getMessage()
//            ], 500);
//        }
//    }
    
    /**
     * Load machinery data for creating a report.
     */
    public function createData(Request $request)
{
    if (!Auth::user()->isAbleTo('machinery-dpr create')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action.'
        ], 403);
    }

    try {
        $request->validate([
            'site_id' => 'required|integer',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
        ]);

        $siteId = $request->site_id;

        $machinery = Machinery::select('id', 'name', 'owned_by')
            ->where('site_id', $siteId)
            ->get();

        if ($machinery->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Machinery not found.'
            ], 404);
        }

        // ✅ Get all stock at once (NO N+1)
        $stockCollection = getCurrentStockBySiteId($siteId);

        // Convert to key-value array: material_id => total_qty
        $stockMap = collect($stockCollection)
            ->pluck('total_qty', 'material_id')
            ->toArray();

        $materials = \App\Models\Material::with(['category:id,name', 'unit:id,name'])
            ->where('category_id', 2)
            ->get()
            ->map(function ($m) use ($stockMap) {

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'current_stock' => $stockMap[$m->id] ?? 0, // ✅ Inject stock here
                    'category' => $m->category ? [
                        'id' => $m->category->id,
                        'name' => $m->category->name,
                    ] : null,
                    'unit' => $m->unit ? [
                        'id' => $m->unit->id,
                        'name' => $m->unit->name,
                    ] : null,
                ];
            });

        if ($materials->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Materials not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data loaded successfully.',
            'machinery' => $machinery,
            'materials' => $materials
        ], 200);

    } catch (\Exception $e) {

        \Log::error('Create Data Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Unable to load data.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Store a new report.
     */
    public function store(Request $request)
{
    Log::info('DPR Store API called', ['request' => $request->all()]);

    if (!Auth::user()->isAbleTo('machinery-dpr create')) {
        Log::warning('Unauthorized DPR attempt', ['user_id' => Auth::id()]);
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action.'
        ], 403);
    }

    try {
        $validated = $request->validate([
            'date' => 'required|date',
            'machinery_id' => 'required|exists:machineries,id',
            'machine_start_reading' => 'nullable|integer',
            'machine_end_reading' => 'nullable|integer',
            'machine_idle_reading' => 'nullable|integer',
            'number_of_operators' => 'nullable|integer',
            'work_details' => 'nullable|string',
            'diesel_consumption' => 'nullable|numeric',
            'maintenance_notes' => 'nullable|string',
            'machinery_advances' => 'nullable|string',
            'consumption_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'site_id' => 'required|integer',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',            
            'activity_completed_id' => 'required|integer|exists:activities_completed,id',
            'consumption_type' => 'required|in:fuel,all',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.remarks' => 'nullable|string',
        ]);

        Log::info('Validation passed');

        // Get machinery for validation and calculations
        $machinery = \App\Models\Machinery::find($request->machinery_id);

        // Validate meter readings
        $readingValidation = \App\Services\MeterReadingValidationService::validateReading($request->all(), $machinery);
        
        if (!$readingValidation['valid']) {
            Log::warning('Meter reading validation failed', ['errors' => $readingValidation['errors']]);
            return response()->json([
                'success' => false,
                'message' => 'Meter reading validation failed',
                'errors' => $readingValidation['errors']
            ], 422);
        }

        // Validate month is not closed
        \App\Services\MonthlyClosureService::validateMonthNotClosed(
            $request->workspace_id, 
            $request->site_id, 
            \Carbon\Carbon::parse($request->date), 
            'DPR creation'
        );

        // Calculate billable hours using centralized service
        $billableHours = \App\Services\MeterReadingValidationService::calculateBillableHours($request->all());
        
        // Calculate DPR amount using centralized service
        $calculatedAmount = \App\Services\MachineryBillingCalculatorService::calculateDprAmount($machinery, $billableHours);

        $report = DailyProgressReport::create([
            'date' => $request->date,
            'machinery_id' => $request->machinery_id,
            'machine_start_reading' => $request->machine_start_reading,
            'machine_end_reading' => $request->machine_end_reading,
            'machine_idle_reading' => $request->machine_idle_reading,
            'number_of_operators' => $request->number_of_operators,
            'work_details' => $request->work_details,
            'diesel_consumption' => $request->diesel_consumption,
            'maintenance_notes' => $request->maintenance_notes,
            'machinery_advances' => $request->machinery_advances,
            'billable_hours' => $billableHours,
            'calculated_amount' => $calculatedAmount,                
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
            'site_id' => $request->site_id,            
            'activity_completed_id' => $request->activity_completed_id,
            
        ]);

        Log::info('DPR Created', ['dpr_id' => $report->id]);

        $machinery = \App\Models\Machinery::find($request->machinery_id);
        $machineryType = $machinery && $machinery->owned_by === 'owned' ? 'own' : 'rental';

        Log::info('Machinery Type Determined', [
            'machinery_id' => $request->machinery_id,
            'machinery_type' => $machineryType
        ]);

        $consumptionFilePath = null;

        if ($request->hasFile('consumption_file')) {
            Log::info('Consumption file detected');

            $file = $request->file('consumption_file');
            $filename = time() . '_dpr_' . $report->id . '.' . $file->getClientOriginalExtension();
            $consumptionFilePath = $file->storeAs('consumptions', $filename, 'public');

            Log::info('File uploaded', ['path' => $consumptionFilePath]);
        }

        $consumptionData = [
            'daily_progress_report_id' => $report->id,
            'consumption_date' => $request->date,
            'site_id' => $request->site_id,
            'consumption_type' => $request->consumption_type,
            'machinery_type' => $machineryType,
            'machinery_id' => $request->machinery_id,
            'items' => $request->items,
            'consumption_file' => $consumptionFilePath,
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
            'activity_completed_id' => $request->activity_completed_id,
        ];

        Log::info('Calling Consumption Controller', ['payload' => $consumptionData]);

        $consumptionRequest = new \Illuminate\Http\Request($consumptionData);
        $consumptionController = app(\App\Http\Controllers\Api\DailyConsumptionApiController::class);
        $consumptionResponse = $consumptionController->store($consumptionRequest);

        Log::info('Consumption response received');

        $consumptionResult = json_decode($consumptionResponse->getContent(), true);

        Log::info('Decoded Consumption Result', ['result' => $consumptionResult]);

        if (!$consumptionResult || !($consumptionResult['success'] ?? false)) {

            Log::error('Consumption creation failed', ['response' => $consumptionResult]);

            $report->delete();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create consumption record.',
                'error' => $consumptionResult['error'] ?? 'Unknown error'
            ], 500);
        }

        Log::info('DPR & Consumption created successfully');

        return response()->json([
            'success' => true,
            'message' => 'Daily Progress Report & Consumption created successfully.',
            'data' => [
                'report' => $report,
                'calculation_summary' => [
                    'billable_hours' => $billableHours,
                    'calculated_amount' => $calculatedAmount,
                    'machinery_rate_type' => $machinery->rate_type,
                    'machinery_rate' => $machinery->rate,
                    'calculation_method' => $machinery->rate_type === 'monthly' ? 'handled_in_payment_request' : 'dpr_level'
                ]
            ],
            'consumption' => $consumptionResult,
            'validation_warnings' => $readingValidation['warnings'] ?? []
        ], 201);

    } catch (\Exception $e) {

        Log::error('DPR Creation Exception', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Creation failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Show a single report.
     */
    public function show($id)
    {
        try {
            $report = DailyProgressReport::with([
                'machinery',
                'consumptionMaster.details.material.unit'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $report
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a report.
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            $request->validate([
                'date' => 'required|date',
                'machinery_id' => 'required|exists:machineries,id',
                'machine_start_reading' => 'nullable|integer',
                'machine_end_reading' => 'nullable|integer',
                'number_of_operators' => 'nullable|integer',
                'work_details' => 'nullable|string',
                'diesel_consumption' => 'nullable|numeric',
                'maintenance_notes' => 'nullable|string',
                'machinery_advances' => 'nullable|string',
                'consumption_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',                
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'consumption_type' => 'nullable|in:fuel,all',
                'items' => 'nullable|array',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string',
                'items.*.remarks' => 'nullable|string',
            ]);

            $report = DailyProgressReport::findOrFail($id);

            $report->update($request->only([
                'date',
                'machinery_id',
                'machine_start_reading',
                'machine_end_reading',
                'number_of_operators',
                'work_details',
                'diesel_consumption',
                'maintenance_notes',
                'machinery_advances',
                'site_id',
                'workspace_id',             
                'activity_completed_id',
            ]));

            // Handle consumption update if items are provided
            if ($request->has('items') && is_array($request->items)) {
                // Get machinery owned_by for consumption
                $machinery = \App\Models\Machinery::find($request->machinery_id);
                $machineryType = $machinery && $machinery->owned_by === 'owned' ? 'own' : 'rental';

                // Handle file upload for ConsumptionMaster
                $consumptionFilePath = null;
                if ($report->consumptionMaster) {
                    $consumptionFilePath = $report->consumptionMaster->consumption_file;
                }
                if ($request->hasFile('consumption_file')) {
                    // Delete old file if exists
                    if ($consumptionFilePath) {
                        Storage::disk('public')->delete($consumptionFilePath);
                    }
                    $file = $request->file('consumption_file');
                    $filename = time() . '_dpr_' . $report->id . '_update.' . $file->getClientOriginalExtension();
                    $consumptionFilePath = $file->storeAs('consumptions', $filename, 'public');
                }

                // Update or create Consumption Master
                $master = $report->consumptionMaster;
                if (!$master) {
                    $master = \App\Models\DailyConsumptionMaster::create([
                        'daily_progress_report_id' => $report->id,
                        'consumption_type' => $request->consumption_type ?? 'fuel',
                        'machinery_id' => $report->machinery_id,
                        'consumption_date' => $report->date,
                        'machinery_type' => $machineryType,
                        'workspace_id' => $report->workspace_id,
                        'created_by' => $request->created_by ?? auth()->id(),
                        'status' => 1,
                        'consumption_file' => $consumptionFilePath,
                    ]);
                } else {
                    $master->update([
                        'machinery_id' => $report->machinery_id,
                        'machinery_type' => $machineryType,
                        'consumption_date' => $report->date,
                        'consumption_file' => $consumptionFilePath,
                    ]);
                }

                // Replace details
                $master->details()->delete();
                foreach ($request->items as $item) {
                    $master->details()->create([
                        'material_id' => $item['material_id'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'remarks' => $item['remarks'] ?? null,
                    ]);
                }
            }

            // Reload the report with consumption data
            $report->load(['machinery', 'consumptionMaster.details.material.unit']);

            return response()->json([
                'success' => true,
                'message' => 'Daily Progress Report & Consumption updated successfully.',
                'data' => $report
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a report.
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            $report = DailyProgressReport::findOrFail($id);
            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Daily Progress Report deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
