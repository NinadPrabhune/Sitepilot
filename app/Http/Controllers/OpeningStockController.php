<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;

class OpeningStockController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the opening stock.
     */
    public function index()
    {
        if (\Auth::user()->isAbleTo('opening-stock manage')) {
            return view('opening-stock.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating new opening stock.
     */
    public function create()
    {
        if (\Auth::user()->isAbleTo('opening-stock create')) {
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->get()
                ->pluck('name', 'id');
            
            $materials = Material::where('status', 'active')                
                ->pluck('name', 'id');

            return view('opening-stock.create', compact('projects', 'materials'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store newly created opening stock.
     */
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'project_id' => 'required|exists:projects,id',
        'material_id' => 'required|exists:materials,id',
        'quantity' => 'required|numeric|min:0.0001',
        'rate' => 'nullable|numeric|min:0',
        'remarks' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->with('error', $validator->errors()->first())
            ->withInput();
    }

    try {
        // Prevent duplicate opening stock
        $existingStock = $this->stockService->getCurrentStock(
            $request->project_id,
            $request->material_id
        );

        if ($existingStock > 0) {
            return redirect()->back()
                ->with('error', 'Opening stock already exists for this material in selected project.')
                ->withInput();
        }

        $rate = $request->rate ?? 0;

        $this->stockService->addOpeningStock(
            $request->project_id,
            $request->material_id,
            $request->quantity,
            $rate,
            $request->remarks
        );

        return redirect()->route('opening-stock.index')
            ->with('success', 'Opening stock added successfully.');

    } catch (\Exception $e) {
        \Log::error($e);

        return redirect()->back()
            ->with('error', 'Something went wrong.')
            ->withInput();
    }
}

    /**
     * Get current stock for a material at a project via AJAX.
     */
    public function getStock(Request $request)
    {
        $projectId = $request->project_id;
        $materialId = $request->material_id;

        if (!is_numeric($projectId) || !is_numeric($materialId)) {
            return response()->json(['stock' => 0]);
        }

        $stock = $this->stockService->getCurrentStock($projectId, $materialId);
        return response()->json(['stock' => $stock]);
    }
}
