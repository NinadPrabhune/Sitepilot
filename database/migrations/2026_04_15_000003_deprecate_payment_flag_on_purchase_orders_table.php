<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // CRITICAL SAFETY CHECK: Only rename if payment_flag exists AND payment_flag_deprecated doesn't exist
        if (Schema::hasColumn('purchase_orders', 'payment_flag') && !Schema::hasColumn('purchase_orders', 'payment_flag_deprecated')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Rename payment_flag to payment_flag_deprecated
                // This marks it as deprecated while keeping the data
                $table->renameColumn('payment_flag', 'payment_flag_deprecated');
            });

            // Add a comment to indicate deprecation
            DB::statement("
                ALTER TABLE purchase_orders
                MODIFY COLUMN payment_flag_deprecated ENUM('pending', 'partial_received', 'fully_received')
                COMMENT 'DEPRECATED: Use invoiced_status instead. Will be dropped after Phase 8 verification.'
            ");
        } elseif (Schema::hasColumn('purchase_orders', 'payment_flag_deprecated')) {
            // Column already renamed - skip
            // This is safe - the migration has already been applied
        } elseif (!Schema::hasColumn('purchase_orders', 'payment_flag')) {
            // payment_flag doesn't exist - skip
            // This is safe - the column may have been dropped in a previous migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // CRITICAL SAFETY CHECK: Only restore if payment_flag_deprecated exists AND payment_flag doesn't exist
        if (Schema::hasColumn('purchase_orders', 'payment_flag_deprecated') && !Schema::hasColumn('purchase_orders', 'payment_flag')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Remove comment
                DB::statement("
                    ALTER TABLE purchase_orders
                    MODIFY COLUMN payment_flag_deprecated ENUM('pending', 'partial_received', 'fully_received')
                    COMMENT ''
                ");

                // Restore original column name
                $table->renameColumn('payment_flag_deprecated', 'payment_flag');
            });
        }
    }
};
