<?php

namespace App\Http\Controllers;

use App\DataTables\AssetsToolsAndEquipmentDataTable;
use Illuminate\Http\Request;
use App\Models\AssetsToolsAndEquipment;
use App\Models\Material;
use App\Events\CreateAssetsToolsAndEquipment;
use App\Events\UpdateAssetsToolsAndEquipment;
use App\Events\DestroyAssetsToolsAndEquipment;

class AssetsToolsAndEquipmentController extends Controller {

    /**
     * List all tools and equipment
     *
     * @authenticated
     * @response view="assets_tools_and_equipment.index"
     */
    public function index(AssetsToolsAndEquipmentDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('tools-and-equipment manage')) {
            return $dataTable->render('assets_tools_and_equipment.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show create tool/equipment form
     *
     * @authenticated
     * @response view="assets_tools_and_equipment.create"
     */
    public function create() {
        if (\Auth::user()->isAbleTo('tools-and-equipment create')) {
            $materials = Material::where('category_id', 3)->pluck('name', 'id');

//        $site = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');


            $site = getAllSitesWithWorkspace();

            return view('assets_tools_and_equipment.create', compact('materials', 'site'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a new tool/equipment
     *
     * @authenticated
     * @bodyParam material_id int required The material ID. Must exist in materials table.
     * @bodyParam quantity int required The quantity. Minimum 1.
     * @bodyParam operational_status string required The operational status. Must be one of: active, breakdown, scrap.
     * @bodyParam site_id int The site ID.
     * @response redirect="assets_tools_and_equipment.index"
     */
    public function store(Request $request) {
        if (\Auth::user()->isAbleTo('tools-and-equipment create')) {
            $validator = \Validator::make($request->all(), [
                'material_id' => 'required|exists:materials,id',
                'quantity' => 'required|integer|min:1',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->getMessageBag()->first());
            }

            $workspaceId = getWorkspaceIDFromSiteID($request->site_id);

            // 👇 Check if record already exists for same material + site + workspace
            $tool = AssetsToolsAndEquipment::where('material_id', $request->material_id)
                    ->where('site_id', $request->site_id)
                    ->where('workspace_id', $workspaceId)
                    ->first();

            if ($tool) {
                // ✅ Update existing record: increment quantity
                $tool->quantity += $request->quantity;
                $tool->operational_status = $request->operational_status; // update status if needed
                $tool->save();

                event(new CreateAssetsToolsAndEquipment($request, $tool));

                return redirect()->route('assets_tools_and_equipment.index')
                                ->with('success', __('Tool/Equipment quantity updated successfully.'));
            } else {
                // ✅ Create new record
                $tool = new AssetsToolsAndEquipment();
                $tool->material_id = $request->material_id;
                $tool->quantity = $request->quantity;
                $tool->operational_status = $request->operational_status;
                $tool->site_id = $request->site_id;
                $tool->created_by = creatorId();
                $tool->workspace_id = $workspaceId;
                $tool->save();

                event(new CreateAssetsToolsAndEquipment($request, $tool));

                return redirect()->route('assets_tools_and_equipment.index')
                                ->with('success', __('Tool/Equipment created successfully.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

//    public function store(Request $request) {
//        if (\Auth::user()->isAbleTo('tools-and-equipment create')) {
//            $validator = \Validator::make($request->all(), [
//                'material_id' => 'required|exists:materials,id',
//                'quantity' => 'required|integer|min:1',
//                'operational_status' => 'required|in:active,breakdown,scrap',
//                'site_id' => 'nullable|integer',
//            ]);
//
//            if ($validator->fails()) {
//                return redirect()->back()->with('error', $validator->getMessageBag()->first());
//            }
//
//            $tool = new AssetsToolsAndEquipment();
//            $tool->material_id = $request->material_id;
//            $tool->quantity = $request->quantity;
//            $tool->operational_status = $request->operational_status;
//            $tool->site_id = $request->site_id;
//
//            $tool->created_by = creatorId();
////        $tool->workspace_id = getActiveWorkSpace();
//
//            $tool->workspace_id = getWorkspaceIDFromSiteID($request->site_id); // helper or session value
//            $tool->save();
//
//            event(new CreateAssetsToolsAndEquipment($request, $tool));
//
//            return redirect()->route('assets_tools_and_equipment.index')->with('success', __('Tool/Equipment created successfully.'));
//        } else {
//            return redirect()->back()->with('error', __('Permission denied.'));
//        }
//    }

    /**
     * Show a tool/equipment
     *
     * @authenticated
     * @urlParam assetsToolsAndEquipment int required The tool/equipment ID.
     * @response {"id":1,"material_id":1,"quantity":10,"operational_status":"active"}
     */
    public function show(AssetsToolsAndEquipment $assetsToolsAndEquipment) {
        if (\Auth::user()->isAbleTo('tools-and-equipment show')) {
            return response()->json($assetsToolsAndEquipment);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show edit tool/equipment form
     *
     * @authenticated
     * @urlParam assetsToolsAndEquipment int required The tool/equipment ID.
     * @response view="assets_tools_and_equipment.edit"
     */
    public function edit(AssetsToolsAndEquipment $assetsToolsAndEquipment) {
        if (\Auth::user()->isAbleTo('tools-and-equipment edit')) {
            $materials = Material::where('category_id', 3)->pluck('name', 'id');
//        $site = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');


            $site = getAllSitesWithWorkspace();
            return view('assets_tools_and_equipment.edit', compact('assetsToolsAndEquipment', 'materials', 'site'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update a tool/equipment
     *
     * @authenticated
     * @urlParam assetsToolsAndEquipment int required The tool/equipment ID.
     * @bodyParam material_id int required The material ID. Must exist in materials table.
     * @bodyParam quantity int required The quantity. Minimum 1.
     * @bodyParam operational_status string required The operational status. Must be one of: active, breakdown, scrap.
     * @bodyParam site_id int The site ID.
     * @response redirect="assets_tools_and_equipment.index"
     */
    public function update(Request $request, AssetsToolsAndEquipment $assetsToolsAndEquipment) {
        if (\Auth::user()->isAbleTo('tools-and-equipment edit')) {
            $validator = \Validator::make($request->all(), [
                'material_id' => 'required|exists:materials,id',
                'quantity' => 'required|integer|min:1',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->getMessageBag()->first());
            }

            $workspaceId = getWorkspaceIDFromSiteID($request->site_id);

            // 👇 Check if another record already exists with same material + site + workspace
            $existingTool = AssetsToolsAndEquipment::where('material_id', $request->material_id)
                    ->where('site_id', $request->site_id)
                    ->where('workspace_id', $workspaceId)
                    ->where('id', '!=', $assetsToolsAndEquipment->id) // exclude current record
                    ->first();

            if ($existingTool) {
                // ✅ Merge: increment quantity on existing record
                $existingTool->quantity += $request->quantity;
                $existingTool->operational_status = $request->operational_status;
                $existingTool->save();

                // Optionally delete the current record to avoid duplicates
                $assetsToolsAndEquipment->delete();

                event(new UpdateAssetsToolsAndEquipment($request, $existingTool));

                return redirect()->route('assets_tools_and_equipment.index')
                                ->with('success', __('Tool/Equipment merged and updated successfully.'));
            } else {
                // ✅ Update current record normally
                $assetsToolsAndEquipment->material_id = $request->material_id;
                $assetsToolsAndEquipment->quantity = $request->quantity;
                $assetsToolsAndEquipment->operational_status = $request->operational_status;
                $assetsToolsAndEquipment->site_id = $request->site_id;
                $assetsToolsAndEquipment->workspace_id = $workspaceId;
                $assetsToolsAndEquipment->created_by = creatorId();
                $assetsToolsAndEquipment->save();

                event(new UpdateAssetsToolsAndEquipment($request, $assetsToolsAndEquipment));

                return redirect()->route('assets_tools_and_equipment.index')
                                ->with('success', __('Tool/Equipment updated successfully.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Delete a tool/equipment
     *
     * @authenticated
     * @urlParam assetsToolsAndEquipment int required The tool/equipment ID.
     * @response redirect="assets_tools_and_equipment.index"
     */
    public function destroy(AssetsToolsAndEquipment $assetsToolsAndEquipment) {
        if (\Auth::user()->isAbleTo('tools-and-equipment delete')) {
            $assetsToolsAndEquipment->delete();

            event(new DestroyAssetsToolsAndEquipment($assetsToolsAndEquipment));

            return redirect()->route('assets_tools_and_equipment.index')->with('success', __('Tool/Equipment deleted successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
