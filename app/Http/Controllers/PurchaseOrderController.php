<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\DataTables\PurchaseOrderDataTable;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Indent;
use App\Models\Supplier;
use App\Models\GstMaster;
use App\Models\Material;
use App\Models\WorkSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\LedgerHelper;
use App\Services\POAdvanceService;
use App\Services\PurchaseOrderService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }
    /**
     * Display a listing of the purchase orders.
     */
    public function index(PurchaseOrderDataTable $dataTable) {
        $suppliers = Supplier::pluck('name', 'id');
        return $dataTable->render('purchase-order.index', compact('suppliers'));
    }

    public function debugLog(Request $request) {
        if ($request->action == 'export_po') {
            Log::info("Export Purchase Order", ['ids' => $request->ids]);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Show the form for creating a new purchase order.
     */
    public function create(Request $request)
    {
        $workspaceId = getActiveWorkSpace();

        $siteId = getActiveProject();
        $suppliers = Supplier::get();
       
        
        
        $materials = \App\Models\Material::with('category','unit')->get();

        // Transform into JSON structure
        $materialsJson = $materials->map(function($m) {
            return [
                'id'            => $m->id,
                'name'          => $m->name,
                'unit'          => $m->unit?->name ?? '',   // if unit is a relation
                'price'         => $m->price ?? 0,
                'category_id'   => $m->category_id,
                'category_name' => $m->category?->name,
            ];
        })->values()->toJson();
        
        
//        $sites = \Workdo\Taskly\Entities\Project::where('workspace', $workspaceId)->get();
        // Fetch the active project/site
        $sites = \Workdo\Taskly\Entities\Project::where('id', getActiveProject())->projectonly()->get();
        
        // Get available indents (Open or Partially Closed)
        $indents = Indent::where('site_id', $siteId)
            ->whereIn('status', [Indent::STATUS_OPEN, Indent::STATUS_PARTIALLY_CLOSED])
            ->when($request->site_id, function($query) use ($request) {
                return $query->where('site_id', $request->site_id);
            })
            ->with(['items.material', 'supplier', 'purchaseOrders.items'])
            ->get();

        $selectedSiteId = $request->site_id ?? getActiveProject();

        // Get GST Masters for dropdown
        $gstMasters = GstMaster::where('is_active', true)->get();

        // Get all users for assign_to field
         $users = getActiveProjectEmployees();
//         dd($users);
         
        return view('purchase-order.create', compact('suppliers', 'materials', 'sites', 'indents', 'selectedSiteId', 'materialsJson', 'gstMasters', 'users'));
    }

    /**
     * Store a newly created purchase order in storage.
     */
    public function store(Request $request)
    {
        $workspaceId = getActiveWorkSpace();
        
        
        $validator = Validator::make($request->all(), [
            'po_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'site_id' => 'nullable|exists:projects,id',
            'indent_id' => 'nullable|exists:indents,id',
            'tax_type' => 'required|in:cgst,igst',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.gst_master_id' => 'nullable|exists:gst_masters,id',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'additional_charge' => 'nullable|numeric|min:0',
            'additional_deduction' => 'nullable|numeric|min:0',
            'additional_discount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'delivery_terms_conditions' => 'nullable|string',
            'payment_terms_conditions' => 'nullable|string',
            'remark' => 'nullable|string',
            'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate quantity against indent if selected
        if ($request->indent_id) {
            $indent = Indent::with('items')->find($request->indent_id);
            
            if (!$indent || !$indent->canAcceptPurchaseOrder()) {
                return redirect()->back()
                    ->with('error', __('This indent is closed and cannot accept new purchase orders.'))
                    ->withInput();
            }

            foreach ($request->items as $index => $itemData) {
                $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();
                
                if (!$indentItem) {
                    return redirect()->back()
                        ->with('error', __('Material not found in selected indent.'))
                        ->withInput();
                }

                $remainingQuantity = $indent->getRemainingQuantityForMaterial($itemData['material_id']);

                if (floatval($itemData['quantity']) > floatval($remainingQuantity)) {
                    return redirect()->back()
                        ->with('error', __("Quantity for material exceeds remaining indent quantity. Maximum available: {$remainingQuantity}"))
                        ->withInput();
                }
            }
        }

        // Reference File Upload
        $referenceFilePath = null;

        if ($request->hasFile('reference_file')) {
            $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('reference_file')->getClientOriginalExtension();

            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            // Upload using helper
            $path = upload_file($request, 'reference_file', $fileNameToStore, 'purchase-orders');

            if ($path['flag'] == 0) {
                return redirect()->back()->with('error', $path['msg']);
            }

            if (!empty($path['url'])) {
                $referenceFilePath = $path['url'];
            }
        }

        // Use DB transaction for data integrity
        \DB::beginTransaction();
        try {
            // CRITICAL: PO uses workspace scope, not site scope
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generatePONumber($workspaceId), // Use workspace ID for PO
                'po_date' => $request->po_date,
                'supplier_id' => $request->supplier_id,
                'site_id' => $request->site_id,
                'indent_id' => $request->indent_id,
                'description' => $request->description ?? null,
                'tax_type' => $request->tax_type,
                'delivery_date' => $request->delivery_date ?? null,
                'delivery_address' => $request->delivery_address ?? null,
                'delivery_terms_conditions' => $request->delivery_terms_conditions ?? null,
                'payment_terms_conditions' => $request->payment_terms_conditions ?? null,
                'remark' => $request->remark ?? null,
                'reference_file' => $referenceFilePath,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
                // Initial values - will be recalculated
                'additional_charge' => floatval($request->additional_charge ?? 0),
                'additional_deduction' => floatval($request->additional_deduction ?? 0),
                'additional_discount' => floatval($request->additional_discount ?? 0),
                'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
            ]);

            // Create items first
            foreach ($request->items as $itemData) {
                $quantity = floatval($itemData['quantity']);
                $price = floatval($itemData['price']);
                $discountAmount = floatval($itemData['discount_amount'] ?? 0);
                $rowTotal = $quantity * $price;

                // Validate discount doesn't exceed row total
                if ($discountAmount > $rowTotal) {
                    $discountAmount = $rowTotal;
                }

                // Get indent quantity if indent is selected
                $indentQuantity = null;
                if ($request->indent_id && isset($indent)) {
                    $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();
                    $indentQuantity = $indentItem ? $indentItem->quantity : null;
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'material_id' => $itemData['material_id'],
                    'gst_master_id' => $itemData['gst_master_id'] ?? null,
                    'quantity' => $quantity,
                    'unit' => $itemData['unit'],
                    'price' => $price,
                    'discount_amount' => $discountAmount,
                    'indent_quantity' => $indentQuantity,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            // Reload with items and GST for calculation
            $purchaseOrder->load('items.gstMaster');

            // Recalculate all totals on backend (DO NOT trust frontend values)
            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();

            // Update indent status
            $purchaseOrder->updateIndentStatus();

            // Generate and save PDF
            try {
                // Delete old PDF if exists
                if (!empty($purchaseOrder->po_pdf) && function_exists('delete_file')) {
                    try {
                        delete_file($purchaseOrder->po_pdf);
                    } catch (\Exception $e) {
                        Log::error('Error deleting old PO PDF: ' . $e->getMessage());
                    }
                }
                $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
                if ($pdfPath) {
                    $purchaseOrder->po_pdf = $pdfPath;
                    $purchaseOrder->save();
                }
            } catch (\Exception $e) {
                // Log error but don't fail the PO creation
                Log::error('Error generating PO PDF: ' . $e->getMessage());
            }

            // Create supplier ledger entry for PO (inside transaction)
            try {
                app(\App\Services\LedgerService::class)->createPOEntry($purchaseOrder);
            } catch (\Exception $e) {
                Log::error('Failed to create supplier ledger entry for PO: ' . $e->getMessage());
                throw $e; // Rollback transaction
            }

            \DB::commit();

            return redirect()->route('purchase-order.index')
                ->with('success', __('Purchase Order created successfully.'));

        } catch (\Exception $e) {
            \DB::rollBack();
            return redirect()->back()
                ->with('error', __('Error creating purchase order: ') . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'creator', 'indent', 'items.material', 'site']);

        // Fetch supplier ledger transactions for this PO's supplier
        $supplierTransactions = \App\Models\SupplierTransaction::with(['supplier', 'site'])
            ->where('supplier_id', $purchaseOrder->supplier_id)
            ->where('workspace_id', $purchaseOrder->workspace_id)
            ->when($purchaseOrder->site_id, function($query) use ($purchaseOrder) {
                return $query->where('site_id', $purchaseOrder->site_id);
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->unique(function ($item) {
                return $item->reference_type . '-' . $item->reference_id;
            });

        // Fetch payment requests related to this PO (both direct PO requests and invoice payment requests)
        $paymentRequests = \App\Models\PaymentRequest::with(['requestedBy', 'approvedBy', 'invoice', 'po', 'payments' => function($q) {
            $q->with('creator');
        }])
            ->where(function($query) use ($purchaseOrder) {
                $query->where('po_id', $purchaseOrder->id)
                      ->orWhereHas('invoice', function($q) use ($purchaseOrder) {
                          $q->where('po_id', $purchaseOrder->id);
                      });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch assigned users for display (N+1 fix - moved from Blade)
        $assignedUsers = collect();
        if ($purchaseOrder->assign_to) {
            $assignedUsers = \App\Models\User::whereIn('id', explode(',', $purchaseOrder->assign_to))->get();
        }

        return view('purchase-order.show', compact('purchaseOrder', 'supplierTransactions', 'paymentRequests', 'assignedUsers'));
    }

    /**
     * Show the form for editing the specified purchase order.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();
        
        // Only allow editing if status is Draft or Approved
        if (!$purchaseOrder->canEdit()) {
            return redirect()->route('purchase-order.index')
                ->with('error', __('Purchase Order cannot be edited because GRN has already been received.'));
        }

        $suppliers = Supplier::where('status', 0)->get();
        $materials = Material::where('status', 'active')->get();
        $sites = \Workdo\Taskly\Entities\Project::where('workspace', $workspaceId)->get();
        
        // Get indents - only Open or Partially Closed for new selections
        $indents = Indent::where('site_id', $siteId)            
            ->with(['items.material', 'supplier'])
            ->get();

        // Get GST Masters for dropdown
        $gstMasters = GstMaster::where('is_active', true)->get();

        // Get users for assign_to field
        $users = getActiveProjectEmployees();
//        \Log::info('Users count for PO edit: ' . $users->count());
//        \Log::info('Users data: ' . json_encode($users));

        // CRITICAL FIX: Load purchase order items with material and indent relationships
        $purchaseOrder->load(['items.material', 'indent.items']);

        // Pre-calculate available quantities for each PO item
        $itemsWithAvailability = [];
        $currentPoId = $purchaseOrder->id;
        
        foreach ($purchaseOrder->items as $item) {

            $availableQty = 0;
            $indentTotalQty = 0;

            if ($purchaseOrder->indent) {

                $indentItem = $purchaseOrder->indent
                    ->items
                    ->where('material_id', $item->material_id)
                    ->first();

                if ($indentItem) {

                    $indentTotalQty = (float) $indentItem->quantity;

                    // DO NOT add current quantity
                    $availableQty = $purchaseOrder->indent
                        ->getAvailableQuantityForEdit(
                            $item->material_id,
                            $purchaseOrder->id
                        );
                }
            }

            $itemsWithAvailability[] = [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material_name' => $item->material?->name ?? 'Unknown',
                'quantity' => (float) $item->quantity,
                'indent_quantity' => $indentTotalQty,
                'available_qty' => $availableQty,
                'unit' => $item->unit,
                'price' => (float) $item->price,
                'gst_master_id' => $item->gst_master_id,
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'remarks' => $item->remarks ?? ''
            ];
        }

        // dd($itemsWithAvailability);

        // Pass selected indent for dropdown preselection (even if closed)
        $selectedIndent = null;
        if ($purchaseOrder->indent_id) {
            $selectedIndent = Indent::with(['supplier', 'items.material'])
                ->find($purchaseOrder->indent_id);
        }

        return view('purchase-order.edit',
            compact('purchaseOrder', 'suppliers', 'materials', 'sites', 'indents', 'gstMasters', 'selectedIndent', 'itemsWithAvailability', 'users')
        );
    }

    /**
     * Update the specified purchase order in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $workspaceId = getActiveWorkSpace();

        // Only allow editing if status is Draft or Approved
        if (!$purchaseOrder->canEdit()) {
            return redirect()->route('purchase-order.index')
                ->with('error', __('Purchase Order cannot be edited because GRN has already been received.'));
        }

        $validator = Validator::make($request->all(), [
            'po_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'site_id' => 'nullable|exists:projects,id',
            'indent_id' => 'nullable|exists:indents,id',
            'tax_type' => 'required|in:cgst,igst',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.gst_master_id' => 'nullable|exists:gst_masters,id',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'additional_charge' => 'nullable|numeric|min:0',
            'additional_deduction' => 'nullable|numeric|min:0',
            'additional_discount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'delivery_terms_conditions' => 'nullable|string',
            'payment_terms_conditions' => 'nullable|string',
            'remark' => 'nullable|string',
            'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate quantity against indent if selected
        // For edit, we skip the indent status check since we're not changing the indent
        if ($request->indent_id) {
            $indent = Indent::with('items')->find($request->indent_id);
            
            // Skip canAcceptPurchaseOrder check for edit mode - just validate materials and quantities
            
            foreach ($request->items as $index => $itemData) {
                $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();
                
                if (!$indentItem) {
                    return redirect()->back()
                        ->with('error', __('Material not found in selected indent.'))
                        ->withInput();
                }

                // Calculate remaining quantity excluding current PO
                $remainingQuantity = $indent->getRemainingQuantityForMaterial($itemData['material_id']);

                // Add back current item quantity for validation (since we're updating)
                $currentItem = $purchaseOrder->items->where('material_id', $itemData['material_id'])->first();
                if ($currentItem && $currentItem->material_id == $itemData['material_id']) {
                    $remainingQuantity += $currentItem->quantity;
                }

                if ($itemData['quantity'] > $remainingQuantity) {
                    return redirect()->back()
                        ->with('error', __("Quantity for material exceeds remaining indent quantity. Maximum available: {$remainingQuantity}"))
                        ->withInput();
                }
            }
        }

        // Reference File Upload - keep old file by default
        $referenceFilePath = $purchaseOrder->reference_file;

        if ($request->hasFile('reference_file')) {
            $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('reference_file')->getClientOriginalExtension();

            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $path = upload_file($request, 'reference_file', $fileNameToStore, 'purchase-orders');

            if ($path['flag'] == 0) {
                return redirect()->back()->with('error', $path['msg']);
            }

            if (!empty($path['url'])) {
                // Delete old file if exists
                if (!empty($purchaseOrder->reference_file) && function_exists('delete_file')) {
                    delete_file($purchaseOrder->reference_file);
                }

                $referenceFilePath = $path['url'];
            }
        }

        // Use DB transaction for data integrity
        \DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'po_date' => $request->po_date,
                'supplier_id' => $request->supplier_id,
                'site_id' => $request->site_id,
                'indent_id' => $request->indent_id,
                'description' => $request->description,
                'tax_type' => $request->tax_type,
                'delivery_date' => $request->delivery_date ?? null,
                'delivery_address' => $request->delivery_address ?? null,
                'delivery_terms_conditions' => $request->delivery_terms_conditions ?? null,
                'payment_terms_conditions' => $request->payment_terms_conditions ?? null,
                'remark' => $request->remark ?? null,
                'reference_file' => $referenceFilePath,
                'additional_charge' => floatval($request->additional_charge ?? 0),
                'additional_deduction' => floatval($request->additional_deduction ?? 0),
                'additional_discount' => floatval($request->additional_discount ?? 0),
                'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
            ]);

        // Delete existing items and recreate
        $purchaseOrder->items()->delete();

        foreach ($request->items as $itemData) {
            $quantity = floatval($itemData['quantity']);
            $price = floatval($itemData['price']);
            $discountAmount = floatval($itemData['discount_amount'] ?? 0);
            $rowTotal = $quantity * $price;

            // Validate discount doesn't exceed row total
            if ($discountAmount > $rowTotal) {
                $discountAmount = $rowTotal;
            }

            // Get indent quantity if indent is selected
            $indentQuantity = null;
            if ($request->indent_id && isset($indent)) {
                $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();
                $indentQuantity = $indentItem ? $indentItem->quantity : null;
            }

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'material_id' => $itemData['material_id'],
                'gst_master_id' => $itemData['gst_master_id'] ?? null,
                'quantity' => $quantity,
                'unit' => $itemData['unit'],
                'price' => $price,
                'discount_amount' => $discountAmount,
                'indent_quantity' => $indentQuantity,
                'remarks' => $itemData['remarks'] ?? null,
            ]);
        }

        // Reload with items and GST for calculation
        $purchaseOrder->load('items.gstMaster');

        // Recalculate all totals on backend (DO NOT trust frontend values)
        $purchaseOrder->calculateTotals();
        $purchaseOrder->save();

        // Update indent status
        $purchaseOrder->updateIndentStatus();

        // Update supplier ledger entry for PO
        try {
            LedgerHelper::upsertPOEntry($purchaseOrder);
        } catch (\Exception $e) {
            Log::error('Failed to update supplier ledger entry for PO: ' . $e->getMessage());
        }

        // Generate and save PDF (regenerate on update)
        try {
            // Delete old PDF if exists
            if (!empty($purchaseOrder->po_pdf) && function_exists('delete_file')) {
                try {
                    delete_file($purchaseOrder->po_pdf);
                } catch (\Exception $e) {
                    Log::error('Error deleting old PO PDF: ' . $e->getMessage());
                }
            }
            $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
            if ($pdfPath) {
                $purchaseOrder->po_pdf = $pdfPath;
                $purchaseOrder->save();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the PO update
            Log::error('Error generating PO PDF: ' . $e->getMessage());
        }

        \DB::commit();

        return redirect()->route('purchase-order.index')
            ->with('success', __('Purchase Order updated successfully.'));

    } catch (\Exception $e) {
        \DB::rollBack();
        return redirect()->back()
            ->with('error', __('Error updating purchase order: ') . $e->getMessage())
            ->withInput();
    }
    }

    /**
     * Remove the specified purchase order from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // Only allow deleting if status is Draft
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return redirect()->route('purchase-order.index')
                ->with('error', __('Only Draft purchase orders can be deleted.'));
        }

        // Store indent reference before deleting
        $indent = $purchaseOrder->indent;

        // Delete supplier ledger entries and recalculate balance
        try {
            LedgerHelper::handlePODeletion($purchaseOrder->id);
        } catch (\Exception $e) {
            Log::error('Failed to delete supplier ledger entry for PO: ' . $e->getMessage());
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        // Update indent status
        if ($indent) {
            $indent->updateStatus();
        }

        return redirect()->route('purchase-order.index')
            ->with('success', __('Purchase Order deleted successfully.'));
    }

    /**
     * Get materials for a specific indent (AJAX)
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
     * Show approve form for purchase order
     * Supports updating status from Draft, Approved, or Flagged
     */
    public function showApproveForm(PurchaseOrder $purchaseOrder)
    {
        // Only allow status update if status allows transition
        $allowedTransitions = $purchaseOrder->getAllowedTransitions();
        
        if (empty($allowedTransitions)) {
            return redirect()->route('purchase-order.index')
                ->with('error', __('This purchase order status cannot be changed.'));
        }

        return view('purchase-order.approve', compact('purchaseOrder', 'allowedTransitions'));
    }

    /**
     * Update purchase order status
     * 
     * Status workflow:
     * - Draft -> Approved, Rejected
     * - Approved -> Flagged, Rejected
     * - Flagged -> Approved, Rejected, Flagged (re-flag)
     * - Partial Received -> Short Closed
     * - Completed, Rejected -> No changes allowed
     */
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Approved,Rejected,Flagged,Short Closed',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $newStatus = $request->status;
        $reason = $request->reason;

        // Use service for Rejected status (includes validation, indent recalculation, audit logging)
        if ($newStatus === PurchaseOrder::STATUS_REJECTED) {
            try {
                $this->purchaseOrderService->reject($purchaseOrder->id, $reason, Auth::id());
                return redirect()->route('purchase-order.index')
                    ->with('success', __('Purchase Order rejected successfully.'));
            } catch (\DomainException $e) {
                return redirect()->back()
                    ->with('error', $e->getMessage());
            } catch (\Exception $e) {
                Log::error('Error rejecting PO: ' . $e->getMessage());
                return redirect()->back()
                    ->with('error', __('Failed to reject purchase order.'));
            }
        }

        // Keep existing logic for other status changes
        // Check if transition is allowed
        if (!$purchaseOrder->canTransitionTo($newStatus)) {
            return redirect()->back()
                ->with('error', __('Invalid status transition from :from to :to', [
                    'from' => $purchaseOrder->status,
                    'to' => $newStatus
                ]));
        }

        // Validate reason for Flagged and Short Closed status
        // The form handles showing/hiding the reason fields, but we don't enforce it server-side
        // to avoid validation errors when using the modal

        // Use DB transaction for data integrity
        \DB::beginTransaction();
        try {
            $oldStatus = $purchaseOrder->status;

            // Update status and reason fields
            $updateData = ['status' => $newStatus];

            if ($newStatus === PurchaseOrder::STATUS_FLAGGED) {
                $updateData['flag_reason'] = $reason;
            } elseif ($newStatus === PurchaseOrder::STATUS_SHORT_CLOSED) {
                $updateData['short_close_reason'] = $reason;
                $updateData['short_closed_at'] = now();
                $updateData['short_closed_by'] = Auth::id();
            }

            $purchaseOrder->update($updateData);

            // Log status change
            $purchaseOrder->logStatusChange(
                $oldStatus,
                $newStatus,
                $reason,
                Auth::id()
            );

            \DB::commit();

            return redirect()->route('purchase-order.index')
                ->with('success', __('Purchase Order status updated successfully.'));
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Error updating PO status: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('Failed to update purchase order status.'));
        }
    }

    /**
     * Short close a Purchase Order.
     * Only Partial Received POs can be short closed.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\PurchaseOrder $purchaseOrder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function shortClose(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Validation
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Check if PO can be short closed
        if (!$purchaseOrder->canShortClose()) {
            return redirect()->back()
                ->with('error', __('Only Partial Received PO can be short closed.'));
        }

        // Use DB transaction
        \DB::beginTransaction();
        try {
            // Update status
            $oldStatus = $purchaseOrder->status;
            $purchaseOrder->status = PurchaseOrder::STATUS_SHORT_CLOSED;
            $purchaseOrder->short_close_reason = $request->reason;
            $purchaseOrder->short_closed_at = now();
            $purchaseOrder->short_closed_by = creatorId();
            $purchaseOrder->save();

            // Log status change
            $purchaseOrder->logStatusChange($oldStatus, PurchaseOrder::STATUS_SHORT_CLOSED, $request->reason);

            \DB::commit();

            return redirect()->route('purchase-order.index')
                ->with('success', __('Purchase Order short closed successfully.'));
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Error in PO short close: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('Failed to short close purchase order.'));
        }
    }

    /**
     * Print Invoice
     *
     * If a PDF has been generated and saved, serve it directly.
     * Otherwise, render the HTML view.
     */
    public function printInvoice(PurchaseOrder $purchaseOrder)
    {
  
        
        // If no PDF exists, render the HTML view
        $purchaseOrder->load([
            'items.material',
            'items.gstMaster',
            'supplier',
            'indent.items',
            'site',
            'creator'
        ]);

        // Get company settings
        $settings = [];
        $keys = [
            'company_name', 'company_email', 'company_address', 'company_city',
            'company_state', 'company_country', 'company_zipcode', 'company_telephone',
            'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
            'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
            'company_logo'
        ];
        foreach ($keys as $key) {
            $settings[$key] = company_setting($key);
        }

        // Get project details if site is linked
        $projectDetails = null;
        try {
            if ($purchaseOrder->site_id && class_exists('\Workdo\Taskly\Entities\Project')) {
                $projectDetails = \Workdo\Taskly\Entities\Project::find($purchaseOrder->site_id);
            }
        } catch (\Exception $e) {
            $projectDetails = null;
        }
        
        
        // Get workspace details (use workspace info as primary if available)
        $workspaceDetails = null;
        if ($purchaseOrder->workspace) {
            $workspaceDetails = $purchaseOrder->workspace;
            // Override settings with workspace details
            $settings['workspace_name'] = $workspaceDetails->name;
            $settings['workspace_contact_person'] = $workspaceDetails->contact_person;
            $settings['workspace_phone'] = $workspaceDetails->phone;
            $settings['workspace_email'] = $workspaceDetails->email;
            $settings['workspace_address'] = $workspaceDetails->address;
            $settings['workspace_city'] = $workspaceDetails->city;
            $settings['workspace_state'] = $workspaceDetails->state;
            $settings['workspace_pincode'] = $workspaceDetails->pincode;
            $settings['workspace_country'] = $workspaceDetails->country;
            $settings['workspace_gst_number'] = $workspaceDetails->gst_number;
            $settings['workspace_pan_number'] = $workspaceDetails->pan_number;
            $settings['workspace_bank_name'] = $workspaceDetails->bank_name;
            $settings['workspace_account_number'] = $workspaceDetails->account_number;
            $settings['workspace_ifsc_code'] = $workspaceDetails->ifsc_code;
        }
        
        $workspaceId = getActiveWorkSpace();
        
        // Check if PDF already exists, delete it and regenerate
        if (!empty($purchaseOrder->po_pdf)) {
            // Delete existing PDF file
            try {
                delete_file($purchaseOrder->po_pdf);
            } catch (\Exception $e) {
                Log::error('Failed to delete existing PO PDF: ' . $e->getMessage());
            }
        }

        // Generate and save PDF (regenerate every time)
        try {
            $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
            if ($pdfPath) {
                $purchaseOrder->po_pdf = $pdfPath;
                $purchaseOrder->save();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the PO update
            Log::error('Error generating PO PDF: ' . $e->getMessage());
        }
        
        

        return view('purchase-order.print-invoice', compact('purchaseOrder', 'settings', 'projectDetails', 'workspaceDetails'));
    }

    /**
     * Print Invoice - Revision 2
     * Regenerates PDF every time to ensure latest data is reflected.
     */
    public function printInvoice2(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'items.material',
            'items.gstMaster',
            'supplier',
            'indent.items',
            'site',
            'creator',
            'workspace'
        ]);

        // Check if PDF already exists, delete it and regenerate
        if (!empty($purchaseOrder->po_pdf)) {
            // Delete existing PDF file
            try {
                delete_file($purchaseOrder->po_pdf);
            } catch (\Exception $e) {
                Log::error('Failed to delete existing PO PDF: ' . $e->getMessage());
            }
        }

        // Generate and save PDF (regenerate every time)
        try {
            $workspaceId = getActiveWorkSpace();
            $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
            if ($pdfPath) {
                $purchaseOrder->po_pdf = $pdfPath;
                $purchaseOrder->save();
            }
        } catch (\Exception $e) {
            Log::error('Error generating PO PDF: ' . $e->getMessage());
        }

        // Get company settings
        $settings = [];
        $keys = [
            'company_name', 'company_email', 'company_address', 'company_city',
            'company_state', 'company_country', 'company_zipcode', 'company_telephone',
            'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
            'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
            'company_logo'
        ];
        foreach ($keys as $key) {
            $settings[$key] = company_setting($key);
        }

        // Get workspace details (use workspace info as primary if available)
        $workspaceDetails = null;
        if ($purchaseOrder->workspace) {
            $workspaceDetails = $purchaseOrder->workspace;
            // Override settings with workspace details
            $settings['workspace_name'] = $workspaceDetails->name;
            $settings['workspace_contact_person'] = $workspaceDetails->contact_person;
            $settings['workspace_phone'] = $workspaceDetails->phone;
            $settings['workspace_email'] = $workspaceDetails->email;
            $settings['workspace_address'] = $workspaceDetails->address;
            $settings['workspace_city'] = $workspaceDetails->city;
            $settings['workspace_state'] = $workspaceDetails->state;
            $settings['workspace_pincode'] = $workspaceDetails->pincode;
            $settings['workspace_country'] = $workspaceDetails->country;
            $settings['workspace_gst_number'] = $workspaceDetails->gst_number;
            $settings['workspace_pan_number'] = $workspaceDetails->pan_number;
            $settings['workspace_bank_name'] = $workspaceDetails->bank_name;
            $settings['workspace_account_number'] = $workspaceDetails->account_number;
            $settings['workspace_ifsc_code'] = $workspaceDetails->ifsc_code;
        }

        // Get project details if site is linked
        $projectDetails = null;
        try {
            if ($purchaseOrder->site_id && class_exists('\Workdo\Taskly\Entities\Project')) {
                $projectDetails = \Workdo\Taskly\Entities\Project::find($purchaseOrder->site_id);
            }
        } catch (\Exception $e) {
            // Project module not available or error loading
            $projectDetails = null;
        }

        return view('purchase-order.print-invoice-2', compact('purchaseOrder', 'settings', 'projectDetails', 'workspaceDetails'));
    }

    /**
     * Generate and save PDF for Purchase Order.
     *
     * @param PurchaseOrder $purchaseOrder
     * @param int $workspaceId
     * @return string|null
     */
    private function generatePurchaseOrderPdf(PurchaseOrder $purchaseOrder, int $workspaceId): ?string
    {
        try {
            // Load relationships - must match exactly what printInvoice loads
            $purchaseOrder->load([
                'items.material',
                'items.gstMaster',
                'supplier',
                'indent.items',
                'site',
                'creator'
            ]);

            // Get company settings - same as printInvoice
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
                'company_logo'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }

            // Get project details - same as printInvoice
            $projectDetails = null;
            try {
                if ($purchaseOrder->site_id && class_exists('\Workdo\Taskly\Entities\Project')) {
                    $projectDetails = \Workdo\Taskly\Entities\Project::find($purchaseOrder->site_id);
                }
            } catch (\Exception $e) {
                $projectDetails = null;
            }

            // Get workspace details - same as printInvoice
            $workspaceDetails = null;
            if ($purchaseOrder->workspace) {
                $workspaceDetails = $purchaseOrder->workspace;
                // Override settings with workspace details
                $settings['workspace_name'] = $workspaceDetails->name;
                $settings['workspace_contact_person'] = $workspaceDetails->contact_person;
                $settings['workspace_phone'] = $workspaceDetails->phone;
                $settings['workspace_email'] = $workspaceDetails->email;
                $settings['workspace_address'] = $workspaceDetails->address;
                $settings['workspace_city'] = $workspaceDetails->city;
                $settings['workspace_state'] = $workspaceDetails->state;
                $settings['workspace_pincode'] = $workspaceDetails->pincode;
                $settings['workspace_country'] = $workspaceDetails->country;
                $settings['workspace_gst_number'] = $workspaceDetails->gst_number;
                $settings['workspace_pan_number'] = $workspaceDetails->pan_number;
                $settings['workspace_bank_name'] = $workspaceDetails->bank_name;
                $settings['workspace_account_number'] = $workspaceDetails->account_number;
                $settings['workspace_ifsc_code'] = $workspaceDetails->ifsc_code;
            }

            // Prepare data for the view - must match exactly what printInvoice passes
            $data = [
                'purchaseOrder' => $purchaseOrder,
                'settings' => $settings,
                'projectDetails' => $projectDetails,
                'workspaceDetails' => $workspaceDetails,
            ];

            // Generate PDF using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
//            $options->set('defaultFont', 'Arial');
            $options->set('isPhpEnabled', true);
            
            
            $dompdf = new Dompdf($options);
            $data['isPdf'] = true;

            $html = view('purchase-order.print-invoice', $data)->render();

            $dompdf->loadHtml($html);

            $dompdf->setPaper('A4', 'portrait');

            $dompdf->render();

            $pdfContent = $dompdf->output();



//            $dompdf = new Dompdf($options);
//            
//            // Render the view to HTML
//            $html = view('purchase-order.print-invoice', $data)->render();
//            
//            // Log for debugging
//            Log::info('PO PDF HTML length: ' . strlen($html) . ' for PO: ' . $purchaseOrder->po_number);
//            
//            $dompdf->loadHtml($html);
//            
//            // Set paper size and orientation
//            $dompdf->setPaper('A4', 'portrait');
//            
//            // Generate PDF content
//            $pdfContent = $dompdf->output();

            // Generate file name using PO ID as prefix
            $fileName = $purchaseOrder->id . '_' . $purchaseOrder->po_number . '.pdf';
            
            // Upload the PDF
            $uploadPath = 'pdf/purchase-orders';
            $uploadResult = upload_pdf_content($pdfContent, $uploadPath, $fileName);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                return $uploadResult['url'];
            }

            Log::error('Failed to upload PO PDF: ' . ($uploadResult['msg'] ?? 'Unknown error'));
            return null;

        } catch (\Exception $e) {
            Log::error('Error generating PO PDF: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get PO list by supplier for payment module.
     */
    public function getPOBySupplier(Request $request)
    {
        try {
            $supplierId = $request->supplier_id;
            $status = $request->status ?? 'Approved';

            $statuses = explode(',', $status);
            
            $query = PurchaseOrder::where('supplier_id', $supplierId)
                ->whereIn('status', $statuses)
                ->whereNotIn('status', ['Closed', 'Short Closed', 'Rejected', 'Draft']);

            $purchaseOrders = $query->orderBy('po_date', 'desc')->get(['id', 'po_number', 'po_date', 'grand_total', 'status', 'invoiced_amount']);

            $result = $purchaseOrders->map(function ($po) {
                $invoicedAmount = $po->invoiced_amount ?? \App\Models\PurchaseInvoice::where('po_id', $po->id)->sum('grand_total');
                $poBalance = max(0, $po->grand_total - $invoicedAmount);
                
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date,
                    'grand_total' => (float) $po->grand_total,
                    'status' => $po->status,
                    'invoiced_amount' => $invoicedAmount,
                    'po_balance' => $poBalance,
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show advance request modal for PO.
     */
    public function advanceRequestModal($poId)
    {
        try {
            $poAdvanceService = new POAdvanceService();
            $modalData = $poAdvanceService->getModalData($poId);

            return response()->json([
                'success' => true,
                'data' => $modalData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading PO advance modal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load modal data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store PO advance request.
     */
    public function storeAdvanceRequest(Request $request, $poId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'percentage' => 'required|integer|min:1|max:100',
                'advance_amount' => 'required|numeric|min:0',
                'payment_date' => 'required|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $percentage = $request->percentage;
            $advanceAmount = $request->advance_amount;
            $paymentDate = $request->payment_date;
            $notes = $request->notes;

            $po = PurchaseOrder::findOrFail($poId);

            // Server-side validation: Check if PO payment is completed
            if ($po->isPaymentCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already completed for this PO. Cannot request advance.'
                ], 422);
            }

            // Server-side validation: Check if active advance request already exists
            // Allow new request if previous one was rejected
            if ($po->hasActiveAdvanceRequest()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active advance request already exists for this PO. Only one active advance request allowed per PO.'
                ], 422);
            }

            // Validate business rules
            $poAdvanceService = new POAdvanceService();
            $validationErrors = $poAdvanceService->validateAdvanceRequest($po, $percentage, $advanceAmount);

            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $validationErrors),
                ], 422);
            }

            // Check pending requests
            $pendingErrors = $poAdvanceService->checkPendingRequests($poId, $advanceAmount);

            if (!empty($pendingErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $pendingErrors),
                ], 422);
            }

            // Create advance request
            $paymentRequest = $poAdvanceService->createAdvanceRequest(
                $poId,
                $percentage,
                $advanceAmount,
                $notes,
                $paymentDate,
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Advance request created successfully',
                'data' => $paymentRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating PO advance request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create advance request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment request details for modal display.
     */
    public function getPaymentRequestDetails(Request $request)
    {
        try {
            $requestId = $request->request_id;
            $poId = $request->po_id;

            Log::info('Fetching payment request details', [
                'request_id' => $requestId,
                'po_id' => $poId
            ]);

            $paymentRequest = \App\Models\PaymentRequest::where('id', $requestId)
                ->where('po_id', $poId)
                ->where('type', 'po_advance')
                ->first();

            if (!$paymentRequest) {
                Log::warning('Payment request not found', [
                    'request_id' => $requestId,
                    'po_id' => $poId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment request not found'
                ], 404);
            }

            // Calculate paid amount
            $paidAmount = $paymentRequest->payments()->sum('amount');

            $statusLabels = [
                'pending' => 'Requested',
                'approved' => 'Approved',
                'partially_approved' => 'Partially Approved',
                'rejected' => 'Rejected',
                'partially_paid' => 'Partially Paid',
                'paid' => 'Paid',
            ];

            // Format amounts safely
            $requestedAmount = number_format($paymentRequest->requested_amount, 2);
            $approvedAmount = $paymentRequest->approved_amount ? number_format($paymentRequest->approved_amount, 2) : null;
            $paidAmountFormatted = number_format($paidAmount, 2);

            // Try to use currency_format_with_sym if available, otherwise use simple format
            try {
                $requestedAmountDisplay = currency_format_with_sym($paymentRequest->requested_amount);
                $approvedAmountDisplay = $approvedAmount ? currency_format_with_sym($paymentRequest->approved_amount) : '-';
                $paidAmountDisplay = currency_format_with_sym($paidAmount);
            } catch (\Exception $e) {
                $requestedAmountDisplay = $requestedAmount;
                $approvedAmountDisplay = $approvedAmount ? $approvedAmount : '-';
                $paidAmountDisplay = $paidAmountFormatted;
            }

            $data = [
                'success' => true,
                'status' => $paymentRequest->status,
                'status_label' => $statusLabels[$paymentRequest->status] ?? ucfirst($paymentRequest->status),
                'requested_amount' => $requestedAmountDisplay,
                'approved_amount' => $approvedAmountDisplay,
                'paid_amount' => $paidAmountDisplay,
                'rejection_reason' => $paymentRequest->rejection_reason,
            ];

            Log::info('Payment request details fetched successfully', $data);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching payment request details: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $request->request_id ?? null,
                'po_id' => $request->po_id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load payment request details: ' . $e->getMessage()
            ], 500);
        }
    }
}
