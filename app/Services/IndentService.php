<?php

namespace App\Services;

use App\Models\Indent;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndentService
{
    /**
     * Recalculate indent status using query-based logic (no model method dependency).
     * This ensures fresh DB state and avoids cached relation bugs.
     *
     * @param int $indentId
     * @return void
     */
    public function recalculate(int $indentId): void
    {
        $indent = Indent::with([])->findOrFail($indentId);

        // Query-based calculation (no cached relations, no model method dependency)
        $totalIndentQuantity = DB::table('indent_items')
            ->where('indent_id', $indentId)
            ->sum('quantity');

        $totalOrderedQuantity = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.indent_id', $indentId)
            ->whereNotIn('purchase_orders.status', [
                PurchaseOrder::STATUS_REJECTED,
                PurchaseOrder::STATUS_CANCELLED
            ])
            ->whereNull('purchase_orders.deleted_at')
            ->sum('purchase_order_items.quantity');

        // Determine status
        if ($totalOrderedQuantity >= $totalIndentQuantity) {
            $newStatus = Indent::STATUS_CLOSED;
        } elseif ($totalOrderedQuantity > 0) {
            $newStatus = Indent::STATUS_PARTIALLY_CLOSED;
        } else {
            $newStatus = Indent::STATUS_OPEN;
        }

        // Update status and touch timestamp
        $indent->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

        Log::info('Indent status recalculated', [
            'indent_id' => $indentId,
            'old_status' => $indent->status,
            'new_status' => $newStatus,
            'total_indent_quantity' => $totalIndentQuantity,
            'total_ordered_quantity' => $totalOrderedQuantity,
        ]);
    }

    /**
     * Bulk recalculate multiple indents (future enhancement for batch operations).
     *
     * @param array $indentIds
     * @return void
     */
    public function recalculateBulk(array $indentIds): void
    {
        foreach ($indentIds as $indentId) {
            $this->recalculate($indentId);
        }
    }
}
