<?php

namespace App\Http\Controllers;

use App\DataTables\PurchaseInvoiceDataTable;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\PurchaseInvoiceItem;
use App\Models\Grn;
use App\Models\GrnItem;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Http\Requests\UpdatePurchaseInvoiceRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Helpers\LedgerHelper;
use Workdo\Taskly\Entities\Project;
use Dompdf\Dompdf;
use Dompdf\Options;

class PurchaseInvoiceController extends Controller {
    
    
     protected NotificationService $notificationService; 
     
     public function __construct(NotificationService $notificationService) { 
         $this->notificationService = $notificationService;          
     }

    public function index(PurchaseInvoiceDataTable $dataTable) {

        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            $suppliers = Supplier::pluck('name', 'id');
            return $dataTable->render('purchase-invoice.index', compact('suppliers'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function debugLog(Request $request) {
        if ($request->action == 'export_purchase_invoice') {
            Log::info("Export Purchase Invoice", ['ids' => $request->ids]);
        }
        return response()->json(['success' => true]);
    }

    public function create() {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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

                $selectedSiteId = getActiveProject();
                $nextInvoiceNumber = \App\Models\PurchaseInvoice::generateInvoiceNumber($selectedSiteId);

                $supplierCategories = \App\Models\SupplierCategory::pluck('name', 'id');

                // Get users for assign_to field
                $users = getActiveProjectEmployees();

                return view('purchase-invoice.create', compact('suppliers', 'materials', 'sites', 'nextInvoiceNumber', 'supplierCategories', 'users', 'selectedSiteId'));
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
    }

    public function store(Request $request) {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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
            'assign_to' => 'nullable|array',
            'assign_to.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        if ($validated['invoice_type'] === 'minor_misc_service') {
            unset($validated['items']);
        }

        DB::beginTransaction();
        try {
            $validated['invoice_number'] = PurchaseInvoice::generateInvoiceNumber($validated['site_id'] ?? null); // Force override any user input

            if ($request->hasFile('invoice_file')) {
                $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('invoice_file')->getClientOriginalExtension();
                $fileNameToStore = time() . '_invoice_' . $validated['invoice_number'] . '.' . $extension;

                $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                if ($path['flag'] == 0) {
                    DB::rollBack();
                    return back()->withErrors(['error' => $path['msg']])->withInput();
                }

                if (!empty($path['url'])) {
                    $validated['invoice_file'] = $path['url'];
                }
            }

            $validated['created_by'] = creatorId();
            $validated['workspace_id'] = getActiveWorkSpace();
            $validated['payment_status'] = 'unpaid';
            $validated['assign_to'] = $request->assign_to; // Trait mutator handles array to string conversion

            $invoice = PurchaseInvoice::create($validated);

            if (!empty($validated['po_id'])) {
                try {
                    app(\App\Services\POCalculationService::class)->updatePOInvoiceAmount($validated['po_id']);
                } catch (\Exception $e) {
                    Log::warning('Failed to update PO invoiced amount: ' . $e->getMessage());
                }
            }

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

            // Recalculate grand_total from items
            $invoice->calculateTotals();
            $invoice->save();

            $this->notificationService->createPOGeneratedNotification($invoice->id,$invoice->site_id,$invoice->invoice_number,$invoice->creator->name );
            
            try {
                app(\App\Services\LedgerService::class)->createInvoiceEntry($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to create supplier ledger entry: ' . $e->getMessage());
                throw $e; // Rollback transaction
            }

            // PDF generation is now handled by PurchaseInvoiceObserver
            // No need to generate PDF here as it will be automatically generated via observer

            DB::commit();

            return redirect()->route('purchase-invoice.index')->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating purchase invoice: ' . $e->getMessage());
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(PurchaseInvoice $purchaseInvoice) {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice show')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
                // Eager load relationships to avoid N+1 queries
                $purchaseInvoice->load(['items.material', 'items.gstMaster', 'supplier', 'site', 'payments', 'purchaseOrder', 'grn']);

                // Fetch assigned users for display (N+1 fix - moved from Blade)
                $assignedUsers = collect();
                if ($purchaseInvoice->assign_to) {
                    $assignedUsers = \App\Models\User::whereIn('id', explode(',', $purchaseInvoice->assign_to))->get();
                }

                // Pass to the Blade view
                return view('purchase-invoice.show', compact('purchaseInvoice', 'assignedUsers'));
            } catch (\Exception $e) {
                // Redirect back with error message
                return redirect()->back()->withErrors(['error' => $e->getMessage()]);
            }
    }

    public function edit(PurchaseInvoice $purchaseInvoice) {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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

                // Get users for assign_to field
                $users = getActiveProjectEmployees();

                return view('purchase-invoice.edit', compact('purchaseInvoice', 'suppliers', 'materials', 'sites', 'supplierCategories', 'users'));
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice) {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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
                    'assign_to' => 'nullable|array',
                    'assign_to.*' => 'integer|exists:users,id',
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
                $validated['assign_to'] = $request->assign_to; // Trait mutator handles array to string conversion

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

                // Recalculate grand_total from items
                $purchaseInvoice->calculateTotals();
                $purchaseInvoice->save();
                
                // Update supplier ledger entry
                try {
                    app(\App\Services\LedgerService::class)->createInvoiceEntry($purchaseInvoice);
                } catch (\Exception $e) {
                    Log::error('Failed to update supplier ledger entry for invoice: ' . $e->getMessage());
                    throw $e; // Rollback transaction
                }

                // Trigger notification instantly 
                $this->notificationService->createPOGeneratedNotification($purchaseInvoice->id, $purchaseInvoice->site_id,$purchaseInvoice->invoice_number,$purchaseInvoice->creator->name  );

                // PDF regeneration is now handled by PurchaseInvoiceObserver
                // No need to regenerate PDF here as it will be automatically regenerated via observer when relevant fields change
                
                

                return redirect()->route('purchase-invoice.index')->with('success', 'Invoice updated successfully.');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
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
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
                
                // Check if material is used in daily_consumption_details
                $existsInPaymentsModule = \DB::table('payments_module')
                    ->where('purchase_invoice_id', $purchaseInvoice->id)
                    ->exists();

                if ($existsInPaymentsModule) {                    
                    return redirect()->back()->with('error', 'Purchase Invoice cannot be deleted because it is used in Payments Module.');
                } 
                
                $poId = $purchaseInvoice->po_id;
                
                if ($purchaseInvoice->invoice_file) {
                    Storage::disk('public')->delete($purchaseInvoice->invoice_file);
                }
                
                // Delete supplier ledger entries and recalculate balance
                try {
                    LedgerHelper::handleInvoiceDeletion($purchaseInvoice->id);
                } catch (\Exception $e) {
                    Log::error('Failed to delete supplier ledger entry: ' . $e->getMessage());
                }
                
                $purchaseInvoice->items()->delete();
                $purchaseInvoice->delete();

                // Update PO invoiced amount after invoice deletion
                if ($poId) {
                    try {
                        app(\App\Services\POCalculationService::class)->updatePOInvoiceAmount($poId);
                    } catch (\Exception $e) {
                        Log::warning('Failed to update PO invoiced amount after invoice deletion: ' . $e->getMessage());
                    }
                }

                return redirect()->route('purchase-invoice.index')->with('success', 'Invoice deleted successfully.');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
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

    /**
     * Store invoice from GRN.
     */
    public function storeFromGrn(Request $request)
    {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $validator = Validator::make($request->all(), [
            'grn_id' => 'required|exists:grns,id',
            'invoice_number' => 'required|string|unique:purchase_invoices,invoice_number',
            'invoice_date' => 'required|date',
            'supplier_invoice_number' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $grn = Grn::with(['purchaseOrder', 'items.poItem.gstMaster', 'items.gstMaster', 'items.material'])->findOrFail($request->grn_id);

            // Check if invoice already exists for this GRN
            if (PurchaseInvoice::where('grn_id', $grn->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invoice already exists for this GRN'
                ], 422);
            }

            $validated = $validator->validated();

            // Handle file upload
            $invoiceFile = null;
            if ($request->hasFile('invoice_file')) {
                $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('invoice_file')->getClientOriginalExtension();
                $fileNameToStore = time() . '_invoice_' . $validated['invoice_number'] . '.' . $extension;

                $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                if ($path['flag'] == 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => $path['msg']
                    ], 422);
                }

                if (!empty($path['url'])) {
                    $invoiceFile = $path['url'];
                }
            }

            // For Direct GRN, tax_type is stored on the GRN itself; for PO-based GRN, it's on the PO
            $isDirectGrn = $grn->isDirectGrn();
            $taxType = $isDirectGrn ? ($grn->tax_type ?? 'cgst') : ($grn->purchaseOrder->tax_type ?? 'cgst');

            // Create Purchase Invoice - explicitly set payment_status to ensure proper formatting
            $invoice = PurchaseInvoice::create([
                'invoice_number' => $validated['invoice_number'],
                'invoice_type' => 'general_po',
                'invoice_date' => $validated['invoice_date'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_id' => $grn->supplier_id,
                'site_id' => $grn->site_id,
                'po_id' => $grn->po_id,
                'grn_id' => $grn->id,
                'tax_type' => $taxType,
                'invoice_file' => $invoiceFile,
                'assign_to' => $grn->assign_to,
                'status' => 'Approved',
                'created_by' => creatorId(),
                'workspace_id' => getActiveWorkSpace(),
                'payment_status' => 'unpaid', // Ensure proper value formatting without quotes
            ]);

            // Calculate totals
            $totalTaxableValue = 0;
            $totalDiscount = 0;
            $totalCgst = 0;
            $totalSgst = 0;
            $totalIgst = 0;
            $totalTax = 0;

            // Create invoice items from GRN items
            foreach ($grn->items as $grnItem) {
                $poItem = $grnItem->poItem;

                // For Direct GRN, get values from GrnItem; for PO-based GRN, get from PO item
                if ($isDirectGrn) {
                    $gstMaster = $grnItem->gstMaster ?? ($grnItem->material?->gstMaster ?? null);
                    $quantity = (float) $grnItem->accepted_qty;
                    $price = (float) $grnItem->price;
                    $unit = $grnItem->unit ?? ($grnItem->material?->unit?->name ?? 'PCS');
                    $discountAmount = 0;
                } else {
                    $gstMaster = $poItem?->gstMaster ?? null;
                    $quantity = (float) $grnItem->accepted_qty;
                    $poOrderedQty = (float) ($poItem?->quantity ?? 1);
                    $price = (float) ($poItem?->price ?? 0);
                    $unit = $poItem?->unit ?? 'PCS';
                    $poDiscountAmount = (float) ($poItem?->discount_amount ?? 0);
                    
                    // DEBUG: Log discount calculation for diagnosis
                    Log::info('[DEBUG] Discount Calculation - PO Item', [
                        'po_item_id' => $poItem?->id,
                        'material_id' => $grnItem->material_id,
                        'po_ordered_qty' => $poOrderedQty,
                        'grn_accepted_qty' => $quantity,
                        'po_discount_amount' => $poDiscountAmount,
                        'proportion' => $poOrderedQty > 0 ? round($quantity / $poOrderedQty, 4) : 0,
                    ]);
                    
                    // Calculate proportional discount: (accepted_qty / ordered_qty) * po_discount
                    $discountAmount = $poOrderedQty > 0 ? ($quantity / $poOrderedQty) * $poDiscountAmount : 0;
                }
                
                $rowTotal = $quantity * $price;
                $taxableValue = max(0, $rowTotal - $discountAmount);

                $cgstAmount = 0;
                $sgstAmount = 0;
                $igstAmount = 0;
                $taxAmount = 0;

                if ($gstMaster) {
                    if ($taxType === 'igst') {
                        $igstRate = (float) ($gstMaster->igst ?? 0);
                        $igstAmount = ($taxableValue * $igstRate) / 100;
                        $taxAmount = $igstAmount;
                    } else {
                        $cgstRate = (float) ($gstMaster->cgst ?? 0);
                        $sgstRate = (float) ($gstMaster->sgst ?? 0);
                        $cgstAmount = ($taxableValue * $cgstRate) / 100;
                        $sgstAmount = ($taxableValue * $sgstRate) / 100;
                        $taxAmount = $cgstAmount + $sgstAmount;
                    }
                }

                $subtotal = $taxableValue + $taxAmount;

                // Create invoice item
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'grn_item_id' => $grnItem->id,
                    'material_id' => $grnItem->material_id,
                    'gst_master_id' => $gstMaster->id ?? null,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'price' => $price,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotal,
                ]);

                $totalTaxableValue += $taxableValue;
                $totalDiscount += $discountAmount;
                $totalCgst += $cgstAmount;
                $totalSgst += $sgstAmount;
                $totalIgst += $igstAmount;
                $totalTax += $taxAmount;
            }

            $grandTotal = $totalTaxableValue + $totalTax;

            // Update invoice with totals
            $invoice->update([
                'total_taxable_value' => round($totalTaxableValue, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_cgst' => round($totalCgst, 2),
                'total_sgst' => round($totalSgst, 2),
                'total_igst' => round($totalIgst, 2),
                'total_tax' => round($totalTax, 2),
                'grand_total' => round($grandTotal, 2),
                'total_amount' => round($grandTotal, 2),
            ]);

            DB::commit();

            // Update PO invoiced amount after invoice creation
            if ($grn->po_id) {
                try {
                    app(\App\Services\POCalculationService::class)->updatePOInvoiceAmount($grn->po_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to update PO invoiced amount: ' . $e->getMessage());
                }
            }

            // Create supplier ledger entry for the invoice
            try {
                app(\App\Services\LedgerService::class)->createInvoiceEntry($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to create supplier ledger entry: ' . $e->getMessage());
                throw $e; // Rollback transaction
            }

            // Trigger notification
            $this->notificationService->createPOGeneratedNotification(
                $invoice->id,
                $invoice->site_id,
                $invoice->invoice_number,
                $invoice->creator->name
            );

            // PDF generation is now handled by PurchaseInvoiceObserver
            // No need to generate PDF here as it will be automatically generated via observer

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully!',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store Invoice From GRN Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error creating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate and save PDF for Purchase Invoice.
     *
     * @param PurchaseInvoice $purchaseInvoice
     * @param int $workspaceId
     * @return string|null
     */
    private function generatePurchaseInvoicePdf(PurchaseInvoice $purchaseInvoice, int $workspaceId): ?string
    {
        try {
            Log::info('PDF Generation - Loading relationships (Web)', ['invoice_id' => $purchaseInvoice->id]);
            // Load relationships - must match what print method loads
            $purchaseInvoice->load([
                'items.material',
                'items.gstMaster',
                'supplier',
                'site',
                'purchaseOrder',
                'creator',
                'workspace'
            ]);

            Log::info('PDF Generation - Loading settings (Web)', ['invoice_id' => $purchaseInvoice->id, 'workspace_id' => $workspaceId]);
            // Get company settings
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
                'company_logo'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }

            // Get workspace details
            $workspaceDetails = null;
            if ($purchaseInvoice->workspace) {
                $workspaceDetails = $purchaseInvoice->workspace;
                $settings['workspace_name'] = $workspaceDetails->name;
            }

            Log::info('PDF Generation - Initializing Dompdf (Web)', ['invoice_id' => $purchaseInvoice->id]);
            // Generate PDF using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

            $dompdf = new Dompdf($options);
            $data['isPdf'] = true;
            $data['purchaseInvoice'] = $purchaseInvoice;
            $data['settings'] = $settings;
            $data['workspaceDetails'] = $workspaceDetails;

            Log::info('PDF Generation - Rendering view (Web)', ['invoice_id' => $purchaseInvoice->id]);
            $html = view('purchase-invoice.print', $data)->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            Log::info('PDF Generation - Rendering PDF (Web)', ['invoice_id' => $purchaseInvoice->id]);
            $dompdf->render();

            Log::info('PDF Generation - Outputting PDF content (Web)', ['invoice_id' => $purchaseInvoice->id]);
            $pdfContent = $dompdf->output();

            // Generate file name using Invoice ID as prefix
            $fileName = $purchaseInvoice->id . '_' . $purchaseInvoice->invoice_number . '.pdf';

            // Upload the PDF
            $uploadPath = 'pdf/purchase-invoice';
            Log::info('PDF Generation - Uploading PDF (Web)', ['invoice_id' => $purchaseInvoice->id, 'filename' => $fileName, 'path' => $uploadPath]);
            $uploadResult = upload_pdf_content($pdfContent, $uploadPath, $fileName);

            Log::info('PDF Generation - Upload result (Web)', ['invoice_id' => $purchaseInvoice->id, 'result' => $uploadResult]);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                Log::info('PDF Generation - Success (Web)', ['invoice_id' => $purchaseInvoice->id, 'url' => $uploadResult['url']]);
                return $uploadResult['url'];
            }

            Log::warning('PDF Generation - Upload failed (Web)', ['invoice_id' => $purchaseInvoice->id, 'result' => $uploadResult]);
            return null;
        } catch (\Exception $e) {
            Log::error('Purchase Invoice PDF Generation Error (Web): ' . $e->getMessage(), [
                'invoice_id' => $purchaseInvoice->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Print purchase invoice.
     */
    public function print(PurchaseInvoice $purchaseInvoice)
    {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice show')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            $purchaseInvoice->load([
                'items.material',
                'items.gstMaster',
                'supplier',
                'site',
                'purchaseOrder',
                'creator',
                'workspace'
            ]);

            // Get company settings
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
                'company_logo'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }

            // Get workspace details
            $workspaceDetails = null;
            if ($purchaseInvoice->workspace) {
                $workspaceDetails = $purchaseInvoice->workspace;
                $settings['workspace_name'] = $workspaceDetails->name;
            }

              // Check if PDF already exists, delete it and regenerate
            if (!empty($purchaseInvoice->pi_pdf)) {
                // Delete existing PDF file
                try {
                    delete_file($purchaseInvoice->pi_pdf);
                } catch (\Exception $e) {
                    Log::error('Failed to delete existing Purchase Invoice PDF: ' . $e->getMessage());
                }
            }

            // Generate new PDF
            try {
                $workspaceId = $purchaseInvoice->workspace_id ?? getActiveWorkSpace();
                $pdfPath = $this->generatePurchaseInvoicePdf($purchaseInvoice, $workspaceId);
                if ($pdfPath) {
                    $purchaseInvoice->pi_pdf = $pdfPath;
                    $purchaseInvoice->save();
                }
            } catch (\Exception $e) {
                Log::error('Failed to regenerate Purchase Invoice PDF: ' . $e->getMessage());
            }

            return view('purchase-invoice.print', compact('purchaseInvoice', 'settings', 'workspaceDetails'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error loading invoice: ' . $e->getMessage());
        }
    }

    /**
     * Download purchase invoice PDF.
     */
    public function downloadPdf(PurchaseInvoice $purchaseInvoice)
    {
        $user = \Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice show')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            // Check if PDF already exists
            if (!empty($purchaseInvoice->pi_pdf)) {
                $pdfUrl = $purchaseInvoice->pi_pdf;
                // If relative path, prepend the base URL
                if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                    $pdfUrl = url($pdfUrl);
                }
                return redirect()->away($pdfUrl);
            }

            // Generate PDF if not exists
            $workspaceId = $purchaseInvoice->workspace_id ?? getActiveWorkSpace();
            $pdfPath = $this->generatePurchaseInvoicePdf($purchaseInvoice, $workspaceId);

            if ($pdfPath) {
                $purchaseInvoice->pi_pdf = $pdfPath;
                $purchaseInvoice->save();
                $pdfUrl = $pdfPath;
                if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                    $pdfUrl = url($pdfUrl);
                }
                return redirect()->away($pdfUrl);
            }

            return redirect()->back()->with('error', 'Unable to generate PDF.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error downloading PDF: ' . $e->getMessage());
        }
    }
}
