<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentsModule;
use App\Models\PaymentModuleAllocation;
use App\Models\Supplier;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Helpers\LedgerHelper;
use Workdo\Taskly\Entities\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Validator;


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

            // Purchase Orders for advance payments
            $purchaseOrdersQuery = PurchaseOrder::query();
            if (!empty($workspaceId) && $workspaceId != 0) {
                $purchaseOrdersQuery->where('workspace_id', $workspaceId);
            }
            if (!empty($siteId) && $siteId != 0) {
                $purchaseOrdersQuery->where('site_id', $siteId);
            }
            $purchaseOrders = $purchaseOrdersQuery->pluck('po_number', 'id');

            $customFields = null;

            $maxId = PaymentsModule::max('id');
            $i = $maxId ? $maxId + 1 : 1;
            $nextPaymentNumber = 'PAY-' . str_pad($i, 4, '0', STR_PAD_LEFT);

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
                'payment_type' => 'required|in:against_invoice,advance,mixed',
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

                // Safer payment number assignment
                $payment->update([
                    'payment_number' => 'PAY-' . str_pad($payment->id, 4, '0', STR_PAD_LEFT)
                ]);

                // Process allocations if payment is against_invoice or mixed
                if (in_array($request->payment_type, ['against_invoice', 'mixed'])) {
                    $allocations = $request->input('allocations', []);
                    
                    foreach ($allocations as $allocation) {
                        if (!empty($allocation['invoice_id']) && !empty($allocation['amount']) && $allocation['amount'] > 0) {
                            PaymentModuleAllocation::create([
                                'payment_module_id' => $payment->id,
                                'purchase_invoice_id' => $allocation['invoice_id'],
                                'purchase_order_id' => !empty($allocation['order_id']) ? $allocation['order_id'] : null,
                                'allocated_amount' => $allocation['amount'],
                            ]);

                            // Update invoice payment status
                            $this->paymentService->updateInvoicePaymentStatus($allocation['invoice_id']);
                        }
                    }
                }

                // Process advance payment against PO
                if ($request->payment_type === 'advance' && $request->purchase_order_id) {
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

                // Create supplier ledger entry (MANDATORY)
                try {
                    LedgerHelper::createPaymentEntry($payment);
                } catch (\Exception $e) {
                    Log::error('Failed to create supplier ledger entry: ' . $e->getMessage());
                }

                // Auto-close PO if fully paid (optional feature)
                try {
                    $summary = LedgerHelper::getPOSummary($supplierId, $siteId);
                    if ($summary['remaining'] <= 0 && $request->purchase_order_id) {
                        $po = PurchaseOrder::find($request->purchase_order_id);
                        if ($po && $po->status !== 'Closed') {
                            $po->update(['status' => 'Closed']);
                            Log::info('PO auto-closed due to full payment', ['po_id' => $po->id]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to auto-close PO: ' . $e->getMessage());
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
                'payment_type' => 'required|in:against_invoice,advance,mixed',
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

            // Re-run same allocation logic as store()
            if (in_array($request->payment_type, ['against_invoice', 'mixed'])) {
                $allocations = $request->input('allocations', []);
                
                foreach ($allocations as $allocation) {
                    if (!empty($allocation['invoice_id']) && !empty($allocation['amount']) && $allocation['amount'] > 0) {
                        PaymentModuleAllocation::create([
                            'payment_module_id' => $payment->id,
                            'purchase_invoice_id' => $allocation['invoice_id'],
                            'purchase_order_id' => !empty($allocation['order_id']) ? $allocation['order_id'] : null,
                            'allocated_amount' => $allocation['amount'],
                        ]);

                        // Update invoice payment status
                        $this->paymentService->updateInvoicePaymentStatus($allocation['invoice_id']);
                    }
                }
            }

            // Handle advance payment against PO
            if ($request->payment_type === 'advance' && $request->purchase_order_id) {
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
     * Get advance payments that can be adjusted (AJAX).
     * Input: supplier_id
     * Output: id, payment_number, payment_date, amount, unallocated_amount
     * Logic: payment_type = advance AND $payment->getUnallocatedAmount() > 0
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
                ->where('payment_type', 'advance')
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
                    'payment_type' => 'against_invoice',
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
}