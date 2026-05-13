<?php

namespace App\Services;

use App\Models\MachineryBill;
use App\Models\MachineryBillingItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillGroupingService
{
    /**
     * Group billing items by supplier and create bills
     */
    public function groupBySupplier(int $month, int $year, int $workspaceId): \Illuminate\Support\Collection
    {
        $fromDate = now()->create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        // Get unbilled items grouped by supplier
        $itemsBySupplier = MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereBetween('from_date', [$fromDate, $toDate])
            ->whereNull('bill_id')
            ->with(['machinery', 'supplier'])
            ->get()
            ->groupBy('supplier_id');

        $bills = collect();

        foreach ($itemsBySupplier as $supplierId => $items) {
            if ($items->isEmpty()) {
                continue;
            }

            $totalAmount = $items->sum('amount');
            $totalHours = $items->sum('total_hours');
            $totalDiesel = $items->sum('total_diesel');
            $totalDprs = $items->count();

            $bill = MachineryBill::create([
                'supplier_id' => $supplierId,
                'workspace_id' => $workspaceId,
                'created_by' => auth()->id(),
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'total_amount' => $totalAmount,
                'total_hours' => $totalHours,
                'total_diesel' => $totalDiesel,
                'total_dprs' => $totalDprs,
                'status' => 'draft',
                'audit_snapshot' => [
                    'generated_at' => now()->toISOString(),
                    'items_count' => $totalDprs,
                    'calculation_method' => 'monthly_grouping',
                    'workspace_id' => $workspaceId,
                ],
            ]);

            // Link items to bill
            foreach ($items as $item) {
                $item->update(['bill_id' => $bill->id]);
            }

            $bills->push($bill);

            Log::info('Bill created from grouped items', [
                'bill_id' => $bill->id,
                'supplier_id' => $supplierId,
                'total_amount' => $totalAmount,
                'items_count' => $totalDprs,
            ]);
        }

        return $bills;
    }

    /**
     * Get unbilled items summary by supplier
     */
    public function getUnbilledSummary(int $month, int $year, int $workspaceId): array
    {
        $fromDate = now()->create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        $summary = MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereBetween('from_date', [$fromDate, $toDate])
            ->whereNull('bill_id')
            ->with(['supplier'])
            ->get()
            ->groupBy('supplier_id')
            ->map(function ($items, $supplierId) {
                return [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $items->first()->supplier->name ?? 'Unknown',
                    'items_count' => $items->count(),
                    'total_amount' => $items->sum('amount'),
                    'total_hours' => $items->sum('total_hours'),
                    'total_diesel' => $items->sum('total_diesel'),
                ];
            });

        return [
            'suppliers' => $summary->values(),
            'total_suppliers' => $summary->count(),
            'total_amount' => $summary->sum('total_amount'),
            'total_items' => $summary->sum('items_count'),
        ];
    }

    /**
     * Check if any unbilled items exist
     */
    public function hasUnbilledItems(int $month, int $year, int $workspaceId): bool
    {
        $fromDate = now()->create($year, $month, 1)->startOfDay();
        $toDate = $fromDate->copy()->endOfMonth()->endOfDay();

        return MachineryBillingItem::where('workspace_id', $workspaceId)
            ->whereBetween('from_date', [$fromDate, $toDate])
            ->whereNull('bill_id')
            ->exists();
    }

    /**
     * Ungroup items from bills (for regeneration)
     */
    public function ungroupItems(array $billIds, int $workspaceId): int
    {
        $count = MachineryBillingItem::whereIn('bill_id', $billIds)
            ->where('workspace_id', $workspaceId)
            ->update(['bill_id' => null]);

        Log::info('Ungrouped items from bills', [
            'bill_ids' => $billIds,
            'items_count' => $count,
            'workspace_id' => $workspaceId,
        ]);

        return $count;
    }
}
