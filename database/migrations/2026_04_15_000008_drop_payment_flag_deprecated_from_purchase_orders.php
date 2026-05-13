<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration drops the payment_flag_deprecated column from purchase_orders
     * Should ONLY be run after Phase 3-7 are complete and validated
     */
    public function up(): void
    {
        // Verify that invoicing_status is being used
        $posWithInvoicingStatus = DB::table('purchase_orders')
            ->whereNotNull('invoiced_status')
            ->count();

        $totalPOs = DB::table('purchase_orders')->count();

        // Allow if database is empty or all POs have invoicing_status set
        if ($totalPOs > 0 && $posWithInvoicingStatus === 0) {
            throw new \Exception(
                'Cannot drop payment_flag_deprecated: invoicing_status is not populated. ' .
                'Ensure Phase 3 migration completed successfully.'
            );
        }

        // Drop the deprecated column (only if it exists)
        if (Schema::hasColumn('purchase_orders', 'payment_flag_deprecated')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('payment_flag_deprecated');
            });
        }

        Log::channel('payment_audit')->info('Phase 5: Dropped payment_flag_deprecated column', [
            'verified_invoicing_status_count' => $posWithInvoicingStatus,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the column only if it doesn't exist
        if (!Schema::hasColumn('purchase_orders', 'payment_flag')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->enum('payment_flag', ['pending', 'partial_received', 'fully_received'])
                      ->default('pending')
                      ->after('short_closed_by')
                      ->comment('DEPRECATED: Use invoiced_status instead');
            });

            // Backfill with default value
            DB::statement("
                UPDATE purchase_orders
                SET payment_flag = CASE
                    WHEN invoiced_status = 'fully_invoiced' THEN 'fully_received'
                    WHEN invoiced_status = 'partially_invoiced' THEN 'partial_received'
                    ELSE 'pending'
                END
            ");

            Log::channel('payment_audit')->info('Phase 5: Restored payment_flag column');
        }
    }
};
