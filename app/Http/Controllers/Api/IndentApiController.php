<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Indent;
use App\Models\IndentItem;
use App\Models\Supplier;
use App\Models\Material;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\Project;

/**
 * IndentApiController - RESTful API Controller for Indent management
 * Optimized for Flutter mobile application with Laravel Sanctum authentication
 */
/**
 * @group Indents
 * Endpoints for indent management including material requisition and tracking
 */
class IndentApiController extends Controller {

    /**
     * GET /api/indents
     * 
     * List all indents for the authenticated user's workspace
     * Optional filters: site_id, status
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('indent manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
    try {

        $workspaceId = $request->workspace_id;
        $siteId = $request->site_id;

        $query = Indent::where('workspace_id', $workspaceId)
            ->with([
                'supplier:id,name',
                'creator:id,name',
                'items.material',
                'site',
                'purchaseOrders'
            ]);

        if (!empty($siteId)) {
            $query->where('site_id', $siteId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $indents = $query->get();

        // Collect all user IDs
        $allUserIds = $indents
            ->pluck('assign_to')
            ->filter()
            ->flatMap(function ($value) {
                return collect(explode(',', $value))
                    ->map(fn($id) => (int) trim($id));
            })
            ->unique()
            ->values()
            ->toArray();

        // Fetch users
        $users = User::whereIn('id', $allUserIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Attach assigned users
        $indents = $indents->map(function ($indent) use ($users) {

            $assignedUsers = [];

            if (!empty($indent->assign_to)) {

                $ids = collect(explode(',', $indent->assign_to))
                    ->map(fn($id) => (int) trim($id));

                foreach ($ids as $id) {
                    if ($users->has($id)) {
                        $assignedUsers[] = $users[$id];
                    }
                }
            }

            $indent->assigned_users = $assignedUsers;

            return $indent;
        });

        return response()->json([
            'success' => true,
            'count' => $indents->count(),
            'data' => $indents
        ], 200);

    } catch (\Exception $e) {

        \Log::error('Error fetching indents: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve indents'
        ], 500);
    }
}

    /**
     * GET /api/indents/create
     * 
     * Get data needed to create a new indent (suppliers, materials, sites)
     */
    public function createData(Request $request) {
        if (!Auth::user()->isAbleTo('indent create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {

            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $created_by = $request->input('created_by');

            // Fetch suppliers
            $suppliers = Supplier::select('id', 'name', 'email', 'phone', 'address')
                    ->get();

            // Fetch materials with category and unit
            $materials = Material::with(['category:id,name', 'unit:id,name'])
                    ->get()
                    ->map(function ($material) {
                        return [
                            'id' => $material->id,
                            'name' => $material->name,
                            'sku' => $material->sku ?? '',
                            'price' => $material->price ?? 0,
                            'category_id' => $material->category_id,
                            'category_name' => $material->category?->name,
                            'unit_id' => $material->unit?->id,
                            'unit_name' => $material->unit?->name ?? '',
                        ];
                    });

            // Fetch sites/projects for the workspace
            $sites = Project::where('workspace', $workspaceId)
                    ->projectonly()
                    ->select('id', 'name', 'status')
                    ->get();

            // Generate next indent number
            $nextIndentNumber = Indent::generateIndentNumber($request->site_id ?? null);

            $users = getActiveProjectEmployees();

            return response()->json([
                        'success' => true,
                        'message' => 'Indent creation data retrieved successfully',
                        'data' => [
                            'suppliers' => $suppliers,
                            'users' => $users,
                            'materials' => $materials,
                            'sites' => $sites,
                            'next_indent_number' => $nextIndentNumber,
                        ]
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching indent creation data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve creation data',
                        'error' => 'An error occurred while fetching creation data'
                            ], 500);
        }
    }

    /**
     * POST /api/indents
     *
     * Create a new indent
     *
     * @bodyParam indent_date date required Indent date. Example: 2024-01-15
     * @bodyParam supplier_id integer optional Supplier ID. Example: 1
     * @bodyParam site_id integer required Site/Project ID. Example: 5
     * @bodyParam description string optional Description. Example: Material requisition for foundation
     * @bodyParam assign_to string required Assigned users (comma-separated IDs). Example: 1,2,3
     * @bodyParam delivery_date date optional Expected delivery date. Example: 2024-01-20
     * @bodyParam remark string optional Remarks. Example: Urgent requirement
     * @bodyParam reference_file file optional Reference document (max 10MB).
     * @bodyParam items array required Array of indent items.
     * @bodyParam items.*.material_id integer required Material ID. Example: 10
     * @bodyParam items.*.quantity number required Quantity. Example: 100
     * @bodyParam items.*.unit string required Unit. Example: kg
     * @bodyParam items.*.price number required Unit price. Example: 500.00
     * @bodyParam items.*.remarks string optional Item remarks. Example: High quality required
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @response {"success": true, "message": "Indent created successfully", "data": {...}}
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('indent create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        Log::info('Indent store request received', [
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'indent_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'site_id' => 'required|exists:projects,id',
            'description' => 'nullable|string|max:1000',
            'assign_to' => 'required',
            'delivery_date' => 'nullable|date',
            'remark' => 'nullable|string|max:2000',
            'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string|max:50',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:500',
            'site_id' => 'required|integer',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            Log::warning('Indent validation failed', [
                'errors' => $validator->errors()
            ]);
            return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                            ], 422);
        }

        Log::info('Validation passed');

        DB::beginTransaction();
        try {
            $referenceFilePath = null;

            if ($request->hasFile('reference_file')) {
                Log::info('Reference file detected', [
                    'file_name' => $request->file('reference_file')->getClientOriginalName()
                ]);

                $filenameWithExt = $request->file('reference_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('reference_file')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'reference_file', $fileNameToStore, 'indents');

                Log::debug('File upload result', $path);

                if ($path['flag'] == 0) {
                    DB::rollBack();
                    Log::error('File upload failed', ['msg' => $path['msg']]);
                    return response()->json(['status' => 0, 'message' => $path['msg']], 500);
                }

                $referenceFilePath = $path['url'];
            }

            Log::info('Creating indent record');
            $indent = Indent::create([
                'indent_number' => Indent::generateIndentNumber($request->site_id), // Force override any user input
                'indent_date' => $request->indent_date,
                'supplier_id' => $request->supplier_id,
                'site_id' => $request->site_id,
                'description' => $request->description,
                'total_amount' => 0,
                'status' => Indent::STATUS_OPEN,
                'created_by' => $request->created_by,
                'workspace_id' => $request->workspace_id,
                'assign_to' => implode(',', (array) $request->assign_to),
                'delivery_date' => $request->delivery_date,
                'remark' => $request->remark,
                'reference_file' => $referenceFilePath,
            ]);

            Log::info('Indent created', ['indent_id' => $indent->id]);

            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $subtotal = $itemData['quantity'] * $itemData['price'];
                $totalAmount += $subtotal;

                Log::debug('Creating indent item', [
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'subtotal' => $subtotal
                ]);

                IndentItem::create([
                    'indent_id' => $indent->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'price' => $itemData['price'],
                    'subtotal' => $subtotal,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            $indent->update(['total_amount' => $totalAmount]);
            Log::info('Indent total updated', ['total_amount' => $totalAmount]);

            DB::commit();

            $indent->load(['supplier', 'creator', 'items.material', 'site']);
            Log::info('Indent relationships loaded');

            return response()->json([
                        'success' => true,
                        'message' => 'Indent created successfully',
                        'data' => $indent
                            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating indent in transaction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to create indent',
                        'error' => 'An error occurred while creating the indent'
                            ], 500);
        }
    }

    /**
     * GET /api/indents/{id}
     * 
     * Show a specific indent with all details
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('indent show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
    try {

        $indent = Indent::where('id', $id)
            ->with([
                'supplier',
                'creator:id,name',
                'items.material',
                'site:id,name',
                'purchaseOrders'
            ])
            ->first();

        // Check if indent exists
        if (!$indent) {
            return response()->json([
                'success' => false,
                'message' => 'Indent not found',
                'error' => 'The requested indent does not exist'
            ], 404);
        }

        // Prepare assigned users from comma-separated IDs
        $assignedUsers = collect();

        if (!empty($indent->assign_to)) {

            // Remove spaces and convert IDs to integers
            $userIds = collect(explode(',', str_replace([' ', "\n", "\r"], '', $indent->assign_to)))
                ->map(fn($id) => (int) $id)
                ->filter(); // remove non-numeric

            // Fetch all users in a single query
            $assignedUsers = User::whereIn('id', $userIds)
                ->select('id', 'name')
                ->get();
        }

        // Add assigned users to the response
        $responseData = $indent->toArray();
        $responseData['assigned_users'] = $assignedUsers;

        return response()->json([
            'success' => true,
            'message' => 'Indent retrieved successfully',
            'data' => $responseData
        ], 200);

    } catch (\Exception $e) {
        Log::error('Error fetching indent: ' . $e->getMessage(), [
            'indent_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve indent',
            'error' => 'An error occurred while fetching the indent'
        ], 500);
    }
}

    /**
     * PUT /api/indents/{id}
     * 
     * Update an existing indent
     */
    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('indent edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {


            $indent = Indent::where('id', $id)
                    ->first();

            // Check if indent exists
            if (!$indent) {
                return response()->json([
                            'success' => false,
                            'message' => 'Indent not found',
                            'error' => 'The requested indent does not exist'
                                ], 404);
            }

            // Check if indent is closed (cannot edit)
            if ($indent->status === Indent::STATUS_CLOSED) {
                return response()->json([
                            'success' => false,
                            'message' => 'Cannot edit a closed indent',
                            'error' => 'Closed indents cannot be modified'
                                ], 403);
            }

            // Check if indent is used in any purchase order
            if ($indent->purchaseOrders()->exists()) {
                return response()->json([
                            'success' => false,
                            'message' => 'Cannot update or delete this indent because it is already used in a purchase order.',
                            'error' => 'This indent is linked to existing purchase order(s)'
                                ], 409);
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'indent_date' => 'required|date',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'site_id' => 'required|exists:projects,id',
                'description' => 'nullable|string|max:1000',
                // New validation rules for additional fields
                'assign_to' => 'required',
                'delivery_date' => 'nullable|date',
                'remark' => 'nullable|string|max:2000',
                'reference_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|exists:materials,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit' => 'required|string|max:50',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.remarks' => 'nullable|string|max:500',
            ]);

            // Return validation errors
            if ($validator->fails()) {
                return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                                ], 422);
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

