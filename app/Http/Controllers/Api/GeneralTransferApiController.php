<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\GeneralTransfer;
use App\Models\Machinery;
use App\Models\User;
use Workdo\Hrm\Entities\Employee;
use App\Models\AssetsToolsAndEquipment;
use Illuminate\Support\Facades\Auth;

/**
 * @group General Transfer
 * Endpoints for managing transfers of machinery, tools, equipment, and employees between sites
 */
class GeneralTransferApiController extends Controller {

    /**
     * List General Transfers
     *
     * Returns a list of transfers filtered by workspace, site, type, and date range.
     *
     * @authenticated
     * @requiredPermission general-transfer manage
     *
     * @queryParam workspace_id integer optional Filter by workspace ID. Example: 1
     * @queryParam site_id integer optional Filter by source site ID. Example: 1
     * @queryParam transfer_type string optional Filter by transfer type. Allowed: machinery, tools_and_equipment, employee. Example: machinery
     * @queryParam start_date date optional Filter transfers from this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date date optional Filter transfers up to this date (YYYY-MM-DD). Example: 2025-12-31
     *
     * @response status=200 scenario="Success"
     * {
     *   "status": "success",
     *   "data": [
     *     {"id": 1, "transfer_type": "machinery", "machinery_id": 5, "transfer_date": "2025-06-01", "from_site_id": 1, "to_site_id": 2, ...}
     *   ]
     * }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('general-transfer manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get filters from request
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $transferType = $request->input('transfer_type');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Build query with eager loading
            $query = GeneralTransfer::with(['machinery', 'toolsAndEquipment', 'employee', 'fromSite', 'toSite']);

            // Apply filters if provided
            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId)) {
                $query->where('from_site_id', $siteId);
            }

            if (!empty($transferType)) {
                $query->where('transfer_type', $transferType);
            }

            if (!empty($startDate)) {
                $query->whereDate('transfer_date', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('transfer_date', '<=', $endDate);
            }

            $transfers = $query->latest()->get();

            return response()->json([
                        'status' => 'success',
                        'data' => $transfers,
                            ], 200);
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer API index error: ' . $e->getMessage());

            return response()->json([
                        'status' => 'error',
                        'message' => 'Unable to load transfers.',
                        'error' => $e->getMessage(),
                            ], 500);
        }
    }

    /**
     * Get Transfer Create Data
     *
     * Retrieve reference data (machineries, tools, employees, sites) needed to create a new transfer.
     *
     * @authenticated
     *
     * @bodyParam transfer_type string optional Type to filter reference data. Example: machinery
     * @bodyParam employee_id integer optional Employee user ID. Example: 5
     * @bodyParam machinery_id integer optional Machinery ID. Example: 3
     * @bodyParam tools_and_equipment_id integer optional Tool/Equipment ID. Example: 2
     * @bodyParam user_id integer optional User ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * {
     *   "status": "success",
     *   "data": {
     *     "transfer_type": "machinery",
     *     "machineries": {"3": "Excavator"},
     *     "tools": null,
     *     "employees": null,
     *     "sites": {"1": "Main Site", "2": "Branch Site"},
     *     "users": null,
     *     "machineryId": 3
     *   }
     * }
     */
    public function createData(Request $request) {
        try {
            $transfer_type = $request->input('transfer_type');
            $employee_id = $request->input('employee_id');
            $machineryId = $request->input('machinery_id');
            $tools_and_equipment_id = $request->input('tools_and_equipment_id');
            $user_id = $request->input('user_id');

            $machineries = null;
            if ($transfer_type === 'machinery' && $machineryId) {
                $machineries = Machinery::where('id', $machineryId)->pluck('name', 'id');
            }

            $tools = null;
            if ($tools_and_equipment_id) {
                $tools = AssetsToolsAndEquipment::with('material')->find($tools_and_equipment_id);
            }

            $employees = null;
            if ($employee_id) {
                $employees = Employee::where('user_id', $employee_id)->first();
            }

//            $ActiveProjectIDArr[] = getActiveProject($user_id);
//            $to_site_id = getSitesWithWorkspace($ActiveProjectIDArr);
//
//            $fromSiteArr = getSitesWithWorkspaceAndSiteId($ActiveProjectIDArr);
//            $fromSiteId = $fromSiteArr->keys()->first();
//            $fromSiteName = $fromSiteArr->first();
//            $from_site_id = [$fromSiteId => $fromSiteName];
//        $users = User::pluck('name', 'id');
            $users = null;

            $sites = getSitesWithWorkspace();

            return response()->json([
                        'status' => 'success',
                        'data' => compact(
                                'transfer_type',
                                'machineries',
                                'tools',
                                'employees',
                                'sites',
                                'users',
                                'machineryId',
                        )
                            ], 200);
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer API createData error: ' . $e->getMessage());

            return response()->json([
                        'status' => 'error',
                        'message' => 'Unable to load create form data.',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Create General Transfer
     *
     * Create a new transfer of machinery, tools/equipment, or employee between sites.
     * Handles inventory adjustments and entity reassignments automatically.
     *
     * @authenticated
     * @requiredPermission general-transfer create
     *
     * @bodyParam transfer_type string required Type. Allowed: machinery, tools_and_equipment, employee. Example: machinery
     * @bodyParam machinery_id integer optional Machinery ID (required if transfer_type=machinery, must exist in machineries). Example: 3
     * @bodyParam tools_and_equipment_id integer optional Tool/Equipment ID (required if transfer_type=tools_and_equipment). Example: 2
     * @bodyParam employee_id integer optional Employee user ID (required if transfer_type=employee, must exist in employees). Example: 5
     * @bodyParam transfer_date date required Transfer date (YYYY-MM-DD). Example: 2025-06-01
     * @bodyParam transfer_qty integer optional Transfer quantity (required if transfer_type=tools_and_equipment, minimum 1). Example: 5
     * @bodyParam transfer_date_end date optional End date for temporary transfers (must be after or equal to transfer_date). Example: 2025-12-31
     * @bodyParam from_site_id integer required Source site/project ID. Example: 1
     * @bodyParam to_site_id integer required Destination site/project ID. Example: 2
     * @bodyParam operational_status string optional Status. Allowed: pending, active, completed, cancelled. Example: active
     * @bodyParam status boolean optional Active status. Example: 1
     *
     * @response status=201 scenario="Created successfully"
     * {
     *   "status": "success",
     *   "message": "Transfer created successfully.",
     *   "data": {
     *     "transfer": {"id": 1, "transfer_type": "machinery", ...},
     *     "updated": {"id": 3, "name": "Excavator", ...}
     *   }
     * }
     * @response status=422 scenario="Validation error or insufficient quantity"
     * { "status": "error", "message": "Insufficient quantity available." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('general-transfer create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
    \DB::beginTransaction();

    try {
        // ✅ Validate request
        $validated = $request->validate([
            'transfer_type'          => 'required|in:machinery,tools_and_equipment,employee',
            'machinery_id'           => 'nullable|exists:machineries,id',
            'tools_and_equipment_id' => 'nullable|exists:assets_tools_and_equipment,id',
            'employee_id'            => 'nullable|exists:employees,user_id',
            'transfer_date'          => 'required|date',
            'transfer_qty'           => 'required_if:transfer_type,tools_and_equipment|integer|min:1',
            'transfer_date_end'      => 'nullable|date|after_or_equal:transfer_date',
            'from_site_id'           => 'required|exists:projects,id',
            'to_site_id'             => 'required|exists:projects,id',
            'operational_status'     => 'nullable|in:pending,active,completed,cancelled',
            'status'                 => 'nullable|boolean',
        ]);

        // ✅ Add system fields
        $validated['created_by']   = creatorId();
        $validated['workspace_id'] = getWorkspaceIDFromSiteID($request->to_site_id);

        // ✅ Create transfer record
        $transfer = GeneralTransfer::create($validated);

        $transferType = $validated['transfer_type'];
        $workspaceId  = $validated['workspace_id'];
        $updatedEntity = null;

        // ✅ Machinery transfer
        if ($transferType === 'machinery') {
            if ($machinery = Machinery::find($request->machinery_id)) {
                $machinery->update([
                    'site_id'     => $request->to_site_id,
                    'workspace_id'=> $workspaceId,
                ]);
                $updatedEntity = $machinery;
            }
        }

        // ✅ Tools & Equipment transfer
        if ($transferType === 'tools_and_equipment') {
            $transferQty = (int) $request->transfer_qty;

            $fromTool = AssetsToolsAndEquipment::where('id', $request->tools_and_equipment_id)
                ->where('site_id', $request->from_site_id)
                ->where('workspace_id', getWorkspaceIDFromSiteID($request->from_site_id))
                ->first();

            if (!$fromTool || $fromTool->quantity < $transferQty) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Insufficient quantity available.'
                ], 422);
            }

            // Subtract from source
            $fromTool->decrement('quantity', $transferQty);

            // Add to destination (or create new)
            $toTool = AssetsToolsAndEquipment::firstOrNew([
                'material_id' => $fromTool->material_id,
                'site_id'     => $request->to_site_id,
                'workspace_id'=> $workspaceId,
            ]);

            $toTool->quantity += $transferQty;
            $toTool->operational_status = $toTool->operational_status ?? 'active';
            $toTool->created_by = $toTool->created_by ?? creatorId();
            $toTool->save();

            $updatedEntity = $toTool;
        }

        // ✅ Employee transfer
        if ($transferType === 'employee') {
            if ($employee = Employee::where('user_id', $request->employee_id)->first()) {
                $employee->update([
                    'workspace' => $workspaceId,
                ]);
                $updatedEntity = $employee;
            }

            if ($user = User::where('id', $request->employee_id)->first()) {
                $user->update([
                    'site_id'     => $request->to_site_id,
                    'workspace_id'=> $workspaceId,
                ]);
            }
        }

        \DB::commit();

        return response()->json([
            'status'  => 'success',
            'message' => 'Transfer created successfully.',
            'data'    => [
                'transfer' => $transfer,
                'updated'  => $updatedEntity,
            ]
        ], 201);

    } catch (\Exception $e) {
        \DB::rollBack();
        \Log::error('GeneralTransfer API store error: ' . $e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'An error occurred while creating the transfer.',
            'error'   => $e->getMessage()
        ], 500);
    }
}


    /**
     * Show General Transfer
     *
     * Retrieve details of a specific transfer including related machinery, employee, and equipment.
     *
     * @authenticated
     * @requiredPermission general-transfer show
     *
     * @urlParam id string required Transfer ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * {
     *   "status": "success",
     *   "data": {
     *     "transfer": {"id": 1, "transfer_type": "machinery", ...},
     *     "tools_and_equipment": null,
     *     "employee": null,
     *     "machinery": {"id": 3, "name": "Excavator"}
     *   }
     * }
     * @response status=404 scenario="Not found"
     * { "status": "error", "message": "Transfer not found." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show(string $id) {
        if (!Auth::user()->isAbleTo('general-transfer show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Find the transfer by ID
            $transfer = GeneralTransfer::with(['machinery', 'employee', 'toolsAndEquipment'])
                    ->findOrFail($id);

            return response()->json([
                        'status' => 'success',
                        'data' => [
                            'transfer' => $transfer,
                            'tools_and_equipment' => $transfer->toolsAndEquipment ?? null,
                            'employee' => $transfer->employee ?? null,
                            'machinery' => $transfer->machinery ?? null,
                        ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                        'status' => 'error',
                        'message' => 'Transfer not found.'
                            ], 404);
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer API show error: ' . $e->getMessage());

            return response()->json([
                        'status' => 'error',
                        'message' => 'Unable to fetch transfer details.',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Update General Transfer
     *
     * Update an existing transfer. Also updates related entity (machinery/tool/employee) site assignments.
     *
     * @authenticated
     * @requiredPermission general-transfer edit
     *
     * @urlParam id string required Transfer ID. Example: 1
     *
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam transfer_type string required Type. Allowed: machinery, tools_and_equipment, employee. Example: machinery
     * @bodyParam machinery_id integer optional Machinery ID (must exist in machineries). Example: 3
     * @bodyParam tools_and_equipment_id integer optional Tool/Equipment ID. Example: 2
     * @bodyParam employee_id integer optional Employee user ID (must exist in employees). Example: 5
     * @bodyParam transfer_date date required Transfer date (YYYY-MM-DD). Example: 2025-06-01
     * @bodyParam transfer_date_end date optional End date (must be after or equal to transfer_date). Example: 2025-12-31
     * @bodyParam from_site_id integer required Source site/project ID. Example: 1
     * @bodyParam to_site_id integer required Destination site/project ID. Example: 2
     * @bodyParam operational_status string optional Status. Allowed: pending, active, completed, cancelled. Example: active
     * @bodyParam status boolean optional Active status. Example: 1
     *
     * @response status=200 scenario="Updated successfully"
     * {
     *   "status": "success",
     *   "message": "Transfer updated successfully.",
     *   "data": {"id": 1, "transfer_type": "machinery", ...}
     * }
     * @response status=404 scenario="Not found"
     * { "status": "error", "message": "Transfer not found." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, string $id) {
        if (!Auth::user()->isAbleTo('general-transfer edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // ✅ Validate request
            $validated = $request->validate([
                'created_by' => 'required|integer',
                'transfer_type' => 'required|in:machinery,tools_and_equipment,employee',
                'machinery_id' => 'nullable|exists:machineries,id',
                'tools_and_equipment_id' => 'nullable|exists:assets_tools_and_equipment,id',
                'employee_id' => 'nullable|exists:employees,user_id',
                'transfer_date' => 'required|date',
                'transfer_date_end' => 'nullable|date|after_or_equal:transfer_date',
                'from_site_id' => 'required|exists:projects,id',
                'to_site_id' => 'required|exists:projects,id',
                'operational_status' => 'nullable|in:pending,active,completed,cancelled',
                'status' => 'nullable|boolean',
            ]);

            // ✅ Find transfer
            $transfer = GeneralTransfer::findOrFail($id);

            // ✅ Add system fields
            $validated['workspace_id'] = getWorkspaceIDFromSiteID($request->to_site_id);
            $validated['created_by'] = $request->created_by;

            // ✅ Update transfer record
            $transfer->update($validated);

            $workspaceId = getWorkspaceIDFromSiteID($request->to_site_id);
            $transferType = $request->transfer_type;

            /*
              |--------------------------------------------------------------------------
              | ✅ Update Related Models Based on Transfer Type
              |--------------------------------------------------------------------------
             */

            // ✅ Machinery Transfer
            if ($transferType === 'machinery' && $request->machinery_id) {
                if ($machinery = Machinery::find($request->machinery_id)) {
                    $machinery->update([
                        'site_id' => $request->to_site_id,
                        'workspace_id' => $workspaceId,
                    ]);
                }
            }

            // ✅ Tools & Equipment Transfer
            if ($transferType === 'tools_and_equipment' && $request->tools_and_equipment_id) {
                if ($tool = AssetsToolsAndEquipment::find($request->tools_and_equipment_id)) {
                    $tool->update([
                        'site_id' => $request->to_site_id,
                        'workspace_id' => $workspaceId,
                    ]);
                }
            }

            // ✅ Employee Transfer
            if ($transferType === 'employee' && $request->user_id) {

                // Update Employee table (workspace column)
                if ($employee = Employee::where('user_id', $request->user_id)->first()) {
                    $employee->update([
                        'workspace' => $workspaceId,
                    ]);
                }

                // Update User table (site + workspace)
                if ($user = User::find($request->user_id)) {
                    $user->update([
                        'site_id' => $request->to_site_id,
                        'workspace_id' => $workspaceId,
                    ]);
                }
            }

            return response()->json([
                        'status' => 'success',
                        'message' => 'Transfer updated successfully.',
                        'data' => $transfer
                            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                        'status' => 'error',
                        'message' => 'Transfer not found.'
                            ], 404);
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer API update error: ' . $e->getMessage());

            return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to update transfer.',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Delete General Transfer
     *
     * Permanently delete a general transfer record.
     *
     * @authenticated
     * @requiredPermission general-transfer delete
     *
     * @urlParam id string required Transfer ID. Example: 1
     *
     * @response status=200 scenario="Deleted successfully"
     * { "status": "success", "message": "Transfer deleted successfully." }
     * @response status=404 scenario="Not found"
     * { "status": "error", "message": "Transfer not found." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function destroy(string $id) {
        if (!Auth::user()->isAbleTo('general-transfer delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $transfer = GeneralTransfer::findOrFail($id);
            $transfer->delete();

            return response()->json([
                        'status' => 'success',
                        'message' => 'Transfer deleted successfully.'
                            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                        'status' => 'error',
                        'message' => 'Transfer not found.'
                            ], 404);
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer API destroy error: ' . $e->getMessage());

            return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to delete transfer.',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }
}
