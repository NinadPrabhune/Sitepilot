<?php

namespace App\Http\Controllers;

use App\DataTables\DailyProgressReportDataTable;
use App\Models\DailyProgressReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Machinery;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Machinery\Services\MachineryRateService;
use App\Domain\Machinery\Services\DprCalculationService;
use App\Domain\Machinery\Services\FinancialPeriodService;
use App\Domain\Machinery\Services\InvariantLogger;
use App\Services\MeterReadingValidationService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use Illuminate\Support\Facades\Storage;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Services\StockService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DailyProgressReportController extends Controller
{
    use AuthorizesRequests;

    public function index(DailyProgressReportDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr manage')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            return $dataTable->render('daily-progress-reports.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load reports: '.$e->getMessage()]);
        }
    }
    
    public function getPreviousReading(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'machinery_id' => 'required|exists:machineries,id',
            'date' => 'required|date',
        ]);

        try {
            // Get the previous day's reading for the same machinery
            $previousReading = DailyProgressReport::where('machinery_id', $validated['machinery_id'])
                ->where('date', '<', $validated['date'])
                ->orderBy('date', 'desc')
                ->first();

            if ($previousReading) {
                return response()->json([
                    'success' => true,
                    'previous_reading' => [
                        'date' => $previousReading->date,
                        'end_reading' => $previousReading->machine_end_reading,
                        'start_reading' => $previousReading->machine_start_reading,
                        'working_hours' => $previousReading->working_hours ?? 0,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'previous_reading' => null,
                    'message' => 'No previous reading found'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch previous reading: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'machinery_id' => 'required|exists:machineries,id',
            'date' => 'required|date',
        ]);

        $existingDPR = DailyProgressReport::where('machinery_id', $validated['machinery_id'])
            ->where('date', $validated['date'])
            ->where(function($query) {
                $query->where('status', '!=', 'deleted')
                      ->orWhereNull('status');
            })
            ->first();

        return response()->json([
            'exists' => $existingDPR ? true : false,
            'dpr_id' => $existingDPR?->id,
            'machinery_name' => $existingDPR?->machinery?->name
        ]);
    }

    public function createdpr(Request $request, $activity_completed_id = null)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        // Get activity_completed_id from request if not in route
        if (!$activity_completed_id) {
            $activity_completed_id = $request->get('activity_completed_id');
        }

        // Check permissions
        if (!Auth::user()->isAbleTo('machinery-dpr create')) {
            \Log::warning('DailyProgressReportController@createdpr - Permission denied', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
                'user_type' => Auth::user()?->type,
                'required_permission' => 'machinery-dpr create'
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Permission denied. You do not have permission to create DPR.',
                    'required_permission' => 'machinery-dpr create'
                ], 403);
            }
            
            // If coming from activity context, provide a more specific error message
            if ($request->get('activity_id') || $activity_completed_id) {
                return back()->with('error', 'You do not have permission to create DPR. Please contact your administrator for "machinery-dpr create" permission.');
            }
            
            abort(403, 'Permission denied. You do not have permission to create DPR.');
        }

        try {
            
            // Get all machinery with needed fields
            $machineryList = Machinery::where('workspace_id', getActiveWorkSpace())
                ->where('site_id', getActiveProject())
                ->get()
                ->mapWithKeys(function ($m) {
                    return [
                        $m->id => [
                            'id' => $m->id,
                            'name' => $m->name,
                            'owned_by' => $m->owned_by,
                            'site_id' => $m->site_id,
                            'site_name' => $m->site?->name ?? 'N/A',
                            'rate' => $m->rate,
                            'rate_type' => $m->rate_type,
                            'minimum_billing_hours' => $m->minimum_billing_hours
                        ]
                    ];
                });
          
            $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name','id');
            $defaultSiteId = getActiveProject();

            // Load fuel materials with stock data for the default site
            $materials = [];
            if ($defaultSiteId) {
                $stockItems = getCurrentStockBySiteId($defaultSiteId, null, null, null, null, null, true);
                foreach ($stockItems as $item) {
                    // Only include category_id = 2 (fuels)
                    if ((int)$item->category_id === 2) {
                        $materials[$item->material_id] = [
                            'name'          => $item->material_name,
                            'unit'          => $item->unit_name,
                            'price'         => $item->material_price,
                            'total_qty'     => max(0, getStockQtyForConsumptionForm($defaultSiteId, $item->material_id)),
                            'category_id'   => $item->category_id,
                            'category_name' => $item->category_name,
                        ];
                    }
                }
            }

            return view('daily-progress-reports.create-new', compact('machineryList','materials','sites', 'activity_completed_id', 'defaultSiteId'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: '.$e->getMessage()]);
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
        if (!Auth::user()->isAbleTo('machinery-dpr create')) {
            \Log::warning('DailyProgressReportController@create - Permission denied', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
                'user_type' => Auth::user()?->type,
                'required_permission' => 'machinery-dpr create'
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Permission denied. You do not have permission to create DPR.',
                    'required_permission' => 'machinery-dpr create'
                ], 403);
            }
            
            abort(403, 'Permission denied. You do not have permission to create DPR.');
        }

        try {
            
            $machinery = Machinery::find($request->machinery_id);
            
            
//            dd($machinery);
            
            $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name','id');
            $defaultSiteId = getActiveProject();

            // Load fuel materials with stock data for the default site
            $materials = [];
            if ($defaultSiteId) {
                $stockItems = getCurrentStockBySiteId($defaultSiteId, null, null, null, null, null, true);
                foreach ($stockItems as $item) {
                    // Only include category_id = 2 (fuels)
                    if ((int)$item->category_id === 2) {
                        $materials[$item->material_id] = [
                            'name'          => $item->material_name,
                            'unit'          => $item->unit_name,
                            'price'         => $item->material_price,
                            'total_qty'     => max(0, getStockQtyForConsumptionForm($defaultSiteId, $item->material_id)),
                            'category_id'   => $item->category_id,
                            'category_name' => $item->category_name,
                        ];
                    }
                }
            }

            return view('daily-progress-reports.create', compact('machinery','materials','sites',));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form: '.$e->getMessage()]);
        }
    }
    
    
        
    public function store(Request $request)
{
    // STORE DEBUG: Check if store method is being called
    \Log::info('STORE DEBUG - Store Method Called:', [
        'request_method' => $request->method(),
        'request_all_keys' => array_keys($request->all()),
        'has_items' => $request->has('items'),
        'items_data' => $request->items,
        'user_id' => Auth::id()
    ]);
    
    if (!Auth::user()->isAbleTo('machinery-dpr create')) {
        \Log::info('STORE DEBUG - Permission denied for store');
        abort(403, 'Unauthorized action.');
    }

    \Log::info('STORE DEBUG - Permission passed, starting transaction');
    DB::beginTransaction();

    try {
        // Get machinery to check ownership type
        $machinery = Machinery::findOrFail($request->machinery_id);
        $isRental = $machinery->owned_by === 'rental';
        
        // ✅ Comprehensive validation rules
        $baseRules = [
            'date' => 'required|date|before_or_equal:today',
            'machinery_id' => 'required|exists:machineries,id',
            'machine_start_reading' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'machine_end_reading' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|gt:machine_start_reading',
            'machine_idle_reading' => 'nullable|numeric|min:0',
            'number_of_operators' => 'nullable|integer|min:1|max:50',
            'operator_names' => 'nullable|string|max:500',
            'work_details' => 'nullable|string|max:2000',
            'diesel_consumption' => 'nullable|numeric|min:0',
            'maintenance_notes' => 'nullable|string|max:1000',
            'machinery_advances' => 'nullable|string|max:500',
            'consumption_type' => 'required|in:fuel,all',
            'consumption_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];
        
        // Clean empty or incomplete consumption rows before validation
        $items = collect($request->input('items', []))->filter(function ($item) {
            return !empty($item['material_id']) && isset($item['quantity']) && floatval($item['quantity']) > 0;
        })->values()->all();
        $request->merge(['items' => $items]);

        $fuelRules = [
            'items' => 'nullable|array',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01|max:999999.99',
            'items.*.unit' => 'required|string|max:50',
            'items.*.remarks' => 'nullable|string|max:500',
        ];
        $rules = array_merge($baseRules, $fuelRules);
        
        $messages = [
            'date.before_or_equal' => 'Date cannot be in the future',
            'machine_end_reading.gt' => 'End reading must be greater than start reading',
            'number_of_operators.min' => 'Number of operators must be at least 1',
            'number_of_operators.max' => 'Number of operators cannot exceed 50',
            'items.*.quantity.max' => 'Quantity cannot exceed 999,999.99',
            'consumption_file.mimes' => 'File must be PDF, JPG, JPEG, or PNG',
            'consumption_file.max' => 'File size cannot exceed 2MB',
        ];
        
        $validated = $request->validate($rules, $messages);

        // STORE STEP 1: Debug - Check if items are being received after validation
        \Log::info('STORE STEP 1 - Items Debug:', [
            'is_rental' => $isRental,
            'items_received' => $validated['items'] ?? [],
            'items_count' => count($validated['items'] ?? []),
            'validated_keys' => array_keys($validated)
        ]);

        // Enhanced duplicate check for same machinery on same date
        $existingDPR = DailyProgressReport::where('machinery_id', $validated['machinery_id'])
            ->where('date', $validated['date'])
            ->where(function($query) {
                $query->where('status', '!=', 'deleted')
                      ->orWhereNull('status');
            })
            ->lockForUpdate() // Prevent race conditions
            ->first();

        if ($existingDPR) {
            $machineryName = $existingDPR->machinery->name ?? 'Unknown';
            \Log::info('STORE DEBUG - Duplicate DPR found', [
                'existing_dpr_id' => $existingDPR->id,
                'machinery_name' => $machineryName,
                'date' => $validated['date']
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate daily reading detected. A DPR for machinery '{$machineryName}' on {$validated['date']} already exists (DPR #{$existingDPR->id}). Please use the existing record or choose a different date."
                ], 409);
            }
            
            return back()->withErrors([
                'duplicate_dpr' => "Duplicate daily reading detected. A DPR for machinery '{$machineryName}' on {$validated['date']} already exists (DPR #{$existingDPR->id}). Please use the existing record or choose a different date."
            ])->withInput();
        }

        \Log::info('STORE DEBUG - Validation and duplicate check passed');

        // AUDIT-GRADE VALIDATIONS
        \Log::info('STORE DEBUG - About to find machinery' . PHP_EOL);
        $machinery = Machinery::findOrFail($validated['machinery_id']);
        \Log::info('STORE DEBUG - Found machinery: ' . $machinery->id . PHP_EOL);
        
        // 1. Meter Reading Validation
        \Log::info('STORE DEBUG - About to validate reading' . PHP_EOL);
        try {
            $readingValidation = MeterReadingValidationService::validateReading([
                'date' => $validated['date'],
                'machine_start_reading' => $request->machine_start_reading,
                'machine_end_reading' => $request->machine_end_reading,
                'machine_idle_reading' => $request->machine_idle_reading,
                'billable_hours' => null, // Will be calculated
            ], $machinery);
            \Log::info('STORE DEBUG - Reading validation result: ' . print_r($readingValidation, true) . PHP_EOL);
            $validValue = isset($readingValidation['valid']) ? var_export($readingValidation['valid'], true) : 'NULL/not set';
            \Log::info('STORE DEBUG - Reading validation completed, valid: ' . $validValue . PHP_EOL);
        } catch (\Exception $e) {
            \Log::error('STORE DEBUG - Exception in MeterReadingValidationService: ' . $e->getMessage() . PHP_EOL);
            \Log::error('STORE DEBUG - Trace: ' . $e->getTraceAsString() . PHP_EOL);
            throw $e;
        }
        
        if (!$readingValidation['valid']) {
            \Log::info('STORE DEBUG - Validation failed, errors: ' . print_r($readingValidation['errors'] ?? null, true) . PHP_EOL);
            // Check if it's a duplicate DPR error and show user-friendly message
            $errorMessages = $readingValidation['errors'] ?? [];
            $isDuplicateDPR = false;
            
            if (is_array($errorMessages)) {
                foreach ($errorMessages as $error) {
                    if (is_string($error) && (strpos(strtolower($error), 'already exists') !== false || strpos(strtolower($error), 'duplicate') !== false)) {
                        $isDuplicateDPR = true;
                        break;
                    }
                }
            }
            
            \Log::info('STORE DEBUG - Is duplicate DPR: ' . var_export($isDuplicateDPR, true) . PHP_EOL);
            
            if ($isDuplicateDPR) {
                // Show user-friendly duplicate DPR message
                \Log::info('STORE DEBUG - Returning duplicate DPR error' . PHP_EOL);
                
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => "A Daily Progress Report for this machinery on {$validated['date']} already exists. Please edit the existing report or choose a different date."
                    ], 409);
                }
                
                return back()->withErrors([
                    'duplicate_dpr' => "A Daily Progress Report for this machinery on {$validated['date']} already exists. Please edit the existing report or choose a different date."
                ])->withInput();
            } else {
                // Show other validation errors with more specific messaging
                \Log::info('STORE DEBUG - Throwing validation exception for errors: ' . implode(', ', $errorMessages) . PHP_EOL);
                $validationErrors = [];
                foreach ($errorMessages as $error) {
                    if (strpos($error, 'Start reading cannot be less than previous day') !== false) {
                        $validationErrors['machine_start_reading'] = $error;
                    } elseif (strpos($error, 'End reading cannot be less than previous day') !== false) {
                        $validationErrors['machine_end_reading'] = $error;
                    } elseif (strpos($error, 'End reading cannot be less than start reading') !== false) {
                        $validationErrors['machine_end_reading'] = $error;
                    } elseif (strpos($error, 'Idle hours cannot exceed working hours') !== false) {
                        $validationErrors['machine_idle_reading'] = $error;
                    } elseif (strpos($error, 'Date cannot be in the future') !== false) {
                        $validationErrors['date'] = $error;
                    } else {
                        $validationErrors['reading_validation'][] = $error;
                    }
                }
                throw ValidationException::withMessages($validationErrors);
            }
        }
        
        // 2. Financial Period Lock Check
        \Log::info('STORE DEBUG - About to check financial period lock' . PHP_EOL);
        $periodService = new FinancialPeriodService();
        try {
            $periodService->validatePeriodLock($validated['date']);
            \Log::info('STORE DEBUG - Financial period lock check passed' . PHP_EOL);
        } catch (\Exception $e) {
            \Log::error('STORE DEBUG - Financial period lock check failed: ' . $e->getMessage() . PHP_EOL);
            throw ValidationException::withMessages(['date' => $e->getMessage()]);
        }
        
        // 3. Historical Rate Lookup
        \Log::info('STORE DEBUG - About to get historical rate' . PHP_EOL);
        $rateService = new MachineryRateService();
        try {
            $historicalRate = $rateService->getRateForDate($machinery->id, $validated['date']);
            \Log::info('STORE DEBUG - Historical rate retrieved: ' . $historicalRate . PHP_EOL);
        } catch (\Exception $e) {
            \Log::error('STORE DEBUG - Historical rate lookup failed: ' . $e->getMessage() . PHP_EOL);
            throw ValidationException::withMessages(['date' => 'No rate available for this date']);
        }
        
        // 3. Overlap Prevention
        \Log::info('STORE DEBUG - About to check for overlap' . PHP_EOL);
        $existingDpr = DailyProgressReport::where('machinery_id', $machinery->id)
                                        ->where('date', $validated['date'])
                                        ->first();
        if ($existingDpr) {
            \Log::error('STORE DEBUG - Overlap prevention failed - existing DPR found: ' . $existingDpr->id . PHP_EOL);
            throw ValidationException::withMessages(['date' => 'DPR already exists for this machine on this date']);
        }
        \Log::info('STORE DEBUG - Overlap check passed' . PHP_EOL);
        
        // ✅ Insert DPR record with basic fields only
        \Log::info('STORE DEBUG - About to create DPR record' . PHP_EOL);
        $dpr = DailyProgressReport::create([
            'date' => $validated['date'],
            'machinery_id' => $validated['machinery_id'],
            'machine_start_reading' => $request->machine_start_reading,
            'machine_end_reading' => $request->machine_end_reading,
            'machine_idle_reading' => $request->machine_idle_reading,
            'number_of_operators' => $request->number_of_operators,
            'operator_names' => $request->operator_names,
            'work_details' => $request->work_details,
            'maintenance_notes' => $request->maintenance_notes,
            'activity_completed_id' => $request->activity_completed_id ?? null,
            'rate_snapshot' => $historicalRate,
            'created_by' => Auth::id(),
            'workspace_id' => getActiveWorkSpace(),
            'site_id' => $request->site_id ?? getActiveProject(),
        ]);
        \Log::info('STORE DEBUG - DPR record created with ID: ' . $dpr->id . PHP_EOL);
        
        // ✅ Calculate billable hours and credit amount based on rate type
        \Log::info('STORE DEBUG - About to calculate billable hours' . PHP_EOL);
        $billableHours = 0;
        $creditAmount = 0;
        
        if ($request->machine_start_reading && $request->machine_end_reading) {
            $workingHours = $request->machine_end_reading - $request->machine_start_reading;
            
            // Subtract idle hours if present
            if ($request->machine_idle_reading) {
                $workingHours -= $request->machine_idle_reading;
            }
            
            if ($workingHours < 0) {
                $workingHours = 0;
            }
            
            // Apply rate type logic according to machinery billing implementation plan
            switch ($machinery->rate_type) {
                case 'hourly':
                    // Hourly: Working Hours × Rate
                    $billableHours = $workingHours;
                    $creditAmount = $billableHours * $historicalRate;
                    break;
                    
                case 'daily':
                    // Daily: Any usage = Full day charge
                    if ($workingHours > 0) {
                        $minimumBillingHours = $machinery->minimum_billing_hours ?? 8;
                        $billableHours = $minimumBillingHours;
                        $creditAmount = $historicalRate; // Full day rate
                    } else {
                        $billableHours = 0;
                        $creditAmount = 0;
                    }
                    break;
                    
                case 'monthly':
                    // Monthly: Handled at month-end in payment requests
                    // For daily DPR, store working hours but no credit amount
                    $billableHours = $workingHours;
                    $creditAmount = 0; // Will be calculated in payment requests
                    break;
                    
                default:
                    // Fallback to hourly calculation
                    $billableHours = $workingHours;
                    $creditAmount = $billableHours * $historicalRate;
                    break;
            }
        }
        
        \Log::info('STORE DEBUG - Billable hours calculated: ' . $billableHours . ', Credit amount: ' . $creditAmount . PHP_EOL);
        
        // ✅ Store calculated amount
        \Log::info('STORE DEBUG - About to update DPR with calculated amounts' . PHP_EOL);
        $dpr->update([
            'billable_hours' => $billableHours,
            'calculated_amount' => $creditAmount,
        ]);
        \Log::info('STORE DEBUG - DPR updated with calculated amounts' . PHP_EOL);

        // ✅ Create ledger entry
        \Log::info('STORE DEBUG - About to create ledger entry' . PHP_EOL);
        try {
            $ledgerEntry = \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
                'workspace_id' => getActiveWorkSpace(),
                'machinery_id' => $machinery->id,
                'amount' => $creditAmount,
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
                'dpr_id' => $dpr->id,
                'entry_type' => 'reading',
                'description' => "DPR Credit - {$dpr->date}",
                'date' => $dpr->date,
                'idempotency_key' => "dpr_{$dpr->id}_credit",
            ]);
            
            \Log::info('Ledger entry created for DPR', [
                'dpr_id' => $dpr->id,
                'ledger_id' => $ledgerEntry->id ?? 'unknown',
                'amount' => $creditAmount,
            ]);
            
            // Update DPR with ledger entry ID,
            \Log::info('STORE DEBUG - About to update DPR with ledger entry ID' . PHP_EOL);
            $dpr->update(['ledger_entry_id' => $ledgerEntry->id]);
            \Log::info('STORE DEBUG - DPR updated with ledger entry ID' . PHP_EOL);
            
        } catch (\Exception $ledgerError) {
            // If ledger fails, log but don't fail the DPR creation
            \Log::error('Ledger creation failed for DPR', [
                'dpr_id' => $dpr->id,
                'error' => $ledgerError->getMessage(),
            ]);
        }
        
        // ✅ Handle consumption file upload using existing helper function
        \Log::info('STORE DEBUG - About to handle consumption file upload' . PHP_EOL);
        $consumptionFilePath = null;
        if ($request->hasFile('consumption_file')) {
            $filename = time() . '_dpr_' . $dpr->id . '.' . $request->file('consumption_file')->getClientOriginalExtension();
            $upload = upload_file($request, 'consumption_file', $filename, 'consumptions');
            if ($upload['flag'] == 1) {
                $consumptionFilePath = $upload['url'];
            } else {
                \Log::error('Consumption file upload failed', ['error' => $upload['msg'] ?? 'Unknown error']);
            }
        }
        
        if (!empty($validated['items'])) {
            $consumptionData = [
                'daily_progress_report_id' => $dpr->id,
                'consumption_date' => $validated['date'],
                'site_id'          => $request->site_id ?? getActiveProject(),
                'consumption_type' => $validated['consumption_type'],
                'machinery_type'   => $machinery->owned_by,
                'machinery_id'     => $validated['machinery_id'],
                'activity_completed_id' => $request->activity_completed_id ?? null,
                'items'            => $validated['items'],
                'consumption_file' => $consumptionFilePath,
                'InsertFrom'       => 'DailyProgressReportController',
            ];
            
            // STORE STEP 2: Debug - Consumption creation
            \Log::info('STORE STEP 2 - Consumption Creation Debug:', [
                'dpr_id' => $dpr->id,
                'is_rental' => $isRental,
                'items_count' => count($validated['items']),
                'consumption_data_items' => $validated['items']
            ]);
            
            try {
                $consumptionRequest = new \Illuminate\Http\Request($consumptionData);
                $consumptionController = app(\App\Http\Controllers\DailyConsumptionController::class);
                $consumptionController->store($consumptionRequest);
                \Log::info('STORE STEP 2.1 - DailyConsumptionController@store call completed' . PHP_EOL);
            } catch (\Exception $e) {
                \Log::error('STORE STEP 2.2 - DailyConsumptionController@store ERROR: ' . $e->getMessage() . PHP_EOL);
                throw $e;
            }
        } else {
            \Log::info('STORE STEP 2 - No fuel consumption items provided; skipping consumption creation.', [
                'dpr_id' => $dpr->id,
                'is_rental' => $isRental,
            ]);
        }
        
        \Log::info('STORE STEP 3 - DPR + Consumption + Ledger Created Successfully:', ['dpr_id' => $dpr->id]);
        
