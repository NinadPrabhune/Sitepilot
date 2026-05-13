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
                    return [$material->id => [
                        'name' => $material->name,
                        'unit' => $material->unit,
                        'category_id' => $material->category_id,
                        'category' => $material->category ? $material->category->name : null,
                    ]];
                });

            $materials_all = \App\Models\Material::with(['category:id,name', 'unit:id,name'])
                ->where('category_id', '!=', 2)
                ->get()
                ->mapWithKeys(function ($material) {
                    return [$material->id => [
                        'name' => $material->name,
                        'unit' => $material->unit,
                        'category_id' => $material->category_id,
                        'category' => $material->category ? $material->category->name : null,
                    ]];
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
