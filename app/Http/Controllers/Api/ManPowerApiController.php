<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManPowerMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @group Manpower
 * Endpoints for manpower management including daily work records
 */
class ManPowerApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('man-power manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            $query = ManPowerMaster::with('details.type', 'supplier', 'site', 'creator');

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $manpower = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $manpower,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Manpower index error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch manpower records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch data for create form.
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('man-power create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $manpowerTypes = \App\Models\ManPowerType::pluck('name', 'id');

            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            $suppliers = \App\Models\Supplier::where('category_id', 1)->pluck('name', 'id');

            $projectsQuery = \Workdo\Taskly\Entities\Project::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $projectsQuery->where('workspace', $workspaceId);
            }
            $sites = $projectsQuery->projectonly()->pluck('name', 'id');

            return response()->json([
                'manpowerTypes' => $manpowerTypes,
                'suppliers' => $suppliers,
                'sites' => $sites,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching create data', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource.
     *
     * @bodyParam work_date date required Work date. Example: 2024-01-15
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam supplier_id integer required Supplier ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam activity_completed_id integer required Activity completed ID. Example: 10
     * @bodyParam details array required Array of manpower details.
     * @bodyParam details.*.man_power_type_id integer required Manpower type ID. Example: 2
     * @bodyParam details.*.count integer required Count. Example: 5
     * @bodyParam reference_file file optional Reference document.
     * @response {"status": "success", "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('man-power create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validated = $request->validate([
                'work_date' => 'required|date',
                'site_id' => 'required|integer',
                'supplier_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'details' => 'required|array',
                'details.*.man_power_type_id' => 'required|exists:man_power_types,id',
                'details.*.count' => 'required|integer|min:0',
            ]);

            $totalCount = collect($validated['details'])->sum('count');

            $masterData = [
                'work_date' => $validated['work_date'],
                'activity_completed_id' => $validated['activity_completed_id'],
                'site_id' => $validated['site_id'],
                'workspace_id' => $validated['workspace_id'],
                'supplier_id' => $validated['supplier_id'],
                'created_by' => $validated['created_by'],
                'total_count' => $totalCount,
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                $fileName = time() . '_manpower_api_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'manpower');
                if ($upload['flag'] == 1) {
                    $masterData['reference_file'] = $upload['url'];
                }
            }

            $master = ManPowerMaster::create($masterData);

            foreach ($validated['details'] as $detail) {
                $master->details()->create($detail);
            }

           
            return response()->json([
                        'status' => true,
                        'message' => 'Activity created successfully',
                        'data' => $master->load('details.type'),
                            ], 201);
            
        } catch (ValidationException $e) {
            Log::warning('Validation failed in store', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Manpower store error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create manpower record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            return response()->json($manpower->load('details.type', 'supplier', 'site', 'creator'));
        } catch (\Exception $e) {
            Log::error('Manpower show error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'manpower_id' => $manpower->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch manpower record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validated = $request->validate([
                'work_date' => 'required|date',
                'site_id' => 'required|integer',
                'supplier_id' => 'required|integer',
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'activity_completed_id' => 'required|integer|exists:activities_completed,id',
                'details' => 'required|array',
                'details.*.man_power_type_id' => 'required|exists:man_power_types,id',
                'details.*.count' => 'required|integer|min:0',
            ]);

            $totalCount = collect($validated['details'])->sum('count');

            $masterData = [
                'work_date' => $validated['work_date'],
                'activity_completed_id' => $validated['activity_completed_id'],
                'site_id' => $validated['site_id'],
                'workspace_id' => $validated['workspace_id'],
                'supplier_id' => $validated['supplier_id'],
                'created_by' => $validated['created_by'],
                'total_count' => $totalCount,
            ];

            // Handle reference file upload
            if ($request->hasFile('reference_file')) {
                // Delete old file if exists
                if ($manpower->reference_file) {
                    $filePath = public_path($manpower->reference_file);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                $fileName = time() . '_manpower_api_' . $manpower->id . '_' . $request->file('reference_file')->getClientOriginalName();
                $upload = upload_file($request, 'reference_file', $fileName, 'manpower');
                if ($upload['flag'] == 1) {
                    $masterData['reference_file'] = $upload['url'];
                }
            }

            $manpower->update($masterData);

            $manpower->details()->delete();
            foreach ($validated['details'] as $detail) {
                $manpower->details()->create($detail);
            }

            return response()->json($manpower->load('details.type'));
        } catch (ValidationException $e) {
            Log::warning('Validation failed in update', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Manpower update error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'manpower_id' => $manpower->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update manpower record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(ManPowerMaster $manpower)
    {
        if (!Auth::user()->isAbleTo('man-power delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $manpower->details()->delete();
            $manpower->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Manpower record deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Manpower destroy error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'manpower_id' => $manpower->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete manpower record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}