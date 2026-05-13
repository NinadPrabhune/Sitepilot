<?php

namespace App\Http\Controllers;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Services\DprLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineryDprController extends Controller
{
    protected DprLedgerService $ledgerService;
    
    public function __construct(DprLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }
    
    /**
     * List DPRs for a machinery (Direct flow only)
     */
    public function index(Machinery $machinery)
    {
        $dprs = DailyProgressReport::where('machinery_id', $machinery->id)
            ->where('source_type', DailyProgressReport::SOURCE_TYPE_MACHINERY_DIRECT)
            ->with(['ledgerEntries'])
            ->orderBy('date', 'desc')
            ->paginate(20);
            
        return view('machinery.dpr.index', compact('machinery', 'dprs'));
    }
    
    /**
     * Show create form for direct machinery DPR
     */
    public function create(Machinery $machinery)
    {
        return view('machinery.dpr.create', compact('machinery'));
    }
    
    /**
     * Store direct machinery DPR
     */
    public function store(Request $request, Machinery $machinery)
    {
        // Site consistency check
        if ($machinery->site_id != $request->site_id) {
            return back()->withErrors(['site_id' => 'Machinery does not belong to specified site']);
        }
        
        $validated = $request->validate([
            'date' => 'required|date',
            'site_id' => 'required|exists:projects,id',
            'machine_start_reading' => 'required|numeric|min:0',
            'machine_end_reading' => 'required|numeric|gte:machine_start_reading',
            'machine_idle_reading' => 'nullable|numeric',
            'diesel_consumption' => 'nullable|numeric|min:0',
            'number_of_operators' => 'nullable|integer|min:0',
            'work_details' => 'nullable|string',
            'maintenance_notes' => 'nullable|string',
        ]);
        
        try {
            $dpr = DB::transaction(function () use ($machinery, $validated, $request) {
                $dpr = DailyProgressReport::create([
                    'machinery_id' => $machinery->id,
                    'site_id' => $validated['site_id'],
                    'activity_completed_id' => null, // Direct flow
                    'source_type' => DailyProgressReport::SOURCE_TYPE_MACHINERY_DIRECT,
                    'date' => $validated['date'],
                    'machine_start_reading' => $validated['machine_start_reading'],
                    'machine_end_reading' => $validated['machine_end_reading'],
                    'machine_idle_reading' => $validated['machine_idle_reading'] ?? 0,
                    'diesel_consumption' => $validated['diesel_consumption'] ?? 0,
                    'number_of_operators' => $validated['number_of_operators'] ?? 0,
                    'work_details' => $validated['work_details'] ?? null,
                    'maintenance_notes' => $validated['maintenance_notes'] ?? null,
                    'status' => 'pending',
                    'workspace_id' => auth()->user()->workspace_id,
                    'created_by' => auth()->id(),
                ]);
                
                // Transactional ledger creation
                $this->ledgerService->createFromDpr($dpr);
                
                return $dpr;
            });
            
            Log::info('DPR created (Direct Flow)', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $machinery->id,
                'source_type' => $dpr->source_type,
            ]);
            
            return redirect()->route('machinery.dpr.show', [$machinery, $dpr])
                ->with('success', 'DPR created successfully');
                
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation (duplicate DPR)
            if ($e->getCode() == 23000) {
                Log::warning('Duplicate DPR attempt', [
                    'machinery_id' => $machinery->id,
                    'date' => $validated['date'],
                ]);
                return back()->withErrors(['date' => 'DPR already exists for this machinery and date']);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('DPR creation failed', [
                'error' => $e->getMessage(),
                'machinery_id' => $machinery->id,
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Show DPR details
     */
    public function show(Machinery $machinery, DailyProgressReport $dpr)
    {
        $dpr->load(['ledgerEntries', 'machinery']);
        return view('machinery.dpr.show', compact('machinery', 'dpr'));
    }
    
    /**
     * Show edit form
     */
    public function edit(Machinery $machinery, DailyProgressReport $dpr)
    {
        return view('machinery.dpr.edit', compact('machinery', 'dpr'));
    }
    
    /**
     * Update DPR
     */
    public function update(Request $request, Machinery $machinery, DailyProgressReport $dpr)
    {
        $validated = $request->validate([
            'machine_start_reading' => 'required|numeric|min:0',
            'machine_end_reading' => 'required|numeric|gte:machine_start_reading',
            'machine_idle_reading' => 'nullable|numeric',
            'diesel_consumption' => 'nullable|numeric|min:0',
            'number_of_operators' => 'nullable|integer|min:0',
            'work_details' => 'nullable|string',
            'maintenance_notes' => 'nullable|string',
        ]);
        
        try {
            $dpr->update($validated);
            
            Log::info('DPR updated', [
                'dpr_id' => $dpr->id,
                'machinery_id' => $machinery->id,
            ]);
            
            return redirect()->route('machinery.dpr.show', [$machinery, $dpr])
                ->with('success', 'DPR updated successfully');
                
        } catch (\Exception $e) {
            Log::error('DPR update failed', [
                'error' => $e->getMessage(),
                'dpr_id' => $dpr->id,
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
