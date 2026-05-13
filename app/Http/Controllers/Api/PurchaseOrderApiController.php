<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Indent;
use App\Models\Supplier;
use App\Models\GstMaster;
use App\Models\Material;
use App\Models\WorkSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\LedgerHelper;
use Illuminate\Routing\Controllers\Middleware;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @group Purchase Orders
 * Endpoints for purchase order management including creation, approval, and PDF generation
 */
class PurchaseOrderApiController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(middleware: 'auth:sanctum', except: ['index', 'createData', 'show']),
        ];
    }
    /**
     * Display a listing of the purchase orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('purchase-order manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            // Get workspace_id from request (mobile API should pass it explicitly)
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            // Build query with eager loading
            $query = PurchaseOrder::with(['supplier', 'creator', 'indent', 'items.material', 'site']);

            // Apply workspace filter if provided
            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            // Apply site filter if provided
            if (!empty($siteId)) {
                $query->where('site_id', $siteId);
            }

            // Filter by invoicing_status (replaces payment_flag)
            if ($request->has('invoicing_status') && !empty($request->invoicing_status)) {
                $query->where('invoiced_status', $request->invoicing_status);
            }

            // Legacy payment_flag filter for backward compatibility
            if ($request->has('payment_flag') && !empty($request->payment_flag)) {
                $query->where('payment_flag_deprecated', $request->payment_flag);
            }

            // Filter for invoicing eligible POs only (replaces payment_eligible)
            if ($request->has('invoicing_eligible') && $request->invoicing_eligible) {
                $query->whereIn('invoiced_status', [
                    'not_invoiced',
                    'partially_invoiced'
                ]);
            }

            // Order by creation date (newest first)
            $purchaseOrders = $query->orderBy('created_at', 'desc')->get();

            // Transform to include invoicing_status
            $data = $purchaseOrders->map(function ($po) {
                $assignToUsers = $po->assign_to
                    ? \App\Models\User::whereIn('id', explode(',', $po->assign_to))
                        ->select('id', 'name')
                        ->get()
                    : [];
                
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number ?? null,
                    'po_date' => $po->po_date ?? null,
                    'indent_id' => $po->indent_id ?? null,
                    'supplier_id' => $po->supplier_id ?? null,
                    'site_id' => $po->site_id ?? null,
                    'po_pdf' => $po->po_pdf ?? null,
                    'grand_total' => $po->grand_total,
                    'status' => $po->status,
                    'invoicing_status' => $po->invoiced_status ?? 'not_invoiced',
                    'invoicing_status_display' => $po->getInvoicedStatusDisplay(),
                    'delivery_address' => $po->delivery_address ?? null,
                    'created_at' => $po->created_at,
                    'assign_to' => $assignToUsers,
                    'creator_name' => $po->creator->name ?? null,
                    'is_payment_completed' => $po->isPaymentCompleted(),
                    'has_advance_request' => $po->hasAdvanceRequest(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Purchase orders fetched successfully',
                'data' => $data
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching purchase orders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get create data for purchase order form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('purchase-order create')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            // Get workspace and site from request or helper functions
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            // If not provided in request, try helper functions
            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            if (empty($siteId) && function_exists('getActiveProject')) {
                $siteId = getActiveProject();
            }

            // Fetch suppliers
            $suppliers = Supplier::select('id', 'name', 'email', 'phone')
                ->orderBy('name', 'asc')
                ->get();

            // Fetch materials with category and unit
            $materials = Material::with(['category', 'unit'])
                ->orderBy('name', 'asc')
                ->get();

            // Transform materials to JSON structure
            $materialsTransformed = $materials->map(function ($m) {
                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'unit' => $m->unit?->name ?? '',
                    'price' => $m->price ?? 0,
                    'category_id' => $m->category_id,
                    'category_name' => $m->category?->name ?? '',
                ];
            })->values();

            // Fetch sites/projects
            $sites = \Workdo\Taskly\Entities\Project::where('workspace', $workspaceId)
                ->projectonly()
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            // Fetch indents with specific statuses
            $indentsQuery = Indent::with(['items.material', 'supplier', 'purchaseOrders.items'])
                ->whereIn('status', [Indent::STATUS_OPEN, Indent::STATUS_PARTIALLY_CLOSED]);

            // Filter by site if provided
            if (!empty($siteId)) {
                $indentsQuery->where('site_id', $siteId);
            }

            $indents = $indentsQuery->orderBy('created_at', 'desc')->get();

            // Calculate remaining_quantity for each indent item
            $indents = $indents->map(function ($indent) {
                $indent->items = $indent->items->map(function ($item) use ($indent) {
                    $item->remaining_quantity = $indent->getRemainingQuantityForMaterial($item->material_id);
                    return $item;
                });
                return $indent;
            });

            // Get GST masters where is_active = true
            $gstMasters = GstMaster::where('is_active', true)->get();

            // Get users for assign_to field
            $users = getActiveProjectEmployees();

            // Prepare response data
            $data = [
                'suppliers' => $suppliers,
                'materials' => $materialsTransformed,
                'sites' => $sites,
                'indents' => $indents,
                'selectedSiteId' => $siteId,
                'gstMasters' => $gstMasters,
                'next_po_number' => PurchaseOrder::generatePONumber($siteId),
                'users' => $users,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Purchase order create data fetched successfully',
                'data' => $data
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching purchase order create data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_CREATE_DATA_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created purchase order in storage.
     *
     * @bodyParam po_date date required PO date. Example: 2024-01-15
     * @bodyParam supplier_id integer required Supplier ID. Example: 1
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam indent_id integer required Indent ID. Example: 10
     * @bodyParam tax_type string required Tax type (cgst or igst). Example: cgst
     * @bodyParam description string optional Description. Example: Construction materials
     * @bodyParam items array required Array of PO items.
     * @bodyParam items.*.material_id integer required Material ID. Example: 10
     * @bodyParam items.*.quantity number required Quantity. Example: 100
     * @bodyParam items.*.unit string required Unit. Example: kg
     * @bodyParam items.*.price number required Unit price. Example: 500.00
     * @bodyParam items.*.gst_master_id integer required GST Master ID. Example: 1
     * @bodyParam items.*.discount_amount number required Discount amount. Example: 0
     * @bodyParam additional_charge number required Additional charge. Example: 0
     * @bodyParam additional_deduction number required Additional deduction. Example: 0
     * @bodyParam additional_discount number required Additional discount. Example: 0
     * @bodyParam delivery_date date required Delivery date. Example: 2024-02-15
     * @bodyParam delivery_address string optional Delivery address. Example: Site A, Mumbai
     * @bodyParam delivery_terms_conditions string optional Delivery terms. Example: Within 30 days
     * @bodyParam payment_terms_conditions string optional Payment terms. Example: Net 30 days
     * @bodyParam remark string optional Remarks. Example: Urgent delivery
     * @bodyParam reference_file file optional Reference document (max 10MB).
     * @response {"success": true, "message": "Purchase order created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('purchase-order create')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            // Get workspace_id from request (mobile API should pass it explicitly)
            $workspaceId = $request->input('workspace_id');
            $createdBy = $request->input('created_by');

            // If no workspace_id provided, try to get from helper
            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            // If no created_by provided, try to get from auth
            if (empty($createdBy) && function_exists('creatorId')) {
                $createdBy = creatorId();
            }

            // Validation rules based on model $fillable and business logic
            $validator = Validator::make($request->all(), [
                'po_date' => 'required|date',
                'supplier_id' => 'required|exists:suppliers,id',
                'site_id' => 'required|exists:projects,id',
                'indent_id' => 'required|exists:indents,id',
                'tax_type' => 'required|in:cgst,igst',
                'description' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.gst_master_id' => 'required|exists:gst_masters,id',
                'items.*.discount_amount' => 'required|numeric|min:0',
                'additional_charge' => 'required|numeric|min:0',
                'additional_deduction' => 'required|numeric|min:0',
                'additional_discount' => 'required|numeric|min:0',
                'delivery_date' => 'required|date',
                'delivery_address' => 'nullable|string',
                'delivery_terms_conditions' => 'nullable|string',
                'payment_terms_conditions' => 'nullable|string',
                'remark' => 'nullable|string',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
                'idempotency_key' => 'nullable|string|max:64',
            ], [
                'delivery_address.string' => 'The delivery address must be a valid string.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Idempotency check before PO creation
            if (!empty($request->idempotency_key)) {
                $existingPO = PurchaseOrder::where('idempotency_key', $request->idempotency_key)
                    ->where('workspace_id', $workspaceId)
                    ->first();
                
                if ($existingPO) {
                    return response()->json([
                        'success' => true,
                        'message' => 'PO already exists (idempotent)',
                        'data' => $existingPO->load(['supplier', 'creator', 'items.material', 'site'])->toArray()
                    ], 200);
                }
            }

            // Validate quantity against indent if selected
            if ($request->indent_id) {
                $indent = Indent::with('items')->find($request->indent_id);

                if (!$indent || !$indent->canAcceptPurchaseOrder()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'PO_INDENT_ERROR',
                        'message' => 'This indent is closed and cannot accept new purchase orders.'
                    ], 400);
                }

                foreach ($request->items as $index => $itemData) {
                    $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();

                    if (!$indentItem) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_MATERIAL_ERROR',
                            'message' => 'Material not found in selected indent.'
                        ], 400);
                    }

                    $remainingQuantity = $indent->getRemainingQuantityForMaterial($itemData['material_id']);

                    if (floatval($itemData['quantity']) > floatval($remainingQuantity)) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_QUANTITY_ERROR',
                            'message' => "Quantity for material exceeds remaining indent quantity. Maximum available: {$remainingQuantity}"
                        ], 400);
                    }
                }
            }

            // Use DB transaction for data integrity
            \DB::beginTransaction();
            try {
                // Reference File Upload
                $referenceFilePath = null;

                if ($request->hasFile('reference_file')) {
                    $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('reference_file')->getClientOriginalExtension();

                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    // Upload using helper
                    $path = upload_file($request, 'reference_file', $fileNameToStore, 'purchase-orders');

                    if (isset($path['flag']) && $path['flag'] == 0) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_FILE_ERROR',
                            'message' => $path['msg'] ?? 'Error uploading file'
                        ], 400);
                    }

                    if (!empty($path['url'])) {
                        $referenceFilePath = $path['url'];
                    }
                }

                // Create Purchase Order
                $purchaseOrder = PurchaseOrder::create([
                    'po_number' => PurchaseOrder::generatePONumber($request->site_id),
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
                    'created_by' => $createdBy,
                    'workspace_id' => $workspaceId,
                    'additional_charge' => floatval($request->additional_charge ?? 0),
                    'additional_deduction' => floatval($request->additional_deduction ?? 0),
                    'additional_discount' => floatval($request->additional_discount ?? 0),
                    'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
                    'idempotency_key' => $request->idempotency_key,
                ]);

                // Create items
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

                // Recalculate all totals on backend
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
                    Log::info('Starting PDF generation for PO', [
                        'po_id' => $purchaseOrder->id,
                        'po_number' => $purchaseOrder->po_number,
                        'workspace_id' => $workspaceId,
                    ]);
                    $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
                    if ($pdfPath) {
                        $purchaseOrder->po_pdf = $pdfPath;
                        $purchaseOrder->save();
                        Log::info('PO PDF generated successfully', [
                            'po_id' => $purchaseOrder->id,
                            'po_number' => $purchaseOrder->po_number,
                            'pdf_path' => $pdfPath,
                        ]);
                    } else {
                        Log::warning('PO PDF generation returned null', [
                            'po_id' => $purchaseOrder->id,
                            'po_number' => $purchaseOrder->po_number,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the PO creation
                    Log::error('Error generating PO PDF: ' . $e->getMessage(), [
                        'po_id' => $purchaseOrder->id,
                        'po_number' => $purchaseOrder->po_number,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                // Load relationships for response
                $purchaseOrder->load(['supplier', 'creator', 'indent', 'items.material', 'site']);

                $poData = $purchaseOrder->toArray();
                $poData['creator_name'] = $purchaseOrder->creator->name ?? null;
                $poData['po_pdf'] = $purchaseOrder->po_pdf ?? null;

                // Create supplier ledger entry for TYPE_PO (inside transaction)
                try {
                    app(\App\Services\LedgerService::class)->createPOEntry($purchaseOrder);
                } catch (\Exception $e) {
                    Log::error('Failed to create supplier ledger entry for PO: ' . $e->getMessage());
                    throw $e; // Rollback transaction
                }

                \DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase Order created successfully',
                    'data' => $poData
                ], 201);
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('Error creating purchase order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('purchase-order show')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'creator', 'indent', 'items.material', 'items.gstMaster', 'site'])->find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_NOT_FOUND',
                    'message' => 'Purchase order not found'
                ], 404);
            }

            $poData = $purchaseOrder->toArray();
            $poData['creator_name'] = $purchaseOrder->creator->name ?? null;
            $poData['po_pdf'] = $purchaseOrder->po_pdf ?? null;
            $poData['assign_to'] = $purchaseOrder->assign_to
                ? \App\Models\User::whereIn('id', explode(',', $purchaseOrder->assign_to))
                    ->select('id', 'name')
                    ->get()
                : [];

            return response()->json([
                'success' => true,
                'message' => 'Purchase order fetched successfully',
                'data' => $poData
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching purchase order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified purchase order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('purchase-order edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            $purchaseOrder = PurchaseOrder::find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_NOT_FOUND',
                    'message' => 'Purchase order not found'
                ], 404);
            }

            // Only allow editing if status is Draft or Approved
            if (!$purchaseOrder->canEdit()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_STATUS_ERROR',
                    'message' => 'Purchase Order cannot be edited because GRN has already been received.'
                ], 400);
            }

            // Get workspace_id from request
            $workspaceId = $request->input('workspace_id');
            $createdBy = $request->input('created_by');

            if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'po_date' => 'required|date',
                'supplier_id' => 'required|exists:suppliers,id',
                'site_id' => 'required|exists:projects,id',
                'indent_id' => 'required|exists:indents,id',
                'tax_type' => 'required|in:cgst,igst',
                'description' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.gst_master_id' => 'required|exists:gst_masters,id',
                'items.*.discount_amount' => 'required|numeric|min:0',
                'additional_charge' => 'required|numeric|min:0',
                'additional_deduction' => 'required|numeric|min:0',
                'additional_discount' => 'required|numeric|min:0',
                'delivery_date' => 'required|date',
                'delivery_address' => 'nullable|string',
                'delivery_terms_conditions' => 'nullable|string',
                'payment_terms_conditions' => 'nullable|string',
                'remark' => 'nullable|string',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
            ], [
                'delivery_address.string' => 'The delivery address must be a valid string.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate quantity against indent if selected
            // For edit, we skip the indent status check since we're not changing the indent
            if ($request->indent_id) {
                $indent = Indent::with('items')->find($request->indent_id);

                // Skip canAcceptPurchaseOrder check for edit mode - just validate materials and quantities

                foreach ($request->items as $index => $itemData) {
                    $indentItem = $indent->items->where('material_id', $itemData['material_id'])->first();

                    if (!$indentItem) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_MATERIAL_ERROR',
                            'message' => 'Material not found in selected indent.'
                        ], 400);
                    }

                    // Calculate remaining quantity excluding current PO
                    $remainingQuantity = $indent->getRemainingQuantityForMaterial($itemData['material_id']);

                    // Add back current item quantity for validation (since we're updating)
                    $currentItem = $purchaseOrder->items->where('material_id', $itemData['material_id'])->first();
                    if ($currentItem && $currentItem->material_id == $itemData['material_id']) {
                        $remainingQuantity += $currentItem->quantity;
                    }

                    if ($itemData['quantity'] > $remainingQuantity) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_QUANTITY_ERROR',
                            'message' => "Quantity for material exceeds remaining indent quantity. Maximum available: {$remainingQuantity}"
                        ], 400);
                    }
                }
            }

            // Use DB transaction for data integrity
            \DB::beginTransaction();
            try {
                // Reference File Upload - keep old file by default
                $referenceFilePath = $purchaseOrder->reference_file;

                if ($request->hasFile('reference_file')) {
                    $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('reference_file')->getClientOriginalExtension();

                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $path = upload_file($request, 'reference_file', $fileNameToStore, 'purchase-orders');

                    if (isset($path['flag']) && $path['flag'] == 0) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'PO_FILE_ERROR',
                            'message' => $path['msg'] ?? 'Error uploading file'
                        ], 400);
                    }

                    if (!empty($path['url'])) {
                        // Delete old file if exists
                        if (!empty($purchaseOrder->reference_file) && function_exists('delete_file')) {
                            delete_file($purchaseOrder->reference_file);
                        }

                        $referenceFilePath = $path['url'];
                    }
                }

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

                // Recalculate all totals on backend
                $purchaseOrder->calculateTotals();
                $purchaseOrder->save();

                // Update indent status
                $purchaseOrder->updateIndentStatus();

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
                    Log::info('Starting PDF regeneration for PO update', [
                        'po_id' => $purchaseOrder->id,
                        'po_number' => $purchaseOrder->po_number,
                        'workspace_id' => $workspaceId,
                    ]);
                    $pdfPath = $this->generatePurchaseOrderPdf($purchaseOrder, $workspaceId);
                    if ($pdfPath) {
                        $purchaseOrder->po_pdf = $pdfPath;
                        $purchaseOrder->save();
                        Log::info('PO PDF regenerated successfully', [
                            'po_id' => $purchaseOrder->id,
                            'po_number' => $purchaseOrder->po_number,
                            'pdf_path' => $pdfPath,
                        ]);
                    } else {
                        Log::warning('PO PDF regeneration returned null', [
                            'po_id' => $purchaseOrder->id,
                            'po_number' => $purchaseOrder->po_number,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the PO update
                    Log::error('Error generating PO PDF: ' . $e->getMessage(), [
                        'po_id' => $purchaseOrder->id,
                        'po_number' => $purchaseOrder->po_number,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                // Load relationships for response
                $purchaseOrder->load(['supplier', 'creator', 'indent', 'items.material', 'items.gstMaster', 'site']);

                $poData = $purchaseOrder->toArray();
                $poData['creator_name'] = $purchaseOrder->creator->name ?? null;
                $poData['po_pdf'] = $purchaseOrder->po_pdf ?? null;

                \DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase Order updated successfully',
                    'data' => $poData
                ], 200);
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('Error updating purchase order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified purchase order from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('purchase-order delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            $purchaseOrder = PurchaseOrder::find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_NOT_FOUND',
                    'message' => 'Purchase order not found'
                ], 404);
            }

            // Only allow deleting if status is Draft
            if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_STATUS_ERROR',
                    'message' => 'Only Draft purchase orders can be deleted.'
                ], 400);
            }

            // Store indent reference before deleting
            $indent = $purchaseOrder->indent;

            // Delete items first
            $purchaseOrder->items()->delete();

            // Delete the purchase order
            $purchaseOrder->delete();

            // Update indent status
            if ($indent) {
                $indent->updateStatus();
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase Order deleted successfully'
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error deleting purchase order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get materials for a specific indent.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIndentMaterials(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'indent_id' => 'required|exists:indents,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $indent = Indent::with(['items.material'])->find($request->indent_id);

            if (!$indent) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_INDENT_NOT_FOUND',
                    'message' => 'Indent not found'
                ], 404);
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
                'success' => true,
                'message' => 'Indent materials fetched successfully',
                'data' => [
                    'indent' => $indent,
                    'materials' => $materials
                ]
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching indent materials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('purchase-order edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            $purchaseOrder = PurchaseOrder::find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_NOT_FOUND',
                    'message' => 'Purchase order not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:Approved,Rejected,Flagged,Short Closed',
                'reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $newStatus = $request->status;
            $reason = $request->reason;

            // Check if transition is allowed
            if (!$purchaseOrder->canTransitionTo($newStatus)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_INVALID_STATUS_TRANSITION',
                    'message' => 'Invalid status transition from ' . $purchaseOrder->status . ' to ' . $newStatus
                ], 422);
            }

            // Validate reason - optional for all statuses (form handles showing/hiding reason fields)
            // Reason validation is handled on the frontend via required attribute

            // Use DB transaction for data integrity
            \DB::beginTransaction();
            try {
                $oldStatus = $purchaseOrder->status;

                // Update status and reason fields
                $updateData = ['status' => $newStatus];
                
                if ($newStatus === PurchaseOrder::STATUS_REJECTED) {
                    $updateData['rejection_reason'] = $reason;
                } elseif ($newStatus === PurchaseOrder::STATUS_FLAGGED) {
                    $updateData['flag_reason'] = $reason;
                } elseif ($newStatus === PurchaseOrder::STATUS_SHORT_CLOSED) {
                    $updateData['short_close_reason'] = $reason;
                    $updateData['short_closed_at'] = now();
                    $updateData['short_closed_by'] = creatorId();
                }

                $purchaseOrder->update($updateData);

                // Log status change
                $createdBy = $request->input('created_by') ?? (function_exists('creatorId') ? creatorId() : null);
                $purchaseOrder->logStatusChange($newStatus, $reason, $createdBy);

                \DB::commit();

                // Load relationships for response
                $purchaseOrder->load(['supplier', 'creator', 'indent', 'items.material', 'site']);

                $poData = $purchaseOrder->toArray();
                $poData['creator_name'] = $purchaseOrder->creator->name ?? null;
                $poData['po_pdf'] = $purchaseOrder->po_pdf ?? null;

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase order status updated successfully',
                    'data' => $poData
                ], 200);
            } catch (\Exception $e) {
                \DB::rollBack();
                Log::error('Error in PO status update transaction: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_ERROR_500',
                    'message' => 'Failed to update purchase order status'
                ], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Error updating purchase order status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Short close a Purchase Order.
     * Only Partial Received POs can be short closed.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function shortClose(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('purchase-order edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find PO
            $purchaseOrder = PurchaseOrder::find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_NOT_FOUND',
                    'message' => 'Purchase order not found'
                ], 404);
            }

            // Check if PO can be short closed
            if (!$purchaseOrder->canShortClose()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_STATUS_ERROR',
                    'message' => 'Only Partial Received PO can be short closed'
                ], 403);
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

                $purchaseOrder->load(['supplier', 'creator', 'indent', 'items.material', 'site']);
                $poData = $purchaseOrder->toArray();
                $poData['creator_name'] = $purchaseOrder->creator->name ?? null;
                $poData['po_pdf'] = $purchaseOrder->po_pdf ?? null;

                return response()->json([
                    'success' => true,
                    'message' => 'PO short closed successfully',
                    'data' => $poData
                ], 200);
            } catch (\Exception $e) {
                \DB::rollBack();
                Log::error('Error in PO short close transaction: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error_code' => 'PO_ERROR_500',
                    'message' => 'Failed to short close purchase order'
                ], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Error short closing purchase order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PO_ERROR_500',
                'message' => $e->getMessage()
            ], 500);
        }
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

            // Get company settings - same as web controller
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
            $options->set('isPhpEnabled', true);

            $dompdf = new Dompdf($options);
            $data['isPdf'] = true;

            $html = view('purchase-order.print-invoice', $data)->render();

            $dompdf->loadHtml($html);

            $dompdf->setPaper('A4', 'portrait');

            $dompdf->render();

            $pdfContent = $dompdf->output();

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
     * Get approve form data for a purchase order.
     *
     * This endpoint returns the purchase order details along with
     * allowed status transitions for approval workflow.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showApproveForm($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with([
                'supplier',
                'creator',
                'indent',
                'items.material',
                'items.gstMaster',
                'site'
            ])->find($id);

            if (!$purchaseOrder) {
                Log::warning('Purchase order not found for approve form', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order not found',
                    'error_code' => 'PO_NOT_FOUND'
                ], 404);
            }

            // Get allowed status transitions
            $allowedTransitions = $purchaseOrder->getAllowedTransitions();

            // Handle case when no transitions available
            if (empty($allowedTransitions)) {
                Log::info('No allowed transitions for purchase order', [
                    'id' => $id,
                    'status' => $purchaseOrder->status
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'This purchase order status cannot be changed.',
                    'error_code' => 'PO_NO_TRANSITIONS',
                    'data' => [
                        'purchase_order' => $purchaseOrder,
                        'allowed_transitions' => []
                    ]
                ], 400);
            }

            // Format the purchase order for response
            $purchaseOrderData = [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'po_date' => $purchaseOrder->po_date,
                'status' => $purchaseOrder->status,
                'supplier' => $purchaseOrder->supplier ? [
                    'id' => $purchaseOrder->supplier->id,
                    'name' => $purchaseOrder->supplier->name,
                    'email' => $purchaseOrder->supplier->email ?? null,
                    'phone' => $purchaseOrder->supplier->phone ?? null
                ] : null,
                'site' => $purchaseOrder->site ? [
                    'id' => $purchaseOrder->site->id,
                    'name' => $purchaseOrder->site->name
                ] : null,
                'creator_name' => $purchaseOrder->creator->name ?? null,
                'po_pdf' => $purchaseOrder->po_pdf ?? null,
                'total_amount' => floatval($purchaseOrder->total_amount ?? 0),
                'tax_amount' => floatval($purchaseOrder->tax_amount ?? 0),
                'grand_total' => floatval($purchaseOrder->grand_total ?? 0),
                'created_at' => $purchaseOrder->created_at?->toIso8601String(),
                'items_count' => $purchaseOrder->items->count()
            ];

            // Format allowed transitions with labels
            $formattedTransitions = array_map(function ($transition) {
                return [
                    'value' => $transition,
                    'label' => $this->getStatusTransitionLabel($transition)
                ];
            }, $allowedTransitions);

            Log::info('Approve form data retrieved successfully', [
                'purchase_order_id' => $id,
                'transitions_count' => count($allowedTransitions)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order approve form data retrieved successfully',
                'data' => [
                    'purchase_order' => $purchaseOrderData,
                    'allowed_transitions' => $formattedTransitions
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching approve form data: ' . $e->getMessage(), [
                'purchase_order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching approve form data',
                'error_code' => 'PO_APPROVE_ERROR_500'
            ], 500);
        }
    }

    /**
     * Get human-readable label for status transition.
     *
     * @param  string  $status
     * @return string
     */
    private function getStatusTransitionLabel(string $status): string
    {
        $labels = [
            'Approved' => 'Approve',
            'Rejected' => 'Reject',
            'Flagged' => 'Flag for Review',
            'Short Closed' => 'Short Close'
        ];

        return $labels[$status] ?? $status;
    }
}
