<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\DataTables\IndentDataTable;
use App\Models\Indent;
use App\Models\IndentItem;
use App\Models\Supplier;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;

class IndentController extends Controller
{
    /**
     * Display a listing of the indents.
     */
    public function index(IndentDataTable $dataTable) {
        $suppliers = Supplier::pluck('name', 'id');
        return $dataTable->render('indent.index', compact('suppliers'));
    }

    public function debugLog(Request $request) {
        if ($request->action == 'export_indent') {
            Log::info("Export Indent", ['ids' => $request->ids]);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Show the form for creating a new indent.
     */
    public function create(Request $request) {
        $workspaceId = getActiveWorkSpace();
        
        // Fetch all suppliers
        $suppliers = \App\Models\Supplier::all();
        
        // Fetch all material categories for dropdown
        $categories = \App\Models\MaterialCategory::select('id', 'name')->get();
        
        // Fetch the active project/site
        $sites = \Workdo\Taskly\Entities\Project::where('id', getActiveProject())->projectonly()->get();

        // Pre-select the active project in dropdown
        $selectedSiteId = getActiveProject();

        $users = getActiveProjectEmployees();

        return view('indent.create', compact('suppliers', 'categories', 'sites', 'selectedSiteId', 'users'));
    }

    /**
     * Store a newly created indent in storage.
     */
    public function store(Request $request)
    {
        $workspaceId = getActiveWorkSpace();
        
        $validator = Validator::make($request->all(), [
            'indent_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'site_id' => 'required|exists:projects,id',
            'description' => 'nullable|string',
            // New validation rules for additional fields
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'nullable|integer|exists:users,id',
            'delivery_date' => 'nullable|date',
            'remark' => 'nullable|string',
            'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Reference File Upload
            $referenceFilePath = null;

            if ($request->hasFile('reference_file')) {
                $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('reference_file')->getClientOriginalExtension();

                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'reference_file', $fileNameToStore, 'indents');

                if ($path['flag'] == 0) {
                    DB::rollBack();
                    return redirect()->back()->with('error', $path['msg']);
                }

                if (!empty($path['url'])) {
                    $referenceFilePath = $path['url'];
                }
            }

            // Calculate total_amount before creating Indent
            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $subtotal = $itemData['quantity'] * $itemData['price'];
                $totalAmount += $subtotal;
            }

            // Convert assign_to array to comma-separated string
            $assignToString = null;
            if ($request->has('assign_to') && is_array($request->assign_to)) {
                $assignToString = implode(',', $request->assign_to);
            }

            $indent = Indent::create([
                'indent_number' => Indent::generateIndentNumber($request->site_id), // Force override any user input
                'indent_date' => $request->indent_date,
                'supplier_id' => $request->supplier_id,
                'site_id' => $request->site_id,
                'description' => $request->description,
                'total_amount' => $totalAmount,
                'status' => Indent::STATUS_OPEN,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
                'assign_to' => $assignToString,
                'delivery_date' => $request->delivery_date,
                'remark' => $request->remark,
                'reference_file' => $referenceFilePath,
            ]);

            Log::info('Indent created', ['indent_id' => $indent->id, 'indent_number' => $indent->indent_number]);

            $itemsCreated = 0;

            foreach ($request->items as $index => $itemData) {
                try {
                    $subtotal = $itemData['quantity'] * $itemData['price'];

                    $indentItem = IndentItem::create([
                        'indent_id' => $indent->id,
                        'material_id' => $itemData['material_id'],
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'],
                        'price' => $itemData['price'],
                        'subtotal' => $subtotal,
                        'remarks' => $itemData['remarks'] ?? null,
                    ]);

                    if ($indentItem) {
                        $itemsCreated++;
                        Log::info('Indent item created', [
                            'indent_id' => $indent->id,
                            'item_id' => $indentItem->id,
                            'material_id' => $itemData['material_id'],
                            'quantity' => $itemData['quantity']
                        ]);
                    } else {
                        Log::error('Failed to create indent item (returned null)', [
                            'indent_id' => $indent->id,
                            'item_data' => $itemData
                        ]);
                        throw new \Exception("Failed to create indent item at index {$index}");
                    }
                } catch (\Exception $itemException) {
                    Log::error('Error creating indent item', [
                        'indent_id' => $indent->id,
                        'item_index' => $index,
                        'item_data' => $itemData,
                        'error' => $itemException->getMessage()
                    ]);
                    throw $itemException;
                }
            }

            // Validate that at least one item was created
            if ($itemsCreated === 0) {
                throw new \Exception('No indent items were created. Items array may be empty or invalid.');
            }

            Log::info('Indent transaction ready to commit', [
                'indent_id' => $indent->id,
                'total_amount' => $totalAmount,
                'items_created' => $itemsCreated
            ]);

            DB::commit();

            return redirect()->route('indent.index')
                ->with('success', __('Indent created successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating indent: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('Error creating indent: ') . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified indent.
     */
    public function show(Indent $indent)
    {
        $indent->load(['supplier', 'creator', 'items.material', 'purchaseOrders', 'site']);

        return view('indent.show', compact('indent'));
    }

    /**
     * Show the form for editing the specified indent.
     */
    public function edit(Indent $indent)
    {
        $workspaceId = getActiveWorkSpace();
        
        // Only allow editing if status is Open
        if ($indent->status === Indent::STATUS_CLOSED) {
            return redirect()->route('indent.index')
                ->with('error', __('Cannot edit a closed indent.'));
        }

        $suppliers = Supplier::all();
        
        // Fetch all material categories for dropdown
        $categories = \App\Models\MaterialCategory::select('id', 'name')->get();
        
        $sites = \Workdo\Taskly\Entities\Project::where('workspace', $workspaceId)->get();

        // Fetch users for assign_to field
        $users = getActiveProjectEmployees();

        $indent->load('items');

        return view('indent.edit', compact('indent', 'suppliers', 'categories', 'sites', 'users'));
    }

    /**
     * Update the specified indent in storage.
     */
    public function update(Request $request, Indent $indent)
    {
        // Check if indent is used in any purchase order (using exists() for performance)
        if ($indent->purchaseOrders()->exists()) {
            return redirect()->route('indent.index')
                ->with('error', __('Cannot update or delete this indent because it is already used in a purchase order.'));
        }

        // Only allow editing if status is Open
        if ($indent->status === Indent::STATUS_CLOSED) {
            return redirect()->route('indent.index')
                ->with('error', __('Cannot edit a closed indent.'));
        }

        $validator = Validator::make($request->all(), [
            'indent_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'site_id' => 'nullable|exists:projects,id',
            'description' => 'nullable|string',
            // New validation rules for additional fields
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'nullable|integer|exists:users,id',
            'delivery_date' => 'nullable|date',
            'remark' => 'nullable|string',
            'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // ==============================
    // Reference File Upload
    // ==============================

    $referenceFilePath = $indent->reference_file; // keep old file by default

    if ($request->hasFile('reference_file')) {

        $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $request->file('reference_file')->getClientOriginalExtension();

        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

        $path = upload_file($request, 'reference_file', $fileNameToStore, 'indents');

        if ($path['flag'] == 0) {
            return redirect()->back()->with('error', $path['msg']);
        }

        if (!empty($path['url'])) {

            // Delete old file if exists
            if (!empty($indent->reference_file) && function_exists('delete_file')) {
                delete_file($indent->reference_file);
            }

            $referenceFilePath = $path['url'];
        }
    }

//        // Handle file upload - store in public folder
//        $referenceFilePath = $indent->reference_file; // Keep existing file path by default
//        
//        if ($request->hasFile('reference_file')) {
//            // Delete old file if exists
//            if ($indent->reference_file) {
//                $oldFilePath = public_path($indent->reference_file);
//                if (file_exists($oldFilePath)) {
//                    unlink($oldFilePath);
//                }
//            }
//            
//            // Store new file
//            $file = $request->file('reference_file');
//            $fileName = time() . '_' . uniqid() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
//            $filePath = public_path('indents');
//
//            // Create directory if not exists
//            if (!file_exists($filePath)) {
//                mkdir($filePath, 0755, true);
//            }
//
//            // Move file to public/indents
//            $file->move($filePath, $fileName);
//            $referenceFilePath = 'indents/' . $fileName;
//        }

        // Convert assign_to array to comma-separated string
        $assignToString = null;
        if ($request->has('assign_to') && is_array($request->assign_to)) {
            $assignToString = implode(',', $request->assign_to);
        }

        $indent->update([
            'indent_date' => $request->indent_date,
            'supplier_id' => $request->supplier_id,
            'site_id' => $request->site_id,
            'description' => $request->description,
            // New fields
            'assign_to' => $assignToString,
            'delivery_date' => $request->delivery_date,
            'remark' => $request->remark,
            'reference_file' => $referenceFilePath,
        ]);

        // Delete existing items and recreate
        $deletedItems = $indent->items()->delete();
        Log::info('Deleted existing indent items', ['indent_id' => $indent->id, 'count' => $deletedItems]);

        $totalAmount = 0;
        $itemsCreated = 0;

        foreach ($request->items as $index => $itemData) {
            try {
                $subtotal = $itemData['quantity'] * $itemData['price'];
                $totalAmount += $subtotal;

                $indentItem = IndentItem::create([
                    'indent_id' => $indent->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'price' => $itemData['price'],
                    'subtotal' => $subtotal,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                if ($indentItem) {
                    $itemsCreated++;
                    Log::info('Indent item created (update)', [
                        'indent_id' => $indent->id,
                        'item_id' => $indentItem->id,
                        'material_id' => $itemData['material_id'],
                        'quantity' => $itemData['quantity']
                    ]);
                } else {
                    Log::error('Failed to create indent item during update (returned null)', [
                        'indent_id' => $indent->id,
                        'item_data' => $itemData
                    ]);
                    throw new \Exception("Failed to create indent item at index {$index}");
                }
            } catch (\Exception $itemException) {
                Log::error('Error creating indent item during update', [
                    'indent_id' => $indent->id,
                    'item_index' => $index,
                    'item_data' => $itemData,
                    'error' => $itemException->getMessage()
                ]);
                throw $itemException;
            }
        }

        // Validate that at least one item was created
        if ($itemsCreated === 0) {
            throw new \Exception('No indent items were created during update. Items array may be empty or invalid.');
        }

        $indent->update(['total_amount' => $totalAmount]);

        // Update status based on purchase orders
        $indent->updateStatus();

        return redirect()->route('indent.index')
            ->with('success', __('Indent updated successfully.'));
    }

    /**
     * Remove the specified indent from storage.
     */
    public function destroy(Indent $indent)
    {
        // Check if there are any purchase orders (using exists() for performance)
        if ($indent->purchaseOrders()->exists()) {
            return redirect()->route('indent.index')
                ->with('error', __('Cannot update or delete this indent because it is already used in a purchase order.'));
        }

        $indent->items()->delete();
        $indent->delete();

        return redirect()->route('indent.index')
            ->with('success', __('Indent deleted successfully.'));
    }

    /**
     * Get materials for a specific indent (for Purchase Order selection)
     */
    public function getIndentMaterials(Request $request)
    {
        $indent = Indent::with(['items.material'])->find($request->indent_id);

        if (!$indent) {
            return response()->json(['error' => 'Indent not found'], 404);
        }

        $materials = [];

        foreach ($indent->items as $item) {
            $remainingQuantity = $indent->getRemainingQuantityForMaterial($item->material_id);
            
            $materials[] = [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material_name' => $item->material ? $item->material->name : 'Unknown',
                'indent_quantity' => $item->quantity,
                'remaining_quantity' => $remainingQuantity,
                'unit' => $item->unit,
                'price' => $item->price,
            ];
        }

        return response()->json([
            'indent' => $indent,
            'materials' => $materials
        ]);
    }

    /**
     * Get available indents (not closed) for dropdown
     */
    public function getAvailableIndents()
    {
        $workspaceId = getActiveWorkSpace();

        $indents = Indent::where('workspace_id', $workspaceId)
            ->whereIn('status', [Indent::STATUS_OPEN, Indent::STATUS_PARTIALLY_CLOSED])
            ->with(['items.material', 'supplier'])
            ->get();

        return response()->json($indents);
    }
}
