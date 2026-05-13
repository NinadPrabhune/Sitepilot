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
     * Display a listing of the general transfers.
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
     * Show data needed for creating a new general transfer.
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
     * Store a newly created general transfer in storage.
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
     * Display the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
