<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Payments Module
 * Endpoints for supplier payment management including allocation and ledger integration
 */
use App\Models\PaymentsModule;
use App\Models\PaymentModuleAllocation;
use App\Models\Supplier;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SupplierTransaction;
use App\Helpers\LedgerHelper;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentService;
use App\Services\POCalculationService;
use Illuminate\Support\Facades\Validator;
use Dompdf\Dompdf;
use Dompdf\Options;


class PaymentsModuleApiController extends Controller
{

    protected PaymentService $paymentService;


    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Return paginated list of payments (JSON).
     */
    public function index(Request $request)
    {
        try {
            // Collect filters safely
            $supplierId  = $request->query('supplier_id');
            $siteId      = $request->query('site_id');
            $workspaceId = $request->query('workspace_id');

            // Base query with eager loading
            $query = PaymentsModule::with(['supplier', 'invoice', 'site','invoice.creator']);

            // Apply filters only if values are present
            if (!empty($supplierId)) {
                $query->where('supplier_id', $supplierId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace_id', $workspaceId);
            }

            // Fetch results
            $payments = $query->orderByDesc('id')->get();

            // Format response
            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments->map(function ($payment) {
                    $data = $payment->toArray();

                    // Trim supplier to only id + name
                    if (!empty($payment->supplier)) {
                        $data['supplier'] = [
                            'id'   => $payment->supplier->id,
                            'name' => $payment->supplier->name,
                        ];
                    } else {
                        $data['supplier'] = null;
                    }

                    // Trim creator to only id + name
                    if (!empty($payment->invoice) && !empty($payment->invoice->creator)) {
                        $data['invoice']['creator'] = [
                            'id'   => $payment->invoice->creator->id,
                            'name' => $payment->invoice->creator->name,
                        ];
                    } else {
                        $data['invoice']['creator'] = null;
                    }
                    
                    // Trim site to only id + name
                    if (!empty($payment->site)) {
                        $data['site'] = [
                            'id'   => $payment->site->id,
                            'name' => $payment->site->name,
                        ];
                    } else {
                        $data['site'] = null;
                    }

                    return $data;
                })
            ], 200);

        } catch (\Exception $e) {
            Log::error('API Payments index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch payments', 'data' => null], 500);
        }
    }


    public function createData(Request $request)
    {
        try {
            $siteId = $request->input('site_id');
            $workspaceId = $request->input('workspace_id');

            // Get suppliers that have non-draft invoices (matching web app behavior)
            $suppliersQuery = PurchaseInvoice::where('status', '!=', 'draft');
            
            if (!empty($workspaceId) && $workspaceId != 0) {
                $suppliersQuery->where('workspace_id', $workspaceId);
            }
            if (!empty($siteId) && $siteId != 0) {
                $suppliersQuery->where('site_id', $siteId);
            }
            
            $supplierIds = $suppliersQuery->distinct()->pluck('supplier_id')->toArray();
            
            $suppliers = Supplier::whereIn('id', $supplierIds)
                ->orderBy('name')
                ->pluck('name', 'id');

            // Get all suppliers without filtering (for advance payments and other use cases)
            $allSuppliers = Supplier::orderBy('name')->pluck('name', 'id');

            $invoicesQuery = PurchaseInvoice::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $invoicesQuery->where('workspace_id', $workspaceId);
            }
            if (!empty($siteId) && $siteId != 0) {
                $invoicesQuery->where('site_id', $siteId);
            }
            $invoices = $invoicesQuery->pluck('invoice_number', 'id');

            // Sites
            $sitesQuery = \Workdo\Taskly\Entities\Project::query()->projectonly();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $sitesQuery->where('workspace', $workspaceId);
            }
            $sites = $sitesQuery->select('id', 'name')->get();

            // Purchase Orders for advance payments - filter by invoicing_status (not_invoiced, partially_invoiced)
            $purchaseOrdersQuery = PurchaseOrder::whereIn('invoiced_status', [
                'not_invoiced',
                'partially_invoiced'
            ]);
            if (!empty($workspaceId) && $workspaceId != 0) {
                $purchaseOrdersQuery->where('workspace_id', $workspaceId);
            }
            if (!empty($siteId) && $siteId != 0) {
                $purchaseOrdersQuery->where('site_id', $siteId);
            }
            $purchaseOrders = $purchaseOrdersQuery->pluck('po_number', 'id');

            $customFields = null;

            $nextPaymentNumber = PaymentsModule::generatePaymentNumber($request->site_id ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => [
                    'suppliers'         => $suppliers,
                    'all_suppliers'     => $allSuppliers,
                    'invoices'          => $invoices,
                    'sites'             => $sites,
                    'purchase_orders'   => $purchaseOrders,
                    'customFields'      => $customFields,
                    'nextPaymentNumber' => $nextPaymentNumber,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('API Payments createData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load create data', 'data' => null], 500);
        }
    }

    /**
     * Store a newly created payment.
     *
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam supplier_id integer required Supplier ID. Example: 1
     * @bodyParam purchase_invoice_id integer optional Purchase Invoice ID. Example: 5
     * @bodyParam purchase_order_id integer optional Purchase Order ID. Example: 3
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam payment_date date required Payment date. Example: 2024-01-15
     * @bodyParam amount number required Payment amount. Example: 50000.00
     * @bodyParam payment_type string required Payment type (advance_against_po or against_po). Example: against_po
     * @bodyParam mode string optional Payment mode. Example: bank_transfer
     * @bodyParam reference_number string optional Reference number. Example: REF-12345
     * @bodyParam notes string optional Notes. Example: Partial payment for invoice
     * @bodyParam payment_proff_file file optional Payment proof document.
     * @bodyParam ac_payment_status string optional Approval status (pending, approved, rejected). Example: pending
     * @bodyParam rejection_reason string optional Rejection reason. Example: Invalid proof
     * @response {"success": true, "message": "Payment created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'supplier_id' => 'required|exists:suppliers,id',
                'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'site_id' => 'required|exists:projects,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|in:advance_against_po,against_po',
                'mode' => 'nullable|string|max:255',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'payment_proff_file' => 'nullable|file',
                'ac_payment_status' => 'nullable|in:pending,approved,rejected',
                'rejection_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => null], 422);
            }

            // Validate payment against remaining PO liability
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;
            $paymentAmount = floatval($request->amount);
            
            $validation = LedgerHelper::validatePaymentAmount($supplierId, $paymentAmount, $siteId);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false, 
                    'message' => $validation['message'], 
                    'data' => null
                ], 422);
            }

            $data = $request->all();

            // Handle file upload
            if ($request->hasFile('payment_proff_file')) {
                $filenameWithExt = $request->file('payment_proff_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('payment_proff_file')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'payment_proff_file', $fileNameToStore, 'payments/proofs');

                if ($path['flag'] == 0) {
                    return response()->json(['success' => false, 'message' => $path['msg'], 'data' => null], 422);
                }

                if (!empty($path['url'])) {
                    $data['payment_proff_file'] = $path['url'];
                }
            }

            // Use DB transaction to prevent concurrent overpayment
            DB::beginTransaction();
            try {
                // Re-validate with lock to prevent concurrent modifications
                $remainingWithLock = LedgerHelper::getRemainingPOLiabilityWithLock($supplierId, $siteId);
                if ($paymentAmount > $remainingWithLock) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false, 
                        'message' => 'Payment amount exceeds remaining PO liability. Maximum allowable: ' . number_format($remainingWithLock, 2), 
                        'data' => null
                    ], 422);
                }

                // Create payment record
                $payment = PaymentsModule::create($data);

                // Payment number is generated automatically in model's creating event
                // No need to manually set it here

                // Process allocations if payment is against_po - use auto-allocation (FIFO)
                if ($request->payment_type === 'against_po') {
                    app(\App\Services\POCalculationService::class)->autoAllocateToInvoices($payment);
                }

                // Process advance payment against PO
                if ($request->payment_type === 'advance_against_po' && $request->purchase_order_id) {
                    PaymentModuleAllocation::create([
                        'payment_module_id' => $payment->id,
                        'purchase_invoice_id' => null,
                        'purchase_order_id' => $request->purchase_order_id,
                        'allocated_amount' => $payment->amount,
                    ]);
                }

                // Handle advance payment status update (AC payment status)
                if (!empty($request->ac_payment_status)) {
                    $invoiceId = $request->purchase_invoice_id;
                    if ($invoiceId) {
                        DB::table('purchase_invoices')
                            ->where('id', $invoiceId)
                            ->update([
                                'ac_payment_status' => $request->ac_payment_status,
                                'rejection_reason'  => $request->ac_payment_status === 'rejected' 
                                                       ? $request->rejection_reason 
                                                       : null,
                                'updated_at'        => now(),
                            ]);
                    }
                }

                // Generate and save PDF for payment
                try {
                    $pdfPath = $this->generatePaymentPdf($payment, $workspaceId);
                    if ($pdfPath) {
                        $payment->payment_pdf = $pdfPath;
                        $payment->save();
                        Log::info('API Payment PDF generated successfully', ['payment_id' => $payment->id]);
                    }
                } catch (\Exception $e) {
                    Log::error('API Failed to generate Payment PDF: ' . $e->getMessage());
                }

                // Auto-close PO if fully settled (NOT for advance payments)
                // Only settlement payments (against_po, against_invoice) count toward closing PO
                if ($po && $request->payment_type !== 'advance_against_po') {
                    try {
                        // Calculate only settlement payments (direct payments, not via payment requests)
                        $settledAmount = $po->payments()
                            ->whereIn('payment_type', ['against_po', 'against_invoice'])
                            ->whereNull('payment_request_id')
                            ->sum('amount');
                        
                        $poTotal = (float) $po->grand_total;
                        $remaining = max(0, $poTotal - $settledAmount);
                        
                        Log::info('API PO STATUS ANALYSIS', [
                            'po_id' => $po->id,
                            'po_total' => $poTotal,
                            'settled_amount' => $settledAmount,
                            'advance_amount' => $po->payments()
                                ->where('payment_type', 'advance_against_po')
                                ->sum('amount'),
                            'remaining' => $remaining,
                            'payment_type' => $request->payment_type,
                        ]);
                        
                        if ($remaining <= 0) {
                            $po = PurchaseOrder::find($request->purchase_order_id);
                            if ($po && $po->status !== 'Closed') {
                                $po->update(['status' => 'Closed']);
                                Log::info('API PO auto-closed due to full settlement', ['po_id' => $po->id]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('API Failed to auto-close PO: ' . $e->getMessage());
                    }
                }

                DB::commit();

                // Load relationships for response
                $payment->load(['supplier', 'invoice', 'site', 'allocations']);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment created successfully',
                    'data' => $payment
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('API Payments store transaction error: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Failed to create payment: ' . $e->getMessage(), 'data' => null], 500);
            }
        } catch (\Exception $e) {
            Log::error('API Payments store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create payment: ' . $e->getMessage(), 'data' => null], 500);
        }
    }

    public function show($id)
    {
        try {
            // BUG FIX: Use $payment instead of undefined $payments
            $payment = PaymentsModule::with(['supplier', 'invoice', 'site', 'invoice.creator', 'allocations.invoice', 'allocations.purchaseOrder'])->find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'data' => null], 404);
            }
            
            // Return single payment properly (not collection map)
            $data = $payment->toArray();

            // Trim supplier to only id + name
            if (!empty($payment->supplier)) {
                $data['supplier'] = [
                    'id'   => $payment->supplier->id,
                    'name' => $payment->supplier->name,
                ];
            } else {
                $data['supplier'] = null;
            }

            // Trim creator to only id + name
            if (!empty($payment->invoice) && !empty($payment->invoice->creator)) {
                $data['invoice']['creator'] = [
                    'id'   => $payment->invoice->creator->id,
                    'name' => $payment->invoice->creator->name,
                ];
            } else {
                $data['invoice']['creator'] = null;
            }

            // Trim site to only id + name
            if (!empty($payment->site)) {
                $data['site'] = [
                    'id'   => $payment->site->id,
                    'name' => $payment->site->name,
                ];
            } else {
                $data['site'] = null;
            }

            // Trim allocations
            if (!empty($data['allocations'])) {
                foreach ($data['allocations'] as &$allocation) {
                    if (isset($allocation['invoice']) && !empty($allocation['invoice'])) {
                        $allocation['invoice'] = [
                            'id' => $allocation['invoice']['id'],
                            'invoice_number' => $allocation['invoice']['invoice_number'],
                        ];
                    }
                    if (isset($allocation['purchase_order']) && !empty($allocation['purchase_order'])) {
                        $allocation['purchase_order'] = [
                            'id' => $allocation['purchase_order']['id'],
                            'po_number' => $allocation['purchase_order']['po_number'],
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment retrieved successfully',
                'data' => $data
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('API Payments show error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch payment', 'data' => null], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $payment = PaymentsModule::find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'data' => null], 404);
            }

            $validator = Validator::make($request->all(), [
                'created_by' => 'required|integer',
                'workspace_id' => 'required|integer',
                'supplier_id' => 'required|exists:suppliers,id',
                'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'site_id' => 'required|exists:projects,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|in:advance_against_po,against_po',
                'mode' => 'nullable|string|max:255',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'payment_proff_file' => 'nullable|file',
                'ac_payment_status' => 'nullable|in:pending,approved,rejected',
                'rejection_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => null], 422);
            }

            $data = $request->all();

            // Handle file upload with helper
            if ($request->hasFile('payment_proff_file')) {
                // Delete old file if it exists
                if (!empty($payment->payment_proff_file)) {
                    Storage::disk('public')->delete($payment->payment_proff_file);
                }

                // Prepare new filename
                $filenameWithExt = $request->file('payment_proff_file')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('payment_proff_file')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                // Upload using helper
                $path = upload_file($request, 'payment_proff_file', $fileNameToStore, 'payments/proofs');

                if ($path['flag'] == 0) {
                    return response()->json(['success' => false, 'message' => $path['msg'], 'data' => null], 422);
                }

                if (!empty($path['url'])) {
                    $data['payment_proff_file'] = $path['url'];
                }
            }

            $payment->update($data);

            // Update allocations - delete existing first
            $payment->allocations()->delete();

            // Re-run same allocation logic as store() - use auto-allocation (FIFO)
            if ($request->payment_type === 'against_po') {
                app(\App\Services\POCalculationService::class)->autoAllocateToInvoices($payment);
            }

            // Handle advance payment against PO
            if ($request->payment_type === 'advance_against_po' && $request->purchase_order_id) {
                PaymentModuleAllocation::create([
                    'payment_module_id' => $payment->id,
                    'purchase_invoice_id' => null,
                    'purchase_order_id' => $request->purchase_order_id,
                    'allocated_amount' => $payment->amount,
                ]);
            }

            // Handle AC payment status update
            if (!empty($request->ac_payment_status)) {
                $invoiceId = $request->purchase_invoice_id;
                if ($invoiceId) {
                    DB::table('purchase_invoices')
                        ->where('id', $invoiceId)
                        ->update([
                            'ac_payment_status' => $request->ac_payment_status,
                            'rejection_reason'  => $request->ac_payment_status === 'rejected' 
                                                   ? $request->rejection_reason 
                                                   : null,
                            'updated_at'        => now(),
                        ]);
                }
            }

            // Update PO invoice amounts after payment changes
            if (!empty($data['purchase_order_id'])) {
                app(\App\Services\POCalculationService::class)->updatePOInvoiceAmount($data['purchase_order_id']);
            }

            // Regenerate PDF - delete old if exists and generate new
            try {
                // Delete existing PDF if exists
                if (!empty($payment->payment_pdf)) {
                    try {
                        delete_file($payment->payment_pdf);
                        Log::info('API Deleted existing Payment PDF', ['old_path' => $payment->payment_pdf]);
                    } catch (\Exception $e) {
                        Log::error('API Failed to delete existing Payment PDF: ' . $e->getMessage());
                    }
                }

                // Generate new PDF
                $workspaceId = $request->input('workspace_id');
                $pdfPath = $this->generatePaymentPdf($payment, $workspaceId);
                if ($pdfPath) {
                    $payment->payment_pdf = $pdfPath;
                    $payment->save();
                    Log::info('API Payment PDF regenerated successfully', ['payment_id' => $payment->id]);
                }
            } catch (\Exception $e) {
                Log::error('API Failed to regenerate Payment PDF: ' . $e->getMessage());
            }

            // Load relationships for response
            $payment->load(['supplier', 'invoice', 'site', 'allocations']);

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => $payment
            ], 200);
        } catch (\Exception $e) {
            Log::error('API Payments update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update payment', 'data' => null], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $payment = PaymentsModule::find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'data' => null], 404);
            }

            $paymentType = $payment->payment_type;
            
            // Get invoice IDs from allocations before deleting
            $allocationInvoiceIds = $payment->allocations()->pluck('purchase_invoice_id')->filter()->toArray();
            
            // Also check legacy purchase_invoice_id
            $legacyInvoiceId = $payment->purchase_invoice_id;
            
            // Merge both invoice IDs
            $allInvoiceIds = array_merge($allocationInvoiceIds, $legacyInvoiceId ? [$legacyInvoiceId] : []);

            // Delete supplier ledger entries and recalculate balance
            try {
                LedgerHelper::handlePaymentDeletion($payment->id);
            } catch (\Exception $e) {
                Log::error('Failed to delete supplier ledger entry: ' . $e->getMessage());
            }

            // Delete allocations first
            $payment->allocations()->delete();

            // Delete file if exists
            if ($payment->payment_proff_file) {
                Storage::disk('public')->delete($payment->payment_proff_file);
            }

            // Delete the payment record
            $payment->delete();

            // Update invoice payment status for all affected invoices
            foreach ($allInvoiceIds as $invoiceId) {
                if ($invoiceId) {
                    $this->paymentService->updateInvoicePaymentStatus($invoiceId);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            Log::error('API Payments destroy error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete payment', 'data' => null], 500);
        }
    }

    /**
     * Get unpaid invoices for a supplier (AJAX).
     * Input: supplier_id, site_id (optional)
     * Output: id, invoice_number, invoice_date, total_amount, paid_amount, balance
     */
    public function getSupplierUnpaidInvoices(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'site_id' => 'nullable|exists:projects,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => null], 422);
            }

            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;

            $query = PurchaseInvoice::where('supplier_id', $supplierId)
                ->where('status', '!=', 'draft');

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $invoices = $query->orderBy('invoice_date', 'desc')->get();

            $result = [];
            foreach ($invoices as $invoice) {
                $balance = getInvoiceBalance($invoice->id);
                $result[] = [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) ($invoice->total_amount - $balance),
                    'balance' => (float) $balance,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Unpaid invoices retrieved successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            Log::error('API getSupplierUnpaidInvoices error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch unpaid invoices', 'data' => null], 500);
        }
    }

    /**
     * Get advance_against_po payments that can be adjusted (AJAX).
     * Input: supplier_id
     * Output: id, payment_number, payment_date, amount, unallocated_amount
     * Logic: payment_type = advance_against_po AND $payment->getUnallocatedAmount() > 0
     */
    public function getAdjustableAdvances(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => null], 422);
            }

            $supplierId = $request->supplier_id;

            // Get advance payments for this supplier that have unallocated amounts
            $advances = PaymentsModule::where('supplier_id', $supplierId)
                ->where('payment_type', 'advance_against_po')
                ->get()
                ->filter(function ($payment) {
                    return $payment->getUnallocatedAmount() > 0;
                });

            $result = [];
            foreach ($advances as $advance) {
                $result[] = [
                    'id' => $advance->id,
                    'payment_number' => $advance->payment_number,
                    'payment_date' => $advance->payment_date,
                    'amount' => (float) $advance->amount,
                    'unallocated_amount' => (float) $advance->getUnallocatedAmount(),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Adjustable advances retrieved successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            Log::error('API getAdjustableAdvances error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch adjustable advances', 'data' => null], 500);
        }
    }

    /**
     * Prefill payment from Purchase Order
     * 
     * @param int $po_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFromPo($po_id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'site'])->find($po_id);
            
            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase Order not found',
                    'data' => null
                ], 404);
            }

            // Calculate remaining payment against PO grand_total
            $totalAmount = (float) $purchaseOrder->grand_total;
            $paidAmount = $purchaseOrder->payments()
                ->whereIn('payment_type', ['advance_against_po', 'against_po'])
                ->sum('amount');
            $remainingPayment = max(0, $totalAmount - $paidAmount);

            return response()->json([
                'success' => true,
                'message' => 'Payment data prefilled successfully',
                'data' => [
                    'purchase_order_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'supplier_name' => $purchaseOrder->supplier?->name,
                    'site_id' => $purchaseOrder->site_id,
                    'site_name' => $purchaseOrder->site?->name,
                    'order_amount' => (float) ($purchaseOrder->grand_total ?? 0),
                    'paid_amount' => (float) $paidAmount,
                    'remaining_payment' => (float) $remainingPayment,
                    'payment_status' => $purchaseOrder->isInvoicingEligible() ? 'eligible' : 'not_eligible',
                    'invoicing_status' => $purchaseOrder->invoiced_status ?? 'not_invoiced',
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('API Payments createFromPo error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase order data',
                'data' => null
            ], 500);
        }
    }

    /**
     * Prefill payment from Purchase Invoice
     * 
     * @param int $invoice_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFromInvoice($invoice_id)
    {
        try {
            $invoice = PurchaseInvoice::with(['supplier', 'site'])->find($invoice_id);
            
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                    'data' => null
                ], 404);
            }

            // Calculate balance using the helper function
            $balance = getInvoiceBalance($invoice->id);
            $paidAmount = (float) $invoice->total_amount - $balance;
            
            // Determine status
            $status = 'unpaid';
            if ($balance <= 0) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment data prefilled successfully',
                'data' => [
                    'purchase_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_id' => $invoice->supplier_id,
                    'supplier_name' => $invoice->supplier?->name,
                    'site_id' => $invoice->site_id,
                    'site_name' => $invoice->site?->name,
                    'payment_type' => 'against_po',
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_date' => $invoice->invoice_date,
                        'total_amount' => (float) $invoice->total_amount,
                        'paid_amount' => $paidAmount,
                        'balance' => $balance,
                        'status' => $status,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('API Payments createFromInvoice error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to prefill payment data',
                'data' => null
            ], 500);
        }
    }

    /**
     * Get remaining payment for a PO (AJAX).
     * Uses SAME raw SQL aggregation as getPOSummary for consistency.
     */
    public function getRemainingPayment(Request $request)
    {
        try {
            $poId = $request->po_id;
            $invoiceId = $request->invoice_id;
            $supplierId = $request->supplier_id;

            if ($poId) {
                $po = PurchaseOrder::find($poId);
                if ($po) {
                    $supplierId = $po->supplier_id;
                }
            }

            if ($invoiceId) {
                $invoice = \App\Models\PurchaseInvoice::find($invoiceId);
                if ($invoice) {
                    $supplierId = $invoice->supplier_id;
                }
            }

            if (!$supplierId) {
                return response()->json(['success' => false, 'message' => 'Supplier ID is required', 'data' => null], 422);
            }

            $invoiceAmount = PurchaseInvoice::where('supplier_id', $supplierId)
                ->sum('grand_total');

            $paidFromAllocations = PaymentModuleAllocation::whereHas('payment', function ($query) use ($supplierId) {
                $query->where('supplier_id', $supplierId);
            })->sum('allocated_amount');

            $directPayments = PaymentsModule::where('supplier_id', $supplierId)
                ->whereNull('purchase_order_id')
                ->sum('amount');

            $paidAmount = $paidFromAllocations + $directPayments;
            $remainingPayment = $invoiceAmount - $paidAmount;

            return response()->json([
                'success' => true,
                'message' => 'Remaining payment retrieved successfully',
                'data' => [
                    'invoice_amount' => (float) $invoiceAmount,
                    'paid_amount' => (float) $paidAmount,
                    'remaining_payment' => (float) $remainingPayment,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('API getRemainingPayment error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch remaining payment', 'data' => null], 500);
        }
    }

    /**
     * Get PO summary for payment (AJAX).
     * Uses ONLY raw SQL aggregation from supplier_transactions table.
     */
    public function getPOSummary(Request $request)
    {
        try {
            $poId = $request->purchase_order_id;

            if (!$poId) {
                return response()->json(['success' => false, 'message' => 'PO ID is required', 'data' => null], 422);
            }

            $po = \App\Models\PurchaseOrder::findOrFail($poId);
            $supplierId = $po->supplier_id;

            $result = DB::table('supplier_transactions')
                ->selectRaw("
                    SUM(CASE WHEN reference_type = 'po' THEN reference_amount ELSE 0 END) as po_total,
                    SUM(CASE WHEN reference_type = 'invoice' THEN debit ELSE 0 END) as invoice_total,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.payment_subtype')) = 'invoice_payment' THEN credit ELSE 0 END) as invoice_paid,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.payment_subtype')) = 'advance' THEN credit ELSE 0 END) as advance_paid,
                    (SUM(CASE WHEN reference_type = 'invoice' THEN debit ELSE 0 END) - SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.payment_subtype')) = 'invoice_payment' THEN credit ELSE 0 END)) as payable
                ")
                ->where('supplier_id', $supplierId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'PO summary retrieved successfully',
                'data' => [
                    'po_total' => (float) ($result->po_total ?? 0),
                    'invoice_total' => (float) ($result->invoice_total ?? 0),
                    'invoice_paid' => (float) ($result->invoice_paid ?? 0),
                    'payable' => (float) ($result->payable ?? 0),
                    'advance_paid' => (float) ($result->advance_paid ?? 0),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('API getPOSummary error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch PO summary', 'data' => null], 500);
        }
    }

    /**
     * Get POs with pending payments for a supplier (AJAX).
     * Input: supplier_id, site_id (optional)
     * Output: Array of PO with remaining_balance
     */
    public function getPOsWithPendingBalance(Request $request)
    {
        try {
            $supplierId = $request->supplier_id;
            $siteId = $request->site_id;

            if (!$supplierId) {
                return response()->json(['success' => false, 'message' => 'Supplier ID is required', 'data' => null], 422);
            }

            $poQuery = PurchaseOrder::where('supplier_id', $supplierId)
                ->whereIn('invoiced_status', [
                    'not_invoiced',
                    'partially_invoiced'
                ]);

            if ($siteId) {
                $poQuery->where('site_id', $siteId);
            }

            $pos = $poQuery->with('payments')->get();

            $result = [];
            foreach ($pos as $po) {
                // Only count direct payments (not via payment requests)
                $totalPaid = $po->payments()
                    ->whereIn('payment_type', [
                        \App\Models\PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO,
                        \App\Models\PaymentsModule::PAYMENT_TYPE_AGAINST_PO
                    ])
                    ->whereNull('payment_request_id')
                    ->sum('amount');

                $grandTotal = (float) $po->grand_total;
                $remainingBalance = max(0, $grandTotal - $totalPaid);

                $result[] = [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'grand_total' => $grandTotal,
                    'remaining_balance' => $remainingBalance,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'POs with pending balance retrieved successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            Log::error('API getPOsWithPendingBalance error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch POs with pending balance', 'data' => null], 500);
        }
    }

    /**
     * Get supplier ledger entries.
     * 
     * Accepts either po_id or invoice_id (not both).
     * Optional filters: start_date, end_date, type, page, per_page
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSupplierLedger(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'po_id' => 'nullable|integer',
                'invoice_id' => 'nullable|integer',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'type' => 'nullable|string',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => null], 422);
            }

            $poId = $request->po_id;
            $invoiceId = $request->invoice_id;
            $options = $request->only(['start_date', 'end_date', 'type', 'page', 'per_page']);

            $service = app(\App\Services\POCalculationService::class);

            if ($request->has('page')) {
                $result = $service->getSupplierLedgerPaginated($poId, $invoiceId, $options);
                
                $formattedData = array_map(function($entry) {
                    return [
                        'date' => $entry['datetime'],
                        'details' => $entry['details'],
                        'debit' => $entry['debit'] > 0 ? (float) $entry['debit'] : null,
                        'credit' => $entry['credit'] > 0 ? (float) $entry['credit'] : null,
                        'balance' => (float) $entry['running_balance'],
                        'type' => $entry['type'],
                    ];
                }, $result['data']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Supplier ledger retrieved successfully',
                    'data' => $formattedData,
                    'meta' => $result['meta']
                ], 200);
            }

            $entries = $service->getSupplierLedger($poId, $invoiceId, $options);

            $formattedEntries = array_map(function($entry) {
                return [
                    'date' => $entry['datetime'],
                    'details' => $entry['details'],
                    'debit' => $entry['debit'] > 0 ? (float) $entry['debit'] : null,
                    'credit' => $entry['credit'] > 0 ? (float) $entry['credit'] : null,
                    'balance' => (float) $entry['running_balance'],
                    'type' => $entry['type'],
                ];
            }, $entries);

            return response()->json([
                'success' => true,
                'message' => 'Supplier ledger retrieved successfully',
                'data' => $formattedEntries,
                'total' => count($formattedEntries)
            ], 200);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        } catch (\Exception $e) {
            Log::error('API getSupplierLedger error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch supplier ledger', 'data' => null], 500);
        }
    }

    /**
     * Generate and save PDF for Payment.
     *
     * @param PaymentsModule $payment
     * @param int $workspaceId
     * @return string|null
     */
    private function generatePaymentPdf(PaymentsModule $payment, int $workspaceId): ?string
    {
        try {
            Log::info('API PDF DEBUG: Method called', ['payment_id' => $payment->id]);

            Log::info('API Starting Payment PDF generation', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type' => $payment->payment_type
            ]);

            // Load relationships - use 'purchaseOrder' as defined in model
            $payment->load(['supplier', 'site', 'purchaseOrder', 'invoice', 'creator']);
            Log::info('API Relationships loaded');

            // Verify relationship data
            Log::info('API PDF DEBUG: Payment Data', [
                'payment' => $payment->toArray(),
                'supplier' => $payment->supplier ? $payment->supplier->name : 'NULL',
                'site' => $payment->site ? $payment->site->name : 'NULL',
                'po' => $payment->purchaseOrder ? $payment->purchaseOrder->po_number : 'NULL',
                'invoice' => $payment->invoice ? $payment->invoice->invoice_number : 'NULL',
            ]);

            // Get company settings
            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'company_logo', 'company_gst'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key);
            }
            Log::info('API Company settings retrieved');

            // Get workspace details
            $workspaceDetails = null;
            if ($workspaceId) {
                $workspaceDetails = \App\Models\WorkSpace::find($workspaceId);
                if ($workspaceDetails) {
                    // Override settings with workspace details
                    $settings['workspace_name'] = $workspaceDetails->name;
                    $settings['workspace_contact_person'] = $workspaceDetails->contact_person;
                    $settings['workspace_phone'] = $workspaceDetails->phone;
                    $settings['workspace_email'] = $workspaceDetails->email;
                    $settings['workspace_address'] = $workspaceDetails->address;
                    $settings['workspace_city'] = $workspaceDetails->city;
                    $settings['workspace_state'] = $workspaceDetails->state;
                    $settings['workspace_pincode'] = $workspaceDetails->pincode;
                    $settings['workspace_country'] = $workspaceDetails->country;
                    $settings['workspace_gst_number'] = $workspaceDetails->gst_number;
                }
            }
            Log::info('API Workspace details retrieved');

            // Prepare data
            $data = [
                'payment' => $payment,
                'settings' => $settings,
                'workspaceDetails' => $workspaceDetails,
                'isPdf' => true,
            ];

            // Generate PDF using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

            $dompdf = new Dompdf($options);

            Log::info('API PDF DEBUG: Rendering blade view');
            try {
                $html = view('payments-module.pdf.payment_pdf', $data)->render();
                Log::info('API PDF DEBUG: Blade rendered successfully', ['html_length' => strlen($html)]);
            } catch (\Exception $e) {
                Log::error('API PDF DEBUG: Blade render failed', ['error' => $e->getMessage()]);
                throw $e;
            }

            Log::info('API PDF DEBUG: Loading HTML into Dompdf');
            $dompdf->loadHtml($html);
            Log::info('API PDF DEBUG: HTML loaded');

            $dompdf->setPaper('A4', 'portrait');

            Log::info('API PDF DEBUG: Rendering PDF');
            $dompdf->render();
            Log::info('API PDF DEBUG: PDF rendered');

            $pdfContent = $dompdf->output();
            Log::info('API PDF DEBUG: PDF output generated', ['pdf_size' => strlen($pdfContent)]);

            if (empty($pdfContent)) {
                Log::error('API PDF DEBUG: Empty PDF content');
                return null;
            }

            // Upload PDF - use 'pdf/payments' path
            $fileName = $payment->id . '_' . $payment->payment_number . '.pdf';
            Log::info('API PDF DEBUG: Uploading PDF', ['filename' => $fileName]);

            $uploadResult = upload_pdf_content($pdfContent, 'pdf/payments', $fileName);
            Log::info('API PDF DEBUG: Upload Result', ['result' => $uploadResult]);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                Log::info('API PDF DEBUG: PDF upload successful', ['url' => $uploadResult['url']]);
                return $uploadResult['url'];
            }

            Log::error('API PDF DEBUG: PDF upload failed', ['result' => $uploadResult]);
            return null;
        } catch (\Exception $e) {
            Log::error('API PDF DEBUG: Payment PDF Generation Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}