DB::commit();
        \Log::info('STORE DEBUG - Database transaction committed' . PHP_EOL);
        
        // Update success message based on machinery type and consumption status
        $hasConsumption = !empty($validated['items']);
        $successMessage = __('Daily Progress Report created successfully.');
        
        if ($hasConsumption) {
            $successMessage .= ' ' . __('(Fuel consumption recorded successfully)');
        } elseif ($isRental) {
            $successMessage .= ' ' . __('(Fuel consumption skipped - rental machinery supplier bears diesel costs)');
        } else {
            $successMessage .= ' ' . __('(No fuel consumption recorded)');
        }
        
        \Log::info('STORE DEBUG - About to return success response' . PHP_EOL);
        
        // Return JSON for AJAX requests, redirect for regular form submissions
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'dpr_id' => $dpr->id,
                'redirect' => route('daily-progress-reports.index')
            ]);
        }
        
return back()->with('success', $successMessage);


//        return redirect()->route('daily-progress-reports.index')->with('success', __('Daily Progress Report & Consumption created successfully.'));
         
     } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
           \Log::error('DPR Validation Failed', [
               'errors' => $e->validator->errors()->toArray(),
               'request' => $request->all(),
           ]);
           
           if ($request->expectsJson() || $request->ajax()) {
               return response()->json([
                   'success' => false,
                   'message' => 'Validation failed',
                   'errors' => $e->validator->errors()->toArray()
               ], 422);
           }
           
           return back()->withErrors($e->validator)->withInput();
       } catch (\Exception $e) {
           DB::rollBack();
           \Log::error('DPR Store Error: ' . $e->getMessage() . PHP_EOL);
           \Log::error('Stack trace: ' . $e->getTraceAsString() . PHP_EOL);
           \Log::error('Request data: ' . json_encode($request->all()) . PHP_EOL);
           
           if ($request->expectsJson() || $request->ajax()) {
               return response()->json([
                   'success' => false,
                   'message' => 'Failed to save daily progress report',
                   'error' => $e->getMessage()
               ], 500);
           }
           
           return back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
       }
}
    
    /**
     * Generate calculation hash for integrity validation
     */
    private function generateCalculationHash($dprData, $rate): string
    {
        $data = [
            'start' => $dprData['machine_start_reading'] ?? 0,
            'end' => $dprData['machine_end_reading'] ?? 0,
            'idle' => $dprData['machine_idle_reading'] ?? 0,
            'rate_snapshot' => $rate,
        ];
        
        return hash('sha256', json_encode($data));
    }

