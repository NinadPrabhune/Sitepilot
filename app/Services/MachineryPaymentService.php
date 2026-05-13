<?php

namespace App\Services;

use App\Models\MachineryBill;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineryPaymentService
{
    /**
     * Create payment request from bills
     */
    public function createFromBills(array $billIds, int $workspaceId, int $userId): PaymentRequest
    {
        // Validate bills exist and are unpaid
        $bills = MachineryBill::where('workspace_id', $workspaceId)
            ->whereIn('id', $billIds)
            ->where('status', 'approved')
            ->with(['supplier', 'billingItems'])
            ->get();

        if ($bills->isEmpty()) {
            throw new \Exception("No valid bills found for payment request");
        }

        // Check if any bills already have payment requests
        $billsWithPR = $bills->whereNotNull('payment_request_id');
        if ($billsWithPR->isNotEmpty()) {
            throw new \Exception("Some bills already have payment requests");
        }

        $totalAmount = $bills->sum('total_amount');

        return DB::transaction(function () use ($bills, $totalAmount, $userId, $workspaceId) {
            // Create payment request
            $paymentRequest = PaymentRequest::create([
                'amount' => $totalAmount,
                'type' => 'machinery',
                'workspace_id' => $workspaceId,
                'created_by' => $userId,
                'status' => 'pending',
                'remarks' => 'Payment request for machinery bills',
                'request_date' => now(),
            ]);

            // Link bills to payment request
            foreach ($bills as $bill) {
                $bill->update([
                    'payment_request_id' => $paymentRequest->id,
                    'status' => 'submitted',
                ]);
            }

            Log::info('Payment request created from bills', [
                'payment_request_id' => $paymentRequest->id,
                'bill_ids' => $billIds,
                'total_amount' => $totalAmount,
                'workspace_id' => $workspaceId,
            ]);

            return $paymentRequest;
        });
    }

    /**
     * Get bills ready for payment
     */
    public function getBillsForPayment(int $workspaceId): \Illuminate\Support\Collection
    {
        return MachineryBill::where('workspace_id', $workspaceId)
            ->where('status', 'approved')
            ->whereNull('payment_request_id')
            ->with(['supplier', 'billingItems.machinery'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get payment summary for bills
     */
    public function getPaymentSummary(array $billIds, int $workspaceId): array
    {
        $bills = MachineryBill::where('workspace_id', $workspaceId)
            ->whereIn('id', $billIds)
            ->with(['supplier', 'billingItems'])
            ->get();

        return [
            'total_bills' => $bills->count(),
            'total_amount' => $bills->sum('total_amount'),
            'suppliers' => $bills->pluck('supplier.name')->unique()->values(),
            'supplier_breakdown' => $bills->groupBy('supplier_id')->map(function ($bills, $supplierId) {
                return [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $bills->first()->supplier->name,
                    'bill_count' => $bills->count(),
                    'total_amount' => $bills->sum('total_amount'),
                ];
            })->values(),
        ];
    }

    /**
     * Validate bills for payment
     */
    public function validateBillsForPayment(array $billIds, int $workspaceId): array
    {
        $bills = MachineryBill::where('workspace_id', $workspaceId)
            ->whereIn('id', $billIds)
            ->get();

        $errors = [];
        $validBills = [];

        foreach ($bills as $bill) {
            if ($bill->status !== 'approved') {
                $errors[] = "Bill #{$bill->id} is not approved (status: {$bill->status})";
                continue;
            }

            if ($bill->payment_request_id) {
                $errors[] = "Bill #{$bill->id} already has payment request #{$bill->payment_request_id}";
                continue;
            }

            $validBills[] = $bill->id;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_bill_ids' => $validBills,
        ];
    }

    /**
     * Cancel payment request and release bills
     */
    public function cancelPaymentRequest(PaymentRequest $paymentRequest, int $workspaceId): bool
    {
        return DB::transaction(function () use ($paymentRequest, $workspaceId) {
            // Update payment request status
            $paymentRequest->update([
                'status' => 'cancelled',
            ]);

            // Release bills back to approved status
            $bills = MachineryBill::where('payment_request_id', $paymentRequest->id)
                ->where('workspace_id', $workspaceId)
                ->get();

            foreach ($bills as $bill) {
                $bill->update([
                    'status' => 'approved',
                    'payment_request_id' => null,
                ]);
            }

            Log::info('Payment request cancelled', [
                'payment_request_id' => $paymentRequest->id,
                'bills_released' => $bills->count(),
                'workspace_id' => $workspaceId,
            ]);

            return true;
        });
    }
}
