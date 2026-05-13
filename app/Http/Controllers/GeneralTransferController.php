<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaterialTransfer;
use App\Models\MaterialTransferItem;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;
use Illuminate\Support\Facades\Auth;
use App\Domain\Machinery\Services\MachineryLedgerService;

class GeneralTransferController extends Controller {

    /**
     * Display a listing of the general transfers.
     */
    public function index(\App\DataTables\GeneralTransferDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('general-transfer manage')) {
        return $dataTable->render('general_transfer.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new general transfer.
     */
    public function create(Request $request) {
        try {
            $transfer_type = $request->query('transfer_type');

            $machineryId = $request->query('machinery_id');

            $tools_and_equipment_Id = $request->query('tools_and_equipment_id');

            $employee_Id = $request->query('employee_id');

            $machineries = $machineryId ? Machinery::where('id', $machineryId)->pluck('name', 'id') : Machinery::pluck('name', 'id');

            $toolsQuery = AssetsToolsAndEquipment::with('material');

            if ($tools_and_equipment_Id) {
                $toolsQuery->where('id', $tools_and_equipment_Id);
            }

            $tools = AssetsToolsAndEquipment::with('material')
                ->where('workspace_id', getActiveWorkSpace())
                ->where('site_id', getActiveProject())
                ->get()
                ->mapWithKeys(function ($tool) {
                    return [
                        $tool->id => [
                            'name'     => $tool->material ? $tool->material->name : '',
                            'quantity' => $tool->quantity, // ✅ direct quantity from table
                        ],
                    ];
                });




            
            
            
//            dd($tools);

            $employees = $employee_Id ? Employee::where('user_id', $employee_Id)->pluck('name', 'user_id') : Employee::pluck('name', 'user_id');

//            dd($employees);

            $ActiveProjectIDArr[] = getActiveProject();

            $to_site_id = getSitesWithWorkspace($ActiveProjectIDArr);

            $fromSiteArr = getSitesWithWorkspaceAndSiteId($ActiveProjectIDArr);
            $fromSiteId = $fromSiteArr->keys()->first();
            $fromSiteName = $fromSiteArr->first();
            $from_site_id = [$fromSiteId => $fromSiteName];

            $users = User::pluck('name', 'id');

            return view('general_transfer.create', compact(
                            'transfer_type',
                            'machineries',
                            'machineryId',
                            'tools',
                            'tools_and_equipment_Id',
                            'employees',
                            'employee_Id',
                            'to_site_id',
                            'from_site_id',
                            'users',
                            'fromSiteId'
                    ));
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer create error: ' . $e->getMessage());

            return redirect()
                            ->back()
                            ->with('error', 'Unable to load create form. Please try again.');
        }
    }

    /**
     * Store a newly created general transfer in storage.
     */
    public function store(Request $request)
{
    \DB::beginTransaction();

    try {
        // ✅ Validate request
        $validated = $request->validate([
            'transfer_type'        => 'required|in:machinery,tools_and_equipment,employee',
            'machinery_id'         => 'nullable|exists:machineries,id',
            'tools_and_equipment_id'=> 'nullable|exists:assets_tools_and_equipment,id',
            'employee_id'          => 'nullable|exists:employees,user_id',
            'transfer_date'        => 'required|date',
            'transfer_qty'         => 'required_if:transfer_type,tools_and_equipment|integer|min:1',
            'transfer_date_end'    => 'nullable|date|after_or_equal:transfer_date',
            'from_site_id'         => 'required|exists:projects,id',
            'to_site_id'           => 'required|exists:projects,id',
            'operational_status'   => 'nullable|in:pending,active,completed,cancelled',
            'status'               => 'nullable|boolean',
            'transport_cost'       => 'nullable|numeric|min:0',
        ]);

        // ✅ Add system fields
        $validated['created_by']   = creatorId();
        $validated['workspace_id'] = getWorkspaceIDFromSiteID($request->to_site_id);
        $validated['transport_cost'] = $request->transport_cost ?? 0;

        // ✅ Create transfer record
        $transfer = GeneralTransfer::create($validated);

        // Create ledger entry for transport cost (if applicable)
        if ($validated['transport_cost'] > 0 && $transfer->machinery_id) {
            $ledgerEntry = MachineryLedgerService::createDebit([
                'machinery_id' => $transfer->machinery_id,
                'amount' => $validated['transport_cost'],
                'reference_type' => 'GeneralTransfer',
                'reference_id' => $transfer->id,
                'entry_type' => 'transfer',
                'date' => $validated['transfer_date'],
                'description' => "Transport cost for transfer #{$transfer->id}",
                'metadata' => [
                    'from_site_id' => $transfer->from_site_id,
                    'to_site_id' => $transfer->to_site_id,
                ],
            ]);

            $transfer->update(['ledger_entry_id' => $ledgerEntry->id]);
        }

        $transferType = $request->input('transfer_type');
        $workspaceId  = getWorkspaceIDFromSiteID($request->to_site_id);

        // ✅ Machinery transfer
        if ($transferType === 'machinery') {
            if ($machinery = Machinery::find($request->machinery_id)) {
                $machinery->update([
                    'site_id'     => $request->to_site_id,
                    'workspace_id'=> $workspaceId,
                ]);
            }

            \DB::commit();
            return redirect()
                ->route('machineries.index')
                ->with('success', 'Machinery transferred successfully.');
        }

        // ✅ Tools & Equipment transfer
        if ($transferType === 'tools_and_equipment') {
            $transferQty = (int) $request->transfer_qty;

            $fromTool = AssetsToolsAndEquipment::where('id', $request->tools_and_equipment_id)
                ->where('site_id', $request->from_site_id)
                ->where('workspace_id', getWorkspaceIDFromSiteID($request->from_site_id))
                ->first();

            if (!$fromTool || $fromTool->quantity < $transferQty) {
                return redirect()->back()->with('error', 'Insufficient quantity available.');
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

            \DB::commit();
            return redirect()
                ->route('assets_tools_and_equipment.index')
                ->with('success', 'Tools/Equipment transferred successfully.');
        }

        // ✅ Employee transfer
        if ($transferType === 'employee') {
            if ($employee = Employee::where('user_id', $request->employee_id)->first()) {
                $employee->update([
                    'workspace' => $workspaceId,
                ]);
            }

            if ($user = User::where('id', $request->employee_id)->first()) {
                $user->update([
                    'site_id'     => $request->to_site_id,
                    'workspace_id'=> $workspaceId,
                ]);
            }

            \DB::commit();
            return redirect()
                ->route('employee.index')
                ->with('success', 'Employee transferred successfully.');
        }

        \DB::commit();
        return redirect()->back()->with('success', 'Transfer created successfully.');

    } catch (\Exception $e) {
        \DB::rollBack();
        \Log::error('GeneralTransfer store error: ' . $e->getMessage());

        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'An error occurred while creating the transfer. Please try again.');
    }
}


    public function show(string $id) {
        try {
            // ✅ Load transfer with relationships
            $transfer = GeneralTransfer::with([
                        'machinery',
                        'tool', // relationship for tools_and_equipment
                        'employee',
                        'fromSite',
                        'toSite',
                        'creator'
                    ])->findOrFail($id);

            // ✅ Determine transfer type
            $transferType = $transfer->transfer_type;

            // ✅ Prepare related data for the view
            $machinery = $transferType === 'machinery' ? $transfer->machinery : null;
            $tool = $transferType === 'tools_and_equipment' ? $transfer->tool : null;
            $employee = $transferType === 'employee' ? $transfer->employee : null;

            return view('general_transfer.show', compact(
                            'transfer',
                            'transferType',
                            'machinery',
                            'tool',
                            'employee'
                    ));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()
                            ->route('general_transfer.index')
                            ->with('error', 'Transfer not found.');
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer Web Show Error: ' . $e->getMessage());

            return redirect()
                            ->route('general_transfer.index')
                            ->with('error', 'Unable to load transfer details.');
        }
    }

    public function update(Request $request, string $id) {
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

                return redirect()
                                ->route('machineries.index')
                                ->with('success', 'Transfer updated successfully.');
            }

            // ✅ Tools & Equipment Transfer
            if ($transferType === 'tools_and_equipment' && $request->tools_and_equipment_id) {
                if ($tool = AssetsToolsAndEquipment::find($request->tools_and_equipment_id)) {
                    $tool->update([
                        'site_id' => $request->to_site_id,
                        'workspace_id' => $workspaceId,
                        'transfer_qty' => $request->transfer_qty,
                    ]);
                }

                return redirect()
                                ->route('assets_tools_and_equipment.index')
                                ->with('success', 'Transfer updated successfully.');
            }

            // ✅ Employee Transfer
            if ($transferType === 'employee' && $request->employee_id) {

                // Update Employee table (workspace column)
                if ($employee = Employee::where('user_id', $request->employee_id)->first()) {
                    $employee->update([
                        'workspace' => $workspaceId,
                    ]);
                }

                // Update User table (site + workspace)
                if ($user = User::find($request->employee_id)) {
                    $user->update([
                        'site_id' => $request->to_site_id,
                        'workspace_id' => $workspaceId,
                    ]);
                }

                return redirect()
                                ->route('employee.index')
                                ->with('success', 'Transfer updated successfully.');
            }
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer Web Update Error: ' . $e->getMessage());

            return redirect()
                            ->back()
                            ->withInput()
                            ->with('error', 'An error occurred while updating the transfer. Please try again.');
        }
    }

    public function destroy($id) {
        try {
            $transfer = GeneralTransfer::findOrFail($id);

            // Just delete the transfer record
            $transfer->delete();

            return redirect()
                            ->route('general_transfer.index')
                            ->with('success', 'Transfer deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('GeneralTransfer delete error: ' . $e->getMessage());

            return redirect()
                            ->back()
                            ->with('error', 'Unable to delete transfer. Please try again.');
        }
    }
}
