<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Supplier Advance
 * Endpoints for supplier advance management including allocation and utilization
 */
use App\Models\SupplierAdvance;
use App\Models\PurchaseInvoice;
use App\Services\SupplierAdvanceService;
use App\Services\AdvanceAllocationService;
use App\Services\InvoiceAdvanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierAdvanceApiController extends Controller
{
    protected $advanceService;
    protected $allocationService;
    protected $invoiceAdvanceService;

    public function __construct(
        SupplierAdvanceService $advanceService,
        AdvanceAllocationService $allocationService,
        InvoiceAdvanceService $invoiceAdvanceService
    ) {
        $this->advanceService = $advanceService;
        $this->allocationService = $allocationService;
        $this->invoiceAdvanceService = $invoiceAdvanceService;
    }

    /**
     * Create supplier advance
     *
     * Creates a new advance payment for a supplier with idempotency support.
     *
     * @authenticated
     *
     * @urlParam supplierId integer required Supplier ID. Example: 1
     * @bodyParam po_id integer optional Purchase Order ID. Example: 5
     * @bodyParam amount numeric required Advance amount. Example: 50000.00
     * @bodyParam advance_date date required Advance date. Example: 2024-01-15
     * @bodyParam source string required Source (po or manual). Example: po
     * @bodyParam remarks string optional Remarks. Example: Advance for materials
     * @header Idempotency-Key string optional Idempotency key for safe retries.
     *
     * @response status=201 scenario="Created"
     * { "id": 1, "advance_number": "ADV-001", "amount": 50000, "status": "paid", "message": "Advance request created successfully" }
     * @response status=400 scenario="Error"
     * { "error": "Failed to create advance: ..." }
     */
    public function createAdvance(Request $request, $supplierId)
    {
        // API Rule 5: Idempotency check
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $cachedResponse = Cache::get("idempotency:$idempotencyKey");
            if ($cachedResponse) {
                return response()->json($cachedResponse, 200);
            }
        }

        $request->validate([
            'po_id' => 'nullable|exists:purchase_orders,id',
            'amount' => 'required|numeric|min:0',
            'advance_date' => 'required|date',
            'source' => 'required|in:po,manual',
            'remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $supplierId, $idempotencyKey) {
            try {
                // If po_id is provided, validate it belongs to the supplier
                if ($request->po_id) {
                    $po = \App\Models\PurchaseOrder::findOrFail($request->po_id);
                    if ($po->supplier_id != $supplierId) {
                        throw new \Exception('PO does not belong to this supplier');
                    }
                }

                $advance = $this->advanceService->createAdvance(
                    $request->po_id,
                    $request->amount,
                    $request->only(['advance_date', 'source', 'remarks'])
                );

                // Override supplier_id to ensure it matches the request
                $advance->update(['supplier_id' => $supplierId]);

                $response = [
                    'id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'amount' => $advance->amount,
                    'status' => $advance->status,
                    'message' => 'Advance request created successfully',
                ];

                // Cache idempotency response for 24 hours
                if ($idempotencyKey) {
                    Cache::put("idempotency:$idempotencyKey", $response, 86400);
                }

                return response()->json($response, 201);
            } catch (\Exception $e) {
                Log::error('API: Failed to create advance', [
                    'supplier_id' => $supplierId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to create advance: ' . $e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Get supplier advance summary
     *
     * Returns a simplified financial summary of advances for a supplier.
     *
     * @authenticated
     *
     * @urlParam supplierId integer required Supplier ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "supplier_id": 1, "total_advance": 100000, "available_advance": 50000, "allocated_to_invoice": 30000, "utilized_amount": 20000 }
     * @response status=500 scenario="Error"
     * { "error": "Failed to retrieve advance summary" }
     */
    public function getSupplierAdvanceSummary($supplierId)
    {
        try {
            $totalAdvance = SupplierAdvance::forSupplier($supplierId)
                ->paid()
                ->sum('amount');

            $availableAdvance = SupplierAdvance::forSupplier($supplierId)
                ->paid()
                ->get()
                ->sum(function ($advance) {
                    return $advance->getAvailableBalanceAttribute();
                });

            $utilizedAmount = SupplierAdvance::forSupplier($supplierId)
                ->sum('utilized_amount');

            $allocatedToInvoice = SupplierAdvance::forSupplier($supplierId)
                ->sum('allocated_amount');

            // API Rule 1: Return ONLY simplified financial view
            return response()->json([
                'supplier_id' => $supplierId,
                'total_advance' => $totalAdvance,
                'available_advance' => $availableAdvance,
                'allocated_to_invoice' => $allocatedToInvoice,
                'utilized_amount' => $utilizedAmount,
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to get supplier advance summary', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retrieve advance summary',
            ], 500);
        }
    }

    /**
     * Allocate advance to invoice
     *
     * Allocates available advance amounts to an invoice using FIFO.
     *
     * @authenticated
     *
     * @urlParam invoiceId integer required Invoice ID. Example: 5
     * @bodyParam amount numeric optional Specific amount to allocate. If omitted, allocates maximum available. Example: 25000
     * @header Idempotency-Key string optional Idempotency key for safe retries.
     *
     * @response status=200 scenario="Success"
     * { "invoice_id": 5, "success": true, "message": "...", "advance_allocated": 25000, "net_payable": 75000, "allocation_breakdown": [...] }
     * @response status=400 scenario="Failed"
     * { "invoice_id": 5, "success": false, "message": "...", "advance_allocated": 0 }
     */
    public function allocateAdvanceToInvoice(Request $request, $invoiceId)
    {
        // API Rule 5: Idempotency check
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $cachedResponse = Cache::get("idempotency:$idempotencyKey");
            if ($cachedResponse) {
                return response()->json($cachedResponse, 200);
            }
        }

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        // API Rule 4: Transaction-safe allocation
        $result = $this->allocationService->allocateToInvoice($invoiceId);

        $response = [
            'invoice_id' => $invoiceId,
            'success' => $result['success'],
            'message' => $result['message'],
            'advance_allocated' => $result['allocated_amount'] ?? 0,
            'net_payable' => $result['net_payable'] ?? 0,
            'allocation_breakdown' => $result['allocation_breakdown'] ?? [],
        ];

        // Cache idempotency response
        if ($idempotencyKey && $result['success']) {
            Cache::put("idempotency:$idempotencyKey", $response, 86400);
        }

        return response()->json($response, $result['success'] ? 200 : 400);
    }

    /**
     * Release advance allocation
     *
     * Releases advance allocation for an invoice (e.g., when invoice changes).
     *
     * @authenticated
     *
     * @urlParam invoiceId integer required Invoice ID. Example: 5
     * @header Idempotency-Key string optional Idempotency key for safe retries.
     *
     * @response status=200 scenario="Released"
     * { "invoice_id": 5, "allocation_released": true, "message": "Allocation released successfully" }
     * @response status=400 scenario="Failed"
     * { "invoice_id": 5, "allocation_released": false, "message": "Failed to release allocation" }
     */
    public function releaseAdvanceAllocation(Request $request, $invoiceId)
    {
        // API Rule 5: Idempotency check
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $cachedResponse = Cache::get("idempotency:$idempotencyKey");
            if ($cachedResponse) {
                return response()->json($cachedResponse, 200);
            }
        }

        // API Rule 4: Transaction-safe rollback
        $result = $this->allocationService->rollbackAllocation($invoiceId);

        $response = [
            'invoice_id' => $invoiceId,
            'allocation_released' => $result,
            'message' => $result ? 'Allocation released successfully' : 'Failed to release allocation',
        ];

        // Cache idempotency response
        if ($idempotencyKey && $result) {
            Cache::put("idempotency:$idempotencyKey", $response, 86400);
        }

        return response()->json($response, $result ? 200 : 400);
    }

    /**
     * Get invoice net payable
     *
     * Returns the net payable for an invoice with advance breakdown.
     *
     * @authenticated
     *
     * @urlParam invoiceId integer required Invoice ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * { "invoice_id": 5, "invoice_number": "INV-001", "invoice_total": 100000, "direct_payments": 20000, "advance_utilized": 30000, "net_payable": 50000, "advance_breakdown": [...] }
     * @response status=500 scenario="Error"
     * { "error": "Failed to retrieve invoice payable information" }
     */
    public function getInvoiceNetPayable($invoiceId)
    {
        try {
            $result = $this->invoiceAdvanceService->calculateNetPayable($invoiceId);

            // API Rule 1: Simplified financial view
            return response()->json([
                'invoice_id' => $invoiceId,
                'invoice_number' => $result['invoice_number'],
                'invoice_total' => $result['invoice_total'],
                'direct_payments' => $result['direct_payments'],
                'advance_utilized' => $result['advance_utilized'],
                'net_payable' => $result['net_payable'],
                'advance_breakdown' => $result['advance_breakdown'],
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to get invoice net payable', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retrieve invoice payable information',
            ], 500);
        }
    }

    /**
     * Finalize invoice
     *
     * Converts reserved advance amounts to utilized for an invoice.
     *
     * @authenticated
     *
     * @urlParam invoiceId integer required Invoice ID. Example: 5
     *
     * @response status=200 scenario="Finalized"
     * { "invoice_id": 5, "message": "Invoice finalized successfully", "advances_converted": 2 }
     * @response status=400 scenario="Failed"
     * { "error": "Failed to finalize invoice" }
     */
    public function finalizeInvoice($invoiceId)
    {
        return DB::transaction(function () use ($invoiceId) {
            try {
                $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

                // Convert all reserved amounts to utilized
                $utilizations = \App\Models\AdvanceUtilization::where('purchase_invoice_id', $invoiceId)
                    ->get();

                foreach ($utilizations as $utilization) {
                    $advance = $utilization->advance;
                    $advance->update([
                        'reserved_amount' => 0,
                        'reservation_expires_at' => null,
                        'reserved_at' => null,
                    ]);
                }

                $response = [
                    'invoice_id' => $invoiceId,
                    'message' => 'Invoice finalized successfully',
                    'advances_converted' => $utilizations->count(),
                ];

                return response()->json($response, 200);
            } catch (\Exception $e) {
                Log::error('API: Failed to finalize invoice', [
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to finalize invoice',
                ], 400);
            }
        });
    }
}
