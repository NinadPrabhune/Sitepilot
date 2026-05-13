<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Purchase Invoices
 * Endpoints for purchase invoice management including creation from GRN and ledger integration
 */
use Illuminate\Http\Request;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\LedgerHelper;
use Dompdf\Dompdf;
use Dompdf\Options;

class PurchaseInvoiceApiController extends Controller {

    /**
     * Standard API response format
     */
    private function apiResponse(bool $success, string $message, $data = null, int $status = 200, $creatorName = null) {
        $response = [
            'success' => $success,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($creatorName !== null) {
            $response['creator_name'] = $creatorName;
        }
        
        return response()->json($response, $status);
    }

    /**
     * Get creator name from a single purchase invoice
     */
    private function getCreatorName(?PurchaseInvoice $invoice): ?string {
        if (!$invoice || !$invoice->created_by) {
            return null;
        }
        
        $user = User::find($invoice->created_by);
        return $user ? $user->name : null;
    }

    /**
     * Get creator name from creator ID
     */
    private function getCreatorNameFromId($creatorId): ?string {
        if (!$creatorId) {
            return null;
        }
        
        $user = User::find($creatorId);
        return $user ? $user->name : null;
    }

    /**
     * Get creator name from a collection of purchase invoices
     */
    private function getCreatorNamesFromCollection($invoices): array {
        $creatorIds = $invoices->pluck('created_by')->filter()->unique()->values();
        $users = User::whereIn('id', $creatorIds)->pluck('name', 'id')->toArray();
        
        return $invoices->map(function ($invoice) use ($users) {
            return $users[$invoice->created_by] ?? null;
        })->toArray();
    }

    /**
     * Add creator name to a single invoice
     */
    private function addCreatorNameToInvoice($invoice): mixed {
        if (!$invoice) {
            return $invoice;
        }
        
        $invoiceArray = $invoice instanceof PurchaseInvoice ? $invoice->toArray() : (array) $invoice;
        $invoiceArray['creator_name'] = $this->getCreatorName($invoice);
        
        return $invoiceArray;
    }

    /**
     * Add creator names to a collection of invoices
     */
    private function addCreatorNamesToCollection($invoices): array {
        $creatorIds = $invoices->pluck('created_by')->filter()->unique()->values();
        $users = User::whereIn('id', $creatorIds)->pluck('name', 'id')->toArray();
        
        return $invoices->map(function ($invoice) use ($users) {
            $invoiceArray = $invoice instanceof PurchaseInvoice ? $invoice->toArray() : (array) $invoice;
            $invoiceArray['creator_name'] = $users[$invoice->created_by] ?? null;
            return $invoiceArray;
        })->toArray();
    }

    /**
     * Convert storage URL to file path for deletion
     */
    private function getFilePathForDeletion(?string $filePath): ?string {
        if (empty($filePath)) {
            return null;
        }
        
        // If it's a URL starting with /storage/, convert to relative path
        if (str_starts_with($filePath, '/storage/')) {
            return ltrim(str_replace('/storage/', '', $filePath), '/');
        }
        
        // If it's already a relative path, return as-is
        return $filePath;
    }

    public function index(Request $request) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice manage')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');

            $query = PurchaseInvoice::with('items.material', 'supplier', 'site', 'purchaseOrder');

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $invoices = $query->get();

            // Transform to include assign_to as user objects and payment request status
            $invoicesWithCreator = $invoices->map(function ($invoice) {
                $invoiceArray = $invoice instanceof PurchaseInvoice ? $invoice->toArray() : (array) $invoice;
                $invoiceArray['creator_name'] = $this->getCreatorName($invoice);
                $invoiceArray['assign_to'] = $invoice->assign_to
                    ? User::whereIn('id', explode(',', $invoice->assign_to))
                        ->select('id', 'name')
                        ->get()
                    : [];

                // Payment request logic (same as web view)
                $invoiceArray['is_paid'] = $invoice->isPaid();
                $invoiceArray['has_pending_payment_request'] = $invoice->hasPendingPaymentRequest();
                $invoiceArray['max_allowed_payment_request'] = $invoice->getMaxAllowedPaymentRequest();

                // Check PO payment completion
                $po = $invoice->purchaseOrder;
                $poPaymentCompleted = $po ? $po->isPaymentCompleted() : false;

                // Determine payment request status
                if ($invoiceArray['is_paid']) {
                    $invoiceArray['payment_request_status'] = 'paid';
                    $invoiceArray['payment_request_message'] = 'Invoice is fully paid';
                    $invoiceArray['can_create_payment_request'] = false;
                } elseif ($invoiceArray['has_pending_payment_request']) {
                    $invoiceArray['payment_request_status'] = 'pending';
                    $invoiceArray['payment_request_message'] = 'Payment request pending';
                    $invoiceArray['can_create_payment_request'] = false;
                } elseif ($invoiceArray['max_allowed_payment_request'] > 0 && (!$po || !$poPaymentCompleted)) {
                    $invoiceArray['payment_request_status'] = 'can_request';
                    $invoiceArray['payment_request_message'] = 'Create payment request for this invoice';
                    $invoiceArray['can_create_payment_request'] = true;
                } else {
                    $invoiceArray['payment_request_status'] = 'no_balance';
                    $invoiceArray['payment_request_message'] = 'No remaining balance - covered by advances/payments';
                    $invoiceArray['can_create_payment_request'] = false;
                }

                return $invoiceArray;
            })->toArray();

