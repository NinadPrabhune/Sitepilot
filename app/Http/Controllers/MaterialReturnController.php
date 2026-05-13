<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaterialReturn;
use App\Models\MaterialReturnItem;
use App\Models\MaterialIssue;
use App\Models\Material;
use App\Services\StockService;
use App\Http\Requests\StoreMaterialReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\DataTables\MaterialReturnDataTable;

class MaterialReturnController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the material returns.
     */
    public function index(MaterialReturnDataTable $dataTable)
    {
        return $dataTable->render('material-returns.index');
    }

    /**
     * Show the form for creating a new material return.
     */
    public function create(Request $request)
    {
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();

        $materials = Material::with('unit')->get();
        $issues = MaterialIssue::with(['items.material'])
            ->forWorkspace($workspaceId)
            ->forSite($siteId)
            ->latestFirst()
            ->get();

        $selectedIssueId = $request->issue_id ?? null;

        return view('material-returns.create', compact('materials', 'issues', 'selectedIssueId'));
    }

    /**
     * Store a newly created material return.
     */
    public function store(StoreMaterialReturnRequest $request)
    {
        try {
            DB::beginTransaction();

            $workspaceId = getActiveWorkSpace();
            $siteId = getActiveProject();

            // Create material return
            $materialReturn = MaterialReturn::create([
                'return_number' => MaterialReturn::generateReturnNumber(),
                'issue_id' => $request->issue_id,
                'site_id' => $siteId,
                'return_date' => $request->return_date,
                'status' => MaterialReturn::STATUS_COMPLETED,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
            ]);

            // Process items
            foreach ($request->items as $itemData) {
                // Create return item
                $returnItem = MaterialReturnItem::create([
                    'return_id' => $materialReturn->id,
                    'issue_item_id' => $itemData['issue_item_id'],
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Add stock back
                $this->stockService->adjustStock(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Return: ' . $materialReturn->return_number
                );
            }

            DB::commit();

            return redirect()->route('material-returns.index')
                ->with('success', __('Material Return created successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return creation failed: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', __('Material Return creation failed: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified material return.
     */
    public function show($id)
    {
        $materialReturn = MaterialReturn::with(['site', 'creator', 'issue', 'items.material.unit'])
            ->findOrFail($id);

        return view('material-returns.show', compact('materialReturn'));
    }

    /**
     * Show the form for editing the specified material return.
     */
    public function edit($id)
    {
        $materialReturn = MaterialReturn::with(['items.material.unit', 'issue'])->findOrFail($id);
        
        $workspaceId = getActiveWorkSpace();
        $siteId = getActiveProject();

        $materials = Material::with('unit')->get();
        $issues = MaterialIssue::with(['items.material'])
            ->forWorkspace($workspaceId)
            ->forSite($siteId)
            ->latestFirst()
            ->get();

        return view('material-returns.edit', compact('materialReturn', 'materials', 'issues'));
    }

    /**
     * Update the specified material return.
     */
    public function update(Request $request, $id)
    {
        $materialReturn = MaterialReturn::with(['items', 'issue'])->findOrFail($id);
        
        $siteId = getActiveProject();

        $request->validate([
            'issue_id' => 'required|exists:material_issues,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.issue_item_id' => 'required|exists:material_issue_items,id',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string',
        ]);

        // Custom validation for quantity limits
        $issueId = $request->input('issue_id');
        $items = $request->input('items', []);

        $issue = MaterialIssue::with('items')->find($issueId);
        if (!$issue) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('Material Issue not found'));
        }

        // Group return items by issue_item_id
        $returnItemsByIssueItem = [];
        foreach ($items as $index => $item) {
            if (isset($item['issue_item_id'])) {
                $issueItemId = $item['issue_item_id'];
                if (!isset($returnItemsByIssueItem[$issueItemId])) {
                    $returnItemsByIssueItem[$issueItemId] = 0;
                }
                $returnItemsByIssueItem[$issueItemId] += floatval($item['quantity']);
            }
        }

        // Validate each issue item
        $errors = [];
        foreach ($returnItemsByIssueItem as $issueItemId => $returnQty) {
            $issueItem = $issue->items->firstWhere('id', $issueItemId);
            
            if (!$issueItem) {
                $errors["items.{$issueItemId}.issue_item_id"] = ['Invalid issue item selected.'];
                continue;
            }

            // Calculate already returned quantity for this issue item (excluding current return)
            $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItemId)
                ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                ->where('material_returns.issue_id', $issueId)
                ->where('material_returns.id', '!=', $materialReturn->id)
                ->sum('material_return_items.quantity');

            // Calculate remaining quantity
            $issuedQty = floatval($issueItem->quantity);
            $remainingQty = $issuedQty - $alreadyReturnedQty;

            // Validate return quantity doesn't exceed remaining
            if ($returnQty > $remainingQty) {
                $errors["items.{$issueItemId}.quantity"] = [
                    __('Return quantity cannot exceed remaining issued quantity. Issued: :issued, Already Returned: :returned, Remaining: :remaining', [
                        'issued' => $issuedQty,
                        'returned' => $alreadyReturnedQty,
                        'remaining' => $remainingQty,
                    ])
                ];
            }
        }

        if (!empty($errors)) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('Validation error. Please check the quantities.'));
        }

        try {
            DB::beginTransaction();

            $workspaceId = getActiveWorkSpace();

            // Reverse previous stock impact
            foreach ($materialReturn->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    -$item->quantity,
                    'Material Return Reversal (Edit): ' . $materialReturn->return_number
                );
            }

            // Update material return
            $materialReturn->update([
                'issue_id' => $request->issue_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id(),
            ]);

            // Delete old items
            $materialReturn->items()->delete();

            // Process new items
            foreach ($request->items as $itemData) {
                // Create return item
                $returnItem = MaterialReturnItem::create([
                    'return_id' => $materialReturn->id,
                    'issue_item_id' => $itemData['issue_item_id'],
                    'material_id' => $itemData['material_id'],
                    'quantity' => $itemData['quantity'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                // Add stock back
                $this->stockService->adjustStock(
                    $siteId,
                    $itemData['material_id'],
                    $itemData['quantity'],
                    'Material Return: ' . $materialReturn->return_number
                );
            }

            DB::commit();

            return redirect()->route('material-returns.index')
                ->with('success', __('Material Return updated successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return update failed: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', __('Material Return update failed: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified material return.
     */
    public function destroy($id)
    {
        $materialReturn = MaterialReturn::with(['items'])->findOrFail($id);
        
        $siteId = getActiveProject();

        try {
            DB::beginTransaction();

            // Reverse stock impact
            foreach ($materialReturn->items as $item) {
                $this->stockService->adjustStock(
                    $siteId,
                    $item->material_id,
                    -$item->quantity,
                    'Material Return Reversal (Delete): ' . $materialReturn->return_number
                );
            }

            // Delete items
            $materialReturn->items()->delete();

            // Delete main record
            $materialReturn->delete();

            DB::commit();

            return redirect()->route('material-returns.index')
                ->with('success', __('Material Return deleted successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material Return deletion failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', __('Material Return deletion failed: ') . $e->getMessage());
        }
    }

    /**
     * Get issue details for return form.
     */
    public function getIssueDetails(Request $request)
    {
        $request->validate([
            'issue_id' => 'required|exists:material_issues,id',
        ]);

        $issue = MaterialIssue::with(['items.material.unit'])->findOrFail($request->issue_id);

        // Calculate already returned quantities for each issue item
        $issueItemsWithReturns = $issue->items->map(function ($issueItem) use ($issue) {
            $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItem->id)
                ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                ->where('material_returns.issue_id', $issue->id)
                ->sum('material_return_items.quantity');

            $issueItem->already_returned_qty = $alreadyReturnedQty;
            $issueItem->remaining_qty = $issueItem->quantity - $alreadyReturnedQty;

            return $issueItem;
        });

        $issue->items = $issueItemsWithReturns;

        return response()->json([
            'success' => true,
            'issue' => $issue,
        ]);
    }
}