//    public function store(Request $request)
//    {
//        if (!Auth::user()->isAbleTo('machinery-dpr create')) {
//            abort(403, 'Unauthorized action.');
//        }
//
//        try {
//            $request->validate([
//                'date' => 'required|date',
//                'machinery_id' => 'required|exists:machineries,id',
//                'machine_start_reading' => 'nullable|integer',
//                'machine_end_reading' => 'nullable|integer',
//                'number_of_operators' => 'nullable|integer',
//                'work_details' => 'nullable|string',
//                'diesel_consumption' => 'nullable|numeric',
//                'maintenance_notes' => 'nullable|string',
//                'machinery_advances' => 'nullable|string',
//               
//            ]);
//
//            DailyProgressReport::create([
//                'date' => $request->date,
//                'machinery_id' => $request->machinery_id,
//                'machine_start_reading' => $request->machine_start_reading,
//                'machine_end_reading' => $request->machine_end_reading,
//                'number_of_operators' => $request->number_of_operators,
//                'work_details' => $request->work_details,
//                'diesel_consumption' => $request->diesel_consumption,
//                'maintenance_notes' => $request->maintenance_notes,
//                'machinery_advances' => $request->machinery_advances,
//                'created_by' => Auth::id(),
//                'workspace_id' => getActiveWorkSpace(),
//                'site_id' => getActiveProject(),
//            ]);
//            
//
//            return redirect()->route('daily-progress-reports.index')
//                ->with('success', __('Daily Progress Report created successfully.'));
//        } catch (\Exception $e) {
//            return back()->withErrors(['error' => 'Something went wrong: '.$e->getMessage()]);
//        }
//    }

    public function show($id)
    {
        // ✅ POLICY-BASED AUTHORIZATION
        $dpr = DailyProgressReport::findOrFail($id);
        $this->authorize('view', $dpr);

    try {
        // Eager load machinery, consumption master, and details with material + unit
        $report = DailyProgressReport::with([
            'machinery',
            'consumptionMaster.details.material.unit'
        ])->findOrFail($id);

        return view('daily-progress-reports.show', compact('report'));
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->route('daily-progress-reports.index')
            ->with('error', 'Daily Progress Report not found.');
    } catch (\Exception $e) {
        \Log::error('Error loading DPR show view: '.$e->getMessage());
        return back()->withErrors(['error' => 'Unable to load report: '.$e->getMessage()]);
    }
}

    
    public function edit($id)
    {
        // ✅ POLICY-BASED AUTHORIZATION
        $dpr = DailyProgressReport::findOrFail($id);
        $this->authorize('update', $dpr);

    try {
        $report = DailyProgressReport::with(['machinery', 'items.material.unit','consumptionMaster.details.material.unit'])->findOrFail($id);

//        dd($report);
        $sites = Project::pluck('name', 'id');
        $machinery = $report->machinery;

        $materials = [];
        $consumptionMasterId = $report->consumptionMaster?->id;

        if ($report->site_id) {
            $stockItems = getCurrentStockBySiteId(
                $report->site_id,
                $consumptionMasterId,
                null,
                null,
                null,
                null,
                true
            );

            foreach ($stockItems as $item) {
                // Only include category_id = 2 (fuels)
                if ((int)$item->category_id === 2) {
                    $formQty = getStockQtyForConsumptionForm(
                        $report->site_id,
                        $item->material_id,
                        $consumptionMasterId
                    );
                    $materials[$item->material_id] = [
                        'name'          => $item->material_name,
                        'unit'          => $item->unit_name,
                        'price'         => $item->material_price,
                        'total_qty'     => max(0, $formQty),
                        'category_id'   => $item->category_id,
                        'category_name' => $item->category_name,
                    ];
                }
            }
            // #region agent log
            @file_put_contents(base_path('debug-75ca7e.log'), json_encode(['sessionId' => '75ca7e', 'runId' => 'dpr-edit', 'hypothesisId' => 'H7', 'location' => 'DailyProgressReportController@edit', 'message' => 'DPR edit fuel stock', 'data' => ['dpr_id' => $report->id, 'consumption_master_id' => $consumptionMasterId, 'site_id' => $report->site_id, 'materials' => $materials], 'timestamp' => (int) round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
            // #endregion
        }
        
        

        $isRental = $machinery->owned_by === 'rental';
        return view('daily-progress-reports.edit', compact('report', 'sites', 'machinery', 'materials', 'isRental', 'consumptionMasterId'));
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->route('daily-progress-reports.index')
            ->with('error', 'Daily Progress Report not found.');
    } catch (\Exception $e) {
        \Log::error('Error loading DPR edit form: '.$e->getMessage());
        return back()->withErrors(['error' => 'Unable to load edit form.']);
    }
}




//    public function edit($id)
//    {
//        if (!Auth::user()->isAbleTo('machinery-dpr edit')) {
//            abort(403, 'Unauthorized action.');
//        }
//
//        try {
//          $report = DailyProgressReport::with('machinery')->findOrFail($id);
//
//            
//            
//            return view('daily-progress-reports.edit', compact('report'));
//        } catch (\Exception $e) {
//            return back()->withErrors(['error' => 'Unable to load edit form: '.$e->getMessage()]);
//        }
//    }

public function update(Request $request, $id)
{
    // DEBUG: Check if update method is being called
    \Log::info('DEBUG - Update Method Called:', [
        'dpr_id' => $id,
        'request_method' => $request->method(),
        'request_all_keys' => array_keys($request->all()),
        'has_items' => $request->has('items'),
        'items_data' => $request->items,
        'user_id' => Auth::id()
    ]);

    if (!Auth::user()->isAbleTo('machinery-dpr edit')) {
        \Log::info('DEBUG - Permission denied for update');
        abort(403, 'Unauthorized action.');
    }

    \Log::info('DEBUG - Permission passed, starting transaction');
    DB::beginTransaction();

    try {
        // Get machinery to check ownership type
        $machinery = Machinery::findOrFail($request->machinery_id);
        $isRental = $machinery->owned_by === 'rental';
        
        // ✅ Validation rules
        $baseRules = [
            'date' => 'required|date',
            'machinery_id' => 'required|exists:machineries,id',
            'machine_start_reading' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'machine_end_reading' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|gt:machine_start_reading',
            'number_of_operators' => 'nullable|integer',
            'work_details' => 'nullable|string',
            'diesel_consumption' => 'nullable|numeric',
            'maintenance_notes' => 'nullable|string',
            'machinery_advances' => 'nullable|string',
            'consumption_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];
        
    // Clean empty or incomplete consumption rows before validation
        $items = collect($request->input('items', []))->filter(function ($item) {
            return !empty($item['material_id']) && isset($item['quantity']) && floatval($item['quantity']) > 0;
        })->values()->all();
        $request->merge(['items' => $items]);

        $fuelRules = [
            'items' => 'nullable|array',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.remarks' => 'nullable|string',
        ];
        $rules = array_merge($baseRules, $fuelRules);

        $messages = [
            'machine_end_reading.gt' => 'End reading must be greater than start reading',
            'consumption_file.mimes' => 'File must be PDF, JPG, JPEG, or PNG',
            'consumption_file.max' => 'File size cannot exceed 2MB',
        ];

        $validated = $request->validate($rules, $messages);

        // STEP 1: Debug - Check if items are being received
        \Log::info('STEP 1 - DPR Update - Items Debug:', [
            'dpr_id' => $id,
            'is_rental' => $isRental,
            'items_received' => $request->items,
            'items_count' => count($request->items ?? []),
            'all_request_data_keys' => array_keys($request->all())
        ]);

        // ✅ Find DPR with pessimistic locking to prevent concurrent edits
        $report = DailyProgressReport::where('id', $id)->lockForUpdate()->first();
        if (!$report) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Daily Progress Report not found');
        }
        
        // ✅ Check financial period locking - prevent edits on closed periods (optional until migrations run)
        $dprDate = \DateTime::createFromFormat('Y-m-d', $report->date);
        if (!$dprDate) {
            // Fallback to Carbon if DateTime fails
            $dprDate = new \DateTime($report->date);
        }
        
        // Only check financial period locking if the table exists
        try {
            if (!\App\Domain\Machinery\Services\FinancialPeriodLockingService::canEditDPR($report->id, $dprDate)) {
                $lockedPeriod = \App\Domain\Machinery\Services\FinancialPeriodLockingService::getLockedPeriodForDate($dprDate);
                return back()->with('error', 'Cannot edit DPR: Financial period "' . ($lockedPeriod->period_name ?? 'Unknown') . '" is locked. Contact administrator for adjustments.');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Table doesn't exist yet - skip financial period check
            \Log::info('Financial period locking table not found, skipping check', ['error' => $e->getMessage()]);
        }

        // ✅ Handle file upload for ConsumptionMaster using existing helper function
        $newFileUploaded = false;
        $oldFilePath = $report->consumptionMaster?->consumption_file;
        
        if ($request->hasFile('consumption_file')) {
            $newFileUploaded = true;
            // Delete old file if exists
            if ($oldFilePath && check_file($oldFilePath)) {
                delete_file($oldFilePath);
            }
            
            $filename = time() . '_dpr_' . $report->dpr_number . '.' . $request->file('consumption_file')->getClientOriginalExtension();
            $upload = upload_file($request, 'consumption_file', $filename, 'consumptions');
            if ($upload['flag'] == 1) {
                $consumptionFilePath = $upload['url'];
            } else {
                \Log::error('Consumption file upload failed', ['error' => $upload['msg'] ?? 'Unknown error']);
                $consumptionFilePath = $oldFilePath;
            }
        } else {
            $consumptionFilePath = $oldFilePath;
        }

        // ✅ Update DPR (without file - file is stored in ConsumptionMaster)
        $report->update([
            'date' => $validated['date'],
            'machinery_id' => $validated['machinery_id'],
            'machine_start_reading' => $validated['machine_start_reading'] ?? null,
            'machine_end_reading' => $validated['machine_end_reading'] ?? null,
            'number_of_operators' => $validated['number_of_operators'] ?? null,
            'work_details' => $validated['work_details'] ?? null,
            'diesel_consumption' => $validated['diesel_consumption'] ?? null,
            'maintenance_notes' => $validated['maintenance_notes'] ?? null,
            'machinery_advances' => $validated['machinery_advances'] ?? null,
            'activity_id' => $request->activity_id ?? $report->activity_id,
        ]);

        // ✅ Calculate billable hours and credit amount based on rate type
        $billableHours = 0;
        $creditAmount = 0;
        
        if ($validated['machine_start_reading'] && $validated['machine_end_reading']) {
            $workingHours = $validated['machine_end_reading'] - $validated['machine_start_reading'];
            
            // Subtract idle hours if present
            if (isset($validated['machine_idle_reading']) && $validated['machine_idle_reading']) {
                $workingHours -= $validated['machine_idle_reading'];
            }
            
            if ($workingHours < 0) {
                $workingHours = 0;
            }
            
            // Apply rate type logic according to machinery billing implementation plan
            switch ($machinery->rate_type) {
                case 'hourly':
                    // Hourly: Working Hours × Rate
                    $billableHours = $workingHours;
                    $creditAmount = $billableHours * $machinery->rate;
                    break;
                    
                case 'daily':
                    // Daily: Any usage = Full day charge
                    if ($workingHours > 0) {
                        $minimumBillingHours = $machinery->minimum_billing_hours ?? 8;
                        $billableHours = $minimumBillingHours;
                        $creditAmount = $machinery->rate; // Full day rate
                    } else {
                        $billableHours = 0;
                        $creditAmount = 0;
                    }
                    break;
                    
                case 'monthly':
                    // Monthly: Handled at month-end in payment requests
                    // For daily DPR, store working hours but no credit amount
                    $billableHours = $workingHours;
                    $creditAmount = 0; // Will be calculated in payment requests
                    break;
                    
                default:
                    // Fallback to hourly calculation
                    $billableHours = $workingHours;
                    $creditAmount = $billableHours * $machinery->rate;
                    break;
            }
        }

        // ✅ Update calculated amount
        $report->update([
            'billable_hours' => $billableHours,
            'calculated_amount' => $creditAmount,
        ]);

        // STEP 2: Debug - Consumption Master processing
        \Log::info('STEP 2 - Consumption Master Debug:', [
            'dpr_id' => $report->id,
            'existing_master_exists' => !!$report->consumptionMaster,
            'master_id' => $report->consumptionMaster?->id,
            'validated_items_count' => count($validated['items'] ?? [])
        ]);

        // ✅ Update or create Consumption Master
        $master = $report->consumptionMaster;
        if (!$master) {
            \Log::info('STEP 2.1 - Creating new consumption master');
            $master = DailyConsumptionMaster::create([
                'daily_progress_report_id' => $report->id,
                'consumption_type' => 'fuel', // adjust if needed
                'machinery_id' => $report->machinery_id,
                'consumption_date' => $report->date,
                'workspace_id' => $report->workspace_id,
                'created_by' => auth()->id(),
                'status' => 1,
                'consumption_file' => $validated['consumption_file'] ?? null,
            ]);
            \Log::info('STEP 2.2 - New consumption master created', ['master_id' => $master->id]);
        } else {
            // Debug: Check the current state
            \Log::info('Consumption master state check', [
                'master_id' => $master->id,
                'ledger_entry_id' => $master->ledger_entry_id,
                'ledger_entry_id_type' => gettype($master->ledger_entry_id),
                'ledger_entry_id_is_null' => is_null($master->ledger_entry_id),
                'ledger_entry_id_is_empty' => empty($master->ledger_entry_id),
                'new_file_uploaded' => $newFileUploaded
            ]);
            
            // Allow file updates even when ledger entry exists (files don't affect financial integrity)
            // But skip other updates to avoid business rule violations
            if ($newFileUploaded) {
                \Log::info('Updating consumption file (file upload allowed even with ledger entry)', [
                    'master_id' => $master->id,
                    'ledger_entry_id' => $master->ledger_entry_id,
                    'new_file_path' => $consumptionFilePath
                ]);
                
                // Only update the file - don't touch other fields to avoid business rule violations
                $master->update(['consumption_file' => $consumptionFilePath]);
            } elseif (!$master->ledger_entry_id) {
                // Only update other fields if no ledger entry exists
                \Log::info('Updating consumption master (no ledger entry)', [
                    'master_id' => $master->id,
                    'ledger_entry_id' => $master->ledger_entry_id
                ]);
                
                $updateData = [
                    'machinery_id' => $report->machinery_id,
                    'consumption_date' => $report->date,
                    'version' => DB::raw('version + 1')
                ];
                
                $master->update($updateData);
            } else {
                \Log::info('Skipping consumption master updates (ledger entry exists, no file upload)', [
                    'master_id' => $master->id,
                    'ledger_entry_id' => $master->ledger_entry_id
                ]);
            }
        }

        // Only create consumption ledger entry for owned machinery
        if (!$isRental && !$report->ledger_entry_id && !empty($validated['items'])) {
            $machinery = $report->machinery;
            $grossAmount = 0;
            foreach ($request->items ?? [] as $item) {
                // Get material price from database
                $material = \App\Models\Material::find($item['material_id']);
                $price = $material->price ?? 0;
                $grossAmount += $item['quantity'] * $price;
            }
            // Store material rates for future corrections
            $materialRates = [];
            foreach ($request->items ?? [] as $item) {
                $material = \App\Models\Material::find($item['material_id']);
                $materialRates[$item['material_id']] = $material->price ?? 0;
            }
            
            $ledgerEntry = MachineryLedgerService::createCredit([
                'machinery_id' => $machinery->id,
                'amount' => $grossAmount,
                'reference_type' => MachineryLedgerService::REFERENCE_TYPE_DPR,
                'reference_id' => $report->id,
                'entry_type' => MachineryLedgerService::ENTRY_TYPE_READING,
                'date' => $report->date,
                'description' => "DPR #{$report->dpr_number}",
                'metadata' => [
                    'dpr_number' => $report->dpr_number,
                    'site_id' => $report->site_id,
                    'material_rates' => $materialRates,
                ],
            ]);

            // Hard enforcement: verify ledger amount matches calculated amount
            if (!$ledgerEntry) {
                throw new \RuntimeException("Failed to create ledger entry for DPR #{$report->dpr_number}. Please check system configuration.");
            }
            
            if (abs($ledgerEntry->amount - $grossAmount) > 0.01) {
                throw new \RuntimeException("Ledger enforcement failed: Amount mismatch. Calculated ₹{$grossAmount} vs Ledger ₹{$ledgerEntry->amount}. Cannot proceed.");
            }

            // Link ledger entry to report
            $report->update(['ledger_entry_id' => $ledgerEntry->id]);
        } elseif ($isRental) {
            // Skip consumption ledger creation for rental machinery
            \Log::info('Skipping consumption ledger creation for rental machinery - Supplier bears diesel costs', [
                'dpr_id' => $report->id,
                'machinery_id' => $machinery->id
            ]);
        }

        // ✅ Calculate old vs new quantities for ledger correction (only for owned machinery)
        if (!$isRental && $master) {
            $oldQuantities = $master->details()->pluck('quantity', 'material_id')->toArray();
            $newQuantities = collect($request->items ?? [])->pluck('quantity', 'material_id')->toArray();
        } else {
            // For rental machinery, still process items for consumption tracking (but no ledger)
            if ($master) {
                $oldQuantities = $master->details()->pluck('quantity', 'material_id')->toArray();
                $newQuantities = collect($request->items ?? [])->pluck('quantity', 'material_id')->toArray();
            } else {
                $oldQuantities = [];
                $newQuantities = collect($request->items ?? [])->pluck('quantity', 'material_id')->toArray();
            }
        }

        // STEP 3: Debug - Quantity calculation and comparison
        \Log::info('STEP 3 - Quantity Calculation Debug:', [
            'dpr_id' => $report->id,
            'is_rental' => $isRental,
            'master_exists' => !!$master,
            'old_quantities' => $oldQuantities,
            'new_quantities' => $newQuantities,
            'request_items_raw' => $request->items ?? []
        ]);
        
        // ✅ Check if quantities actually changed
        $quantitiesChanged = false;
        foreach ($newQuantities as $materialId => $newQuantity) {
            $oldQuantity = $oldQuantities[$materialId] ?? 0;
            if (abs($oldQuantity - $newQuantity) > 0.01) {
                $quantitiesChanged = true;
                break;
            }
        }
        
        // ✅ Only replace details if quantities changed or materials changed
        $materialsChanged = false;
        $oldMaterialIds = array_keys($oldQuantities);
        $newMaterialIds = array_keys($newQuantities);
        if (count(array_diff($oldMaterialIds, $newMaterialIds)) > 0 || count(array_diff($newMaterialIds, $oldMaterialIds)) > 0) {
            $materialsChanged = true;
        }
        
        \Log::info('DPR update analysis', [
            'dpr_id' => $report->id,
            'quantities_changed' => $quantitiesChanged,
            'materials_changed' => $materialsChanged,
            'file_uploaded' => $newFileUploaded,
            'old_quantities' => $oldQuantities,
            'new_quantities' => $newQuantities
        ]);
        
        // ✅ Replace details if quantities/materials changed OR for rental machinery with items
        $shouldSaveItems = ($quantitiesChanged || $materialsChanged) || ($isRental && !empty($request->items));
        
        // STEP 4: Debug - Save condition and actual item saving
        \Log::info('STEP 4 - Save Condition Debug:', [
            'dpr_id' => $report->id,
            'quantities_changed' => $quantitiesChanged,
            'materials_changed' => $materialsChanged,
            'is_rental' => $isRental,
            'request_items_not_empty' => !empty($request->items),
            'should_save_items' => $shouldSaveItems,
            'save_reason' => $isRental ? 'rental machinery with items' : ($quantitiesChanged ? 'quantities changed' : 'materials changed')
        ]);
        
        if ($shouldSaveItems) {
            \Log::info('STEP 4.1 - Saving consumption details', [
                'reason' => $isRental ? 'rental machinery with items' : 'quantities/materials changed',
                'is_rental' => $isRental,
                'items_count' => count($request->items ?? []),
                'items_to_save' => $request->items ?? []
            ]);
            
            $master->details()->delete();
            \Log::info('STEP 4.2 - Old details deleted');
            
            foreach ($request->items ?? [] as $index => $item) {
                \Log::info('STEP 4.3 - Creating detail item', [
                    'index' => $index,
                    'item_data' => $item
                ]);
                
                $detail = $master->details()->create([
                    'material_id' => $item['material_id'],
                    'quantity'    => $item['quantity'],
                    'unit'        => $item['unit'],
                    'remarks'     => $item['remarks'] ?? null,
                ]);

                \Log::info('STEP 4.4 - Detail item created', [
                    'detail_id' => $detail->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity']
                ]);
            }

            // ✅ Adjust stock for owned machinery (non-rental)
            if (!$isRental && $master) {
                $stockService = app(StockService::class);
                $siteId = $report->site_id;

                foreach ($newQuantities as $materialId => $newQuantity) {
                    $oldQuantity = $oldQuantities[$materialId] ?? 0;
                    $quantityDiff = $newQuantity - $oldQuantity;

                    if (abs($quantityDiff) > 0.01) {
                        // #region agent log
                        @file_put_contents(base_path('debug-75ca7e.log'), json_encode(['sessionId' => '75ca7e', 'hypothesisId' => 'H3', 'location' => 'DailyProgressReportController@update', 'message' => 'DPR stock adjustment', 'data' => ['dpr_id' => $report->id, 'material_id' => $materialId, 'oldQty' => $oldQuantity, 'newQty' => $newQuantity, 'diff' => $quantityDiff, 'receiveMaterialExists' => method_exists($stockService, 'receiveMaterial')], 'timestamp' => (int) round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
                        // #endregion
                        try {
                            if ($quantityDiff > 0) {
                                // New quantity is higher - deduct additional stock
                                $stockService->issueMaterial(
                                    $siteId,
                                    $materialId,
                                    $quantityDiff,
                                    "DPR #{$report->id} update - additional consumption",
                                    'dpr',
                                    $report->id
                                );
                                \Log::info('Stock deducted on DPR update', [
                                    'dpr_id' => $report->id,
                                    'material_id' => $materialId,
                                    'quantity' => $quantityDiff
                                ]);
                            } else {
                                // New quantity is lower - return stock
                                $stockService->receiveMaterial(
                                    $siteId,
                                    $materialId,
                                    abs($quantityDiff),
                                    "DPR #{$report->id} update - stock returned",
                                    'dpr',
                                    $report->id
                                );
                                \Log::info('Stock returned on DPR update', [
                                    'dpr_id' => $report->id,
                                    'material_id' => $materialId,
                                    'quantity' => abs($quantityDiff)
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Stock adjustment failed on DPR update', [
                                'dpr_id' => $report->id,
                                'material_id' => $materialId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        } else {
            \Log::info('STEP 4.5 - Skipping consumption details recreation - no changes detected');
        }
        
        // ✅ Handle ledger corrections for quantity changes (inside transaction)
        if ($report->ledger_entry_id && ($quantitiesChanged || $materialsChanged)) {
            // Check if ledger entry actually exists
            $ledgerEntryExists = DB::table('machinery_ledger')->where('id', $report->ledger_entry_id)->exists();
            
            if (!$ledgerEntryExists) {
                \Log::warning('Ledger entry reference is invalid, clearing reference', [
                    'dpr_id' => $report->id,
                    'invalid_ledger_entry_id' => $report->ledger_entry_id
                ]);
                
                // Clear the invalid reference
                $report->update(['ledger_entry_id' => null]);
                
                // Skip ledger corrections since no valid ledger entry exists
                \Log::info('Skipping ledger corrections - no valid ledger entry found');
            } else {
                foreach ($newQuantities as $materialId => $newQuantity) {
                    $oldQuantity = $oldQuantities[$materialId] ?? 0;
                    
                    if (abs($oldQuantity - $newQuantity) > 0.01) {
                        // Get original rate from ledger entry for consistency
                        $originalLedger = DB::table('machinery_ledger')->where('id', $report->ledger_entry_id)->first();
                        
                        \Log::info('Ledger correction attempt', [
                            'dpr_id' => $report->id,
                            'ledger_entry_id' => $report->ledger_entry_id,
                            'material_id' => $materialId,
                            'old_quantity' => $oldQuantity,
                            'new_quantity' => $newQuantity,
                            'original_ledger_found' => $originalLedger ? 'Yes' : 'No',
                            'original_ledger_amount' => $originalLedger ? $originalLedger->amount : 'null'
                        ]);
                        
                        if (!$originalLedger) {
                            \Log::error('Original ledger entry not found', [
                                'ledger_entry_id' => $report->ledger_entry_id
                            ]);
                            continue; // Skip this material and continue with others
                        }
                        
                        $rate = $originalLedger->metadata['original_rate'] ?? ($oldQuantity > 0 ? $originalLedger->amount / $oldQuantity : 0);
                        
                        if ($rate > 0) {
                            // No graceful degradation - ledger correction must succeed
                            $correction = \App\Domain\Machinery\Services\LedgerCorrectionService::createQuantityCorrection(
                                $report->ledger_entry_id,
                                $newQuantity,
                                $rate,
                                [
                                    'dpr_id' => $report->id,
                                    'material_id' => $materialId,
                                    'site_id' => $report->site_id,
                                    'expected_old_quantity' => $oldQuantity
                                ],
                                \App\Domain\Machinery\Services\LedgerCorrectionService::CORRECTION_REASON_QUANTITY_EDIT
                            );
                            
                            \Log::info('Ledger correction created for DPR update', [
                                'dpr_id' => $report->id,
                                'material_id' => $materialId,
                                'old_quantity' => $oldQuantity,
                                'new_quantity' => $newQuantity,
                                'reversal_entry_id' => $correction['reversal_entry']->id ?? null,
                                'correction_entry_id' => $correction['correction_entry']->id ?? null
                            ]);
                        }
                    }
                }
            }
        }

        DB::commit();

        // STEP 5: Debug - Transaction commit
        \Log::info('STEP 5 - Transaction Commit Debug:', [
            'dpr_id' => $report->id,
            'transaction_status' => 'about_to_commit',
            'final_master_details_count' => $master ? $master->details()->count() : 0
        ]);

        \Log::info('STEP 5.1 - Transaction Committed Successfully', [
            'dpr_id' => $report->id,
            'final_master_details_count' => $master ? $master->details()->count() : 0
        ]);

        return back()->with('success', __('Daily Progress Report & Consumption updated successfully.'));
        
//        return redirect()->route('daily-progress-reports.index')
//            ->with('success', __('Daily Progress Report updated successfully.'));
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error updating DPR: '.$e->getMessage());
        return back()->withErrors(['error' => 'Update failed: '.$e->getMessage()]);
    }
}






//    public function update(Request $request, $id)
//    {
//        if (!Auth::user()->isAbleTo('machinery-dpr edit')) {
//            abort(403, 'Unauthorized action.');
//        }
//
//        try {
//            $request->validate([
//                'date' => 'required|date',
//                'machinery_id' => 'required|exists:machineries,id',
//                'machine_start_reading' => 'nullable|integer',
//                'machine_end_reading' => 'nullable|integer',
//                'number_of_operators' => 'nullable|integer',
//                'work_details' => 'nullable|string',
//                'diesel_consumption' => 'nullable|numeric',
//                'maintenance_notes' => 'nullable|string',
//                'machinery_advances' => 'nullable|string',
//                
//            ]);
////            dd($request->all());
//            $report = DailyProgressReport::findOrFail($id);
//
//            $report->update([
//                'date' => $request->date,
//                'machinery_id' => $request->machinery_id,
//                'machine_start_reading' => $request->machine_start_reading,
//                'machine_end_reading' => $request->machine_end_reading,
//                'number_of_operators' => $request->number_of_operators,
//                'work_details' => $request->work_details,
//                'diesel_consumption' => $request->diesel_consumption,
//                'maintenance_notes' => $request->maintenance_notes,
//                'machinery_advances' => $request->machinery_advances,
//            ]);
//
//            return redirect()->route('daily-progress-reports.index')
//                ->with('success', __('Daily Progress Report updated successfully.'));
//        } catch (\Exception $e) {
//            return back()->with('error', 'Error updating record: ' . $e->getMessage());
//        }
//    }

    public function destroy(DailyProgressReport $report)
    {
        if (!Auth::user()->isAbleTo('machinery-dpr delete')) {
            abort(403, 'Permission denied.');
        }
        DB::beginTransaction();

        try {
            // Period lock guard
            if (\App\Domain\Machinery\Models\MachineryPaymentPeriod::isDateLocked($report->machinery_id, $report->date->format('Y-m-d'))) {
                throw new \RuntimeException("Cannot delete DPR #{$report->id} because the date is within a locked period.");
            }

            // Orphan prevention: block delete if ledger entry exists
            if ($report->ledger_entry_id) {
                throw new \RuntimeException("Cannot delete DPR #{$report->id} because it has a linked ledger entry. Use reversal to remove the financial impact first.");
            }

            $report->delete();

            event(new DailyProgressReportDeleted($report));

            return redirect()->route('daily-progress-reports.index')
                ->with('success', 'Daily Progress Report deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting record: ' . $e->getMessage());
        }
    }
}