            return $this->apiResponse(true, 'Purchase invoices fetched successfully', $invoicesWithCreator);
        } catch (\Exception $e) {
            Log::error('Error fetching purchase invoices: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to fetch purchase invoices.', $e->getMessage(), 500);
        }
    }

    public function createData(Request $request) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            // Suppliers
            $suppliers = \App\Models\Supplier::select('id', 'name')->get();

            // Materials - FIX #7: N+1 Query Fix - Eager load unit relation
            $materials = \App\Models\Material::with('unit')
                ->where('category_id', '!=', 3)
                ->get()
                ->mapWithKeys(function ($material) {
                    return [
                        $material->id => [
                            'name'  => $material->name,
                            'price' => $material->price,
                            'unit'  => [
                                'id'   => $material->unit->id,
                                'name' => $material->unit->name,
                            ],
                        ],
                    ];
                });

            $stockData = getCurrentStockBySiteId(
                $siteId,
                null,
                null,
                null,
                null,
                null
            );

            // Sites
            $sitesQuery = \Workdo\Taskly\Entities\Project::query()->projectonly();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $sitesQuery->where('workspace', $workspaceId);
            }
            $sites = $sitesQuery->select('id', 'name')->get();

            // Generate next invoice number
            $nextInvoiceNumber = PurchaseInvoice::generateInvoiceNumber($request->site_id ?? null);

            return $this->apiResponse(true, 'Data fetched successfully', [
                'suppliers' => $suppliers,
                'materials' => $materials,
                'sites' => $sites,
                'stockData' => $stockData,
                'next_invoice_number' => $nextInvoiceNumber,
                'users' => getActiveProjectEmployees(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching purchase invoice create data: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to fetch form data', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created purchase invoice.
     *
     * @bodyParam supplier_invoice_number string optional Supplier invoice number. Example: INV-12345
     * @bodyParam supplier_id integer required Supplier ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam invoice_date date required Invoice date. Example: 2024-01-15
     * @bodyParam invoice_file file optional Invoice document (PDF, JPG, JPEG, PNG, max 2MB).
     * @bodyParam invoice_type string required Invoice type (general_po or minor_misc_service). Example: general_po
     * @bodyParam total_amount number optional Total amount (required for minor_misc_service). Example: 50000.00
     * @bodyParam items array required if invoice_type=general_po Array of invoice items.
     * @bodyParam items.*.material_id integer required if invoice_type=general_po Material ID. Example: 10
     * @bodyParam items.*.quantity number required if invoice_type=general_po Quantity. Example: 100
     * @bodyParam items.*.unit string optional Unit. Example: kg
     * @bodyParam items.*.price number required if invoice_type=general_po Unit price. Example: 500.00
     * @response {"success": true, "message": "Purchase invoice created successfully", "data": {...}}
     */
    public function store(Request $request) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            // FIX #4: Validation Improvement - items required only when invoice_type = general_po
            // FIX #5: Security Fix - Remove created_by from validation, use Auth::id()
            $validated = $request->validate([
                'supplier_invoice_number' => 'nullable|string',
                'supplier_id' => 'required|exists:suppliers,id',
                'workspace_id' => 'required|integer',
                'site_id' => 'required|exists:projects,id',
                'invoice_date' => 'required|date',
                'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'invoice_type' => 'required|in:general_po,minor_misc_service',
                'total_amount' => 'nullable|numeric',
                'items' => 'required_if:invoice_type,general_po|array',
                'items.*.material_id' => 'required_if:invoice_type,general_po|exists:materials,id',
                'items.*.quantity' => 'required_if:invoice_type,general_po|numeric',
                'items.*.unit' => 'nullable|string',
                'items.*.price' => 'required_if:invoice_type,general_po|numeric',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
                'idempotency_key' => 'nullable|string|max:64',
            ]);

            // Idempotency check before invoice creation
            if (!empty($request->idempotency_key)) {
                $existingInvoice = PurchaseInvoice::where('idempotency_key', $request->idempotency_key)
                    ->where('workspace_id', $validated['workspace_id'])
                    ->first();
                
                if ($existingInvoice) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Invoice already exists (idempotent)',
                        'data' => $existingInvoice->load(['items.material', 'supplier', 'site'])->toArray()
                    ], 200);
                }
            }

            // FIX #6: Database Transaction - Wrap in DB transaction
            $purchaseInvoice = DB::transaction(function () use ($validated, $request) {
                // FIX #3: Generate invoice number AFTER record creation using auto-increment ID
                // Explicitly set payment_status to 'unpaid' to ensure clean string without quotes
                $purchaseInvoice = PurchaseInvoice::create([
                    'invoice_date' => $validated['invoice_date'],
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                    'supplier_id' => $validated['supplier_id'],
                    'site_id' => $validated['site_id'],
                    'created_by' => Auth::id(), // FIX #5: Use Auth::id() instead of request
                    'workspace_id' => $validated['workspace_id'],
                    'invoice_type' => $validated['invoice_type'],
                    'payment_status' => 'unpaid', // Ensure proper value formatting without quotes
                    'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
                    'idempotency_key' => $request->idempotency_key,
                ]);

                // Generate invoice number using model method
                $invoiceNumber = PurchaseInvoice::generateInvoiceNumber($purchaseInvoice->site_id);
                $purchaseInvoice->update(['invoice_number' => $invoiceNumber]);

                // Branch logic
                if ($validated['invoice_type'] === 'minor_misc_service') {
                    $purchaseInvoice->update([
                        'total_amount' => $validated['total_amount'] ?? 0,
                    ]);
                    // For minor_misc_service, set grand_total equal to total_amount
                    $purchaseInvoice->grand_total = $purchaseInvoice->total_amount;
                } else {
                    $total = 0;
                    foreach ($validated['items'] ?? [] as $item) {
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
                    // Recalculate grand_total from items
                    $purchaseInvoice->calculateTotals();
                }
                $purchaseInvoice->save();

                // Handle file upload
                if ($request->hasFile('invoice_file')) {
                    $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('invoice_file')->getClientOriginalExtension();
                    $fileNameToStore = time() . '_invoice_' . $invoiceNumber . '_' . $filename . '.' . $extension;

                    $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                    if ($path['flag'] == 0) {
                        throw new \Exception('File upload failed: ' . ($path['msg'] ?? 'Unknown error'));
                    }

                    if (!empty($path['url'])) {
                        $purchaseInvoice->update(['invoice_file' => $path['url']]);
                    }
                }

                // Create supplier ledger entry for TYPE_INVOICE (inside transaction)
                try {
                    app(\App\Services\LedgerService::class)->createInvoiceEntry($purchaseInvoice);
                } catch (\Exception $e) {
                    Log::error('Failed to create supplier ledger entry for invoice: ' . $e->getMessage());
                    throw $e; // Rollback transaction
                }

                return $purchaseInvoice->fresh(['items.material']);
            });

            // PDF generation is now handled by PurchaseInvoiceObserver
            // No need to generate PDF here as it will be automatically generated via observer

            $invoiceArray = $this->addCreatorNameToInvoice($purchaseInvoice);
            $invoiceArray['pi_pdf'] = $purchaseInvoice->pi_pdf ?? null;

            return $this->apiResponse(true, 'Purchase invoice created successfully', $invoiceArray, 201);
        } catch (\Exception $e) {
            Log::error('Error creating purchase invoice: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to create invoice.', $e->getMessage(), 500);
        }
    }

    public function show(PurchaseInvoice $purchase_invoice) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice show')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        $purchase_invoice->load('items.material', 'supplier', 'site', 'purchaseOrder');
        $invoiceWithCreator = $this->addCreatorNameToInvoice($purchase_invoice);
        $invoiceWithCreator['assign_to'] = $purchase_invoice->assign_to
            ? User::whereIn('id', explode(',', $purchase_invoice->assign_to))
                ->select('id', 'name')
                ->get()
            : [];

        // Payment request logic (same as web view)
        $invoiceWithCreator['is_paid'] = $purchase_invoice->isPaid();
        $invoiceWithCreator['has_pending_payment_request'] = $purchase_invoice->hasPendingPaymentRequest();
        $invoiceWithCreator['max_allowed_payment_request'] = $purchase_invoice->getMaxAllowedPaymentRequest();

        // Check PO payment completion
        $po = $purchase_invoice->purchaseOrder;
        $poPaymentCompleted = $po ? $po->isPaymentCompleted() : false;

        // Determine payment request status
        if ($invoiceWithCreator['is_paid']) {
            $invoiceWithCreator['payment_request_status'] = 'paid';
            $invoiceWithCreator['payment_request_message'] = 'Invoice is fully paid';
            $invoiceWithCreator['can_create_payment_request'] = false;
        } elseif ($invoiceWithCreator['has_pending_payment_request']) {
            $invoiceWithCreator['payment_request_status'] = 'pending';
            $invoiceWithCreator['payment_request_message'] = 'Payment request pending';
            $invoiceWithCreator['can_create_payment_request'] = false;
        } elseif ($invoiceWithCreator['max_allowed_payment_request'] > 0 && (!$po || !$poPaymentCompleted)) {
            $invoiceWithCreator['payment_request_status'] = 'can_request';
            $invoiceWithCreator['payment_request_message'] = 'Create payment request for this invoice';
            $invoiceWithCreator['can_create_payment_request'] = true;
        } else {
            $invoiceWithCreator['payment_request_status'] = 'no_balance';
            $invoiceWithCreator['payment_request_message'] = 'No remaining balance - covered by advances/payments';
            $invoiceWithCreator['can_create_payment_request'] = false;
        }

        return $this->apiResponse(true, 'Purchase invoice fetched successfully', $invoiceWithCreator);
    }

    public function update(Request $request, PurchaseInvoice $purchase_invoice) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice edit')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            // FIX #4: Validation Improvement - items required only when invoice_type = general_po
            // FIX #5: Security Fix - Remove created_by from validation
            $validated = $request->validate([
                'supplier_invoice_number' => 'nullable|string',
                'workspace_id' => 'required|integer',
                'supplier_id' => 'required|exists:suppliers,id',
                'site_id' => 'required|exists:projects,id',
                'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'invoice_date' => 'required|date',
                'invoice_type' => 'required|in:general_po,minor_misc_service',
                'total_amount' => 'nullable|numeric',
                'items' => 'required_if:invoice_type,general_po|array',
                'items.*.material_id' => 'required_if:invoice_type,general_po|exists:materials,id',
                'items.*.quantity' => 'required_if:invoice_type,general_po|numeric',
                'items.*.unit' => 'nullable|string',
                'items.*.price' => 'required_if:invoice_type,general_po|numeric',
                'assign_to' => 'nullable|array',
                'assign_to.*' => 'integer|exists:users,id',
            ]);

            // FIX #6: Database Transaction
            $purchaseInvoice = DB::transaction(function () use ($validated, $request, $purchase_invoice) {
                // Update invoice fields - FIX #5: Use Auth::id() for created_by
                $purchase_invoice->update([
                    'supplier_id' => $validated['supplier_id'],
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                    'site_id' => $validated['site_id'],
                    'invoice_date' => $validated['invoice_date'],
                    'created_by' => Auth::id(),
                    'workspace_id' => $validated['workspace_id'],
                    'invoice_type' => $validated['invoice_type'],
                    'assign_to' => $request->assign_to, // Trait mutator handles array to string conversion
                ]);

                // Branch logic
                if ($validated['invoice_type'] === 'minor_misc_service') {
                    $purchase_invoice->items()->delete();
                    $purchase_invoice->update([
                        'total_amount' => $validated['total_amount'] ?? 0,
                    ]);
                    // For minor_misc_service, set grand_total equal to total_amount
                    $purchase_invoice->grand_total = $purchase_invoice->total_amount;
                } else {
                    $purchase_invoice->items()->delete();
                    $total = 0;
                    foreach ($validated['items'] ?? [] as $item) {
                        $subtotal = $item['quantity'] * $item['price'];
                        PurchaseInvoiceItem::create([
                            'purchase_invoice_id' => $purchase_invoice->id,
                            'material_id' => $item['material_id'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'price' => $item['price'],
                            'subtotal' => $subtotal,
                        ]);
                        $total += $subtotal;
                    }
                    $purchase_invoice->update(['total_amount' => $total]);
                    // Recalculate grand_total from items
                    $purchase_invoice->calculateTotals();
                }
                $purchase_invoice->save();

                // FIX #2: File Handling - Handle file upload with proper path conversion
                if ($request->hasFile('invoice_file')) {
                    // Delete old file if exists - FIX #2: Convert URL to path
                    if (!empty($purchase_invoice->invoice_file)) {
                        $oldFilePath = $this->getFilePathForDeletion($purchase_invoice->invoice_file);
                        if ($oldFilePath) {
                            Storage::disk('public')->delete($oldFilePath);
                        }
                    }

                    $filenameWithExt = $request->file('invoice_file')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('invoice_file')->getClientOriginalExtension();
                    $fileNameToStore = time() . '_invoice_' . $purchase_invoice->invoice_number . '_' . $filename . '.' . $extension;

                    $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                    if ($path['flag'] == 0) {
                        throw new \Exception('File upload failed: ' . ($path['msg'] ?? 'Unknown error'));
                    }

                    if (!empty($path['url'])) {
                        $purchase_invoice->update(['invoice_file' => $path['url']]);
                    }
                }

                return $purchase_invoice->fresh(['items.material']);
            });

            // Update supplier ledger entry if exists
            try {
                $existingEntry = \App\Models\SupplierTransaction::where('reference_type', \App\Models\SupplierTransaction::TYPE_INVOICE)
                    ->where('reference_id', $purchase_invoice->id)
                    ->first();
                
                if ($existingEntry) {
                    $existingEntry->update([
                        'reference_amount' => $purchase_invoice->grand_total,
                        'transaction_date' => $purchase_invoice->invoice_date,
                        'description' => "Purchase Invoice {$purchase_invoice->invoice_number} Amount: " . number_format($purchase_invoice->grand_total, 2),
                    ]);
                } else {
                    LedgerHelper::createInvoiceEntry($purchase_invoice);
                }
            } catch (\Exception $e) {
                Log::error('Failed to update supplier ledger entry for invoice: ' . $e->getMessage());
            }

            // PDF regeneration is now handled by PurchaseInvoiceObserver
            // No need to regenerate PDF here as it will be automatically regenerated via observer when relevant fields change

            $invoiceArray = $this->addCreatorNameToInvoice($purchaseInvoice);
            $invoiceArray['pi_pdf'] = $purchase_invoice->pi_pdf ?? null;

            return $this->apiResponse(true, 'Purchase invoice updated successfully', $invoiceArray);
        } catch (\Exception $e) {
            Log::error('Error updating purchase invoice: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to update invoice.', $e->getMessage(), 500);
        }
    }

    public function destroy(PurchaseInvoice $purchase_invoice) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice delete')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            // FIX #1: Bug Fix - Correct variable $purchase_invoice instead of $purchaseInvoice
            // FIX #10: Safety Check - Prevent deletion if invoice exists in payments_module
            $existsInPaymentsModule = \DB::table('payments_module')
                ->where('purchase_invoice_id', $purchase_invoice->id)
                ->exists();

            if ($existsInPaymentsModule) {
                return $this->apiResponse(false, 'Purchase Invoice cannot be deleted because it is used in Payments Module.', null, 400);
            }

            // FIX #2: File Handling - Convert URL to proper storage path before deleting
            if ($purchase_invoice->invoice_file) {
                $filePath = $this->getFilePathForDeletion($purchase_invoice->invoice_file);
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            $purchase_invoice->items()->delete();
            $purchase_invoice->delete();

            // Delete supplier ledger entry
            try {
                LedgerHelper::deleteByReference(\App\Models\SupplierTransaction::TYPE_INVOICE, $purchase_invoice->id);
            } catch (\Exception $e) {
                Log::error('Failed to delete supplier ledger entry for invoice: ' . $e->getMessage());
            }

            return $this->apiResponse(true, 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting purchase invoice: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to delete invoice.', $e->getMessage(), 500);
        }
    }

    public function getPurchaseInvoiceBySupplierId(Request $request) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice manage')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            $invoices = PurchaseInvoice::where('supplier_id', $request->supplier_id)
                ->where('payment_status', '!=', 'paid')
                ->pluck('invoice_number', 'id');

            if ($invoices->isEmpty()) {
                return $this->apiResponse(false, 'No purchase invoices found for this supplier.', null, 404);
            }

            return $this->apiResponse(true, 'Purchase invoices fetched successfully', $invoices);
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'Failed to fetch purchase invoices.', $e->getMessage(), 500);
        }
    }

    public function getPurchaseInvoiceBySupplierIdEdit(Request $request) {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice edit')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }
        
        try {
            $invoices = PurchaseInvoice::where('supplier_id', $request->supplier_id)
                ->where(function ($query) use ($request) {
                    $query->where('payment_status', '!=', 'paid')
                        ->orWhere('id', $request->payments_module_id);
                })
                ->pluck('invoice_number', 'id');

            if ($invoices->isEmpty()) {
                return $this->apiResponse(false, 'No purchase invoices found for this supplier.', null, 404);
            }

            return $this->apiResponse(true, 'Purchase invoices fetched successfully', $invoices);
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'Failed to fetch purchase invoices.', $e->getMessage(), 500);
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

            return $this->apiResponse(true, 'Remaining amount fetched successfully', [
                'remaining_amount' => max($remainingAmount, 0),
            ]);
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'Failed to fetch remaining amount.', $e->getMessage(), 500);
        }
    }

    /**
     * Get GRN details for invoice creation preview
     */
    public function getGrnDetailsForInvoice(Request $request, $grn_id)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }

        try {
        // Use grn_id from route parameter
        if (PurchaseInvoice::where('grn_id', $grn_id)->exists()) {
            return $this->apiResponse(false, 'Invoice already exists for this GRN', null, 400);
        }

        $grn = \App\Models\Grn::with([
            'items.material',
            'items.poItem',
            'items.poItem.gstMaster',
            'items.gstMaster',
            'purchaseOrder',
            'supplier',
            'site'
        ])->findOrFail($grn_id);

        // Generate next invoice number
        $nextInvoiceNumber = PurchaseInvoice::generateInvoiceNumber($grn->site_id);

        // Get tax_type - check GRN first (for direct GRN), then purchase order
        $taxType = $grn->tax_type ?? $grn->purchaseOrder?->tax_type ?? 'cgst';

        // Initialize totals
        $totalTaxableValue = 0;
        $totalDiscount = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        // Calculate item-level values
        $calculatedItems = $grn->items->map(function ($item) use ($taxType, &$totalTaxableValue, &$totalDiscount, &$totalCgst, &$totalSgst, &$totalIgst) {
            $poItem = $item->poItem;
            // For direct GRN, use item's own price; for PO-based GRN, use PO item price; fallback to material price
            $price = (float) ($item->price > 0 ? $item->price : ($poItem ? $poItem->price : ($item->material->price ?? 0)));
            
            // Get GST master - check GRN item first, then PO item
            $gstMaster = null;
            if ($item->gst_master_id) {
                $gstMaster = $item->gstMaster;
            } elseif ($poItem) {
                $gstMaster = $poItem->gstMaster;
            }

            // Basic values
            $quantity = (float) $item->accepted_qty;

            // Base amount = quantity * price
            $baseAmount = round($quantity * $price, 2);

            // Calculate discount
            $perUnitDiscount = null;
            if ($poItem && $poItem->quantity > 0) {
                $perUnitDiscount = $poItem->discount_amount / $poItem->quantity;
            }

            // Discount amount: use per_unit_discount if exists, else use discount (default 0)
            $discountAmount = 0;
            if ($perUnitDiscount !== null) {
                $discountAmount = round($quantity * $perUnitDiscount, 2);
            } elseif ($poItem && $poItem->discount_amount) {
                $discountAmount = (float) $poItem->discount_amount;
            }

            // Prevent discount overflow
            if ($discountAmount > $baseAmount) {
                $discountAmount = $baseAmount;
            }

            // Taxable value = base_amount - discount_amount
            $taxableValue = round($baseAmount - $discountAmount, 2);

            // Calculate GST based on tax_type
            $cgst = 0;
            $sgst = 0;
            $igst = 0;

            if ($gstMaster) {
                if ($taxType === 'igst') {
                    $igstRate = (float) ($gstMaster->igst ?? 0);
                    $igst = round($taxableValue * $igstRate / 100, 2);
                } else {
                    // CGST/SGST mode
                    $cgstRate = (float) ($gstMaster->cgst ?? 0);
                    $sgstRate = (float) ($gstMaster->sgst ?? 0);
                    $cgst = round($taxableValue * $cgstRate / 100, 2);
                    $sgst = round($taxableValue * $sgstRate / 100, 2);
                }
            }

            // Tax amount = cgst + sgst + igst
            $taxAmount = round($cgst + $sgst + $igst, 2);

            // Subtotal = taxable_value + tax_amount
            $subtotal = round($taxableValue + $taxAmount, 2);

            // Accumulate totals
            $totalTaxableValue += $taxableValue;
            $totalDiscount += $discountAmount;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
            $totalIgst += $igst;

            return [
                'grn_item_id' => $item->id,
                'material_id' => $item->material_id,
                'material' => $item->material,
                'accepted_qty' => $quantity,
                'price' => $price,
                'base_amount' => $baseAmount,
                'discount' => $poItem ? $poItem->discount_amount : 0,
                'per_unit_discount' => $perUnitDiscount,
                'discount_amount' => $discountAmount,
                'taxable_value' => $taxableValue,
                'gst_master_id' => $gstMaster ? $gstMaster->id : null,
                'gst' => $gstMaster ? [
                    'id' => $gstMaster->id,
                    'name' => $gstMaster->name,
                    'cgst' => $gstMaster->cgst,
                    'sgst' => $gstMaster->sgst,
                    'igst' => $gstMaster->igst,
                    'total_gst' => $gstMaster->total_gst,
                ] : null,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal,
            ];
        });

        // Calculate invoice-level totals
        $totalTax = round($totalCgst + $totalSgst + $totalIgst, 2);
        $grandTotal = round($totalTaxableValue + $totalTax, 2);

        return $this->apiResponse(true, 'GRN details fetched successfully', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'supplier_id' => $grn->supplier_id,
            'site_id' => $grn->site_id,
            'supplier' => $grn->supplier ? ['id' => $grn->supplier->id, 'name' => $grn->supplier->name] : null,
            'site' => $grn->site ? ['id' => $grn->site->id, 'name' => $grn->site->name] : null,
            'purchase_order' => $grn->purchaseOrder ? [
                'id' => $grn->purchaseOrder->id,
                'po_number' => $grn->purchaseOrder->po_number,
                'tax_type' => $taxType,
            ] : null,
            'next_invoice_number' => $nextInvoiceNumber,
            'items' => $calculatedItems,
            'totals' => [
                'total_taxable_value' => round($totalTaxableValue, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_cgst' => round($totalCgst, 2),
                'total_sgst' => round($totalSgst, 2),
                'total_igst' => round($totalIgst, 2),
                'total_tax' => $totalTax,
                'grand_total' => $grandTotal,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('GRN Details Error: ' . $e->getMessage());
        return $this->apiResponse(false, 'Failed to fetch GRN details', $e->getMessage(), 500);
    }
}

    /**
     * Create Purchase Invoice from GRN (Full quantity invoicing only)
     */
    public function createInvoiceFromGrn(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice create')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }

        try {
        $validated = $request->validate([
            'grn_id' => 'required|exists:grns,id',
            'invoice_date' => 'required|date',
            'supplier_invoice_number' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'workspace_id' => 'nullable|integer',
        ]);

        $invoice = DB::transaction(function () use ($validated, $request) {

            // 🔒 Lock GRN row to prevent duplicate invoice creation
            $grn = \App\Models\Grn::with([
                'purchaseOrder',
                'items.poItem.gstMaster',
                'items.gstMaster',
                'items.material.unit',
                'supplier'
            ])
            ->where('id', $validated['grn_id'])
            ->lockForUpdate()
            ->firstOrFail();

            // ❌ Prevent duplicate invoice
            if (PurchaseInvoice::where('grn_id', $grn->id)->exists()) {
                throw new \Exception('Invoice already exists for this GRN');
            }

            $workspaceId = $validated['workspace_id'] ?? $grn->workspace_id;

            // ✅ Create Invoice - explicitly set payment_status to ensure proper formatting
            $invoice = PurchaseInvoice::create([
                'invoice_type' => 'general_po',
                'invoice_date' => $validated['invoice_date'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_id' => $grn->supplier_id,
                'site_id' => $grn->site_id,
                'po_id' => $grn->po_id,
                'grn_id' => $grn->id,
                'tax_type' => $grn->tax_type ?? optional($grn->purchaseOrder)->tax_type ?? 'cgst',
                'status' => 'Approved',
                'created_by' => Auth::id(),
                'workspace_id' => $workspaceId,
                'payment_status' => 'unpaid', // Ensure proper value formatting without quotes
            ]);

            // Generate Invoice Number
            $invoiceNumber = 'INV-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT);
            $invoice->update(['invoice_number' => $invoiceNumber]);

            // ✅ Create Items (FULL QUANTITY ONLY)
            foreach ($grn->items as $grnItem) {

                $poItem = $grnItem->poItem;
                $isDirectGrn = $grn->isDirectGrn();

                // For Direct GRN, use GRN item's own data; for PO-based, use PO item data
                if ($isDirectGrn) {
                    // Direct GRN: Use GRN item's price, GST master, and material unit
                    $gstMaster = $grnItem->gstMaster ?? null;
                    $quantity = (float) $grnItem->accepted_qty;
                    $price = (float) ($grnItem->price ?? 0);
                    $unit = $grnItem->material->unit->name ?? 'PCS';
                    $discountAmount = 0; // Direct GRN has no PO discount

                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id' => $invoice->id,
                        'grn_item_id' => $grnItem->id,
                        'material_id' => $grnItem->material_id,
                        'gst_master_id' => $gstMaster ? $gstMaster->id : null,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'price' => $price,
                        'discount_amount' => $discountAmount,
                        'tax_amount' => 0,
                        'subtotal' => 0,
                    ]);
                } elseif ($poItem) {
                    // PO-based GRN: Use existing logic with PO item
                    $gstMaster = $poItem->gstMaster ?? null;

                    // Calculate proportional discount: (accepted_qty / ordered_qty) * po_discount
                    $quantity = (float) $grnItem->accepted_qty;
                    $poOrderedQty = (float) ($poItem->quantity ?? 1);
                    $poDiscountAmount = (float) ($poItem->discount_amount ?? 0);

                    // DEBUG: Log discount calculation for diagnosis
                    Log::info('[DEBUG] Discount Calculation - API - PO Item', [
                        'po_item_id' => $poItem->id,
                        'material_id' => $grnItem->material_id,
                        'po_ordered_qty' => $poOrderedQty,
                        'grn_accepted_qty' => $quantity,
                        'po_discount_amount' => $poDiscountAmount,
                        'proportion' => $poOrderedQty > 0 ? round($quantity / $poOrderedQty, 4) : 0,
                    ]);

                    $proportionalDiscount = $poOrderedQty > 0 ? ($quantity / $poOrderedQty) * $poDiscountAmount : 0;

                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id' => $invoice->id,
                        'grn_item_id' => $grnItem->id,
                        'material_id' => $grnItem->material_id,
                        'gst_master_id' => $gstMaster->id ?? null,
                        'quantity' => $quantity,
                        'unit' => $poItem->unit ?? 'PCS',
                        'price' => $poItem->price ?? 0,
                        'discount_amount' => $proportionalDiscount,
                        'tax_amount' => 0,
                        'subtotal' => 0,
                    ]);
                } else {
                    // Skip items with no valid mapping (shouldn't happen in normal flow)
                    Log::warning('Skipping GRN item with no valid mapping', [
                        'grn_item_id' => $grnItem->id,
                        'grn_id' => $grn->id,
                        'po_id' => $grn->po_id,
                    ]);
                    continue;
                }
            }

            // ✅ IMPORTANT: Use model logic
            $invoice->load('items.gstMaster');
            $invoice->calculateTotals();
            $invoice->save();

            // ✅ File Upload
            if ($request->hasFile('invoice_file')) {
                $file = $request->file('invoice_file');

                $fileNameToStore = time() . '_invoice_' . $invoiceNumber . '.' . $file->getClientOriginalExtension();

                $path = upload_file($request, 'invoice_file', $fileNameToStore, 'invoices');

                if ($path['flag'] == 0) {
                    throw new \Exception($path['msg'] ?? 'File upload failed');
                }

                if (!empty($path['url'])) {
                    $invoice->update(['invoice_file' => $path['url']]);
                }
            }

            return $invoice;
        });

        $creatorName = $this->getCreatorNameFromId($invoice->created_by);

        return $this->apiResponse(true, 'Invoice created successfully', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'creator_name' => $creatorName,
        ], 201);

    } catch (\Exception $e) {

        if ($e->getMessage() === 'Invoice already exists for this GRN') {
            return $this->apiResponse(false, $e->getMessage(), null, 400);
        }

        Log::error('GRN Invoice Error: ' . $e->getMessage());

        return $this->apiResponse(false, 'Failed to create invoice', $e->getMessage(), 500);
    }
}

    /**
     * Request payment for a purchase invoice
     * Sets payment_request_flag to 1 for unpaid invoices with no prior request
     */
    public function requestPayment(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAbleTo('purchase-invoice manage')) {
            return $this->apiResponse(false, 'Permission denied', null, 403);
        }

        try {
            $validated = $request->validate([
                'purchase_invoice_id' => 'required|exists:purchase_invoices,id',
            ]);

            $invoice = PurchaseInvoice::findOrFail($validated['purchase_invoice_id']);

            // Check if payment request flag is already set
            if ($invoice->payment_request_flag == 1) {
                return $this->apiResponse(false, 'Payment request already submitted for this invoice.', null, 400);
            }

            // Check if invoice is unpaid
            if (strtolower($invoice->payment_status) !== 'unpaid') {
                return $this->apiResponse(false, 'Payment request is only allowed for unpaid invoices.', null, 400);
            }

            // Update payment_request_flag to 1
            $invoice->update(['payment_request_flag' => 1]);

            return $this->apiResponse(true, 'Payment request submitted successfully', [
                'purchase_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_request_flag' => $invoice->payment_request_flag,
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting payment: ' . $e->getMessage());
            return $this->apiResponse(false, 'Failed to submit payment request.', $e->getMessage(), 500);
        }
    }

    /**
     * Generate and save PDF for Purchase Invoice.
     *
     * @param PurchaseInvoice $purchaseInvoice
     * @param int|null $workspaceId
     * @return string|null
     */
    private function generatePurchaseInvoicePdf(PurchaseInvoice $purchaseInvoice, ?int $workspaceId): ?string
    {
        try {
            Log::info('PDF Generation - Loading relationships', ['invoice_id' => $purchaseInvoice->id]);
            $purchaseInvoice->load([
                'items.material',
                'items.gstMaster',
                'supplier',
                'site',
                'purchaseOrder',
                'creator',
                'workspace'
            ]);

            Log::info('PDF Generation - Loading settings', ['invoice_id' => $purchaseInvoice->id, 'workspace_id' => $workspaceId]);
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
                'company_logo'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key, null, $workspaceId);
            }

            $workspaceDetails = null;
            if ($purchaseInvoice->workspace) {
                $workspaceDetails = $purchaseInvoice->workspace;
                $settings['workspace_name'] = $workspaceDetails->name;
            }

            Log::info('PDF Generation - Initializing Dompdf', ['invoice_id' => $purchaseInvoice->id]);
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

            $dompdf = new Dompdf($options);
            $data['isPdf'] = true;
            $data['purchaseInvoice'] = $purchaseInvoice;
            $data['settings'] = $settings;
            $data['workspaceDetails'] = $workspaceDetails;

            Log::info('PDF Generation - Rendering view', ['invoice_id' => $purchaseInvoice->id]);
            $html = view('purchase-invoice.print', $data)->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            Log::info('PDF Generation - Rendering PDF', ['invoice_id' => $purchaseInvoice->id]);
            $dompdf->render();

            Log::info('PDF Generation - Outputting PDF content', ['invoice_id' => $purchaseInvoice->id]);
            $pdfContent = $dompdf->output();

            $fileName = $purchaseInvoice->id . '_' . $purchaseInvoice->invoice_number . '.pdf';

            $uploadPath = 'pdf/purchase-invoice';
            Log::info('PDF Generation - Uploading PDF', ['invoice_id' => $purchaseInvoice->id, 'filename' => $fileName, 'path' => $uploadPath]);
            $uploadResult = upload_pdf_content($pdfContent, $uploadPath, $fileName);

            Log::info('PDF Generation - Upload result', ['invoice_id' => $purchaseInvoice->id, 'result' => $uploadResult]);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                Log::info('PDF Generation - Success', ['invoice_id' => $purchaseInvoice->id, 'url' => $uploadResult['url']]);
                return $uploadResult['url'];
            }

            Log::warning('PDF Generation - Upload failed', ['invoice_id' => $purchaseInvoice->id, 'result' => $uploadResult]);
            return null;
        } catch (\Exception $e) {
            Log::error('Purchase Invoice PDF Generation Error: ' . $e->getMessage(), [
                'invoice_id' => $purchaseInvoice->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
