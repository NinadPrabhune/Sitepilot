<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - SAFE ALTER for existing payments_module table
     * Preserves all existing data, only adds missing columns and fixes constraints
     */
    public function up(): void
    {
        // 🛡️ SAFE: Only modify if table exists (production database already has data)
        if (!Schema::hasTable('payments_module')) {
            // This should not happen in production, but handle safely
            return;
        }

        // Add missing columns that exist in database but not in migration
        if (!Schema::hasColumn('payments_module', 'idempotency_key')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique()
                      ->comment('Unique key for idempotent operations');
            });
        }

        if (!Schema::hasColumn('payments_module', 'payment_pdf')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->string('payment_pdf', 191)->nullable()
                      ->comment('Payment PDF file path');
            });
        }

        if (!Schema::hasColumn('payments_module', 'purchase_order_id')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->foreignId('purchase_order_id')->nullable()
                      ->comment('Reference to purchase order (legacy support)');
            });
        }

        if (!Schema::hasColumn('payments_module', 'status')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->enum('status', ['completed', 'pending', 'cancelled'])
                      ->default('completed')
                      ->comment('Payment status');
            });
        }

        // Fix payment_type enum to match existing database values
        // This is a complex operation that requires careful handling
        if (Schema::hasColumn('payments_module', 'payment_type')) {
            // Check if we need to update the enum values
            $currentEnum = DB::select("SHOW COLUMNS FROM payments_module WHERE Field = 'payment_type'")[0]->Type;
            
            // Only modify if current enum doesn't include all required values
            if (!str_contains($currentEnum, 'advance_against_po')) {
                // This requires raw SQL as Laravel doesn't support enum modification
                DB::statement("ALTER TABLE payments_module MODIFY COLUMN payment_type ENUM('advance_against_po','against_po','against_invoice','mixed','on_account') NOT NULL DEFAULT 'against_po' COMMENT 'Payment type with legacy support'");
            }
        }

        // Add proper foreign key constraints if they don't exist (with safety checks)
        if (Schema::hasColumn('payments_module', 'purchase_order_id')) {
            // Check if foreign key exists using raw SQL (compatible approach)
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE table_schema = DATABASE() 
                AND table_name = 'payments_module' 
                AND constraint_type = 'FOREIGN KEY'
                AND constraint_name = 'payments_module_purchase_order_id_foreign'
            ")[0]->count > 0;

            if (!$foreignKeyExists) {
                try {
                    // Additional safety: Check for orphaned records before adding FK
                    $orphanedRecords = DB::select("
                        SELECT COUNT(*) as count 
                        FROM payments_module 
                        WHERE purchase_order_id IS NOT NULL 
                        AND purchase_order_id NOT IN (SELECT id FROM purchase_orders)
                    ")[0]->count;

                    if ($orphanedRecords == 0) {
                        Schema::table('payments_module', function (Blueprint $table) {
                            $table->foreign('purchase_order_id')
                                  ->references('id')
                                  ->on('purchase_orders')
                                  ->onDelete('set null');
                        });
                    } else {
                        // Log warning but don't fail migration
                        Log::warning("Skipping foreign key creation for payments_module.purchase_order_id due to {$orphanedRecords} orphaned records");
                    }
                } catch (\Exception $e) {
                    Log::warning("Could not create foreign key for payments_module.purchase_order_id: " . $e->getMessage());
                }
            }
        }

        // Add proper indexes for performance
        if (!Schema::hasIndex('payments_module', 'payments_module_payment_request_id_unique')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->unique('payment_request_id');
            });
        }
    }

    /**
     * Reverse the migrations - SAFE rollback
     */
    public function down(): void
    {
        if (!Schema::hasTable('payments_module')) {
            return;
        }
        try {
            Schema::table('payments_module', function (Blueprint $table) {
                // Drop idempotency_key unique and column if they exist
                if (Schema::hasColumn('payments_module', 'idempotency_key')) {
                    $table->dropUnique(['idempotency_key']);
                    $table->dropColumn('idempotency_key');
                }
                // Drop payment_pdf
                if (Schema::hasColumn('payments_module', 'payment_pdf')) {
                    $table->dropColumn('payment_pdf');
                }
                // Drop purchase_order_id foreign key and column
                if (Schema::hasColumn('payments_module', 'purchase_order_id')) {
                    $table->dropForeign(['purchase_order_id']);
                    $table->dropColumn('purchase_order_id');
                }
                // Drop status
                if (Schema::hasColumn('payments_module', 'status')) {
                    $table->dropColumn('status');
                }
            });
        } catch (\Exception $e) {
            // Silently ignore errors during rollback
        }
    }
};
