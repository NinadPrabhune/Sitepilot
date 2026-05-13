<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransfer;
use App\Models\MaterialTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @group Material Transfer
 * Endpoints for material transfer management between sites
 */
class MaterialTransferApiController extends Controller {

    /**
     * Display a paginated listing of material transfers.
     */
    public function index(Request $request) {
        if (!Auth::user()->isAbleTo('material-transfer manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');
            
            $query = MaterialTransfer::with(['items.material', 'fromSite', 'toSite']);

            if (!empty($siteId) && $siteId != 0) {
                $query->where('from_site_id', $siteId);  
            }
//            if ($request->has('from_site_id')) {
//                $query->where('from_site_id', $request->query('from_site_id'));
//            }
//            if ($request->has('to_site_id')) {
//                $query->where('to_site_id', $request->query('to_site_id'));
//            }

            $transfers = $query->orderBy('id', 'desc')->get();

            return response()->json([
                        'success' => true,
                        'message' => 'Material transfers fetched successfully',
                        'data' => $transfers
                            ], 200);
        } catch (\Exception $e) {
            Log::error('API MaterialTransfer index error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch transfers',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    public function createData(Request $request) {
        if (!Auth::user()->isAbleTo('material-transfer create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            
            
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');
          

            // Materials
//            $materialsQuery = \App\Models\Material::query();
////            if (!empty($siteId) && $siteId != 0) {
////                $materialsQuery->where('site_id', $siteId);
////            }
////            if (!empty($workspaceId) && $workspaceId != 0) {
////                $materialsQuery->where('workspace_id', $workspaceId);
////            }
//            $materials = $materialsQuery->get()->mapWithKeys(function ($material) {   
//                return [
//                    $material->id => [
//                        'id'   => $material->id,
//                        'name' => $material->name,
//                        'unit' => [
//                            'id'   => $material->unit->id ?? null,
//                            'name' => $material->unit->name ?? null,
//                        ],
//                    ]
//                ];                        
//            });
            
            
            $materialId = $request->material_id;

            $material = \App\Models\Material::with('unit')->find($materialId);

            $materials = [
                $material->id => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => [
                        'id' => $material->unit->id ?? null,
                        'name' => $material->unit->name ?? null,
                    ],
                ]
            ];
            
            $stock = getCurrentStockBySiteId($siteId, null, null, null, null, $materialId);

            
//            // Sites
//            $sitesQuery = \Workdo\Taskly\Entities\Project::query()->projectonly();
//            if (!empty($workspaceId) && $workspaceId != 0) {
//                $sitesQuery->where('workspace', $workspaceId);
//            }
//            $sites = $sitesQuery->select('id', 'name')->get();
            
            $sites = getSitesWithWorkspace();
            
            

            $maxId = MaterialTransfer::max('id');
            $nextInvoiceNumber = 'MT-' . str_pad($maxId ? $maxId + 1 : 1, 4, '0', STR_PAD_LEFT);

            return response()->json([
                        'success' => true,
                        'message' => 'Form data fetched successfully',
                        'data' => [                            
                            'sites' => $sites,
                            'next_record_number' => $nextInvoiceNumber,
                            'materials' => $materials,
                            'stock' => $stock,
                        ]
                            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching Material Transfer create data: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch form data',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Store a newly created material transfer.
     *
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam record_date date required Transfer date. Example: 2024-01-15
     * @bodyParam from_site_id integer required Source site ID. Example: 5
     * @bodyParam to_site_id integer required Destination site ID (must be different from from_site_id). Example: 6
     * @bodyParam items array required Array of transfer items.
     * @bodyParam items.*.material_id integer required Material ID. Example: 10
     * @bodyParam items.*.quantity number required Quantity. Example: 100
     * @bodyParam items.*.unit string required Unit. Example: kg
     * @bodyParam items.*.price number required Unit price. Example: 500.00
     * @bodyParam invoice_file file optional Invoice document.
     * @response {"success": true, "message": "Material transfer created successfully", "data": {...}}
     */
    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('material-transfer create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $validated = $request->validate([
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
            'record_date' => 'required|date',
            'from_site_id' => 'required|integer',
            'to_site_id' => 'required|integer|different:from_site_id',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|integer|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'invoice_file' => 'nullable|file',
        ]);

        try {
            $maxId = MaterialTransfer::max('id');
            $recordNumber = 'MT-' . str_pad($maxId ? $maxId + 1 : 1, 4, '0', STR_PAD_LEFT);
            $data = $validated;
            $data['record_number'] = $recordNumber;

            if ($request->hasFile('invoice_file')) {
                $file = $request->file('invoice_file');
                $filename = time() . '_transfer_' . $recordNumber . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('transfers', $filename, 'public');
                $data['invoice_file'] = $path;
            }

            $data['created_by'] = $validated['created_by'];
            $data['workspace_id'] = $validated['workspace_id'];
            $data['total_amount'] = 0;

            $transfer = MaterialTransfer::create($data);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['price'];
                MaterialTransferItem::create([
                    'material_transfer_id' => $transfer->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal,
                ]);
                $total += $subtotal;
            }

            $transfer->update(['total_amount' => $total]);

            return response()->json([
                        'success' => true,
                        'message' => 'Material transfer created successfully',
                        'data' => $transfer->load('items.material')
                            ], 201);
        } catch (\Exception $e) {
            Log::error('API MaterialTransfer store error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to create transfer',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Display the specified material transfer.
     */
    public function show($id) {
        if (!Auth::user()->isAbleTo('material-transfer show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $transfer = MaterialTransfer::with('items.material')->find($id);

            if (!$transfer) {
                return response()->json([
                            'success' => false,
                            'message' => 'Material transfer not found'
                                ], 404);
            }

            return response()->json([
                        'success' => true,
                        'message' => 'Material transfer fetched successfully',
                        'data' => $transfer
                            ], 200);
        } catch (\Exception $e) {
            Log::error('API MaterialTransfer show error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch transfer',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Update the specified material transfer.
     */
    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('material-transfer edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $validated = $request->validate([
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
            'record_date' => 'required|date',
            'from_site_id' => 'required|integer',
            'to_site_id' => 'required|integer|different:from_site_id',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|integer|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'invoice_file' => 'nullable|file',
        ]);

        try {
            $transfer = MaterialTransfer::find($id);
            if (!$transfer) {
                return response()->json([
                            'success' => false,
                            'message' => 'Material transfer not found'
                                ], 404);
            }

            $data = $validated;

            if ($request->hasFile('invoice_file')) {
                if ($transfer->invoice_file) {
                    Storage::disk('public')->delete($transfer->invoice_file);
                }
                $file = $request->file('invoice_file');
                $filename = time() . '_transfer_' . $transfer->record_number . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('transfers', $filename, 'public');
                $data['invoice_file'] = $path;
            }

            $data['created_by'] = $validated['created_by'];
            $data['workspace_id'] = $validated['workspace_id'];
            $data['total_amount'] = 0;

            $transfer->update($data);

            // Recreate items
            MaterialTransferItem::where('material_transfer_id', $transfer->id)->delete();

            $total = 0;
            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['price'];
                MaterialTransferItem::create([
                    'material_transfer_id' => $transfer->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal,
                ]);
                $total += $subtotal;
            }

            $transfer->update(['total_amount' => $total]);

            return response()->json([
                        'success' => true,
                        'message' => 'Material transfer updated successfully',
                        'data' => $transfer->load('items.material')
                            ], 200);
        } catch (\Exception $e) {
            Log::error('API MaterialTransfer update error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to update transfer',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * Remove the specified material transfer.
     */
    public function destroy($id) {
        if (!Auth::user()->isAbleTo('material-transfer delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $transfer = MaterialTransfer::find($id);
            if (!$transfer) {
                return response()->json([
                            'success' => false,
                            'message' => 'Material transfer not found'
                                ], 404);
            }

            if ($transfer->invoice_file) {
                Storage::disk('public')->delete($transfer->invoice_file);
            }

            $transfer->items()->delete();
            $transfer->delete();

            return response()->json([
                        'success' => true,
                        'message' => 'Material transfer deleted successfully'
                            ], 200);
        } catch (\Exception $e) {
            Log::error('API MaterialTransfer destroy error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete transfer',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    /**
     * API: get stock by site
     */
    public function getStockBySite(Request $request) {
        if (!Auth::user()->isAbleTo('material-transfer manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
           
            $siteId      = $request->input('site_id');
            $material_id = $request->input('material_id');

            // Pass material_id as the last argument
            $stock = getCurrentStockBySiteId($siteId, null, null, null, null, $material_id);


            

            return response()->json([
                        'success' => true,
                        'message' => 'Stock fetched successfully',
                        'data' => $stock
                            ], 200);
        } catch (\Exception $e) {
            Log::error('API getStockBySite error: ' . $e->getMessage());
            return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch stock',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }
}
