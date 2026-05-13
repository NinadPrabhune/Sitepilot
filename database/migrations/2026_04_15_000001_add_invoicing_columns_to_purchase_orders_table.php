<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Add invoicing tracking columns only if they don't exist
            if (!Schema::hasColumn('purchase_orders', 'invoiced_amount')) {
                $table->decimal('invoiced_amount', 15, 2)->default(0.00)->after('total_amount');
            }

            if (!Schema::hasColumn('purchase_orders', 'invoiced_status')) {
                $table->enum('invoiced_status', ['not_invoiced', 'partially_invoiced', 'fully_invoiced'])
                      ->default('not_invoiced')
                      ->after('invoiced_amount');
            }
        });

        // Backfill existing data only if invoiced_status was added
        if (Schema::hasColumn('purchase_orders', 'invoiced_status')) {
            // Use total_amount if it exists, otherwise use 0 as fallback
            $totalAmountColumn = Schema::hasColumn('purchase_orders', 'total_amount') ? 'total_amount' : '0';
            
            if ($totalAmountColumn === '0') {
                // If total_amount doesn't exist, just set invoiced_amount and default status
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
            } else {
                // If total_amount exists, use it for comparison
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
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['invoiced_amount', 'invoiced_status']);
        });
    }
};
