<?php

namespace App\Http\Controllers;

use App\DataTables\SupplierDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Events\CreateSupplier;
use App\Events\DestroySupplier;
use App\Events\UpdateSupplier;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller {

    public function index(SupplierDataTable $dataTable) {


        if (\Auth::user()->isAbleTo('supplier manage')) {
            return $dataTable->render('suppliers.index');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create() {
        if (\Auth::user()->isAbleTo('supplier create')) {
            $categories = \App\Models\SupplierCategory::pluck('name', 'id');

            return view('suppliers.create', compact('categories'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function store(Request $request) {
        // Log session state before processing
        \Log::info('Supplier store - Session check', [
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'auth_check' => Auth::check(),
            'request_data' => $request->except(['upi_screenshot_1', 'upi_screenshot_2'])
        ]);

        if (\Auth::user()->isAbleTo('supplier create')) {
//        dd($request->all());

            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:supplier_categories,id',
                'type' => 'nullable|in:company,individual',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:10',
                'country' => 'nullable|string|max:100',
                'gst_number' => 'nullable|string|max:20',
                'pan_number' => 'nullable|string|max:20',
                'registration_number' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'account_number' => 'nullable|string|max:30',
                'ifsc_code' => 'nullable|string|max:20',
                'payment_terms' => 'nullable|string|max:50',
                'upi_screenshot_1' => 'nullable|image|max:2048',
                'upi_screenshot_2' => 'nullable|image|max:2048',
            ]);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

//        $supplier = new Supplier();
//        $supplier->name = $request->name;
//        $supplier->sku = $request->sku;
//        $supplier->category_id = $request->category_id;
//        $supplier->unit_id = $request->unit_id;
//        $supplier->description = $request->description;
//        $supplier->price = $request->price;
//        $supplier->reorder_level = $request->reorder_level;
//        $supplier->status = $request->status;
//        $supplier->site_id = $request->site_id;
//        $supplier->created_by = creatorId();
//        $supplier->workspace_id = getActiveWorkSpace();

            $supplier = new Supplier();
            $supplier->fill($request->except(['upi_screenshot_1', 'upi_screenshot_2']));
            $supplier->created_by = creatorId();

            // Handle image uploads using helper
            foreach (['upi_screenshot_1', 'upi_screenshot_2'] as $field) {

                if ($request->hasFile($field)) {

                    $originalName = $request->file($field)->getClientOriginalName();
                    $filename = pathinfo($originalName, PATHINFO_FILENAME);
                    $extension = $request->file($field)->getClientOriginalExtension();

                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $path = upload_file($request, $field, $fileNameToStore, 'supplier');

                    if ($path['flag'] == 0) {
                        return redirect()->back()->with('error', $path['msg']);
                    }

                    if (!empty($path['url'])) {
                        $supplier->$field = $path['url'];
                    }
                }
            }

            $supplier->save();

//            if(module_is_active('CustomField'))
//            {
//                \Workdo\CustomField\Entities\CustomField::saveData($supplier, $request->customField);
//            }

            event(new CreateSupplier($request, $supplier));

            // Log session state before redirect
            \Log::info('Supplier store - Before redirect', [
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'auth_check' => Auth::check(),
                'supplier_id' => $supplier->id
            ]);

            // If form was submitted from modal, skip normal redirect
            if ($request->filled('insert_from') && $request->insert_from === 'modal') {
                return response()->json([
                            'success' => true,
                            'message' => __('The Supplier has been created successfully'),
                            'supplier' => $supplier, // optional: return new supplier data
                ]);
            }

            return redirect()->route('supplier.index')->with('success', __('The Supplier has been created successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(Supplier $supplier) {
        if (\Auth::user()->isAbleTo('supplier create')) {
            $categories = \App\Models\SupplierCategory::pluck('name', 'id');

            return view('suppliers.edit', compact('supplier', 'categories'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function update(Request $request, Supplier $supplier) {



        if (\Auth::user()->isAbleTo('supplier edit')) {
//            $supplier = Supplier::find($id);
//            if($supplier->created_by == creatorId()  && $supplier->workspace == getActiveWorkSpace())
//            {
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:supplier_categories,id',
                'type' => 'nullable|in:company,individual',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:10',
                'country' => 'nullable|string|max:100',
                'gst_number' => 'nullable|string|max:20',
                'pan_number' => 'nullable|string|max:20',
                'registration_number' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'account_number' => 'nullable|string|max:30',
                'ifsc_code' => 'nullable|string|max:20',
                'payment_terms' => 'nullable|string|max:50',
                'upi_screenshot_1' => 'nullable|image|max:2048',
                'upi_screenshot_2' => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }




//        $supplier->site_id = $request->site_id;
            $supplier->created_by = creatorId();

            $supplier->fill($request->except(['upi_screenshot_1', 'upi_screenshot_2']));

            // Handle image uploads using helper
            foreach (['upi_screenshot_1', 'upi_screenshot_2'] as $field) {

                if ($request->hasFile($field)) {

                    // Delete old file if exists
                    if (!empty($supplier->$field) && file_exists(public_path($supplier->$field))) {
                        unlink(public_path($supplier->$field));
                    }

                    $originalName = $request->file($field)->getClientOriginalName();
                    $filename = pathinfo($originalName, PATHINFO_FILENAME);
                    $extension = $request->file($field)->getClientOriginalExtension();

                    $fileNameToStore = Str::slug($filename) . '_' . time() . '.' . $extension;

                    $path = upload_file($request, $field, $fileNameToStore, 'supplier');

                    if ($path['flag'] == 0) {
                        return redirect()->back()->with('error', $path['msg']);
                    }

                    if (!empty($path['url'])) {
                        $supplier->$field = $path['url'];
                    }
                }
            }

            $supplier->save();

//                if(module_is_active('CustomField'))
//                {
//                    \Workdo\CustomField\Entities\CustomField::saveData($supplier, $request->customField);
//                }
            event(new UpdateSupplier($request, $supplier));
            return redirect()->route('supplier.index')->with('success', __('The Supplier details are updated successfully'));
//            }
//            else
//            {
//                return redirect()->back()->with('error', __('Permission denied.'));
//            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(Supplier $supplier) {
        if (!\Auth::user()->isAbleTo('supplier delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Check relational usage
        $usageChecks = [
            'Purchase Invoices' => \DB::table('purchase_invoices')
                    ->where('supplier_id', $supplier->id)
                    ->exists(),
            'Man-Power Masters' => \DB::table('man_power_masters')
                    ->where('supplier_id', $supplier->id)
                    ->exists(),
            'Payments' => \DB::table('payments_module')
                    ->where('supplier_id', $supplier->id)
                    ->exists(),
        ];

        foreach ($usageChecks as $module => $exists) {
            if ($exists) {
                return redirect()->back()->with(
                                'error',
                                "Supplier cannot be deleted because it is used in {$module}."
                        );
            }
        }

        // Delete uploaded files if exist
        foreach (['upi_screenshot_1', 'upi_screenshot_2'] as $field) {
            if (!empty($supplier->$field) && file_exists(public_path($supplier->$field))) {
                unlink(public_path($supplier->$field));
            }
        }

        $supplier->delete();

        event(new DestroySupplier($supplier));

        return redirect()->route('supplier.index')
                        ->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier) {

        if (\Auth::user()->isAbleTo('supplier show')) {
            return view('suppliers.show', compact('supplier'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getSuppliersBySiteId(Request $request) {
        try {
//            $suppliers = Supplier::where('site_id', $request->site_id)->pluck('name', 'id');
//            $suppliers = Supplier::where('site_id', $request->site_id)->pluck('name', 'id');

            $suppliers = Supplier::orderBy('name')->pluck('name', 'id');

            return response()->json($suppliers);
        } catch (\Exception $e) {
            // Return error response with message
            return response()->json([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                            ], 500);
        }
    }
}
