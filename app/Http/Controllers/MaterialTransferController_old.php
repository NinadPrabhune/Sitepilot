<?php

namespace App\Http\Controllers;

use App\DataTables\MaterialTransferDataTable;
use App\Models\MaterialTransfer;
use App\Models\MaterialTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use App\Models\WorkSpace;

class MaterialTransferController extends Controller {

    public function index(MaterialTransferDataTable $dataTable) {

        if (!Auth::user()->isAbleTo('material-transfer manage')) {
            abort(403, 'Permission denied.');
        }


        return $dataTable->render('material-transfer.index');
    }

    public function create() {

        if (!Auth::user()->isAbleTo('material-transfer create')) {
            abort(403, 'Permission denied.');
        }
        try {

            $materials = \App\Models\Material::all()->mapWithKeys(function ($material) {
                return [
                    $material->id => [
                        'id' => $material->id,
                        'name' => $material->name,
                        'unit' => [
                            'id' => $material->unit->id ?? null,
                            'name' => $material->unit->name ?? null,
                        ],
                    ]
                ];
            });

            $maxId = MaterialTransfer::max('id');
            $nextRecordNumber = 'MT-' . str_pad($maxId ? $maxId + 1 : 1, 4, '0', STR_PAD_LEFT);

            $ActiveWorkSpaceID = getActiveWorkSpace();
            $ActiveWorkSpaceName = Auth::user()->ActiveWorkspaceName();
            $ActiveWorkSpace[$ActiveWorkSpaceID] = $ActiveWorkSpaceName;
            $ActiveProjectIDArr[] = getActiveProject();
            $ActiveProjectID = getActiveProject();
            $ActiveProjectIDarr[] = getActiveProject();
            $ActiveProjectName = getActiveProjectName();
            $ActiveProject[$ActiveProjectID] = $ActiveProjectName;
            $sites = getSitesWithWorkspace($ActiveProjectIDArr);
            $ActiveProject = getSitesWithWorkspaceAndSiteId($ActiveProjectIDarr);
            return view('material-transfer.create', compact('materials', 'sites', 'nextRecordNumber', 'ActiveWorkSpaceID', 'ActiveWorkSpaceName', 'ActiveWorkSpace', 'ActiveProject'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: ' . $e->getMessage()]);
        }
    }

    public function store(Request $request) {

        if (!Auth::user()->isAbleTo('material-transfer create')) {
            abort(403, 'Permission denied.');
        }
        try {
            // ✅ Validate input
            $data = $request->validate([
                'record_date' => 'required|date',
                'from_site_id' => 'required|integer',
                'to_site_id' => 'required|integer|different:from_site_id',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|integer',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'invoice_file' => 'nullable|file',
            ]);

            // 🔄 Start transaction
            DB::beginTransaction();
            try {
                // 🔒 Lock table to avoid race conditions when generating record_number
                $maxId = DB::table('material_transfers')
                        ->lockForUpdate()
                        ->max('id');

                $recordNumber = 'MT-' . str_pad($maxId ? $maxId + 1 : 1, 4, '0', STR_PAD_LEFT);
                $data['record_number'] = $recordNumber;

                // 📂 Handle file upload
                if ($request->hasFile('invoice_file')) {
                    $file = $request->file('invoice_file');
                    $filename = time() . '_transfer_' . $recordNumber . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('transfers', $filename, 'public');
                    $data['invoice_file'] = $path;
                }

                // 🧑 Metadata
                $data['created_by'] = creatorId();
                $data['workspace_id'] = getActiveWorkSpace();
                $data['total_amount'] = 0;

                // 🚀 Create master record
                $transfer = MaterialTransfer::create($data);

                // 📊 Create detail records and calculate total
                $total = 0;
                foreach ($request->items as $item) {
                    $availableStock = getCurrentStockBySiteId($data['from_site_id'], null, null, null, null, null)
                                    ->firstWhere('material_id', $item['material_id'])
                            ->total_qty ?? 0;

                    if ($item['quantity'] > $availableStock) {
                        throw new \Exception("Insufficient stock for material ID {$item['material_id']}");
                    }

                    if ($item['quantity'] <= 0) {
                        throw new \Exception("Invalid quantity for material ID {$item['material_id']}");
                    }

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

                // 💰 Update total atomically
                $transfer->update(['total_amount' => $total]);

                // ✅ Commit transaction if everything succeeds
                DB::commit();

                return redirect()->route('material-transfer.index')
                                ->with('success', 'Material transfer created successfully.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return redirect()->back()->withErrors($e->validator)->withInput();
            } catch (QueryException $e) {
                DB::rollBack();
                // Handle duplicate record_number gracefully
                if ($e->errorInfo[1] == 1062) { // MySQL duplicate key error
                    return redirect()->back()->with('error', 'Duplicate record number detected. Please try again.');
                }
                \Log::error('Database error: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Database error occurred.');
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Material Transfer Store Error: ' . $e->getMessage());
                return redirect()->back()->withInput()->with('error', 'An error occurred while creating the material transfer. Please try again.');
            }
        } catch (\Exception $e) {
            // Outer catch for validation or unexpected errors
            return redirect()->back()->withInput()->with('error', 'Unexpected error: ' . $e->getMessage());
        }
    }

    public function edit(MaterialTransfer $materialTransfer) {

        if (!Auth::user()->isAbleTo('material-transfer edit')) {
            abort(403, 'Permission denied.');
        }

        try {

            $materials = \App\Models\Material::all()->mapWithKeys(function ($material) {
                return [$material->id => [
                        'name' => $material->name,
                        'price' => $material->price,
                        'unit' => $material->unit,
                ]];
            });

            // Eager load items relationship
            $materialTransfer->load('items');
            $materialTransferId = $materialTransfer->id;
            $ActiveProjectIDArr[] = getActiveProject();
            $ActiveProjectID = getActiveProject();
            $ActiveProjectName = getActiveProjectName();
            $ActiveProject[$ActiveProjectID] = $ActiveProjectName;
            $sites = getSitesWithWorkspace($ActiveProjectIDArr);
            $from_site_id_Arr[] = $materialTransfer->from_site_id;
            $from_site_id = getSitesWithWorkspaceAndSiteId($from_site_id_Arr);
            $siteStock = []; // Initialize empty stock
            $currentQty = 0;
            if ($materialTransfer->from_site_id) {
                $stockItems = getPurchaseInvoiceStockBySiteId($materialTransfer->from_site_id);

                foreach ($stockItems as $item) {
                    $materialId = $item->material_id;

                    // Get total transferred quantity of this material from this site (excluding current transfer)
                    $transferredQty = \App\Models\MaterialTransferItem::whereHas('transfer', function ($query) use ($materialTransfer) {
                                $query->where('from_site_id', $materialTransfer->from_site_id)
                                        ->where('id', '!=', $materialTransfer->id);
                            })->where('material_id', $materialId)
                            ->sum('quantity');

                    // Get quantity from current transfer to add back
//                    $currentQty = $materialTransfer->items->where('material_id', $materialId)->sum('quantity');

                    $availableQty = max(0, ($item->total_qty - $transferredQty + $currentQty));

                    $siteStock[$materialId] = [
                        'name' => $item->material_name,
                        'unit' => $item->unit_name,
                        'price' => $item->material_price,
                        'total_qty' => $availableQty,
                    ];
                }
            }

            return view('material-transfer.edit', [
                'materials' => $materials,
                'transfer' => $materialTransfer,
                'sites' => $sites,
                'siteStock' => $siteStock,
                'materialTransferId' => $materialTransferId,
                'ActiveProject' => $ActiveProject,
                'from_site_id' => $from_site_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Material Transfer Edit Error: ' . $e->getMessage());
            return redirect()->route('material-transfer.index')->with('error', 'Unable to load the material transfer for editing.');
        }
    }

    public function update(Request $request, MaterialTransfer $materialTransfer) {

        if (!Auth::user()->isAbleTo('material-transfer edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            // ✅ Validate input
            $data = $request->validate([
                'record_date' => 'required|date',
                'from_site_id' => 'required|integer',
                'to_site_id' => 'required|integer|different:from_site_id',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|integer',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'invoice_file' => 'nullable|file',
            ]);

            // 🔄 Start transaction
            DB::beginTransaction();

            try {
                // 📂 Handle invoice file update
                if ($request->hasFile('invoice_file')) {
                    $file = $request->file('invoice_file');
                    $filename = time() . '_transfer_' . $materialTransfer->record_number . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('transfers', $filename, 'public');
                    $data['invoice_file'] = $path;
                }

                $data['updated_by'] = creatorId();
                $data['workspace_id'] = getActiveWorkSpace();
                $data['total_amount'] = 0; // reset before recalculation
                // 📝 Update main transfer record
                $materialTransfer->update($data);

                // ❌ Delete old items
                MaterialTransferItem::where('material_transfer_id', $materialTransfer->id)->delete();

                // ➕ Recreate items and calculate total
                $total = 0;
                foreach ($request->items as $item) {
                    $item = (object) $item;   // convert array to object

                    $availableStock = getCurrentStockBySiteId(
                            $data['from_site_id'],
                            $excludeConsumptionId = null,
                            $materialTransfer->id, null, null, null
                            )->firstWhere('material_id', $item->material_id);

                    $totalQty = $availableStock ? $availableStock->total_qty : 0;

                    if ($item->quantity > $totalQty) {
                        throw new \Exception("Insufficient stock for material ID {$item->material_id}");
                    }

                    if ($item->quantity <= 0) {
                        throw new \Exception("Invalid quantity for material ID {$item->material_id}");
                    }

                    $subtotal = $item->quantity * $item->price;
                    MaterialTransferItem::create([
                        'material_transfer_id' => $materialTransfer->id,
                        'material_id' => $item->material_id,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'price' => $item->price,
                        'subtotal' => $subtotal,
                    ]);
                    $total += $subtotal;
                }
                // 💰 Update total atomically
                $materialTransfer->update(['total_amount' => $total]);

                // ✅ Commit transaction
                DB::commit();

                return redirect()->route('material-transfer.index')
                                ->with('success', 'Material transfer updated successfully.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return redirect()->back()->withErrors($e->validator)->withInput();
            } catch (QueryException $e) {
                DB::rollBack();
                \Log::error('Database error during update: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Database error occurred.');
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Material Transfer Update Error: ' . $e->getMessage());
                return redirect()->back()->withInput()->with('error', 'An error occurred while updating the material transfer. Please try again.');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Unexpected error: ' . $e->getMessage());
        }
    }

    public function show(MaterialTransfer $materialTransfer) {

        if (!Auth::user()->isAbleTo('material-transfer show')) {
            abort(403, 'Permission denied.');
        }
        try {
            $materialTransfer->load('items.material');
            return view('material-transfer.show', compact('materialTransfer'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to show record: ' . $e->getMessage()]);
        }
    }

    public function destroy(MaterialTransfer $materialTransfer) {

        if (!Auth::user()->isAbleTo('material-transfer delete')) {
            abort(403, 'Permission denied.');
        }


        try {
            if ($materialTransfer->invoice_file) {
                Storage::disk('public')->delete($materialTransfer->invoice_file);
            }

            $materialTransfer->items()->delete();
            $materialTransfer->delete();

            return redirect()->route('material-transfer.index')->with('success', 'Material transfer deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting record: ' . $e->getMessage());
        }
    }

    public function getStockBySite(Request $request) {
        try {
            $siteId = $request->input('site_id');

            // Get full stock list
            $stock = getCurrentStockBySiteId($siteId, null, null, null, null, null);

            return response()->json($stock);
        } catch (\Exception $e) {
            \Log::error('Error fetching stock: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch stock'], 500);
        }
    }

    public function getStockBySiteMaterialTransferEdit(Request $request) {
        try {
            $siteId = $request->input('site_id');

            $materialTransferId = $request->input('materialTransferId');
            // Get full stock list
            $stock = getCurrentStockBySiteId($siteId, $excludeConsumptionId = null, $materialTransferId);

            return response()->json($stock);
        } catch (\Exception $e) {
            \Log::error('Error fetching stock: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch stock'], 500);
        }
    }
}
