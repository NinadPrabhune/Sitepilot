<?php

namespace App\Http\Controllers;

use App\DataTables\PurchaseInvoiceDataTable;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Http\Requests\UpdatePurchaseInvoiceRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
use Workdo\Taskly\Entities\Project;

class PurchaseInvoiceController extends Controller {
    
    
     protected NotificationService $notificationService; 
     
     public function __construct(NotificationService $notificationService) { 
         $this->notificationService = $notificationService;          
     }

    public function index(PurchaseInvoiceDataTable $dataTable) {

        if (\Auth::user()->isAbleTo('purchase-invoice manage')) {
            try {
                return $dataTable->render('purchase-invoice.index');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create() {
        if (\Auth::user()->isAbleTo('purchase-invoice create')) {
            try {
                $suppliers = \App\Models\Supplier::pluck('name', 'id');
//                $materials = \App\Models\Material::all()->mapWithKeys(function ($material) {
//                    return [$material->id => [
//                            'name' => $material->name,
//                            'price' => $material->price,
//                            'unit' => $material->unit,
//                    ]];
//                });
                
                $materials = \App\Models\Material::where('category_id', '!=', 3)
                ->get()
                ->mapWithKeys(function ($material) {
                    return [
                        $material->id => [
                            'name'     => $material->name,
                            'price'    => $material->price,
                            'unit'     => $material->unit,
                            'category' => optional($material->category)->name, // if you want category name too
                        ],
                    ];
                });
              
                
                $sites = Project::where('workspace', getActiveWorkSpace())->where('id', getActiveProject())->projectonly()->get()->pluck('name','id');

                $maxId = PurchaseInvoice::max('id');
                $i = $maxId ? $maxId + 1 : 1;
                $nextInvoiceNumber = 'INV-' . str_pad($i, 4, '0', STR_PAD_LEFT);

                $supplierCategories = \App\Models\SupplierCategory::pluck('name', 'id');

                return view('purchase-invoice.create', compact('suppliers', 'materials', 'sites', 'nextInvoiceNumber', 'supplierCategories'));
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function store(Request $request) {
        if (\Auth::user()->isAbleTo('purchase-invoice create')) {
            try {
                $validator = Validator::make($request->all(), [
                    'supplier_invoice_number' => 'nullable|string',
                    'supplier_id' => 'required|exists:suppliers,id',
                    'site_id' => 'required|exists:projects,id',
                    'invoice_date' => 'required|date',
                    'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                    'invoice_type' => 'required|in:general_po,minor_misc_service',
                    'items' => 'nullable|array',
                    'items.*.material_id' => 'nullable|exists:materials,id',
                    'items.*.quantity' => 'nullable|numeric',
                    'items.*.unit' => 'nullable|string',
                    'items.*.price' => 'nullable|numeric',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $validated = $validator->validated();

                // Remove items if invoice_type is minor_misc_service
                if ($validated['invoice_type'] === 'minor_misc_service') {
                    unset($validated['items']);
                }

                // Generate invoice number
                $maxId = PurchaseInvoice::max('id');
                $i = $maxId ? $maxId + 1 : 1;
                $validated['invoice_number'] = 'INV-' . str_pad($i, 4, '0', STR_PAD_LEFT);

                // ✅ Handle file upload with helper
                if ($request->hasFile('invoice_file')) {
                    $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('invoice_file')->getClientOriginalExtension();
                    $fileNameToStore = time() . '_invoice_' . $validated['invoice_number'] . '.' . $extension;

                    $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                    if ($path['flag'] == 0) {
                        return back()->withErrors(['error' => $path['msg']])->withInput();
                    }

                    if (!empty($path['url'])) {
                        $validated['invoice_file'] = $path['url'];
                    }
                }

                // Add created_by and workspace_id
                $validated['created_by'] = creatorId();
                $validated['workspace_id'] = getActiveWorkSpace();

                // ✅ Create invoice
                $invoice = PurchaseInvoice::create($validated);

                // Branch logic
                if ($validated['invoice_type'] === 'minor_misc_service') {
                    $invoice->update(['total_amount' => $request->total_amount]);
                } else {
                    $total = 0;
                    foreach ($request->items ?? [] as $item) {
                        $subtotal = $item['quantity'] * $item['price'];
                        PurchaseInvoiceItem::create([
                            'purchase_invoice_id' => $invoice->id,
                            'material_id' => $item['material_id'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'price' => $item['price'],
                            'subtotal' => $subtotal,
                        ]);
                        $total += $subtotal;
                    }
                    $invoice->update(['total_amount' => $total]);
                }
                
                
                // Trigger notification instantly 
                $this->notificationService->createPOGeneratedNotification($invoice->id,$invoice->site_id,$invoice->invoice_number,$invoice->creator->name );
                
                
                

                return redirect()->route('purchase-invoice.index')->with('success', 'Invoice created successfully.');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

//    public function store(StorePurchaseInvoiceRequest $request) {
//        try {
//            $data = $request->validated();
//            $maxId = PurchaseInvoice::max('id');
//            $i = $maxId ? $maxId + 1 : 1;
//            $nextInvoiceNumber = 'INV-' . str_pad($i, 4, '0', STR_PAD_LEFT);
//            $data['invoice_number'] = $nextInvoiceNumber;
//
//            $data['invoice_date'] = $request->invoice_date;
//            $data['supplier_invoice_number'] = $request->supplier_invoice_number;
//            $data['invoice_type'] = $request->invoice_type;
//            $data['supplier_id'] = $request->supplier_id;
//            $data['status'] = 'Approved';
//            $data['site_id'] = $request->site_id;
//
//            if ($request->hasFile('invoice_file')) {
//                $file = $request->file('invoice_file');
//                $extension = $file->getClientOriginalExtension();
//                $filename = time() . '_invoice_' . $nextInvoiceNumber . '.' . $extension;
//                $path = $file->storeAs('invoices', $filename, 'public');
//                $data['invoice_file'] = $path;
//            }
//
//            $data['created_by'] = creatorId();
//            $data['workspace_id'] = getActiveWorkSpace();
//
//            $invoice = PurchaseInvoice::create($data);
//
//            if ($request->invoice_type === 'minor_misc_service') {
//                $invoice->update(['total_amount' => $request->total_amount]);
//            } else {
//                $total = 0;
//                foreach ($request->items as $item) {
//                    $subtotal = $item['quantity'] * $item['price'];
//                    PurchaseInvoiceItem::create([
//                        'purchase_invoice_id' => $invoice->id,
//                        'material_id' => $item['material_id'],
//                        'quantity' => $item['quantity'],
//                        'unit' => $item['unit'],
//                        'price' => $item['price'],
//                        'subtotal' => $subtotal,
//                    ]);
//                    $total += $subtotal;
//                }
//                $invoice->update(['total_amount' => $total]);
//            }
//
//            return redirect()->route('purchase-invoice.index')->with('success', 'Invoice created successfully.');
//        } catch (\Exception $e) {
//            return back()->withErrors(['error' => $e->getMessage()]);
//        }
//    }

    public function show(PurchaseInvoice $purchaseInvoice) {
        if (\Auth::user()->isAbleTo('purchase-invoice show')) {
            try {
                // Eager load relationships to avoid N+1 queries
                $purchaseInvoice->load(['items.material', 'supplier', 'site', 'payments']);

                // Pass to the Blade view
                return view('purchase-invoice.show', compact('purchaseInvoice'));
            } catch (\Exception $e) {
                // Redirect back with error message
                return redirect()->back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(PurchaseInvoice $purchaseInvoice) {
        if (\Auth::user()->isAbleTo('purchase-invoice edit')) {
            try {
                $suppliers = \App\Models\Supplier::pluck('name', 'id');
                $sites = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                                ->projectonly()->get()->pluck('name', 'id');

                $materials = \App\Models\Material::all()->mapWithKeys(function ($material) {
                    return [$material->id => [
                            'name' => $material->name,
                            'price' => $material->price,
                            'unit' => $material->unit,
                    ]];
                });

                $purchaseInvoice->load('items');
                $supplierCategories = \App\Models\SupplierCategory::pluck('name', 'id');

                return view('purchase-invoice.edit', compact('purchaseInvoice', 'suppliers', 'materials', 'sites', 'supplierCategories'));
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice) {
        if (\Auth::user()->isAbleTo('purchase-invoice edit')) {
            try {
               
                // Validate input
                $validator = Validator::make($request->all(), [
                    'supplier_invoice_number' => 'nullable|string',
                    'supplier_id' => 'required|exists:suppliers,id',
                    'site_id' => 'required|exists:projects,id',
                    'invoice_date' => 'required|date',
                    'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                    'invoice_type' => 'required|in:general_po,minor_misc_service',
                    'items' => 'nullable|array',
                    'items.*.material_id' => 'nullable|exists:materials,id',
                    'items.*.quantity' => 'nullable|numeric',
                    'items.*.unit' => 'nullable|string',
                    'items.*.price' => 'nullable|numeric',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $validated = $validator->validated();

                // ✅ Handle file upload with helper
                if ($request->hasFile('invoice_file')) {
                    // Delete old file if exists
                    if (!empty($purchaseInvoice->invoice_file)) {
                        Storage::disk('public')->delete($purchaseInvoice->invoice_file);
                    }

                    $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('invoice_file')->getClientOriginalExtension();
                    $fileNameToStore = time() . '_invoice_' . $purchaseInvoice->invoice_number . '_' . $filename . '.' . $extension;

                    $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                    if ($path['flag'] == 0) {
                        return back()->withErrors(['error' => $path['msg']])->withInput();
                    }

                    if (!empty($path['url'])) {
                        $validated['invoice_file'] = $path['url'];
                    }
                }

                // Add system fields
                $validated['created_by'] = creatorId();
                $validated['workspace_id'] = getActiveWorkSpace();

                // ✅ Update invoice
                $purchaseInvoice->update($validated);

                // Branch logic
                if ($validated['invoice_type'] === 'minor_misc_service') {
                    $purchaseInvoice->items()->delete();
                    $purchaseInvoice->update(['total_amount' => $request->total_amount]);
                } else {
                    $purchaseInvoice->items()->delete();
                    $total = 0;
                    foreach ($request->items ?? [] as $item) {
                        $subtotal = $item['quantity'] * $item['price'];
                        PurchaseInvoiceItem::create([
                            'purchase_invoice_id' => $purchaseInvoice->id,
                            'material_id' => $item['material_id'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'price' => $item['price'],
                            'subtotal' => $subtotal,
                        ]);
                        $total += $subtotal;
                    }
                    $purchaseInvoice->update(['total_amount' => $total]);
                }
                
                // Trigger notification instantly 
                $this->notificationService->createPOGeneratedNotification($purchaseInvoice->id, $purchaseInvoice->site_id,$purchaseInvoice->invoice_number,$purchaseInvoice->creator->name  );
               
                

                return redirect()->route('purchase-invoice.index')->with('success', 'Invoice updated successfully.');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

//    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice) {
//        try {
//            $data = $request->validated();
//            $data['supplier_id'] = $request->supplier_id;
//            $data['status'] = 'Approved';
//            $data['site_id'] = $request->site_id;
//            $data['invoice_date'] = $request->invoice_date;
//            $data['created_by'] = creatorId();
//            $data['workspace_id'] = getActiveWorkSpace();
//            $data['invoice_type'] = $request->invoice_type;
//            $data['supplier_invoice_number'] = $request->supplier_invoice_number;
//
//            if ($request->hasFile('invoice_file')) {
//                if ($purchaseInvoice->invoice_file) {
//                    Storage::disk('public')->delete($purchaseInvoice->invoice_file);
//                }
//                $file = $request->file('invoice_file');
//                $extension = $file->getClientOriginalExtension();
//                $filename = time() . '_invoice_' . $purchaseInvoice->invoice_number . '.' . $extension;
//                $path = $file->storeAs('invoices', $filename, 'public');
//                $data['invoice_file'] = $path;
//            }
//
//            $purchaseInvoice->update($data);
//
//            if ($request->invoice_type === 'minor_misc_service') {
//                $purchaseInvoice->items()->delete();
//                $purchaseInvoice->update(['total_amount' => $request->total_amount]);
//            } else {
//                $purchaseInvoice->items()->delete();
//                $total = 0;
//                foreach ($request->items as $item) {
//                    $subtotal = $item['quantity'] * $item['price'];
//                    PurchaseInvoiceItem::create([
//                        'purchase_invoice_id' => $purchaseInvoice->id,
//                        'material_id' => $item['material_id'],
//                        'quantity' => $item['quantity'],
//                        'unit' => $item['unit'],
//                        'price' => $item['price'],
//                        'subtotal' => $subtotal,
//                    ]);
//                    $total += $subtotal;
//                }
//                $purchaseInvoice->update(['total_amount' => $total]);
//            }
//
//            return redirect()->route('purchase-invoice.index')->with('success', 'Invoice updated successfully.');
//        } catch (\Exception $e) {
//            return back()->withErrors(['error' => $e->getMessage()]);
//        }
//    }

    public function destroy(PurchaseInvoice $purchaseInvoice) {
        if (\Auth::user()->isAbleTo('purchase-invoice delete')) {
            try {
                
                // Check if material is used in daily_consumption_details
                $existsInPaymentsModule = \DB::table('payments_module')
                    ->where('purchase_invoice_id', $purchaseInvoice->id)
                    ->exists();

                if ($existsInPaymentsModule) {                    
                    return redirect()->back()->with('error', 'Purchase Invoice cannot be deleted because it is used in Payments Module.');
                } 

                
                
                if ($purchaseInvoice->invoice_file) {
                    Storage::disk('public')->delete($purchaseInvoice->invoice_file);
                }
                $purchaseInvoice->items()->delete();
                $purchaseInvoice->delete();

                return redirect()->route('purchase-invoice.index')->with('success', 'Invoice deleted successfully.');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getPurchaseInvoiceBySupplierId(Request $request) {

        try {
            $invoices = PurchaseInvoice::where('supplier_id', $request->supplier_id)
                    ->where('payment_status', '!=', 'paid')
                    ->pluck('invoice_number', 'id');

            if ($invoices->isEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'No purchase invoices found for this supplier.'], 404);
            }
            return response()->json($invoices);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getPurchaseInvoiceBySupplierIdEdit(Request $request) {


        try {

            $invoices = PurchaseInvoice::where('supplier_id', $request->supplier_id)
                    ->where(function ($query) use ($request) {
                        $query->where('payment_status', '!=', 'paid')
                                ->orWhere('id', $request->payments_module_id);
                    })
                    ->pluck('invoice_number', 'id');

            if ($invoices->isEmpty()) {
                return response()->json([
                            'status' => 'error',
                            'message' => 'No purchase invoices found for this supplier.'
                                ], 404);
            }

            return response()->json($invoices);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                            ], 500);
        }
    }

    public function getPurchaseInvoiceRemainingAmountByPurchaseInvoiceId(Request $request) {
        try {
            $invoice = PurchaseInvoice::findOrFail($request->purchase_invoice_id);

            // Sum all payments for this invoice, excluding the current one if editing
            $query = \App\Models\PaymentsModule::where('purchase_invoice_id', $invoice->id);

            if ($request->filled('payments_module_id')) {
                $query->where('id', '!=', $request->payments_module_id);
            }

            $paidAmount = $query->sum('amount');

            $remainingAmount = $invoice->total_amount - $paidAmount;

            return response()->json([
                        'remaining_amount' => max($remainingAmount, 0), // never negative
            ]);
        } catch (\Exception $e) {
            return response()->json([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                            ], 500);
        }
    }
    
    

}
