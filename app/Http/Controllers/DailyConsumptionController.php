<?php

namespace App\Http\Controllers;

use App\DataTables\DailyConsumptionDataTable;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Events\DailyConsumptionCreated;
use App\Events\DailyConsumptionUpdated;
use App\Events\DailyConsumptionDeleted;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Services\SupplierLedgerService;
use App\Services\StockService;

class DailyConsumptionController extends Controller
{
    public function index(DailyConsumptionDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('consumption-log manage')) {
            abort(403, 'Permission denied.');
        }

        try {
            return $dataTable->render('daily-consumption.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load consumption logs: '.$e->getMessage()]);
        }
    }

    public function create(Request $request)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        // Check permissions
        if (!Auth::user()->isAbleTo('consumption-log create')) {
            \Log::warning('DailyConsumptionController@create - Permission denied', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
                'user_type' => Auth::user()?->type,
                'required_permission' => 'consumption-log create'
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Permission denied. You do not have permission to create consumption logs.',
                    'required_permission' => 'consumption-log create'
                ], 403);
            }
            
            // If coming from activity context, provide a more specific error message
            if ($request->get('activity_id') || $request->get('activity_completed_id')) {
                return back()->with('error', 'You do not have permission to create consumption logs. Please contact your administrator for "consumption-log create" permission.');
            }
            
            abort(403, 'Permission denied. You do not have permission to create consumption logs.');
        }

        // Check if activity_completed_id is passed
        $activity_completed_id = $request->get('activity_completed_id');
        $activity_id = $request->get('activity_id');

        try {
            $materials_fules = \App\Models\Material::with('category','unit')
                ->where('category_id',2)->get()
                ->mapWithKeys(fn($m)=>[$m->id=>[
                    'name'=>$m->name,'unit'=>$m->unit->name ?? 'unit',
                    'category_id'=>$m->category_id,
                    'category_name'=>$m->category?->name,
                ]]);

            $materials_all = \App\Models\Material::with('category','unit')
                ->where('category_id','!=',2)->get()
                ->mapWithKeys(fn($m)=>[$m->id=>[
                    'name'=>$m->name,'unit'=>$m->unit->name ?? 'unit',
                    'category_id'=>$m->category_id,
                    'category_name'=>$m->category?->name,
                ]]);

            $sites = Project::where('workspace', getActiveWorkSpace())->where('id', getActiveProject())->projectonly()->get()->pluck('name','id');

            $defaultSiteId = getActiveProject();

            $machineryOptions = \App\Models\Machinery::where('operational_status', 'active')
                ->where('site_id', $defaultSiteId)
                ->get()
                ->mapWithKeys(fn($i)=>[$i->id=>$i->name.'('.$i->vehicle_number.')'])
                ->toArray();

            $maxId = DailyConsumptionMaster::max('id');
            $i = $maxId ? $maxId+1 : 1;
            $nextConsumptionNumber = 'DCM-'.str_pad($i,4,'0',STR_PAD_LEFT);

            return view('daily-consumption.create', compact(
                'materials_fules','materials_all','sites',
                'nextConsumptionNumber','machineryOptions','activity_id','activity_completed_id','defaultSiteId'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: '.$e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        // DEBUG: Log method entry
        file_put_contents(storage_path('debug.log'), "DailyConsumptionController@store ENTRY - Request data: " . json_encode($request->all()) . "\n", FILE_APPEND);
        
        if (!Auth::user()->isAbleTo('consumption-log create')) {
            file_put_contents(storage_path('debug.log'), "DailyConsumptionController@store - Permission denied\n", FILE_APPEND);
            abort(403, 'Permission denied.');
        }

        file_put_contents(storage_path('debug.log'), "DailyConsumptionController@store - Permission passed\n", FILE_APPEND);

        try {
            $data = $request->validate([
                'consumption_date'=>'required|date',
                'site_id'=>'required|exists:projects,id',
                'consumption_type'=>'required|in:all,fuel',
                'machinery_type'=>'nullable|in:own,rental',
                'machinery_id'=>'nullable|exists:machineries,id',
                'daily_progress_report_id'=>'nullable|exists:daily_progress_reports,id',
                'activity_completed_id'=>'nullable|exists:activities_completed,id',
                'items'=>'required|array|min:1',
                'items.*.material_id'=>'required|exists:materials,id',
                'items.*.quantity'=>'required|numeric|min:0.01',
                'items.*.unit'=>'required|string',
                'items.*.remarks'=>'nullable|string',
            ]);

            // Additional validation: Machinery is required for fuel consumption
            if ($data['consumption_type'] === 'fuel' && empty($data['machinery_id'])) {
                return back()->withErrors(['machinery_id' => 'Machinery is required for fuel consumption.'])->withInput();
            }

            // Check for duplicate daily reading for same machine and date
            if (!empty($data['machinery_id']) && !empty($data['consumption_date'])) {
                $existingConsumption = DailyConsumptionMaster::where('machinery_id', $data['machinery_id'])
                    ->where('consumption_date', $data['consumption_date'])
                    ->where('status', '!=', 'deleted')
                    ->first();

                if ($existingConsumption) {
                    return back()->withErrors(['error' => 'Duplicate daily reading detected. A consumption record for this machinery on this date already exists. Please use the existing record or choose a different date.']);
                }
            }

            DB::beginTransaction();

            $maxId = DailyConsumptionMaster::lockForUpdate()->max('id');
            $i = $maxId ? $maxId+1 : 1;
            $data['consumption_number'] = 'DCM-'.str_pad($i,4,'0',STR_PAD_LEFT);

            $data['created_by'] = creatorId();
            $data['workspace_id'] = getActiveWorkSpace();

            // Handle file upload - check if already uploaded or need to upload
            if (isset($data['consumption_file']) && is_string($data['consumption_file'])) {
                // File path already provided (e.g., from DPR controller)
                // No action needed, use existing path
            } elseif ($request->hasFile('consumption_file')) {
                // Upload new file
                $file = $request->file('consumption_file');
                $filename = time().'_'.$data['consumption_number'].'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('consumptions',$filename,'public');
                $data['consumption_file'] = $path;
            } else {
                $data['consumption_file'] = null;
            }

            $master = DailyConsumptionMaster::create($data);

            $stockService = new StockService();
            
            foreach ($request->items as $item) {
                // Check stock availability using StockService for real-time validation
                $availableStock = $stockService->getCurrentStock($data['site_id'], $item['material_id']);
                if ($item['quantity'] > $availableStock) {
                    $materialName = \App\Models\Material::find($item['material_id'])->name ?? 'Unknown';
                    throw new \Exception("Insufficient stock for material '{$materialName}'. Available: {$availableStock}, Requested: {$item['quantity']}");
                }
                
                // Create consumption detail
                DailyConsumptionDetails::create([
                    'daily_consumption_master_id'=>$master->id,
                    'material_id'=>$item['material_id'],
                    'quantity'=>$item['quantity'],
                    'unit'=>$item['unit'],
                    'remarks'=>$item['remarks'] ?? null,
                ]);
                
                // Create stock transaction for proper stock deduction
                try {
                    $stockService->issueMaterial(
                        $data['site_id'],
                        $item['material_id'],
                        $item['quantity'],
                        "Fuel consumption - {$master->consumption_number}",
                        'DailyConsumptionMaster',
                        $master->id
                    );
                } catch (\Exception $stockError) {
                    // If stock transaction fails, we need to rollback the consumption detail
                    throw new \Exception("Stock deduction failed: " . $stockError->getMessage());
                }
            }

            // Create ledger entry for diesel consumption (debit)
            if ($master->machinery_id && $master->consumption_type === 'fuel') {
                // Calculate total diesel cost
                $totalDieselCost = 0;
                foreach ($master->details as $detail) {
                    $material = $detail->material;
                    if ($material && $material->category_id == 2) { // Fuel category
                        $totalDieselCost += $detail->quantity * ($material->price ?? 0);
                    }
                }

                if ($totalDieselCost > 0) {
                    $ledgerEntry = MachineryLedgerService::createDebit([
                        'machinery_id' => $master->machinery_id,
                        'amount' => $totalDieselCost,
                        'reference_type' => MachineryLedgerService::REFERENCE_TYPE_DIESEL,
                        'reference_id' => $master->id,
                        'entry_type' => MachineryLedgerService::ENTRY_TYPE_DIESEL,
                        'date' => $master->consumption_date,
                        'description' => "Diesel consumption #{$master->consumption_number}",
                        'metadata' => [
                            'consumption_number' => $master->consumption_number,
                            'site_id' => $master->site_id,
                        ],
                    ]);

                    // Hard enforcement: verify ledger amount matches calculated amount
                    if (abs($ledgerEntry->amount - $totalDieselCost) > 0.01) {
                        throw new \RuntimeException("Ledger enforcement failed: Diesel amount mismatch. Calculated ₹{$totalDieselCost} vs Ledger ₹{$ledgerEntry->amount}. Cannot proceed.");
                    }

                    // Link ledger entry to consumption master
                    $master->update(['ledger_entry_id' => $ledgerEntry->id]);

                    // Create supplier ledger entry (credit supplier for diesel provided)
                    // Find supplier from material
                    $supplierId = null;
                    foreach ($master->details as $detail) {
                        $material = $detail->material;
                        if ($material && $material->category_id == 2 && $material->supplier_id) {
                            $supplierId = $material->supplier_id;
                            break;
                        }
                    }

                    if ($supplierId) {
                        $supplierLedgerEntry = SupplierLedgerService::createCredit([
                            'supplier_id' => $supplierId,
                            'amount' => $totalDieselCost,
                            'reference_type' => 'DailyConsumptionMaster',
                            'reference_id' => $master->id,
                            'entry_type' => SupplierLedgerService::ENTRY_TYPE_DIESEL,
                            'date' => $master->consumption_date,
                            'description' => "Diesel consumption #{$master->consumption_number}",
                            'metadata' => [
                                'consumption_number' => $master->consumption_number,
                                'site_id' => $master->site_id,
                                'machinery_id' => $master->machinery_id,
                            ],
                        ]);

                        // Link supplier ledger entry to consumption master
                        $master->update(['supplier_ledger_entry_id' => $supplierLedgerEntry->id]);
                    }
                }
            }

            event(new DailyConsumptionCreated($master));
            DB::commit();
            
            file_put_contents(storage_path('debug.log'), "DailyConsumptionController@store SUCCESS - Master ID: " . $master->id . "\n", FILE_APPEND);
            
            return back()->with('success', 'Daily Consumption created successfully');
            
            
//            if ($request->input('InsertFrom') === 'DailyProgressReportController') {
//                return back()->with('success', 'Daily Consumption created successfully from DPR.');
//            }
//            return redirect()->route('daily-consumption.index')->with('success','Daily Consumption created successfully.');
            
            
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->validator)->withInput();
        } catch (QueryException $e) {
            DB::rollBack();
            if ($e->errorInfo[1] == 1062) {
                return back()->with('error','Duplicate consumption number detected. Please try again.');
            }
            \Log::error('Database error: '.$e->getMessage());
            return back()->with('error','Database error occurred.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating DailyConsumption: '.$e->getMessage());
            return back()->with('error','Error creating record: '.$e->getMessage());
        }
    }

    public function show(DailyConsumptionMaster $daily_consumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log show')) {
            abort(403, 'Permission denied.');
        }

        try {
            $daily_consumption->load('details.material','site');
            return view('daily-consumption.show', compact('daily_consumption'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to show record: '.$e->getMessage()]);
        }
    }
    
    public function edit(DailyConsumptionMaster $daily_consumption)
    {
        // Debug: Log method entry
        \Log::info('Daily Consumption edit method called', [
            'consumption_id' => $daily_consumption->id,
            'url_parameters' => request()->all(),
            'method' => request()->method(),
            'timestamp' => now()
        ]);

        if (!Auth::user()->isAbleTo('consumption-log edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            // Debug: Check if consumption exists
            if (!$daily_consumption) {
                \Log::error('Daily Consumption not found', ['consumption_id' => request()->route('id')]);
                return back()->withErrors(['error' => 'Daily Consumption record not found.']);
            }

            $daily_consumption->load(['details','machinery']);
            
            // Debug: Log loaded data
            \Log::info('Daily Consumption edit data loaded', [
                'consumption_id' => $daily_consumption->id,
                'details_count' => $daily_consumption->details->count(),
                'machinery_id' => $daily_consumption->machinery_id,
                'site_id' => $daily_consumption->site_id,
                'consumption_type' => $daily_consumption->consumption_type,
                'loaded_details' => $daily_consumption->details->toArray(),
                'materials_fules_count' => count($materials_fules ?? []),
                'materials_all_count' => count($materials_all ?? [])
            ]);
            
            $sites = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()->get()->pluck('name','id');


            $materials_fules = [];
            $materials_all = [];

            if ($daily_consumption->site_id) {
                $stockItems = getCurrentStockBySiteId($daily_consumption->site_id,$daily_consumption->id,null, null,null, null);
                foreach ($stockItems as $item) {
                    $materialData = [
                        'name'=>$item->material_name,
                        'unit'=>$item->unit_name,
                        'price'=>$item->material_price,
                        'total_qty'=>max(0,$item->total_qty),
                        'category_id'=>$item->category_id,
                        'category_name'=>$item->category_name,
                    ];
                    if ((int)$item->category_id === 2) {
                        $materials_fules[$item->material_id] = $materialData;
                    } else {
                        $materials_all[$item->material_id] = $materialData;
                    }
                }
            }

            $materials = array_merge($materials_fules,$materials_all);

            $machineryOptions = \App\Models\Machinery::all()
                ->mapWithKeys(fn($i)=>[$i->id=>$i->name.'('.$i->vehicle_number.')'])
                ->toArray();
            $daily_consumption_masters_id=$daily_consumption->id;

            
//            dd($daily_consumption);
            
            return view('daily-consumption.edit', compact(
                'daily_consumption','materials','materials_fules','materials_all',
                'sites','machineryOptions','daily_consumption_masters_id'
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form: '.$e->getMessage()]);
        }
    }

    public function update(Request $request, DailyConsumptionMaster $daily_consumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log edit')) {
            abort(403, 'Permission denied.');
        }

        try {
            $data = $request->validate([
                'consumption_date'=>'required|date',
                'site_id'=>'required|exists:projects,id',
                'consumption_type'=>'required|in:all,fuel',
                'machinery_type'=>'nullable|in:own,rental',
                'machinery_id'=>'nullable|exists:machineries,id',
                'items'=>'required|array|min:1',
                'items.*.material_id'=>'required|exists:materials,id',
                'items.*.quantity'=>'required|numeric|min:0.01',
                'items.*.unit'=>'required|string',
                'items.*.remarks'=>'nullable|string',
            ]);

            DB::beginTransaction();

            $data['created_by'] = creatorId();
            $data['workspace_id'] = getActiveWorkSpace();

            if ($request->hasFile('consumption_file')) {
                if ($daily_consumption->consumption_file) {
                    Storage::disk('public')->delete($daily_consumption->consumption_file);
                }
                $file = $request->file('consumption_file');
                $filename = time().'_'.$daily_consumption->consumption_number.'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('consumptions',$filename,'public');
                $data['consumption_file'] = $path;
            }

            $daily_consumption->update($data);
            $daily_consumption->refresh();

            $stockService = new StockService();
            
            // Get old consumption details for stock reversal
            $oldDetails = $daily_consumption->details()->get();
            
            $daily_consumption->details()->delete();

            foreach ($request->items as $item) {
                // Check stock availability using StockService for real-time validation
                $availableStock = $stockService->getCurrentStock($data['site_id'], $item['material_id']);
                if ($item['quantity'] > $availableStock) {
                    $materialName = \App\Models\Material::find($item['material_id'])->name ?? 'Unknown';
                    throw new \Exception("Insufficient stock for material '{$materialName}'. Available: {$availableStock}, Requested: {$item['quantity']}");
                }
                
                DailyConsumptionDetails::create([
                    'daily_consumption_master_id'=>$daily_consumption->id,
                    'material_id'=>$item['material_id'],
                    'quantity'=>$item['quantity'],
                    'unit'=>$item['unit'],
                    'remarks'=>$item['remarks'] ?? null,
                ]);
                
                // Create stock transaction for proper stock deduction
                try {
                    $stockService->issueMaterial(
                        $data['site_id'],
                        $item['material_id'],
                        $item['quantity'],
                        "Fuel consumption updated - {$daily_consumption->consumption_number}",
                        'DailyConsumptionMaster',
                        $daily_consumption->id
                    );
                } catch (\Exception $stockError) {
                    // If stock transaction fails, we need to rollback the consumption detail
                    throw new \Exception("Stock deduction failed: " . $stockError->getMessage());
                }
            }
            
            // Reverse old stock transactions
            foreach ($oldDetails as $oldDetail) {
                try {
                    $stockService->adjustStock(
                        $daily_consumption->site_id,
                        $oldDetail->material_id,
                        $oldDetail->quantity, // Add back the old quantity
                        "Stock reversal for consumption update - {$daily_consumption->consumption_number}"
                    );
                } catch (\Exception $reversalError) {
                    // Log reversal error but don't fail the update
                    \Log::warning('Stock reversal failed during consumption update', [
                        'consumption_id' => $daily_consumption->id,
                        'material_id' => $oldDetail->material_id,
                        'quantity' => $oldDetail->quantity,
                        'error' => $reversalError->getMessage()
                    ]);
                }
            }

            // Handle ledger updates for fuel consumption
            if ($daily_consumption->machinery_id && $daily_consumption->consumption_type === 'fuel') {
                // Calculate new total diesel cost
                $newTotalDieselCost = 0;
                foreach ($daily_consumption->details as $detail) {
                    $material = $detail->material;
                    if ($material && $material->category_id == 2) { // Fuel category
                        $newTotalDieselCost += $detail->quantity * ($material->price ?? 0);
                    }
                }

                // Reverse old machinery ledger entry if it exists
                if ($daily_consumption->ledger_entry_id) {
                    try {
                        MachineryLedgerService::reverseEntry(
                            $daily_consumption->ledger_entry_id,
                            "Reversal due to fuel consumption update - {$daily_consumption->consumption_number}"
                        );
                    } catch (\Exception $reversalError) {
                        \Log::error('Machinery ledger reversal failed during consumption update', [
                            'consumption_id' => $daily_consumption->id,
                            'ledger_entry_id' => $daily_consumption->ledger_entry_id,
                            'error' => $reversalError->getMessage()
                        ]);
                        throw new \Exception("Failed to reverse machinery ledger entry: " . $reversalError->getMessage());
                    }
                }

                // Reverse old supplier ledger entry if it exists
                if ($daily_consumption->supplier_ledger_entry_id) {
                    try {
                        SupplierLedgerService::reverseEntry(
                            $daily_consumption->supplier_ledger_entry_id,
                            "Reversal due to fuel consumption update - {$daily_consumption->consumption_number}"
                        );
                    } catch (\Exception $reversalError) {
                        \Log::error('Supplier ledger reversal failed during consumption update', [
                            'consumption_id' => $daily_consumption->id,
                            'supplier_ledger_entry_id' => $daily_consumption->supplier_ledger_entry_id,
                            'error' => $reversalError->getMessage()
                        ]);
                        throw new \Exception("Failed to reverse supplier ledger entry: " . $reversalError->getMessage());
                    }
                }

                // Create new machinery ledger entry if there's a cost
                if ($newTotalDieselCost > 0) {
                    $newLedgerEntry = MachineryLedgerService::createDebit([
                        'machinery_id' => $daily_consumption->machinery_id,
                        'amount' => $newTotalDieselCost,
                        'reference_type' => MachineryLedgerService::REFERENCE_TYPE_DIESEL,
                        'reference_id' => $daily_consumption->id,
                        'entry_type' => MachineryLedgerService::ENTRY_TYPE_DIESEL,
                        'date' => $daily_consumption->consumption_date,
                        'description' => "Updated diesel consumption #{$daily_consumption->consumption_number}",
                        'metadata' => [
                            'consumption_number' => $daily_consumption->consumption_number,
                            'site_id' => $daily_consumption->site_id,
                        ],
                    ]);

                    // Update link to new ledger entry
                    $daily_consumption->update(['ledger_entry_id' => $newLedgerEntry->id]);

                    // Create new supplier ledger entry
                    $supplierId = null;
                    foreach ($daily_consumption->details as $detail) {
                        $material = $detail->material;
                        if ($material && $material->category_id == 2 && $material->supplier_id) {
                            $supplierId = $material->supplier_id;
                            break;
                        }
                    }

                    if ($supplierId) {
                        $newSupplierLedgerEntry = SupplierLedgerService::createCredit([
                            'supplier_id' => $supplierId,
                            'amount' => $newTotalDieselCost,
                            'reference_type' => 'DailyConsumptionMaster',
                            'reference_id' => $daily_consumption->id,
                            'entry_type' => SupplierLedgerService::ENTRY_TYPE_DIESEL,
                            'date' => $daily_consumption->consumption_date,
                            'description' => "Updated diesel consumption #{$daily_consumption->consumption_number}",
                            'metadata' => [
                                'consumption_number' => $daily_consumption->consumption_number,
                                'site_id' => $daily_consumption->site_id,
                                'machinery_id' => $daily_consumption->machinery_id,
                            ],
                        ]);

                        // Update link to new supplier ledger entry
                        $daily_consumption->update(['supplier_ledger_entry_id' => $newSupplierLedgerEntry->id]);
                    }
                } else {
                    // No cost, clear ledger links
                    $daily_consumption->update([
                        'ledger_entry_id' => null,
                        'supplier_ledger_entry_id' => null
                    ]);
                }

                // Recalculate running balances for machinery ledger
                if ($daily_consumption->machinery_id) {
                    \App\Services\LedgerBalancingValidationService::recalculateRunningBalances($daily_consumption->machinery_id);
                }
            }

            event(new DailyConsumptionUpdated($daily_consumption));
            DB::commit();

            
            
             return back()->with('success', 'Daily Consumption updated successfully.');
//            return redirect()->route('daily-consumption.index') ->with('success','Daily Consumption updated successfully.');
            
            
            
        } catch (QueryException $e) {
            DB::rollBack();
            \Log::error('Database error: '.$e->getMessage());
            return back()->with('error','Database error occurred.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating DailyConsumption: '.$e->getMessage());
            return back()->with('error','Error updating record: '.$e->getMessage());
        }
    }

    public function destroy(DailyConsumptionMaster $daily_consumption)
    {
        if (!Auth::user()->isAbleTo('consumption-log delete')) {
            abort(403, 'Permission denied.');
        }

        try {
            // Period lock guard
            if ($daily_consumption->machinery_id && \App\Domain\Machinery\Models\MachineryPaymentPeriod::isDateLocked($daily_consumption->machinery_id, $daily_consumption->consumption_date->format('Y-m-d'))) {
                throw new \RuntimeException("Cannot delete Diesel Consumption #{$daily_consumption->id} because the date is within a locked period.");
            }

            // Orphan prevention: block delete if ledger entry exists
            if ($daily_consumption->ledger_entry_id) {
                throw new \RuntimeException("Cannot delete Diesel Consumption #{$daily_consumption->id} because it has a linked ledger entry. Use reversal to remove the financial impact first.");
            }

            if ($daily_consumption->consumption_file) {
                Storage::disk('public')->delete($daily_consumption->consumption_file);
            }
            $daily_consumption->details()->delete();
            $daily_consumption->delete();

            event(new DailyConsumptionDeleted($daily_consumption));

            return redirect()->route('daily-consumption.index')
                ->with('success','Daily Consumption deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error','Error deleting record: '.$e->getMessage());
        }
    }

    public function getStockBySiteForDailyConsumption(Request $request)
    {
        try {
            $siteId = $request->input('site_id');
            // Call helper function without materialId to get all materials
            $stock = getCurrentStockBySiteId($siteId);
            return response()->json($stock);
        } catch (\Exception $e) {
            \Log::error('Error fetching stock: '.$e->getMessage());
            return response()->json(['error'=>'Failed to fetch stock'],500);
        }
    }

    public function getStockBySiteForDailyConsumptionEdit(Request $request)
    {
        try {
            $siteId = $request->input('site_id');
            $daily_consumption_masters_id = $request->input('daily_consumption_masters_id');
            // Call helper function with excludeConsumptionId parameter to get all materials
            $stock = getCurrentStockBySiteId($siteId, $daily_consumption_masters_id);
            return response()->json($stock);
        } catch (\Exception $e) {
            \Log::error('Error fetching stock: '.$e->getMessage());
            return response()->json(['error'=>'Failed to fetch stock'],500);
        }
    }
}

