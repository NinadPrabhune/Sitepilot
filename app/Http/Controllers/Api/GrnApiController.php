<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseInvoice;
use App\Models\WarehouseProduct;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Helpers\LedgerHelper;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @group Goods Received Note (GRN)
 * Endpoints for GRN management including PO-based and direct GRN creation
 */
class GrnApiController extends Controller
{
    /**
     * Display a listing of the GRNs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('grn manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $poId = $request->input('po_id');
            $status = $request->input('status');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            $query = Grn::with(['purchaseOrder', 'supplier', 'site', 'creator', 'items.material']);

            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId)) {
                $query->where('site_id', $siteId);
            }

            if (!empty($poId)) {
                $query->where('po_id', $poId);
            }

            if (!empty($status)) {
                $query->where('status', $status);
            }

            $grns = $query->orderBy('created_at', 'desc')->get();

            // Transform to include assign_to as user objects
            $data = $grns->map(function ($grn) {
                $assignToUsers = $grn->assign_to
                    ? \App\Models\User::whereIn('id', explode(',', $grn->assign_to))
                        ->select('id', 'name')
                        ->get()
                    : [];
                
                $grnArray = $grn->toArray();
                $grnArray['assign_to'] = $assignToUsers;
                return $grnArray;
            });

            return response()->json([
                'status' => true,
                'message' => 'GRNs fetched successfully',
                'data' => $data,
                'grn_number' => $grns->pluck('grn_number')->toArray()
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Index API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get data for creating a new GRN.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        if (!Auth::user()->isAbleTo('grn create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Get all approved purchase orders that are not fully received
            $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
                ->where('site_id', $siteId)
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
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

            return response()->json([
                'status' => true,
                'message' => 'GRN create data fetched successfully',
                'data' => [
                    'purchase_orders' => $purchaseOrders,
                    'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
                    'materials' => \App\Models\Material::with('category', 'unit', 'gstMaster')->get(),
                    'gst_masters' => \App\Models\GstMaster::where('is_active', true)->get(),
                    'selected_site_id' => $siteId,
                    'users' => getActiveProjectEmployees(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Create API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Fetch PO details via AJAX.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPoDetails(Request $request)
    {
        if (!Auth::user()->isAbleTo('grn manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $validator = Validator::make($request->all(), [
                'po_id' => 'required|exists:purchase_orders,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::with([
                'supplier',
                'site',
                'items.material.unit',
                'items' => function($query) {
                    $query->withSum('grnItems as total_received', 'received_qty');
                }
            ])->findOrFail($request->po_id);

            // Calculate remaining quantities for each item
            $items = $purchaseOrder->items->map(function($item) {
                $receivedQty = $item->grnItems->sum('received_qty');
                $remainingQty = $item->quantity - $receivedQty;
                
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
                'status' => true,
                'message' => 'PO details fetched successfully',
                'data' => [
                    'po' => [
                        'id' => $purchaseOrder->id,
                        'po_number' => $purchaseOrder->po_number,
                        'po_date' => $purchaseOrder->po_date->format('Y-m-d'),
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'supplier_name' => $purchaseOrder->supplier->name ?? 'N/A',
                        'site_id' => $purchaseOrder->site_id,
                        'site_name' => optional($purchaseOrder->site)->name ?? 'N/A',
                        'status' => $purchaseOrder->status,
                    ],
                    'items' => $items
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Get PO Details API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Create GRN from mobile application.
     * Supports both PO-based and Direct GRN.
     *
     * @bodyParam grn_type string required Type (against_po or direct). Example: against_po
     * @bodyParam grn_date date required GRN date. Example: 2024-01-15
     * @bodyParam delivery_challan_number string optional Delivery challan number. Example: DC-123
     * @bodyParam vehicle_number string optional Vehicle number. Example: MH-01-AB-1234
     * @bodyParam gate_entry_number string optional Gate entry number. Example: GE-456
     * @bodyParam description string optional Description. Example: Material delivery
     * @bodyParam received_by string optional Received by. Example: John Doe
     * @bodyParam remarks string optional Remarks. Example: Received in good condition
     * @bodyParam items array required Array of GRN items.
     * @bodyParam items.*.received_qty number required Received quantity. Example: 100
     * @bodyParam items.*.accepted_qty number required Accepted quantity. Example: 95
     * @bodyParam items.*.rejected_qty number required Rejected quantity. Example: 5
     * @bodyParam delivery_challan_file file optional Delivery challan document (max 10MB).
     * @bodyParam reference_file file optional Reference document (max 10MB).
     * @bodyParam po_id integer required if grn_type=against_po Purchase Order ID. Example: 1
     * @bodyParam items.*.po_item_id integer required if grn_type=against_po PO Item ID. Example: 5
     * @bodyParam supplier_id integer required if grn_type=direct Supplier ID. Example: 3
     * @bodyParam site_id integer required if grn_type=direct Site ID. Example: 5
     * @bodyParam supplier_invoice_number string required if grn_type=direct Supplier invoice number. Example: INV-001
     * @bodyParam supplier_invoice_date date optional if grn_type=direct Supplier invoice date. Example: 2024-01-15
     * @bodyParam tax_type string required if grn_type=direct Tax type (cgst or igst). Example: cgst
     * @bodyParam items.*.material_id integer required if grn_type=direct Material ID. Example: 10
     * @bodyParam items.*.price number required if grn_type=direct Unit price. Example: 500.00
     * @bodyParam items.*.gst_master_id integer optional if grn_type=direct GST Master ID. Example: 1
     * @response {"status": true, "message": "GRN created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('grn create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            Log::info('GRN API Request', $request->all());

            $isDirectGrn = $request->input('grn_type') === 'direct';

            // Build conditional validation rules
            $validationRules = [
                'grn_type' => 'required|in:against_po,direct',
                'grn_date' => 'required|date',
                'delivery_challan_number' => 'nullable|string',
                'vehicle_number' => 'nullable|string',
                'gate_entry_number' => 'nullable|string',
                'description' => 'nullable|string',
                'received_by' => 'nullable|string',
                'remarks' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.received_qty' => 'required|numeric|min:0',
                'items.*.accepted_qty' => 'required|numeric|min:0',
                'items.*.rejected_qty' => 'required|numeric|min:0',
                'delivery_challan_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
                'idempotency_key' => 'nullable|string|max:64',
            ];

            if ($isDirectGrn) {
                $validationRules['supplier_id'] = 'required|exists:suppliers,id';
                $validationRules['site_id'] = 'required|exists:projects,id';
                $validationRules['supplier_invoice_number'] = 'required|string|max:255';
                $validationRules['supplier_invoice_date'] = 'nullable|date';
                $validationRules['tax_type'] = 'required|in:cgst,igst';
                $validationRules['items.*.material_id'] = 'required|exists:materials,id';
                $validationRules['items.*.price'] = 'required|numeric|min:0';
                $validationRules['items.*.gst_master_id'] = 'nullable|exists:gst_masters,id';
            } else {
                $validationRules['po_id'] = 'required|exists:purchase_orders,id';
                $validationRules['items.*.po_item_id'] = 'required|exists:purchase_order_items,id';
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Idempotency check before GRN creation
            if (!empty($request->idempotency_key)) {
                $existingGRN = Grn::where('idempotency_key', $request->idempotency_key)
                    ->where('workspace_id', $workspaceId)
                    ->first();
                
                if ($existingGRN) {
                    return response()->json([
                        'status' => true,
                        'message' => 'GRN already exists (idempotent)',
                        'data' => $existingGRN->load(['supplier', 'items.material', 'site'])->toArray()
                    ], 200);
                }
            }

            // Validate that accepted + rejected = received
            foreach ($request->items as $index => $item) {
                $total = floatval($item['accepted_qty']) + floatval($item['rejected_qty']);
                $received = floatval($item['received_qty']);

                if (abs($total - $received) > 0.001) {
                    return response()->json([
                        'status' => false,
                        'message' => "Item " . ($index + 1) . ": Accepted Qty + Rejected Qty must equal Received Qty"
                    ], 422);
                }
            }

            // Prepare common data
            $workspaceId = $request->input('workspace_id');
            $createdBy = $request->input('created_by');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($createdBy) && function_exists('creatorId')) {
                $createdBy = creatorId();
            }

            // Handle file uploads
            $deliveryChallanFile = null;
            $referenceFile = null;

            if ($request->hasFile('delivery_challan_file')) {
                $deliveryChallanFile = upload_file($request, 'delivery_challan_file', 'delivery_challan', 'grn');
                if (isset($deliveryChallanFile['flag']) && $deliveryChallanFile['flag'] == 1) {
                    $deliveryChallanFile = $deliveryChallanFile['url'];
                }
            }

            if ($request->hasFile('reference_file')) {
                $referenceFile = upload_file($request, 'reference_file', 'reference', 'grn');
                if (isset($referenceFile['flag']) && $referenceFile['flag'] == 1) {
                    $referenceFile = $referenceFile['url'];
                }
            }

            if ($isDirectGrn) {
                // Delegate Direct GRN creation to GrnService
                $data = $request->except(['_token']);
                $data['delivery_challan_file'] = $deliveryChallanFile;
                $data['reference_file'] = $referenceFile;
                $data['created_by'] = $createdBy;
                $data['workspace_id'] = $workspaceId;
                $data['assign_to'] = $request->assign_to; // Trait mutator handles array to string conversion
                $data['idempotency_key'] = $request->idempotency_key;

                $grnService = new \App\Services\GrnService(new \App\Services\StockService());
                $grn = $grnService->createDirectGrn($data);

                // Generate and save PDF for GRN
                try {
                    $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
                    if ($pdfPath) {
                        $grn->grn_pdf = $pdfPath;
                        $grn->save();
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to generate GRN PDF: ' . $e->getMessage());
                }

                Log::info('Direct GRN Created via API', [
                    'grn_id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'GRN created successfully',
                    'data' => [
                        'grn_id' => $grn->id,
                        'grn_number' => $grn->grn_number,
                        'grn_pdf' => $grn->grn_pdf ?? null
                    ]
                ], 200);
            }

            // PO-based GRN creation (existing logic)
            $purchaseOrder = PurchaseOrder::with('items')->findOrFail($request->po_id);

            if (!$purchaseOrder->canCreateGrn()) {
                return response()->json([
                    'status' => false,
                    'message' => 'GRN cannot be created for this PO status. Only Approved or Partial Received purchase orders can receive goods.'
                ], 422);
            }

            // Validate quantities don't exceed remaining
            foreach ($request->items as $item) {
                $poItem = PurchaseOrderItem::find($item['po_item_id']);
                $receivedQty = floatval($item['received_qty']);
                $existingReceived = $poItem->received_qty ?? 0;
                $remainingQty = $poItem->quantity - $existingReceived;

                if ($receivedQty > $remainingQty) {
                    return response()->json([
                        'status' => false,
                        'message' => "Cannot receive more than remaining quantity for item ID: {$item['po_item_id']}"
                    ], 422);
                }
            }

            DB::beginTransaction();

            try {
                // Create GRN header
                $grn = Grn::create([
                    'grn_number' => Grn::generateGrnNumber($purchaseOrder->site_id),
                    'grn_type' => Grn::TYPE_AGAINST_PO,
                    'po_id' => $request->po_id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'site_id' => $purchaseOrder->site_id,
                    'grn_date' => $request->grn_date,
                    'delivery_challan_number' => $request->delivery_challan_number ?? null,
                    'vehicle_number' => $request->vehicle_number ?? null,
                    'gate_entry_number' => $request->gate_entry_number ?? null,
                    'delivery_challan_file' => $deliveryChallanFile,
                    'reference_file' => $referenceFile,
                    'description' => $request->description ?? null,
                    'received_by' => $request->received_by ?? null,
                    'remarks' => $request->remarks ?? null,
                    'status' => Grn::STATUS_COMPLETED,
                    'created_by' => $createdBy,
                    'workspace_id' => $workspaceId,
                    'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
                ]);

                // Process items
                foreach ($request->items as $item) {
                    $poItem = PurchaseOrderItem::find($item['po_item_id']);

                    GrnItem::create([
                        'grn_id' => $grn->id,
                        'po_item_id' => $item['po_item_id'],
                        'material_id' => $poItem->material_id,
                        'ordered_qty' => $poItem->quantity,
                        'received_qty' => $item['received_qty'],
                        'accepted_qty' => $item['accepted_qty'],
                        'rejected_qty' => $item['rejected_qty'],
                        'price' => $poItem->price,
                        'remarks' => $item['remarks'] ?? null,
                    ]);

                    // Update PO item received quantity
                    if ($poItem) {
                        $poItem->received_qty = ($poItem->received_qty ?? 0) + floatval($item['received_qty']);
                        $poItem->save();
                    }
                }

                // Update PO status
                $purchaseOrder->load('items');
                $allReceived = true;
                $anyReceived = false;

                foreach ($purchaseOrder->items as $item) {
                    $receivedQty = floatval($item->received_qty ?? 0);
                    $orderedQty = floatval($item->quantity);

                    if ($receivedQty > 0) {
                        $anyReceived = true;
                    }
                    if ($receivedQty < $orderedQty) {
                        $allReceived = false;
                    }
                }

                if ($allReceived) {
                    $purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
                    $purchaseOrder->save();
                } elseif ($anyReceived) {
                    $purchaseOrder->status = PurchaseOrder::STATUS_PARTIAL_RECEIVED;
                    $purchaseOrder->save();
                }

                // Create supplier ledger entry for TYPE_GRN (inside transaction)
                try {
                    app(\App\Services\LedgerService::class)->createGRNEntry($grn);
                } catch (\Exception $e) {
                    Log::error('Failed to create supplier ledger entry for GRN: ' . $e->getMessage());
                    throw $e; // Rollback transaction
                }

                DB::commit();

                // Generate and save PDF for GRN
                try {
                    $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
                    if ($pdfPath) {
                        $grn->grn_pdf = $pdfPath;
                        $grn->save();
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to generate GRN PDF: ' . $e->getMessage());
                }

                Log::info('GRN Created via API', [
                    'grn_id' => $grn->id,
                    'po_id' => $request->po_id
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'GRN created successfully',
                    'data' => [
                        'grn_id' => $grn->id,
                        'grn_number' => $grn->grn_number,
                        'grn_pdf' => $grn->grn_pdf ?? null
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('GRN API ERROR', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Internal Server Error'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('GRN API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Update inventory quantity for a material at a site.
     */
    private function updateInventory($siteId, $materialId, $quantity, $workspaceId)
    {
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
            $createdBy = function_exists('creatorId') ? creatorId() : null;
            WarehouseProduct::create([
                'warehouse_id' => $siteId,
                'product_id' => $materialId,
                'quantity' => $quantity,
                'created_by' => $createdBy,
                'workspace' => $workspaceId,
            ]);
        }
    }

    /**
     * Display the specified GRN.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('grn show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $grn = Grn::with(['purchaseOrder.supplier', 'supplier', 'site', 'creator', 'items.material.unit', 'items.poItem'])->find($id);

            if (!$grn) {
                return response()->json([
                    'status' => false,
                    'message' => 'GRN not found'
                ], 404);
            }

            $grnData = $grn->toArray();
            $grnData['assign_to'] = $grn->assign_to
                ? \App\Models\User::whereIn('id', explode(',', $grn->assign_to))
                    ->select('id', 'name')
                    ->get()
                : [];

            return response()->json([
                'status' => true,
                'message' => 'GRN fetched successfully',
                'data' => $grnData,
                'grn_number' => $grn->grn_number
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Show API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Remove the specified GRN from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('grn delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $grn = Grn::with('items')->find($id);

            if (!$grn) {
                return response()->json([
                    'status' => false,
                    'message' => 'GRN not found'
                ], 404);
            }

            // Check if GRN is locked
            if ($grn->is_locked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete a locked GRN'
                ], 422);
            }

            DB::beginTransaction();

            try {
                // STEP 1: Load all items BEFORE any deletion
                $grnItems = $grn->items()->get();

                // STEP 2: Reverse inventory and PO received_qty
                foreach ($grnItems as $item) {
                    if ($item->accepted_qty > 0) {
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

                // STEP 4: Reverse MaterialProjectStock
                foreach ($grnItems as $item) {
                    if ($item->accepted_qty > 0) {
                        try {
                            $stockService = new \App\Services\StockService();
                            $stockService->updateCurrentStock($grn->site_id, $item->material_id, -$item->accepted_qty);
                        } catch (\Exception $e) {
                            Log::error('Failed to reverse stock: ' . $e->getMessage());
                        }
                    }
                }

                // STEP 5: Clean up supplier ledger entries
                \App\Models\SupplierTransaction::where('reference_type', 'grn')
                    ->where('reference_id', $grn->id)
                    ->delete();
                \App\Helpers\LedgerHelper::recalculateSupplierBalance($grn->supplier_id);

                // STEP 6: Delete GRN items (soft-delete)
                GrnItem::where('grn_id', $grn->id)->delete();

                // STEP 7: Delete GRN (soft-delete)
                $grn->delete();

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'GRN deleted successfully'
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('GRN Delete API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
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
    }

    /**
     * Get data for editing the specified GRN.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Auth::user()->isAbleTo('grn edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        
        
        try {
            $grn = Grn::with(['purchaseOrder', 'purchaseOrder.supplier', 'supplier', 'site', 'items.material', 'items.poItem'])->find($id);

            if (!$grn) {
                return response()->json([
                    'status' => false,
                    'message' => 'GRN not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'GRN data fetched successfully',
                'data' => $grn,
                'grn_number' => $grn->grn_number
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Edit API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Update the specified GRN in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        
        if (!Auth::user()->isAbleTo('grn edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        
        try {
            $grn = Grn::find($id);

            if (!$grn) {
                return response()->json([
                    'status' => false,
                    'message' => 'GRN not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'grn_date' => 'required|date',
                'delivery_challan_number' => 'nullable|string',
                'vehicle_number' => 'nullable|string',
                'gate_entry_number' => 'nullable|string',
                'description' => 'nullable|string',
                'received_by' => 'nullable|string',
                'remarks' => 'nullable|string',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Update GRN
                $grn->update([
                    'grn_date' => $request->grn_date,
                    'delivery_challan_number' => $request->delivery_challan_number ?? null,
                    'vehicle_number' => $request->vehicle_number ?? null,
                    'gate_entry_number' => $request->gate_entry_number ?? null,
                    'description' => $request->description ?? null,
                    'received_by' => $request->received_by ?? null,
                    'remarks' => $request->remarks ?? null,
                    'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'GRN updated successfully',
                    'data' => $grn,
                    'grn_number' => $grn->grn_number
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('GRN Update API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Create a Direct GRN (without Purchase Order).
     * Simplified endpoint for mobile integration.
     *
     * Automatically sets:
     * - received_qty = quantity
     * - accepted_qty = quantity
     * - rejected_qty = 0
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDirectGrn(Request $request)
    {
        if (!Auth::user()->isAbleTo('grn create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            Log::info('Direct GRN API Request', $request->all());

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'site_id' => 'required|exists:projects,id',
                'grn_date' => 'required|date',
                'supplier_invoice_number' => 'required|string|max:255',
                'supplier_invoice_date' => 'nullable|date',
                'tax_type' => 'required|in:cgst,igst',
                'delivery_challan_number' => 'nullable|string|max:255',
                'vehicle_number' => 'nullable|string|max:255',
                'gate_entry_number' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'received_by' => 'nullable|string|max:255',
                'remarks' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.gst_master_id' => 'nullable|exists:gst_masters,id',
                'items.*.remarks' => 'nullable|string',
                'delivery_challan_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Map quantity to received_qty, accepted_qty, rejected_qty
            $items = $request->input('items');
            foreach ($items as $index => $item) {
                $qty = floatval($item['quantity']);
                $items[$index]['received_qty'] = $qty;
                $items[$index]['accepted_qty'] = $qty;
                $items[$index]['rejected_qty'] = 0;
            }

            // Handle file uploads
            $deliveryChallanFile = null;
            $referenceFile = null;

            if ($request->hasFile('delivery_challan_file')) {
                $deliveryChallanFile = upload_file($request, 'delivery_challan_file', 'delivery_challan', 'grn');
                if (isset($deliveryChallanFile['flag']) && $deliveryChallanFile['flag'] == 1) {
                    $deliveryChallanFile = $deliveryChallanFile['url'];
                }
            }

            if ($request->hasFile('reference_file')) {
                $referenceFile = upload_file($request, 'reference_file', 'reference', 'grn');
                if (isset($referenceFile['flag']) && $referenceFile['flag'] == 1) {
                    $referenceFile = $referenceFile['url'];
                }
            }

            // Prepare data for GrnService
            $workspaceId = $request->input('workspace_id');
            $createdBy = $request->input('created_by');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($createdBy) && function_exists('creatorId')) {
                $createdBy = creatorId();
            }

            $data = [
                'supplier_id' => $request->supplier_id,
                'site_id' => $request->site_id,
                'grn_date' => $request->grn_date,
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'supplier_invoice_date' => $request->supplier_invoice_date ?? null,
                'tax_type' => $request->tax_type,
                'delivery_challan_number' => $request->delivery_challan_number ?? null,
                'vehicle_number' => $request->vehicle_number ?? null,
                'gate_entry_number' => $request->gate_entry_number ?? null,
                'delivery_challan_file' => $deliveryChallanFile,
                'reference_file' => $referenceFile,
                'description' => $request->description ?? null,
                'received_by' => $request->received_by ?? null,
                'remarks' => $request->remarks ?? null,
                'items' => $items,
                'created_by' => $createdBy,
                'workspace_id' => $workspaceId,
            ];

            $grnService = new \App\Services\GrnService(new \App\Services\StockService());
            $grn = $grnService->createDirectGrn($data);

            // Generate and save PDF for GRN
            try {
                $pdfPath = $this->generateGrnPdf($grn, $workspaceId);
                if ($pdfPath) {
                    $grn->grn_pdf = $pdfPath;
                    $grn->save();
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate GRN PDF: ' . $e->getMessage());
            }

            Log::info('Direct GRN Created via API', [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Direct GRN created successfully',
                'data' => [
                    'grn_id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                    'grn_pdf' => $grn->grn_pdf ?? null
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Direct GRN API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Legacy function - Create GRN from mobile application.
     * Use store() method instead.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createData(Request $request)
    {
        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Get all approved purchase orders that are not fully received
            $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
                ->where('site_id', $siteId)
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
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

            return response()->json([
                'status' => true,
                'message' => 'GRN create data fetched successfully',
                'data' => [
                    'purchase_orders' => $purchaseOrders,
                    'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
                    'materials' => \App\Models\Material::with('category', 'unit', 'gstMaster')->get(),
                    'gst_masters' => \App\Models\GstMaster::where('is_active', true)->get(),
                    'selected_site_id' => $siteId,
                    'nextGRNno' => Grn::generateGrnNumber($siteId)
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('GRN Create API ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
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
            $grn->load([
                'purchaseOrder',
                'supplier',
                'site',
                'creator',
                'items.material.unit',
                'items.poItem',
                'workspace'
            ]);

            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'company_logo', 'company_gst'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key, null, $workspaceId);
            }

            $workspaceDetails = null;
            if ($grn->workspace) {
                $workspaceDetails = $grn->workspace;
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

            $data = [
                'grn' => $grn,
                'settings' => $settings,
                'workspaceDetails' => $workspaceDetails,
            ];

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

            $fileName = $grn->id . '_' . $grn->grn_number . '.pdf';

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
}
