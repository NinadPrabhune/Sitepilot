<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if total_amount column exists
        $totalAmountExists = DB::select("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'purchase_orders' 
            AND COLUMN_NAME = 'total_amount'
        ")[0]->count > 0;

        if ($totalAmountExists) {
            DB::statement("
                UPDATE purchase_orders po
                LEFT JOIN (
                    SELECT po_id, SUM(grand_total) as total_invoiced
                    FROM purchase_invoices
                    WHERE po_id IS NOT NULL
                    GROUP BY po_id
                ) inv ON po.id = inv.po_id
                SET po.invoiced_amount = COALESCE(inv.total_invoiced, 0),
                    po.invoiced_status = CASE
                        WHEN COALESCE(inv.total_invoiced, 0) >= po.total_amount THEN 'fully_invoiced'
                        WHEN COALESCE(inv.total_invoiced, 0) > 0 THEN 'partially_invoiced'
                        ELSE 'not_invoiced'
                    END
            ");
        } else {
            // If total_amount doesn't exist, just set invoiced_amount and basic status
            DB::statement("
                UPDATE purchase_orders po
                LEFT JOIN (
                    SELECT po_id, SUM(grand_total) as total_invoiced
                    FROM purchase_invoices
                    WHERE po_id IS NOT NULL
                    GROUP BY po_id
                ) inv ON po.id = inv.po_id
                SET po.invoiced_amount = COALESCE(inv.total_invoiced, 0),
                    po.invoiced_status = CASE
                        WHEN COALESCE(inv.total_invoiced, 0) > 0 THEN 'partially_invoiced'
                        ELSE 'not_invoiced'
                    END
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
