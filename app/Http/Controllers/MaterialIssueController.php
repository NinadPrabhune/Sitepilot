<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\DataTables\MaterialIssueDataTable;

class MaterialIssueController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the material issues.
     */
    public function index(MaterialIssueDataTable $dataTable)
    {
        return $dataTable->render('material-issues.index');
    }

    /**
     * Show the form for creating a new material issue.
     */
    public function create()
    {
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();

        $materials = Material::with('unit')->get();
        $users = getActiveProjectEmployees();
        $suppliers = Supplier::where('category_id', 1)->orderBy('name')->get();

        return view('material-issues.create', compact('materials', 'users', 'suppliers'));
    }

    /**
     * Store a newly created material issue.
     */
    public function store(Request $request)
    {
        $siteId = getActiveProject();

        $request->validate([
            'issue_to_type' => 'required|in:user,supplier',
            'issue_to_id' => 'required|integer',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request, $siteId) {
                    $index = explode('.', $attribute)[1];
                    $materialId = $request->input("items.$index.material_id");
                    
                    if ($materialId) {
                        $availableStock = $this->stockService->getCurrentStock($siteId, $materialId);
                        if ($value > $availableStock) {
                            $fail(__('Quantity cannot exceed available stock. Available: :available, Requested: :requested', [
                                'available' => $availableStock,
                                'requested' => $value
                            ]));
                        }
                    }
                },
            ],
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $workspaceId = getActiveWorkSpace();

            // Create material issue
            $materialIssue = MaterialIssue::create([
                'issue_number' => MaterialIssue::generateIssueNumber(),
                'site_id' => $siteId,
                'issue_to_type' => $request->issue_to_type,
                'issue_to_id' => $request->issue_to_id,
                'issue_date' => $request->issue_date,
                'status' => MaterialIssue::STATUS_COMPLETED,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
            ]);

            // Process items
            foreach ($request->items as $itemData) {
                // Create issue item
                $issueItem = MaterialIssueItem::create([
                    'issue_id' => $materialIssue->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'rate' => $itemData['rate'] ?? null,
                    'amount' => $itemData['rate'] ? $itemData['rate'] * $itemData['quantity'] : null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Deduct stock
                $this->stockService->issueMaterial(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Issue: ' . $materialIssue->issue_number,
                    'material_issue',
                    $materialIssue->id
                );
            }

            DB::commit();

            return redirect()->route('material-issues.index')
                ->with('success', __('Material Issue created successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue creation failed: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', __('Material Issue creation failed: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified material issue.
     */
    public function show($id)
    {
        $materialIssue = MaterialIssue::with(['site', 'creator', 'items.material.unit'])
            ->findOrFail($id);

        return view('material-issues.show', compact('materialIssue'));
    }

    /**
     * Show the form for editing the specified material issue.
     */
    public function edit($id)
    {
        $materialIssue = MaterialIssue::with(['items.material.unit'])->findOrFail($id);
        
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();

        $materials = Material::with('unit')->get();
        $users = getActiveProjectEmployees();
        $suppliers = Supplier::where('category_id', 1)->orderBy('name')->get();

        return view('material-issues.edit', compact('materialIssue', 'materials', 'users', 'suppliers'));
    }

    /**
     * Update the specified material issue.
     */
    public function update(Request $request, $id)
    {
        $materialIssue = MaterialIssue::with(['items'])->findOrFail($id);
        
        $siteId = getActiveProject();

        $request->validate([
            'issue_to_type' => 'required|in:user,supplier',
            'issue_to_id' => 'required|integer',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request, $siteId, $materialIssue) {
                    $index = explode('.', $attribute)[1];
                    $materialId = $request->input("items.$index.material_id");
                    $itemId = $request->input("items.$index.id");
                    
                    if ($materialId) {
                        // Get current stock
                        $availableStock = $this->stockService->getCurrentStock($siteId, $materialId);
                        
                        // If updating existing item, add back its quantity to available stock
                        if ($itemId) {
                            $existingItem = $materialIssue->items->firstWhere('id', $itemId);
                            if ($existingItem && $existingItem->material_id == $materialId) {
                                $availableStock += $existingItem->quantity;
                            }
                        }
                        
                        if ($value > $availableStock) {
                            $fail(__('Quantity cannot exceed available stock. Available: :available, Requested: :requested', [
                                'available' => $availableStock,
                                'requested' => $value
                            ]));
                        }
                    }
                },
            ],
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $workspaceId = getActiveWorkSpace();

            // Reverse previous stock impact
            foreach ($materialIssue->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    $item->quantity,
                    'Material Issue Reversal (Edit): ' . $materialIssue->issue_number
                );
            }

            // Update material issue
            $materialIssue->update([
                'issue_to_type' => $request->issue_to_type,
                'issue_to_id' => $request->issue_to_id,
                'issue_date' => $request->issue_date,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id(),
            ]);

            // Delete old items
            $materialIssue->items()->delete();

            // Process new items
            foreach ($request->items as $itemData) {
                // Create issue item
                $issueItem = MaterialIssueItem::create([
                    'issue_id' => $materialIssue->id,
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'rate' => $itemData['rate'] ?? null,
                    'amount' => $itemData['rate'] ? $itemData['rate'] * $itemData['quantity'] : null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Deduct stock
                $this->stockService->issueMaterial(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Issue: ' . $materialIssue->issue_number,
                    'material_issue',
                    $materialIssue->id
                );
            }

            DB::commit();

            return redirect()->route('material-issues.index')
                ->with('success', __('Material Issue updated successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue update failed: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', __('Material Issue update failed: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified material issue.
     */
    public function destroy($id)
    {
        $materialIssue = MaterialIssue::with(['items'])->findOrFail($id);
        
        $siteId = getActiveProject();

        try {
            DB::beginTransaction();

            // Reverse stock impact
            foreach ($materialIssue->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    $item->quantity,
                    'Material Issue Reversal (Delete): ' . $materialIssue->issue_number
                );
            }

            // Delete items
            $materialIssue->items()->delete();

            // Delete main record
            $materialIssue->delete();

            DB::commit();

            return redirect()->route('material-issues.index')
                ->with('success', __('Material Issue deleted successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Issue deletion failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', __('Material Issue deletion failed: ') . $e->getMessage());
        }
    }

    /**
     * Get available stock for a material at the current site.
     */
    public function getAvailableStock(Request $request)
    {
        $request->validate([
            'material_id' => 'required|exists:materials,id',
        ]);

        $siteId = getActiveProject();
        $availableStock = $this->stockService->getCurrentStock($siteId, $request->material_id);
        $material = Material::find($request->material_id);

        return response()->json([
            'success' => true,
            'available_stock' => $availableStock,
            'rate' => $material->price ?? null,
        ]);
    }
} 
