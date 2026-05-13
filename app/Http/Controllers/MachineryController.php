<?php

namespace App\Http\Controllers;

use App\DataTables\MachineryDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Machinery;
use App\Events\CreateMachinery;
use App\Events\DestroyMachinery;
use App\Events\UpdateMachinery;
use App\Models\MachineryCategory;
use Illuminate\Support\Facades\Storage;

class MachineryController extends Controller {

    /**
     * Display a listing of the resource.
     */
    public function index(MachineryDataTable $dataTable) {
        if (\Auth::user()->isAbleTo('machinery manage')) {
            return $dataTable->render('machineries.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        if (\Auth::user()->isAbleTo('machinery manage')) {

            $categories = \App\Models\MachineryCategory::pluck('name', 'id');
            $customFields = null;
            $suppliers = [];
            $suppliers = \App\Models\Supplier::pluck('name', 'id');

            $sites = getAllSitesWithWorkspace();

            return view('machineries.create', compact('customFields', 'categories', 'suppliers', 'sites'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }



        // If you're using Blade views:
        // return view('machineries.create');
        return response()->json(['message' => 'Display machinery creation form']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {

        if (\Auth::user()->isAbleTo('machinery manage')) {
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|integer|exists:machinery_categories,id',
                'model_number' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'purchase_date' => 'nullable|date',
                'capacity' => 'nullable|string|max:255',
                'maintenance_schedule' => 'nullable|date',
                'remarks' => 'nullable|string',
                'description' => 'nullable|string',
                'vehicle_number' => 'required|string|max:255',
            'owned_by' => 'required|in:owned,rental',
            'supplier_id' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental|nullable|exists:suppliers,id',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'nullable|integer',
                'workspace_id' => 'integer',
                'created_by' => 'integer',
                'status' => 'nullable|string|max:50',
                // Rental fields
                'rate_type' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'rate' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'minimum_billing_hours' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'diesel_by_company' => 'nullable|prohibited_unless:owned_by,rental',
                'operator_by_supplier' => 'nullable|prohibited_unless:owned_by,rental',
                'number_of_operators' => 'required_if:operator_by_supplier,1|prohibited_unless:owned_by,rental|nullable|integer|min:1',
                'rental_agreement_file' => 'nullable|mimes:pdf,doc,docx|max:10240|prohibited_unless:owned_by,rental',
                // Owned fields
                'purchase_value' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'insurance_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'puc_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'fitness_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'last_service_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'maintenance_schedule' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'ownership_documents_file' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240|prohibited_unless:owned_by,owned',
            ]);

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $machinery = new Machinery();
            $machinery->name = $request->name;
            $machinery->category_id = $request->category_id;
            $machinery->model_number = $request->model_number;
            $machinery->manufacturer = $request->manufacturer;
            $machinery->purchase_date = $request->purchase_date;
            $machinery->capacity = $request->capacity;
            $machinery->maintenance_schedule = $request->maintenance_schedule;
            $machinery->remarks = $request->remarks;
            $machinery->description = $request->description;
            $machinery->vehicle_number = $request->vehicle_number;
        $machinery->owned_by = $request->owned_by;
        $machinery->supplier_id = $request->owned_by === 'rental' ? $request->supplier_id : null;
            $machinery->operational_status = $request->operational_status;
            $machinery->site_id = $request->site_id;
            $machinery->workspace_id = getWorkspaceIDFromSiteID($request->site_id);
            $machinery->created_by = creatorId();
            $machinery->status = $request->status ?? '0';

            // Rental fields - Company Policy: diesel_by_company false, operator_by_supplier true
            // This ensures supplier bears diesel costs and provides operators for all rental machinery
            $machinery->rate_type = $request->rate_type;
            $machinery->rate = $request->rate;
            $machinery->minimum_billing_hours = $request->minimum_billing_hours;
            $machinery->diesel_by_company = false; // Company Policy: Always false - supplier bears diesel cost
            $machinery->operator_by_supplier = true; // Company Policy: Always true - supplier provides operators
            $machinery->number_of_operators = $request->number_of_operators;

            // Owned fields
            $machinery->purchase_value = $request->purchase_value;
            $machinery->insurance_due_date = $request->insurance_due_date;
            $machinery->puc_due_date = $request->puc_due_date;
            $machinery->fitness_due_date = $request->fitness_due_date;
            $machinery->last_service_date = $request->last_service_date;

            try {
                $machinery->save(); // This triggers machine_id generation

                // Handle rental agreement file upload (after validation passes)
                if ($request->hasFile('rental_agreement_file')) {
                    $uuid = \Illuminate\Support\Str::uuid()->toString();
                    $fileName = $uuid . '_' . $machinery->machine_id . '_' . $request->file('rental_agreement_file')->getClientOriginalName();
                    $request->file('rental_agreement_file')->storeAs('machinery_documents', $fileName, 'public');
                    $machinery->rental_agreement_file = $fileName;
                    $machinery->save();
                }

                // Handle ownership documents file upload (after validation passes)
                if ($request->hasFile('ownership_documents_file')) {
                    $uuid = \Illuminate\Support\Str::uuid()->toString();
                    $fileName = $uuid . '_' . $machinery->machine_id . '_' . $request->file('ownership_documents_file')->getClientOriginalName();
                    $request->file('ownership_documents_file')->storeAs('machinery_documents', $fileName, 'public');
                    $machinery->ownership_documents_file = $fileName;
                    $machinery->save();
                }

                event(new CreateMachinery($machinery, $request));

                return redirect()->route('machineries.index')->with('success', __('Machinery created successfully.'));
            } catch (\Exception $e) {
                \Log::error('Machinery creation failed', [
                    'error' => $e->getMessage(),
                    'user_id' => \Auth::id(),
                    'machinery_name' => $request->name,
                ]);
                return redirect()->back()->with('error', __('Failed to create machinery. Please try again.'));
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Machinery $machinery) {


        if (\Auth::user()->isAbleTo('machinery show')) {
            return view('machineries.show', compact('machinery'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Machinery $machinery) {
        // If you're using Blade views:
        // return view('machineries.edit', compact('machinery'));

        if (\Auth::user()->isAbleTo('machinery edit')) {

            $categories = \App\Models\MachineryCategory::pluck('name', 'id');
            $customFields = null;
            $suppliers = [];
            $suppliers = \App\Models\Supplier::pluck('name', 'id');
            $sites = getAllSitesWithWorkspace();
            return view('machineries.edit', compact('machinery', 'customFields', 'categories', 'suppliers', 'sites'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }



//        return response()->json(['message' => 'Display machinery edit form', 'machinery' => $machinery]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Machinery $machinery) {
        if (\Auth::user()->isAbleTo('machinery edit')) {
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:machineries,name,' . $machinery->id,
                'category_id' => 'required|integer|exists:machinery_categories,id',
                'model_number' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'purchase_date' => 'nullable|date',
                'capacity' => 'nullable|string|max:255',
                'maintenance_schedule' => 'nullable|date',
                'remarks' => 'nullable|string',
                'description' => 'nullable|string',
                'vehicle_number' => 'required|string|max:255',
            'owned_by' => 'required|in:owned,rental',
            'supplier_id' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental|nullable|exists:suppliers,id',
                'operational_status' => 'required|in:active,breakdown,scrap',
                'site_id' => 'nullable|integer',
                'workspace_id' => 'integer',
                'created_by' => 'integer',
                'status' => 'nullable|string|max:50',
                // Rental fields
                'rate_type' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'rate' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'minimum_billing_hours' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental',
                'diesel_by_company' => 'nullable|prohibited_unless:owned_by,rental',
                'operator_by_supplier' => 'nullable|prohibited_unless:owned_by,rental',
                'number_of_operators' => 'required_if:operator_by_supplier,1|prohibited_unless:owned_by,rental|nullable|integer|min:1',
                'rental_agreement_file' => 'nullable|mimes:pdf,doc,docx|max:10240|prohibited_unless:owned_by,rental',
                // Owned fields
                'purchase_value' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'insurance_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'puc_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'fitness_due_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'last_service_date' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'maintenance_schedule' => 'required_if:owned_by,owned|prohibited_unless:owned_by,owned',
                'ownership_documents_file' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240|prohibited_unless:owned_by,owned',
            ]);

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $machinery->name = $request->name;
            $machinery->category_id = $request->category_id;
            $machinery->model_number = $request->model_number;
            $machinery->manufacturer = $request->manufacturer;
            $machinery->purchase_date = $request->purchase_date;
            $machinery->capacity = $request->capacity;
            $machinery->maintenance_schedule = $request->maintenance_schedule;
            $machinery->remarks = $request->remarks;
            $machinery->description = $request->description;
            $machinery->vehicle_number = $request->vehicle_number;
            $oldOwnedBy = $machinery->owned_by;
            $machinery->owned_by = $request->owned_by;
            $machinery->supplier_id = $request->owned_by === 'rental' ? $request->supplier_id : null;
            $machinery->operational_status = $request->operational_status;
            $machinery->site_id = $request->site_id;
            $machinery->workspace_id = getWorkspaceIDFromSiteID($request->site_id);
            $machinery->created_by = creatorId();
            $machinery->status = $request->status ?? '0';

            // Handle ownership switch - cleanup old data and files
            if ($oldOwnedBy === 'rental' && $request->owned_by === 'owned') {
                // Clear rental fields - Note: diesel_by_company and operator_by_supplier will be set to false for owned machinery
                $machinery->rate_type = null;
                $machinery->rate = null;
                $machinery->minimum_billing_hours = null;
                $machinery->diesel_by_company = false; // Not applicable for owned machinery
                $machinery->operator_by_supplier = false; // Not applicable for owned machinery
                $machinery->number_of_operators = null;
                // Delete rental agreement file if exists
                if ($machinery->rental_agreement_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->rental_agreement_file);
                    $machinery->rental_agreement_file = null;
                }
            } elseif ($oldOwnedBy === 'owned' && $request->owned_by === 'rental') {
                // Clear owned fields
                $machinery->purchase_value = null;
                $machinery->insurance_due_date = null;
                $machinery->puc_due_date = null;
                $machinery->fitness_due_date = null;
                $machinery->last_service_date = null;
                // Delete ownership documents file if exists
                if ($machinery->ownership_documents_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->ownership_documents_file);
                    $machinery->ownership_documents_file = null;
                }
            }

            // Rental fields (only if ownership is rental) - Company Policy: diesel false, operator true
            if ($request->owned_by === 'rental') {
                $machinery->rate_type = $request->rate_type;
                $machinery->rate = $request->rate;
                $machinery->minimum_billing_hours = $request->minimum_billing_hours;
                $machinery->diesel_by_company = false; // Company Policy: Always false - supplier bears diesel cost
                $machinery->operator_by_supplier = true; // Company Policy: Always true - supplier provides operators
                $machinery->number_of_operators = $request->number_of_operators;
            }

            // Owned fields (only if ownership is owned)
            if ($request->owned_by === 'owned') {
                $machinery->purchase_value = $request->purchase_value;
                $machinery->insurance_due_date = $request->insurance_due_date;
                $machinery->puc_due_date = $request->puc_due_date;
                $machinery->fitness_due_date = $request->fitness_due_date;
                $machinery->last_service_date = $request->last_service_date;
            }

            // Handle rental agreement file upload
            if ($request->hasFile('rental_agreement_file')) {
                // Delete old file if exists
                if ($machinery->rental_agreement_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->rental_agreement_file);
                }
                $uuid = \Illuminate\Support\Str::uuid()->toString();
                $fileName = $uuid . '_' . $machinery->machine_id . '_' . $request->file('rental_agreement_file')->getClientOriginalName();
                $request->file('rental_agreement_file')->storeAs('machinery_documents', $fileName, 'public');
                $machinery->rental_agreement_file = $fileName;
            }

            // Handle ownership documents file upload
            if ($request->hasFile('ownership_documents_file')) {
                // Delete old file if exists
                if ($machinery->ownership_documents_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->ownership_documents_file);
                }
                $uuid = \Illuminate\Support\Str::uuid()->toString();
                $fileName = $uuid . '_' . $machinery->machine_id . '_' . $request->file('ownership_documents_file')->getClientOriginalName();
                $request->file('ownership_documents_file')->storeAs('machinery_documents', $fileName, 'public');
                $machinery->ownership_documents_file = $fileName;
            }

            try {
                $machinery->save();

                // Log critical changes for audit trail
                $criticalChanges = [];
                if ($oldOwnedBy !== $machinery->owned_by) {
                    $criticalChanges['ownership'] = [
                        'old' => $oldOwnedBy,
                        'new' => $machinery->owned_by,
                    ];
                }
                if (isset($request->rate) && $machinery->rate != $machinery->getOriginal('rate')) {
                    $criticalChanges['rate'] = [
                        'old' => $machinery->getOriginal('rate'),
                        'new' => $machinery->rate,
                    ];
                }
                if (isset($request->supplier_id) && $machinery->supplier_id != $machinery->getOriginal('supplier_id')) {
                    $criticalChanges['supplier'] = [
                        'old' => $machinery->getOriginal('supplier_id'),
                        'new' => $machinery->supplier_id,
                    ];
                }

                if (!empty($criticalChanges)) {
                    \Log::info('Machinery critical changes', [
                        'machinery_id' => $machinery->id,
                        'machine_id' => $machinery->machine_id,
                        'changes' => $criticalChanges,
                        'user_id' => \Auth::id(),
                        'timestamp' => now(),
                    ]);
                }

                event(new UpdateMachinery($machinery, $request));

                return redirect()->route('machineries.index')->with('success', __('The machinery details are updated successfully'));
            } catch (\Exception $e) {
                \Log::error('Machinery update failed', [
                    'error' => $e->getMessage(),
                    'user_id' => \Auth::id(),
                    'machinery_id' => $machinery->id,
                    'machine_id' => $machinery->machine_id,
                ]);
                return redirect()->back()->with('error', __('Failed to update machinery. Please try again.'));
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Machinery $machinery) {
        if (\Auth::user()->isAbleTo('machinery delete')) {
            try {
                $machineId = $machinery->machine_id;
                // Delete files before deleting record (handled by model deleting event)
                $machinery->delete();

                event(new DestroyMachinery($machinery));

                return redirect()->route('machineries.index')->with('success', __('The machinery has been deleted'));
            } catch (\Exception $e) {
                \Log::error('Machinery deletion failed', [
                    'error' => $e->getMessage(),
                    'user_id' => \Auth::id(),
                    'machinery_id' => $machinery->id,
                    'machine_id' => $machinery->machine_id,
                ]);
                return redirect()->back()->with('error', __('Failed to delete machinery. Please try again.'));
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }
}
