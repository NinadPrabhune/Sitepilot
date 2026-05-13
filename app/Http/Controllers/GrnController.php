<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\DataTables\GrnDataTable;
use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseInvoice;
use App\Models\WarehouseProduct;
use App\Models\Material;
use App\Models\Supplier;
use App\Services\StockService;
use App\Services\GrnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Dompdf\Dompdf;
use Dompdf\Options;

class GrnController extends Controller
{
    protected $stockService;
    protected $grnService;

    public function __construct(StockService $stockService, GrnService $grnService)
    {
        $this->stockService = $stockService;
        $this->grnService = $grnService;
    }

    public function debugLog(Request $request) {
        if ($request->action == 'export_grn') {
            Log::info("Export GRN", ['ids' => $request->ids]);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Display a listing of the GRNs.
     */
    public function index(GrnDataTable $dataTable) {
        $suppliers = Supplier::pluck('name', 'id');
        return $dataTable->render('grn.index', compact('suppliers'));
    }

    /**
     * Show the form for creating a new GRN.
     */
    public function create(Request $request)
    {
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();

        // Get all approved AND partial received purchase orders that are not fully received
        // Note: Some POs may have status 'Partial' instead of 'Partial Received'
        $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->whereIn('status', [
                PurchaseOrder::STATUS_APPROVED, 
                PurchaseOrder::STATUS_PARTIAL_RECEIVED,
                'Partial'  // Handle legacy status value
            ])
            ->with(['supplier', 'site', 'items.material'])
            ->get()
            ->filter(function($po) {
                // Only show POs that have remaining quantities to receive
                foreach ($po->items as $item) {
                    if (($item->quantity - ($item->received_qty ?? 0)) > 0) {
                        return true;
                    }
                }
                return false;
            })
            ->values();

        $selectedPoId = $request->po_id ?? null;
        $selectedSiteId = $siteId;

        $suppliers = Supplier::orderBy('name')->pluck('name', 'id')->prepend(__('Select Supplier'), '');
        $materials = Material::with('unit')->get();
        $gstMasters = \App\Models\GstMaster::where('is_active', true)->get();

        // Get users for assign_to field
        $users = getActiveProjectEmployees();

        return view('grn.create', compact('purchaseOrders', 'selectedPoId', 'selectedSiteId', 'suppliers', 'materials', 'gstMasters', 'users'));
    }

    /**
     * Fetch PO details via AJAX.
     */
    public function getPoDetails(Request $request)
    {
        $request->validate([
            'po_id' => 'required|exists:purchase_orders,id'
        ]);

        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'site',
            'items.material.unit'
        ])->findOrFail($request->po_id);

        Log::info('GRN getPoDetails - PO loaded', [
            'po_id' => $request->po_id,
            'po_number' => $purchaseOrder->po_number,
            'status' => $purchaseOrder->status
        ]);

        // Calculate remaining quantities for each item
        // Use PO item's received_qty field as the authoritative source
        // (GrnService updates this field when GRN is created)
        $items = $purchaseOrder->items->map(function($item) {
            // Use the PO item's received_qty field directly (maintained by GrnService)
            $receivedQty = $item->received_qty ?? 0;
            $remainingQty = $item->quantity - $receivedQty;
            
            Log::info('GRN getPoDetails - Item calculation', [
                'item_id' => $item->id,
                'material_id' => $item->material_id,
                'ordered_qty' => $item->quantity,
                'received_qty' => $receivedQty,
                'remaining_qty' => $remainingQty,
            ]);
            
            return [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material_name' => $item->material->name ?? 'N/A',
                'material_unit' => $item->material->unit->name ?? $item->unit,
                'ordered_qty' => $item->quantity,
                'received_qty' => $receivedQty,
                'remaining_qty' => max(0, $remainingQty),
                'unit' => $item->unit,
                'price' => $item->price,
            ];
        });

        return response()->json([
            'success' => true,
            'po' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'po_date' => $purchaseOrder->po_date->format('Y-m-d'),
                'supplier_id' => $purchaseOrder->supplier_id,
                'supplier_name' => $purchaseOrder->supplier->name ?? 'N/A',
                'site_id' => $purchaseOrder->site_id,
                'site_name' => optional($purchaseOrder->site)->name ?? 'N/A',
                'status' => $purchaseOrder->status,
                'assign_to' => $purchaseOrder->assign_to,
            ],
            'items' => $items
        ]);
    }

    /**
     * Store a newly created GRN in storage.
     * Supports both PO-based and Direct GRN.
     */
    public function store(Request $request)
    {
        // Authorization check
        if (!Auth::user()->isAbleTo('grn create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $isDirectGrn = $request->input('grn_type') === 'direct';

        // For Direct GRN, map quantity to received_qty if not already set
        if ($isDirectGrn && $request->has('items')) {
            $items = $request->input('items');
            foreach ($items as $index => $item) {
                $qty = $item['quantity'] ?? 0;
                if (empty($item['received_qty'])) {
                    $items[$index]['received_qty'] = $qty;
                }
                if (empty($item['accepted_qty'])) {
                    $items[$index]['accepted_qty'] = $qty;
                }
                if (!isset($item['rejected_qty']) || $item['rejected_qty'] === '') {
                    $items[$index]['rejected_qty'] = 0;
                }
            }
            $request->merge(['items' => $items]);
        }

        $validationRules = [
            'grn_type' => 'required|in:against_po,direct',
            'grn_date' => 'required|date',
            'delivery_challan_number' => 'nullable|string|max:255',
            'gate_entry_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'received_by' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.accepted_qty' => 'required|numeric|min:0',
            'items.*.rejected_qty' => 'required|numeric|min:0',
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'integer|exists:users,id',
        ];
        
        if ($isDirectGrn) {
            $validationRules['direct_supplier_id'] = 'required|exists:suppliers,id';
            $validationRules['direct_site_id'] = 'required|exists:projects,id';
            $validationRules['supplier_invoice_number'] = 'required|string|max:255|unique:grns,supplier_invoice_number,NULL,id,supplier_id,' . $request->direct_supplier_id;
            $validationRules['vehicle_number_direct'] = 'nullable|string|max:255';
            $validationRules['delivery_challan_file_direct'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['reference_file_direct'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['items.*.material_id'] = 'required|exists:materials,id';
            $validationRules['items.*.price'] = 'required|numeric|min:0';
        } else {
            $validationRules['po_id'] = 'required|exists:purchase_orders,id';
            $validationRules['vehicle_number_po'] = 'nullable|string|max:255';
            $validationRules['delivery_challan_file_po'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['reference_file_po'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['items.*.po_item_id'] = 'required|exists:purchase_order_items,id';
        }
        
        $request->validate($validationRules);

        // Validate items array is not empty
        if (empty($request->items) || count($request->items) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'At least one item is required'
            ], 422);
        }

        // Validate that accepted + rejected = received
        foreach ($request->items as $index => $item) {
            $total = floatval($item['accepted_qty']) + floatval($item['rejected_qty']);
            $received = floatval($item['received_qty']);
            
            if (abs($total - $received) > 0.001) {
                return response()->json([
                    'success' => false,
                    'message' => "Item " . ($index + 1) . ": Accepted Qty + Rejected Qty must equal Received Qty"
                ], 422);
            }
        }

        try {
            // Map prefixed field names to service-expected names
            if ($isDirectGrn) {
                $request->merge([
                    'supplier_id' => $request->direct_supplier_id,
                    'site_id' => $request->direct_site_id,
                    'vehicle_number' => $request->vehicle_number_direct,
                ]);
                
                // Handle file uploads for Direct GRN
                $deliveryChallanFile = null;
                $referenceFile = null;
                
                if ($request->hasFile('delivery_challan_file_direct')) {
                    $deliveryChallanFile = upload_file($request, 'delivery_challan_file_direct', 'delivery_challan', 'grn');
                    if (isset($deliveryChallanFile['flag']) && $deliveryChallanFile['flag'] == 1) {
                        $deliveryChallanFile = $deliveryChallanFile['url'];
                    }
                }
                
                if ($request->hasFile('reference_file_direct')) {
                    $referenceFile = upload_file($request, 'reference_file_direct', 'reference', 'grn');
                    if (isset($referenceFile['flag']) && $referenceFile['flag'] == 1) {
                        $referenceFile = $referenceFile['url'];
                    }
                }
            } else {
                $request->merge([
                    'vehicle_number' => $request->vehicle_number_po,
                ]);
                
                // Handle file uploads for PO-based GRN
                $deliveryChallanFile = null;
                $referenceFile = null;
                
                if ($request->hasFile('delivery_challan_file_po')) {
                    $deliveryChallanFile = upload_file($request, 'delivery_challan_file_po', 'delivery_challan', 'grn');
                    if (isset($deliveryChallanFile['flag']) && $deliveryChallanFile['flag'] == 1) {
                        $deliveryChallanFile = $deliveryChallanFile['url'];
                    }
                }
                
                if ($request->hasFile('reference_file_po')) {
                    $referenceFile = upload_file($request, 'reference_file_po', 'reference', 'grn');
                    if (isset($referenceFile['flag']) && $referenceFile['flag'] == 1) {
                        $referenceFile = $referenceFile['url'];
                    }
                }
            }

            // Prepare data for service
            $data = $request->except(['_token', 'grn_type']);
            $data['delivery_challan_file'] = $deliveryChallanFile;
            $data['reference_file'] = $referenceFile;
            $data['created_by'] = creatorId();
            $data['workspace_id'] = getActiveWorkSpace();
            $data['assign_to'] = $request->assign_to; // Trait mutator handles array to string conversion

            // Use GrnService to create GRN
            if ($isDirectGrn) {
                $grn = $this->grnService->createDirectGrn($data);
            } else {
                $grn = $this->grnService->createGrnAgainstPo($data);
            }

            // Generate and save PDF for GRN
            try {
                $workspaceId = getActiveWorkSpace();
                $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
                if ($pdfPath) {
                    $grn->grn_pdf = $pdfPath;
                    $grn->save();
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate GRN PDF: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'GRN created successfully!',
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number
            ]);
        } catch (\Exception $e) {
            Log::error('GRN Creation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating GRN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update inventory quantity for a material at a site.
     */
    private function updateInventory($siteId, $materialId, $quantity)
    {
        // Keep the legacy warehouse product update for backward compatibility
        // Check if inventory record exists for this site and material
        $inventory = WarehouseProduct::where('warehouse_id', $siteId)
            ->where('product_id', $materialId)
            ->first();

        if ($inventory) {
            // Update existing inventory
            $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
            $inventory->save();
        } else {
            // Create new inventory record
            WarehouseProduct::create([
                'warehouse_id' => $siteId,
                'product_id' => $materialId,
                'quantity' => $quantity,
                'created_by' => creatorId(),
                'workspace' => getActiveWorkSpace(),
            ]);
        }

        // Also update the new inventory ledger system
        try {
            $this->stockService->updateCurrentStock($siteId, $materialId, $quantity);
        } catch (\Exception $e) {
            Log::error('Failed to update new inventory system: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified GRN.
     */
    public function show(Grn $grn)
    {
        
        if (!Auth::user()->isAbleTo('grn show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        
        // Eager load all relationships to prevent N+1 queries
        $grn->load([
            'purchaseOrder.supplier',
            'supplier',
            'site',
            'creator',
            'items.material.unit',
            'items.poItem',
            'items.gstMaster'
        ]);

        // Fetch assigned users for display (N+1 fix - moved from Blade)
        $assignedUsers = [];
        if ($grn->assign_to) {
            $assignedUsers = \App\Models\User::whereIn('id', explode(',', $grn->assign_to))->get();
        }

        return view('grn.show', compact('grn', 'assignedUsers'));
    }

    /**
     * Remove the specified GRN from storage.
     */
    public function destroy(Grn $grn)
    {
        // Authorization check
        if (!Auth::user()->isAbleTo('grn delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        // Check if GRN is locked
        if ($grn->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a locked GRN'
            ], 422);
        }

        // Check if invoice exists before deleting
        if ($grn->hasInvoice()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete GRN with existing invoice. Please delete the invoice first.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // STEP 1: Load all items BEFORE any deletion
            $grnItems = $grn->items()->get();

            // STEP 2: Reverse inventory and PO received_qty for each item
            foreach ($grnItems as $item) {
                if ($item->accepted_qty > 0) {
                    // Reverse legacy inventory
                    $this->reverseInventory(
                        $grn->site_id,
                        $item->material_id,
                        $item->accepted_qty
                    );
                }

                // Reverse PO item received qty
                if ($item->po_item_id) {
                    $poItem = PurchaseOrderItem::find($item->po_item_id);
                    if ($poItem) {
                        $poItem->received_qty = max(0, ($poItem->received_qty ?? 0) - $item->received_qty);
                        $poItem->save();
                    }
                }
            }

            // STEP 3: Delete stock transactions
            \App\Models\StockTransaction::where('reference_type', 'grn')
                ->where('reference_id', $grn->id)
                ->delete();

            // STEP 4: Reverse MaterialProjectStock using pre-loaded items
            foreach ($grnItems as $item) {
                if ($item->accepted_qty > 0) {
                    $this->stockService->updateCurrentStock($grn->site_id, $item->material_id, -$item->accepted_qty);
                }
            }

            // STEP 5: Clean up supplier ledger entries
            \App\Helpers\LedgerHelper::handleGRNDeletion($grn->id);

            // STEP 6: Delete GRN items (soft-delete)
            GrnItem::where('grn_id', $grn->id)->delete();

            // STEP 7: Delete GRN (soft-delete)
            $grn->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'GRN deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GRN Deletion Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error deleting GRN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reverse inventory quantity.
     */
    private function reverseInventory($siteId, $materialId, $quantity)
    {
        $inventory = WarehouseProduct::where('warehouse_id', $siteId)
            ->where('product_id', $materialId)
            ->first();

        if ($inventory) {
            $inventory->quantity = max(0, ($inventory->quantity ?? 0) - $quantity);
            $inventory->save();
        }

        // Also update the new inventory ledger system
        try {
            $this->stockService->updateCurrentStock($siteId, $materialId, -$quantity);
        } catch (\Exception $e) {
            Log::error('Failed to reverse new inventory system: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified GRN.
     */
    public function edit(Grn $grn)
    {
        if (!Auth::user()->isAbleTo('grn edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        
        // Eager load all relationships to prevent N+1 queries
        $grn->load([
            'purchaseOrder',
            'purchaseOrder.supplier',
            'supplier',
            'site',
            'items' => function ($query) {
                $query->with(['material' => function ($q) {
                    $q->with('unit');
                }, 'poItem', 'gstMaster']);
            },
        ]);

        // Load suppliers for Direct GRN dropdown
        $suppliers = Supplier::orderBy('name')->pluck('name', 'id')->prepend(__('Select Supplier'), '');

        // Get users for assign_to field
        $users = getActiveProjectEmployees();

        return view('grn.edit', compact('grn', 'suppliers', 'users'));
    }

    /**
     * Update the specified GRN in storage.
     */
    public function update(Request $request, Grn $grn)
    {
        
        if (!Auth::user()->isAbleTo('grn edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        

        // Check if GRN is locked
        if ($grn->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a locked GRN'
            ], 422);
        }

        $isDirectGrn = $grn->isDirectGrn();

        $validationRules = [
            'grn_type' => 'required|in:against_po,direct',
            'grn_date' => 'required|date',
            'delivery_challan_number' => 'nullable|string|max:255',
            'gate_entry_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'received_by' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'integer|exists:users,id',
        ];

        if ($isDirectGrn) {
            $validationRules['direct_supplier_id'] = 'required|exists:suppliers,id';
            $validationRules['direct_site_id'] = 'required|exists:projects,id';
            $validationRules['vehicle_number_direct'] = 'nullable|string|max:255';
            $validationRules['delivery_challan_file_direct'] = 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['reference_file_direct'] = 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
        } else {
            $validationRules['vehicle_number_po'] = 'nullable|string|max:255';
            $validationRules['delivery_challan_file_po'] = 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            $validationRules['reference_file_po'] = 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
        }

        $request->validate($validationRules);

        try {
            DB::beginTransaction();

            // Handle file uploads based on GRN type
            $deliveryChallanFile = $grn->delivery_challan_file;
            $referenceFile = $grn->reference_file;
            
            if ($isDirectGrn) {
                $vehicleNumber = $request->vehicle_number_direct;
                
                if ($request->hasFile('delivery_challan_file_direct')) {
                    $file = upload_file($request, 'delivery_challan_file_direct', 'delivery_challan', 'grn');
                    if (isset($file['flag']) && $file['flag'] == 1) {
                        $deliveryChallanFile = $file['url'];
                    }
                }
                
                if ($request->hasFile('reference_file_direct')) {
                    $file = upload_file($request, 'reference_file_direct', 'reference', 'grn');
                    if (isset($file['flag']) && $file['flag'] == 1) {
                        $referenceFile = $file['url'];
                    }
                }
            } else {
                $vehicleNumber = $request->vehicle_number_po;
                
                if ($request->hasFile('delivery_challan_file_po')) {
                    $file = upload_file($request, 'delivery_challan_file_po', 'delivery_challan', 'grn');
                    if (isset($file['flag']) && $file['flag'] == 1) {
                        $deliveryChallanFile = $file['url'];
                    }
                }
                
                if ($request->hasFile('reference_file_po')) {
                    $file = upload_file($request, 'reference_file_po', 'reference', 'grn');
                    if (isset($file['flag']) && $file['flag'] == 1) {
                        $referenceFile = $file['url'];
                    }
                }
            }

            // Update GRN
            $updateData = [
                'grn_date' => $request->grn_date,
                'delivery_challan_number' => $request->delivery_challan_number,
                'vehicle_number' => $vehicleNumber,
                'gate_entry_number' => $request->gate_entry_number,
                'delivery_challan_file' => $deliveryChallanFile,
                'reference_file' => $referenceFile,
                'description' => $request->description,
                'received_by' => $request->received_by,
                'remarks' => $request->remarks,
                'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
            ];

            if ($isDirectGrn) {
                $updateData['supplier_id'] = $request->direct_supplier_id;
                $updateData['site_id'] = $request->direct_site_id;
            }

            $grn->update($updateData);

            // Generate and save PDF for GRN
            try {
                $workspaceId = getActiveWorkSpace();
                $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
                if ($pdfPath) {
                    $grn->grn_pdf = $pdfPath;
                    $grn->save();
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate GRN PDF: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'GRN updated successfully!',
                'grn_id' => $grn->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GRN Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating GRN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get GRN details for printing/viewing.
     * Regenerates PDF every time to ensure latest data is reflected.
     */
    public function print(Grn $grn)
    {
        // Eager load all relationships to prevent N+1 queries
        $grn->load([
            'purchaseOrder',
            'supplier',
            'site',
            'creator',
            'items.material.unit',
            'items.poItem',
            'items.gstMaster',
            'workspace'
        ]);

        // Get workspace details
        $workspaceDetails = $grn->workspace ?? null;

        // Check if PDF already exists, delete it and regenerate
        if (!empty($grn->grn_pdf)) {
            // Delete existing PDF file
            try {
                delete_file($grn->grn_pdf);
            } catch (\Exception $e) {
                Log::error('Failed to delete existing GRN PDF: ' . $e->getMessage());
            }
        }

        // Generate new PDF
        try {
            $workspaceId = $grn->workspace_id ?? getActiveWorkSpace();
            $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
            if ($pdfPath) {
                $grn->grn_pdf = $pdfPath;
                $grn->save();
            }
        } catch (\Exception $e) {
            Log::error('Failed to regenerate GRN PDF: ' . $e->getMessage());
        }

        return view('grn.print', compact('grn', 'workspaceDetails'));
    }

    /**
     * Check if invoice exists for a GRN.
     */
    public function checkInvoice(Request $request)
    {
        $request->validate([
            'grn_id' => 'required|exists:grns,id'
        ]);

        $grn = Grn::findOrFail($request->grn_id);
        $invoiceExists = $grn->hasInvoice();
        $invoice = $grn->getInvoice();

        return response()->json([
            'success' => true,
            'invoice_exists' => $invoiceExists,
            'invoice_id' => $invoice ? $invoice->id : null,
            'invoice_number' => $invoice ? $invoice->invoice_number : null,
        ]);
    }

    /**
     * Generate and save PDF for GRN.
     *
     * @param Grn $grn
     * @param int $workspaceId
     * @return string|null
     */
    private function generateGrnPdf(Grn $grn, int $workspaceId): ?string
    {
        try {
            // Load relationships - must match what print view loads
            $grn->load([
                'purchaseOrder',
                'supplier',
                'site',
                'creator',
                'items.material.unit',
                'items.poItem',
                'workspace'
            ]);

            // Get company settings
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'company_logo', 'company_gst'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }

            // Get workspace details
            $workspaceDetails = null;
            if ($grn->workspace) {
                $workspaceDetails = $grn->workspace;
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
            }

            // Prepare data for the view
            $data = [
                'grn' => $grn,
                'settings' => $settings,
                'workspaceDetails' => $workspaceDetails,
            ];

            // Generate PDF using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

            $dompdf = new Dompdf($options);
            $data['isPdf'] = true;

            $html = view('grn.print', $data)->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();

            // Generate file name using GRN ID as prefix
            $fileName = $grn->id . '_' . $grn->grn_number . '.pdf';

            // Upload the PDF
            $uploadPath = 'pdf/grn';
            $uploadResult = upload_pdf_content($pdfContent, $uploadPath, $fileName);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                return $uploadResult['url'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('GRN PDF Generation Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get invoice data for a GRN (for creating invoice).
     */
    public function getInvoiceData(Grn $grn)
    {
        try {
            // Check if invoice already exists
            if (PurchaseInvoice::where('grn_id', $grn->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invoice already exists for this GRN'
                ], 422);
            }

            // Load GRN with relationships
            $grn->load([
                'items.material.unit',
                'items.gstMaster',
                'items.poItem.gstMaster',
                'items.poItem.material',
                'supplier',
                'site',
                'purchaseOrder.items'
            ]);

            // Determine if this is a Direct GRN
            $isDirectGrn = $grn->isDirectGrn();

            // Get tax type - from PO for PO-based GRN, or from GRN for Direct GRN
            $taxType = $isDirectGrn ? ($grn->tax_type ?? 'cgst') : ($grn->purchaseOrder->tax_type ?? 'cgst');

            // Calculate proportional discount (only for PO-based GRN)
            $totalDiscount = 0;
            $perUnitDiscount = 0;
            
            if (!$isDirectGrn) {
                // Get total_discount from Purchase Order
                $totalDiscount = (float) ($grn->purchaseOrder->total_discount ?? 0);
                
                // Get total_po_quantity = sum of all PO item quantities
                $totalPoQuantity = $grn->purchaseOrder->items->sum('quantity');
                
                // Calculate per_unit_discount
                if ($totalPoQuantity > 0) {
                    $perUnitDiscount = $totalDiscount / $totalPoQuantity;
                }
            }

            // Prepare items data
            $items = $grn->items->map(function ($grnItem) use ($taxType, $perUnitDiscount, $isDirectGrn) {
                $poItem = $grnItem->poItem;
                
                // For Direct GRN, get GST master from GrnItem relationship (or fallback to material)
                if ($isDirectGrn) {
                    $gstMaster = $grnItem->gstMaster ?? ($grnItem->material?->gstMaster ?? null);
                    $price = (float) $grnItem->price;
                    $unit = $grnItem->unit ?? ($grnItem->material?->unit?->name ?? 'PCS');
                } else {
                    $gstMaster = $poItem?->gstMaster ?? null;
                    $price = (float) ($poItem?->price ?? 0);
                    $unit = $poItem?->unit ?? 'PCS';
                }

                // Calculate tax amounts based on tax type
                $quantity = (float) $grnItem->accepted_qty;
                
                // Calculate discount using proportional logic based on GRN accepted_qty (only for PO-based GRN)
                $discountAmount = $isDirectGrn ? 0 : round($quantity * $perUnitDiscount, 2);
                
                $rowTotal = $quantity * $price;
                $taxableValue = max(0, $rowTotal - $discountAmount);

                $cgstAmount = 0;
                $sgstAmount = 0;
                $igstAmount = 0;
                $taxAmount = 0;

                if ($gstMaster) {
                    if ($taxType === 'igst') {
                        $igstRate = (float) ($gstMaster->igst ?? 0);
                        $igstAmount = ($taxableValue * $igstRate) / 100;
                        $taxAmount = $igstAmount;
                    } else {
                        $cgstRate = (float) ($gstMaster->cgst ?? 0);
                        $sgstRate = (float) ($gstMaster->sgst ?? 0);
                        $cgstAmount = ($taxableValue * $cgstRate) / 100;
                        $sgstAmount = ($taxableValue * $sgstRate) / 100;
                        $taxAmount = $cgstAmount + $sgstAmount;
                    }
                }

                $subtotal = $taxableValue + $taxAmount;

                return [
                    'id' => $grnItem->id,
                    'grn_item_id' => $grnItem->id,
                    'material_id' => $grnItem->material_id,
                    'material_name' => $grnItem->material->name ?? 'N/A',
                    'material_unit' => $grnItem->material?->unit?->name ?? $unit,
                    'po_item_id' => $poItem->id ?? null,
                    'quantity' => $quantity,
                    'accepted_qty' => $grnItem->accepted_qty,
                    'unit' => $unit,
                    'price' => $price,
                    'discount_amount' => round($discountAmount, 2),
                    'gst_master_id' => $gstMaster->id ?? null,
                    'cgst_rate' => $gstMaster->cgst ?? 0,
                    'sgst_rate' => $gstMaster->sgst ?? 0,
                    'igst_rate' => $gstMaster->igst ?? 0,
                    'cgst_amount' => round($cgstAmount, 2),
                    'sgst_amount' => round($sgstAmount, 2),
                    'igst_amount' => round($igstAmount, 2),
                    'tax_amount' => round($taxAmount, 2),
                    'taxable_value' => round($taxableValue, 2),
                    'subtotal' => round($subtotal, 2),
                ];
            });

            // Calculate totals
            $totalTaxableValue = $items->sum('taxable_value');
            $totalDiscount = $items->sum('discount_amount');
            $totalCgst = $items->sum('cgst_amount');
            $totalSgst = $items->sum('sgst_amount');
            $totalIgst = $items->sum('igst_amount');
            $totalTax = $items->sum('tax_amount');
            $grandTotal = $totalTaxableValue + $totalTax;

            return response()->json([
                'success' => true,
                'grn' => [
                    'id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                    'grn_date' => $grn->grn_date->format('Y-m-d'),
                    'grn_type' => $grn->grn_type,
                    'po_id' => $grn->po_id,
                    'po_number' => $grn->purchaseOrder->po_number ?? 'N/A',
                    'supplier_id' => $grn->supplier_id,
                    'supplier_name' => $grn->supplier->name ?? 'N/A',
                    'site_id' => $grn->site_id,
                    'site_name' => $grn->site->name ?? 'N/A',
                    'tax_type' => $taxType,
                    'assign_to' => $grn->assign_to,
                ],
                'items' => $items,
                'totals' => [
                    'total_taxable_value' => round($totalTaxableValue, 2),
                    'total_discount' => round($totalDiscount, 2),
                    'total_cgst' => round($totalCgst, 2),
                    'total_sgst' => round($totalSgst, 2),
                    'total_igst' => round($totalIgst, 2),
                    'total_tax' => round($totalTax, 2),
                    'grand_total' => round($grandTotal, 2),
                ],
                'next_invoice_number' => PurchaseInvoice::generateInvoiceNumber($grn->site_id),
            ]);
        } catch (\Exception $e) {
            Log::error('Get Invoice Data Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error loading invoice data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Correct PurchaseOrderItem.received_qty based on actual GRN items.
     * This fixes historical data where received_qty may have been double-counted.
     */
    public function correctReceivedQty(Request $request)
    {
        // Authorization check
        if (!Auth::user()->isAbleTo('grn create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $workspaceId = getActiveWorkSpace();
            $siteId = getActiveProject();

            // Get all PO items with their GRN items
            $poItems = PurchaseOrderItem::whereHas('purchaseOrder', function ($query) use ($workspaceId, $siteId) {
                $query->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId);
            })->with(['grnItems', 'purchaseOrder'])->get();

            $corrected = 0;
            $skipped = 0;
            $errors = [];

            foreach ($poItems as $poItem) {
                // Calculate correct received_qty from GrnItem records
                $correctReceivedQty = $poItem->grnItems->sum('received_qty');
                $currentReceivedQty = floatval($poItem->received_qty ?? 0);

                // Check if there's a discrepancy
                if (abs($correctReceivedQty - $currentReceivedQty) > 0.001) {
                    $poItem->received_qty = $correctReceivedQty;
                    $poItem->save();

                    $corrected++;
                    Log::info('GRN Received Qty Corrected', [
                        'po_item_id' => $poItem->id,
                        'po_number' => $poItem->purchaseOrder->po_number ?? 'N/A',
                        'material_id' => $poItem->material_id,
                        'old_received_qty' => $currentReceivedQty,
                        'new_received_qty' => $correctReceivedQty,
                    ]);
                } else {
                    $skipped++;
                }
            }

            // Also update PO statuses based on corrected quantities
            $this->recalculatePoStatuses($workspaceId, $siteId);

            return response()->json([
                'success' => true,
                'message' => "Correction complete. Corrected: {$corrected}, Already correct: {$skipped}",
                'corrected' => $corrected,
                'skipped' => $skipped
            ]);
        } catch (\Exception $e) {
            Log::error('GRN Received Qty Correction Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error correcting received quantity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate PO statuses based on current received quantities.
     */
    private function recalculatePoStatuses($workspaceId, $siteId)
    {
        $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED, PurchaseOrder::STATUS_COMPLETED])
            ->with('items')
            ->get();

        foreach ($purchaseOrders as $po) {
            $po->updateStatusFromGrn();
        }
    }
}