            // Convert assign_to array to comma-separated string
            $assignToString = null;
            if ($request->has('assign_to') && is_array($request->assign_to)) {
                $assignToString = implode(',', $request->assign_to);
            }

            // Update indent details
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
            $indent->items()->delete();

            // Recreate items and calculate total
            $totalAmount = 0;

            foreach ($request->items as $itemData) {
                $subtotal = $itemData['quantity'] * $itemData['price'];
                $totalAmount += $subtotal;

                IndentItem::create([
                    'indent_id' => $indent->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'price' => $itemData['price'],
                    'subtotal' => $subtotal,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            // Update total amount
            $indent->update(['total_amount' => $totalAmount]);

            // Update status based on purchase orders
            $indent->updateStatus();

            // Load relationships for response
            $indent->load(['supplier', 'creator', 'items.material', 'site']);

            // Prepare response with full URL for reference_file
            $responseData = $indent->toArray();
            if ($indent->reference_file) {
                $responseData['reference_file_url'] = asset('storage/' . $indent->reference_file);
            }
            // Convert assign_to string back to array for API response
            if ($indent->assign_to) {
                $responseData['assign_to_array'] = explode(',', $indent->assign_to);
            }

            return response()->json([
                        'success' => true,
                        'message' => 'Indent updated successfully',
                        'data' => $responseData
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating indent: ' . $e->getMessage(), [
                'indent_id' => $id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to update indent',
                        'error' => 'An error occurred while updating the indent'
                            ], 500);
        }
    }

    /**
     * DELETE /api/indents/{id}
     * 
     * Delete an indent
     */
    public function destroy($id) {
        if (!Auth::user()->isAbleTo('indent delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {


            $indent = Indent::where('id', $id)
                    ->first();

            // Check if indent exists
            if (!$indent) {
                return response()->json([
                            'success' => false,
                            'message' => 'Indent not found',
                            'error' => 'The requested indent does not exist'
                                ], 404);
            }

            // Check if there are associated purchase orders (using exists() for performance)
            if ($indent->purchaseOrders()->exists()) {
                return response()->json([
                            'success' => false,
                            'message' => 'Cannot update or delete this indent because it is already used in a purchase order.',
                            'error' => 'This indent is linked to existing purchase order(s)'
                                ], 409);
            }

            // Delete indent and its items
            $indent->items()->delete();
            $indent->delete();

            return response()->json([
                        'success' => true,
                        'message' => 'Indent deleted successfully'
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting indent: ' . $e->getMessage(), [
                'indent_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete indent',
                        'error' => 'An error occurred while deleting the indent'
                            ], 500);
        }
    }

    /**
     * GET /api/indents/{id}/materials
     * 
     * Get materials for a specific indent (for Purchase Order selection)
     * Optional: po_id parameter for edit mode to get available quantity for editing
     */
    public function getIndentMaterials(Request $request, $id) {
        try {
            $poId = $request->get('po_id'); // Optional: Current PO ID for edit mode

            $indent = Indent::where('id', $id)
                    ->with(['items.material', 'supplier'])
                    ->first();

            if (!$indent) {
                return response()->json([
                            'success' => false,
                            'message' => 'Indent not found',
                            'error' => 'The requested indent does not exist'
                                ], 404);
            }

            // Use the model's method to get items with availability
            $itemsWithAvailability = $indent->getItemsWithAvailability($poId);
            
            // Calculate summary
            $totalIndentQty = $itemsWithAvailability->sum('quantity');
            $totalConsumedQty = $itemsWithAvailability->sum('consumed_quantity');
            $totalRemainingQty = $itemsWithAvailability->sum('remaining_quantity');

            return response()->json([
                        'success' => true,
                        'message' => 'Indent materials retrieved successfully',
                        'data' => [
                            'indent' => [
                                'id' => $indent->id,
                                'indent_number' => $indent->indent_number,
                                'status' => $indent->status,
                                'indent_date' => $indent->indent_date,
                                'supplier' => $indent->supplier ? [
                                    'id' => $indent->supplier->id,
                                    'name' => $indent->supplier->name
                                ] : null,
                            ],
                            'materials' => $itemsWithAvailability,
                            'summary' => [
                                'total_indent_quantity' => $totalIndentQty,
                                'total_consumed_quantity' => $totalConsumedQty,
                                'total_remaining_quantity' => $totalRemainingQty,
                                'items_count' => $itemsWithAvailability->count(),
                            ]
                        ]
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching indent materials: ' . $e->getMessage(), [
                'indent_id' => $id,
                'po_id' => $request->get('po_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve indent materials',
                        'error' => 'An error occurred while fetching indent materials'
                            ], 500);
        }
    }

    /**
     * GET /api/indents/available/list
     * 
     * Get available indents (not closed) for dropdown/selection
     */
    public function getAvailableIndents(Request $request) {
        try {


            $query = Indent::whereIn('status', [Indent::STATUS_OPEN, Indent::STATUS_PARTIALLY_CLOSED])
                    ->with(['items.material', 'supplier', 'site'])
                    ->orderBy('indent_date', 'desc');

            // Optional: filter by site
            if ($request->has('site_id')) {
                $query->where('site_id', $request->site_id);
            }

            $indents = $query->get();

            // Transform for mobile dropdown
            $availableIndents = $indents->map(function ($indent) {
                $totalItems = $indent->items->count();
                $totalQuantity = $indent->items->sum('quantity');
                $remainingQuantity = 0;

                foreach ($indent->items as $item) {
                    $remainingQuantity += $indent->getRemainingQuantityForMaterial($item->material_id);
                }

                return [
                    'id' => $indent->id,
                    'indent_number' => $indent->indent_number,
                    'indent_date' => $indent->indent_date,
                    'status' => $indent->status,
                    'site_id' => $indent->site_id,
                    'site_name' => $indent->site?->name,
                    'supplier_name' => $indent->supplier?->name,
                    'total_amount' => $indent->total_amount,
                    'total_items' => $totalItems,
                    'total_quantity' => $totalQuantity,
                    'remaining_quantity' => $remainingQuantity,
                ];
            });

            return response()->json([
                        'success' => true,
                        'message' => 'Available indents retrieved successfully',
                        'data' => $availableIndents
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching available indents: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve available indents',
                        'error' => 'An error occurred while fetching available indents'
                            ], 500);
        }
    }

    /**
     * PATCH /api/indents/{id}/status
     * 
     * Update indent status manually
     */
    public function updateStatus(Request $request, $id) {
        if (!Auth::user()->isAbleTo('indent edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {


            $indent = Indent::where('id', $id)
                    ->first();

            if (!$indent) {
                return response()->json([
                            'success' => false,
                            'message' => 'Indent not found',
                            'error' => 'The requested indent does not exist'
                                ], 404);
            }

            // Validate status
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:' . Indent::STATUS_OPEN . ',' . Indent::STATUS_PARTIALLY_CLOSED . ',' . Indent::STATUS_CLOSED,
            ]);

            if ($validator->fails()) {
                return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                                ], 422);
            }

            // Update status
            $indent->status = $request->status;
            $indent->save();

            return response()->json([
                        'success' => true,
                        'message' => 'Indent status updated successfully',
                        'data' => [
                            'id' => $indent->id,
                            'status' => $indent->status
                        ]
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating indent status: ' . $e->getMessage(), [
                'indent_id' => $id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                        'success' => false,
                        'message' => 'Failed to update indent status',
                        'error' => 'An error occurred while updating the status'
                            ], 500);
        }
    }
}
