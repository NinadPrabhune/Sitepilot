<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @group Daily Consumption
 * Endpoints for daily material consumption logging and tracking
 */
class DailyConsumptionApiController extends Controller
{
    /**
     * List all daily consumption records.
     *
     * @authenticated
     * @requiredPermission consumption-log manage
     *
     * @response status=200 scenario="Success" {"data": [{"id": 1, "consumption_date": "2024-01-15", "details": [...]}]}
     * @response status=403 scenario="Permission denied" {"status": 0, "message": "Permission denied"}
     */
    public function index()
    {
        if (!Auth::user()->isAbleTo('consumption-log manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $data = DailyConsumptionMaster::with('details.material', 'site', 'machinery')->get();
            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error fetching daily consumptions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch records'], 500);
        }
    }

    /**
     * Get data for creating a consumption record.
     *
     * @authenticated
     * @requiredPermission consumption-log create
     *
     * @queryParam workspace_id integer optional Workspace ID for lookups. Example: 1
     * @queryParam site_id integer optional Site ID for lookups. Example: 5
     *
     * @response status=200 scenario="Success" {
     *   "materials_fuels": {...},
     *   "materials_all": {...},
     *   "sites": [...],
     *   "next_consumption_number": "DCM-0001"
     * }
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('consumption-log create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            $materials_fuels = \App\Models\Material::with(['category:id,name', 'unit:id,name'])
                ->where('category_id', 2)
                ->get()
                ->mapWithKeys(function ($material) {
                    return [
                        $material->id => [
                            'name' => $material->name,
                            'unit' => $material->unit,
                            'category_id' => $material->category_id,
                            'category' => $material->category ? $material->category->name : null,
                        ]
                    ];
                });

            $materials_all = \App\Models\Material::with(['category:id,name', 'unit:id,name'])
                ->where('category_id', '!=', 2)
                ->get()
                ->mapWithKeys(function ($material) {
                    return [
                        $material->id => [
                            'name' => $material->name,
                            'unit' => $material->unit,
                            'category_id' => $material->category_id,
                            'category' => $material->category ? $material->category->name : null,
                        ]
                    ];
                });


            $machinery = \App\Models\Machinery::all();
            $machineryOptions = $machinery->mapWithKeys(function ($item) {
                return [$item->id => $item->name . ' (' . $item->vehicle_number . ')'];
            });

            $projectsQuery = \Workdo\Taskly\Entities\Project::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $projectsQuery->where('workspace', $workspaceId);
            }
            $sites = $projectsQuery->projectonly()->get()->pluck('name', 'id');

            $maxId = DailyConsumptionMaster::max('id');
            $i = $maxId ? $maxId + 1 : 1;
            $nextConsumptionNumber = 'DCM-' . str_pad($i, 4, '0', STR_PAD_LEFT);

            return response()->json([
                'materials_fuels' => $materials_fuels,
                'materials_all' => $materials_all,
                //                'machinery' => $machinery,
                'machinery_options' => $machineryOptions,
                'sites' => $sites,
                'next_consumption_number' => $nextConsumptionNumber,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching create data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load data'], 500);
        }
    }

    /**
     * Store a new consumption record.
     *
     * @authenticated
     * @requiredPermission consumption-log create
     *
     * @bodyParam consumption_date date required Consumption date. Example: 2024-01-15
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam consumption_type string required Type (all or fuel). Example: all
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam activity_completed_id integer required Activity completed ID. Example: 10
     * @bodyParam machinery_type string optional Type (own or rental). Example: rental
     * @bodyParam machinery_id integer optional Machinery ID. Example: 10
     * @bodyParam items array required Array of consumption items (use indexed notation).
     * @bodyParam items[0][material_id] integer required Material ID. Example: 10
     * @bodyParam items[0][quantity] number required Quantity. Example: 100.5
     * @bodyParam items[0][unit] string required Unit. Example: kg
     * @bodyParam items[0][remarks] string optional Remarks. Example: Used for construction
     * @bodyParam consumption_file file optional Consumption document (max 2MB).
     *
     * @response status=201 scenario="Success" {"success": true, "data": {...}}
     * @response status=403 scenario="Permission denied" {"status": 0, "message": "Permission denied"}
     * @response status=422 scenario="Validation error" {"errors": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('consumption-log create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        Log::info('Daily Consumption Store API called', [
            'request_data' => $request->all()
        ]);

        try {

            $validator = Validator::make($request->all(), [
                'consumption_date' => 'required|date',
                'site_id' => 'required|exists:projects,id',
                'consumption_type' => 'required|in:all,fuel',
                'machinery_type' => 'nullable|in:own,rental',
                'machinery_id' => 'nullable|exists:machineries,id',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'daily_progress_report_id' => 'nullable|integer',
                'consumption_file' => 'nullable',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string',
                'items.*.remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {

                Log::warning('Daily Consumption Validation Failed', [
                    'errors' => $validator->errors()->toArray()
                ]);

                return response()->json(['errors' => $validator->errors()], 422);
            }

            Log::info('Daily Consumption Validation Passed');

            $data = $validator->validated();

            $nextId = DailyConsumptionMaster::max('id') + 1;
            $data['consumption_number'] = 'DCM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            Log::info('Generated Consumption Number', [
                'consumption_number' => $data['consumption_number']
            ]);

            // Handle file upload
            if (isset($data['consumption_file']) && is_string($data['consumption_file'])) {

                Log::info('Using existing consumption file path', [
                    'path' => $data['consumption_file']
                ]);

            } elseif ($request->hasFile('consumption_file')) {

                Log::info('Uploading new consumption file');

                $file = $request->file('consumption_file');
                $filename = time() . '_consumption_' . $data['consumption_number'] . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('consumptions', $filename, 'public');
                $data['consumption_file'] = $path;

                Log::info('Consumption file uploaded', [
                    'stored_path' => $path
                ]);

            } else {

                Log::info('No consumption file provided');
                $data['consumption_file'] = null;
            }

            $master = DailyConsumptionMaster::create($data);

            Log::info('Daily Consumption Master Created', [
                'master_id' => $master->id
            ]);

            foreach ($request->items as $index => $item) {

                Log::info('Creating Consumption Detail', [
                    'index' => $index,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity']
                ]);

                DailyConsumptionDetails::create([
                    'daily_consumption_master_id' => $master->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            Log::info('All Consumption Details Created Successfully');

            return response()->json([
                'success' => true,
                'data' => $master->load('details'),
            ], 201);

        } catch (\Exception $e) {

            Log::error('Error storing daily consumption', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to create record'
            ], 500);
        }

    }

    /**
     * Display the specified consumption record.
     *
     * @authenticated
     * @requiredPermission consumption-log show
     *
     * @urlParam dailyConsumption required Daily Consumption ID (via route model binding). Example: 1
     *
     * @response status=200 scenario="Success" {"id": 1, "consumption_date": "2024-01-15", "details": [...]}
     * @response status=403 scenario="Permission denied" {"status": 0, "message": "Permission denied"}
     */
    public function show(DailyConsumptionMaster $dailyConsumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            return response()->json($dailyConsumption->load('details.material', 'site', 'machinery'));
        } catch (\Exception $e) {
            \Log::error('Error showing daily consumption: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch record'], 500);
        }
    }

    /**
     * Update the specified consumption record.
     *
     * @authenticated
     * @requiredPermission consumption-log edit
     *
     * @urlParam dailyConsumption required Daily Consumption ID (via route model binding). Example: 1
     * @bodyParam consumption_date date required Consumption date. Example: 2024-01-15
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam consumption_type string required Type (all or fuel). Example: all
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam activity_completed_id integer required Activity completed ID. Example: 10
     * @bodyParam machinery_type string optional Type (own or rental). Example: rental
     * @bodyParam machinery_id integer optional Machinery ID. Example: 10
     * @bodyParam items array required Array of consumption items (use indexed notation).
     * @bodyParam items[0][material_id] integer required Material ID. Example: 10
     * @bodyParam items[0][quantity] number required Quantity. Example: 100.5
     * @bodyParam items[0][unit] string required Unit. Example: kg
     * @bodyParam items[0][remarks] string optional Remarks. Example: Used for construction
     * @bodyParam consumption_file file optional Consumption document (max 2MB).
     *
     * @response status=200 scenario="Success" {"id": 1, "consumption_date": "2024-01-15", "details": [...]}
     * @response status=403 scenario="Permission denied" {"status": 0, "message": "Permission denied"}
     */
    public function update(Request $request, DailyConsumptionMaster $dailyConsumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = Validator::make($request->all(), [
                'consumption_date' => 'required|date',
                'site_id' => 'required|exists:projects,id',
                'consumption_type' => 'required|in:all,fuel',
                'machinery_type' => 'nullable|in:own,rental',
                'machinery_id' => 'nullable|exists:machineries,id',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string',
                'items.*.remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('consumption_file')) {
                if ($dailyConsumption->consumption_file) {
                    Storage::disk('public')->delete($dailyConsumption->consumption_file);
                }
                $file = $request->file('consumption_file');
                $filename = time() . '_consumption_' . $dailyConsumption->consumption_number . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('consumptions', $filename, 'public');
                $data['consumption_file'] = $path;
            }

            $dailyConsumption->update($data);
            $dailyConsumption->details()->delete();

            foreach ($request->items as $item) {
                DailyConsumptionDetails::create([
                    'daily_consumption_master_id' => $dailyConsumption->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return response()->json($dailyConsumption->load('details'), 200);
        } catch (\Exception $e) {
            \Log::error('Error updating daily consumption: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update record'], 500);
        }
    }

    /**
     * Delete the specified consumption record.
     *
     * @authenticated
     * @requiredPermission consumption-log delete
     *
     * @urlParam dailyConsumption required Daily Consumption ID (via route model binding). Example: 1
     *
     * @response status=200 scenario="Success" {"message": "Deleted successfully"}
     * @response status=403 scenario="Permission denied" {"status": 0, "message": "Permission denied"}
     */
    public function destroy(DailyConsumptionMaster $dailyConsumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            if ($dailyConsumption->consumption_file) {
                Storage::disk('public')->delete($dailyConsumption->consumption_file);
            }

            $dailyConsumption->details()->delete();
            $dailyConsumption->delete();

            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('Error deleting daily consumption: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete record'], 500);
        }
    }
}
