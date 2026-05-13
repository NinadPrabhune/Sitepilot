<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add idempotency key to payments_module for payment execution safety
     * Ensure foreign key constraints are properly configured
     * Add performance indexes for payment request operations
     */
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            // Add idempotency_key column if it doesn't exist
            if (!Schema::hasColumn('payments_module', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('id');
                $table->unique('idempotency_key', 'unique_payment_idempotency');
            }
        });

        // Ensure foreign key constraint on payment_request_id is RESTRICT
        // This prevents accidental deletion of payment requests that have payments
        // Production-safe approach: Try Laravel schema builder first, fallback to raw SQL
        try {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->foreign('payment_request_id')
                    ->references('id')
                    ->on('payment_requests')
                    ->restrictOnDelete();
            });
        } catch (\Exception $e) {
            // Fallback to raw SQL if schema builder fails
            // This ensures foreign key creation works across all environments
            try {
                Schema::table('payments_module', function (Blueprint $table) {
                    $table->dropForeign(['payment_request_id']);
                });
            } catch (\Exception $e) {
                // Foreign key may not exist, continue
            }

            DB::statement("
                ALTER TABLE payments_module
                ADD CONSTRAINT payments_module_payment_request_id_foreign
                FOREIGN KEY (payment_request_id)
                REFERENCES payment_requests(id)
                ON DELETE RESTRICT
            ");
        }

        // Add performance indexes if they don't exist
        $indexes = [
            'idx_payment_request_id' => 'payment_request_id',
            'idx_po_id' => 'purchase_order_id',
            'idx_invoice_id' => 'purchase_invoice_id',
        ];

        foreach ($indexes as $indexName => $columnName) {
            $indexExists = collect(DB::select("
                SHOW INDEX FROM payments_module WHERE Key_name = '$indexName'
            "))->isNotEmpty();

            if (!$indexExists) {
                Schema::table('payments_module', function (Blueprint $table) use ($columnName, $indexName) {
                    $table->index($columnName, $indexName);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            // Drop unique constraint and idempotency_key column
            if (Schema::hasColumn('payments_module', 'idempotency_key')) {
                $table->dropUnique('unique_payment_idempotency');
                $table->dropColumn('idempotency_key');
            }

            // Drop performance indexes
            try {
                $table->dropIndex('idx_payment_request_id');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_po_id');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_invoice_id');
            } catch (\Exception $e) {}

            // Revert foreign key to SET NULL (original behavior)
            // Use raw SQL to ensure it works across environments
            try {
                $table->dropForeign(['payment_request_id']);
            } catch (\Exception $e) {}

            DB::statement("
                ALTER TABLE payments_module
                ADD CONSTRAINT payments_module_payment_request_id_foreign
                FOREIGN KEY (payment_request_id)
                REFERENCES payment_requests(id)
                ON DELETE SET NULL
            ");
        });
    }
};
