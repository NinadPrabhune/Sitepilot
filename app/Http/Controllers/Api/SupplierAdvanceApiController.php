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
     * Create advance for supplier.
     * API Rule 2: API-driven service
     * API Rule 5: Idempotency support
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * Get supplier advance summary.
     * API Rule 1: Simplified response (no internal ledger logic)
     * 
     * @param int $supplierId
     * @return \Illuminate\Http\JsonResponse
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
     * Allocate advance to invoice.
     * API Rule 3: Mobile sends only intent (invoice_id, amount), backend controls FIFO
     * API Rule 4: Transaction-safe
     * API Rule 5: Idempotency support
     * 
     * @param Request $request
     * @param int $invoiceId
     * @return \Illuminate\Http\JsonResponse
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
     * Release advance allocation (if invoice changes).
     * API Rule 4: Transaction-safe
     * API Rule 5: Idempotency support
     * 
     * @param Request $request
     * @param int $invoiceId
     * @return \Illuminate\Http\JsonResponse
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
     * Get invoice net payable with advance breakdown.
     * API Rule 1: Simplified response
     * 
     * @param int $invoiceId
     * @return \Illuminate\Http\JsonResponse
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
     * Finalize invoice (convert reserved to utilized).
     * 
     * @param int $invoiceId
     * @return \Illuminate\Http\JsonResponse
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
