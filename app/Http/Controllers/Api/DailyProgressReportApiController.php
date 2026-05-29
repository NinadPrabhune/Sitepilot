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
      *
      * @authenticated
      * @requiredPermission machinery-dpr manage
      *
      * @queryParam workspace_id integer optional Filter by workspace ID. Example: 1
      * @queryParam site_id integer optional Filter by site/project ID. Example: 5
      *
      * @response status=200 scenario="Success" {
      *   "success": true,
      *   "message": "Daily progress reports fetched successfully.",
      *   "data": [
      *     {
      *       "id": 1,
      *       "date": "2024-01-15",
      *       "machinery": {...}
      *     }
      *   ]
      * }
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
      *
      * @authenticated
      * @requiredPermission machinery-dpr create
      *
      * @queryParam site_id integer required Site ID to get machinery/materials. Example: 5
      * @queryParam created_by integer required Creator user ID. Example: 1
      * @queryParam workspace_id integer required Workspace ID. Example: 1
      *
      * @response status=200 scenario="Success" {
      *   "success": true,
      *   "message": "Data loaded successfully.",
      *   "machinery": [...],
      *   "materials": [...]
      * }
      * @response status=404 scenario="Not found" {"success": false, "message": "Machinery/Materials not found."}
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

        $machinery = Machinery::select('id', 'name', 'owned_by', 'registration_number')
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
      * Store a new Daily Progress Report with consumption details.
      *
      * @authenticated
      * @requiredPermission machinery-dpr create
      *
      * @bodyParam date date required Report date. Example: 2024-01-15
      * @bodyParam machinery_id integer required Machinery ID. Example: 10
      * @bodyParam machine_start_reading integer required Start meter reading. Example: 100
      * @bodyParam machine_end_reading integer required End meter reading. Example: 150
      * @bodyParam machine_idle_reading integer optional Idle meter reading. Example: 5
      * @bodyParam number_of_operators integer optional Number of operators. Example: 2
      * @bodyParam work_details string optional Work details description. Example: Excavation work
      * @bodyParam diesel_consumption number optional Diesel consumption. Example: 50.5
      * @bodyParam maintenance_notes string optional Maintenance notes. Example: Regular check
      * @bodyParam machinery_advances string optional Advances notes. Example: Advance paid
      * @bodyParam site_id integer required Site ID. Example: 5
      * @bodyParam workspace_id integer required Workspace ID. Example: 1
      * @bodyParam activity_completed_id integer required Activity completed ID. Example: 10
      * @bodyParam consumption_type string required Consumption type (fuel or all). Example: fuel
      * @bodyParam items array required Array of consumption items (use indexed notation).
      * @bodyParam items[0][material_id] integer required Material ID. Example: 10
      * @bodyParam items[0][quantity] number required Quantity. Example: 100.5
      * @bodyParam items[0][unit] string required Unit. Example: kg
      * @bodyParam items[0][remarks] string optional Remarks. Example: Used for mixing
      * @bodyParam items[1][material_id] integer required Material ID (if multiple). Example: 11
      * @bodyParam items[1][quantity] number required Quantity. Example: 50
      * @bodyParam items[1][unit] string required Unit. Example: liter
      * @bodyParam consumption_file file optional Consumption document (max 2MB, allowed: pdf,jpg,jpeg,png).
      *
      * @response status=201 scenario="Success" {
      *   "success": true,
      *   "message": "DPR created successfully",
      *   "data": {...}
      * }
      * @response status=403 scenario="Permission denied" {"success": false, "message": "Permission denied"}
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

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation Exception', [
            'message' => $e->getMessage(),
            'errors' => $e->errors()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
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
      * Show a single daily progress report.
      *
      * @authenticated
      * @requiredPermission machinery-dpr show
      *
      * @urlParam id integer required Daily Progress Report ID. Example: 1
      *
      * @response status=200 scenario="Success" {"success": true, "data": {...}}
      * @response status=404 scenario="Not found" {"success": false, "message": "Report not found."}
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
      * Update a daily progress report.
      *
      * @authenticated
      * @requiredPermission machinery-dpr edit
      *
      * @urlParam id integer required Daily Progress Report ID. Example: 1
      * @bodyParam date date required Report date. Example: 2024-01-15
      * @bodyParam machinery_id integer required Machinery ID. Example: 10
      * @bodyParam machine_start_reading integer optional Start meter reading. Example: 100
      * @bodyParam machine_end_reading integer optional End meter reading. Example: 150
      * @bodyParam number_of_operators integer optional Number of operators. Example: 2
      * @bodyParam work_details string optional Work details description. Example: Excavation work
      * @bodyParam diesel_consumption number optional Diesel consumption. Example: 50.5
      * @bodyParam maintenance_notes string optional Maintenance notes.
      * @bodyParam site_id integer required Site ID. Example: 5
      * @bodyParam workspace_id integer required Workspace ID. Example: 1
      * @bodyParam activity_completed_id integer required Activity completed ID. Example: 10
      * @bodyParam consumption_type string optional Consumption type (fuel or all). Example: fuel
      * @bodyParam items optional Array of consumption items (use indexed notation).
      * @bodyParam items[0][material_id] integer required Material ID. Example: 10
      * @bodyParam items[0][quantity] number required Quantity. Example: 100.5
      * @bodyParam items[0][unit] string required Unit. Example: kg
      * @bodyParam items[0][remarks] string optional Remarks.
      * @bodyParam consumption_file file optional Consumption document (max 2MB, allowed: pdf,jpg,jpeg,png).
      *
      * @response status=200 scenario="Success" {"success": true, "message": "DPR updated", "data": {...}}
      * @response status=403 scenario="Permission denied" {"success": false, "message": "Unauthorized action."}
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
      * Delete a daily progress report.
      *
      * @authenticated
      * @requiredPermission machinery-dpr delete
      *
      * @urlParam id integer required Daily Progress Report ID. Example: 1
      *
      * @response status=200 scenario="Success" {"success": true, "message": "DPR deleted successfully."}
      * @response status=403 scenario="Permission denied" {"success": false, "message": "Unauthorized action."}
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
