<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration is idempotent - checks if columns exist before adding them.
     * Used for local testing when some columns may already exist from previous migrations.
     */
    public function up(): void
    {
        // Add transaction_flow_id and grn_type to purchase_invoices if missing
        if (!Schema::hasColumn('purchase_invoices', 'transaction_flow_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('transaction_flow_id', 50)->nullable()->after('po_id');
                $table->index('transaction_flow_id', 'idx_invoice_transaction_flow');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'grn_type')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                // CRITICAL: No default value - must be nullable to prevent feature flag leakage
                $table->enum('grn_type', ['PO', 'DIRECT'])->nullable()->after('transaction_flow_id');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'po_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('po_id')->nullable()->after('supplier_id');
                $table->foreign('po_id')->references('id')->on('purchase_orders')->nullOnDelete();
                $table->index('po_id');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'is_locked')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->boolean('is_locked')->default(false);
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'locked_at')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable();
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'locked_by')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('locked_by')->nullable();
            });
        }

        // Add transaction_flow_id and locked_to_po to supplier_advances if missing
        if (!Schema::hasColumn('supplier_advances', 'transaction_flow_id')) {
            Schema::table('supplier_advances', function (Blueprint $table) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_advance_transaction_flow');
            });
        }

        if (!Schema::hasColumn('supplier_advances', 'locked_to_po')) {
            Schema::table('supplier_advances', function (Blueprint $table) {
                $table->boolean('locked_to_po')->default(false)->after('po_id');
            });
        }

        // Add transaction_flow_id and status fields to advance_utilizations if missing
        if (!Schema::hasColumn('advance_utilizations', 'transaction_flow_id')) {
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_utilization_transaction_flow');
            });
        }

        if (!Schema::hasColumn('advance_utilizations', 'status')) {
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->enum('status', ['reserved', 'applied', 'reversed'])->default('applied')->after('utilized_amount');
                $table->index('status');
            });
        }

        if (!Schema::hasColumn('advance_utilizations', 'reserved_at')) {
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->timestamp('reserved_at')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('advance_utilizations', 'applied_at')) {
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->timestamp('applied_at')->nullable()->after('reserved_at');
            });
        }

        if (!Schema::hasColumn('advance_utilizations', 'reversed_at')) {
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable()->after('applied_at');
            });
        }

        // Add idempotency_key to payment_requests if missing
        if (!Schema::hasColumn('payment_requests', 'idempotency_key')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->after('id');
            });

            // Add unique constraint only if workspace_id exists
            if (Schema::hasColumn('payment_requests', 'workspace_id')) {
                Schema::table('payment_requests', function (Blueprint $table) {
                    $table->unique(['idempotency_key', 'workspace_id'], 'unique_idempotency_tenant');
                });
            } else {
                // If workspace_id doesn't exist, just add simple unique on idempotency_key
                Schema::table('payment_requests', function (Blueprint $table) {
                    $table->unique('idempotency_key', 'unique_idempotency');
                });
            }
        }

        // Update foreign key constraints on advance_utilizations if needed
        $this->updateAdvanceUtilizationsForeignKeys();
    }

    /**
     * Update foreign key constraints to restrict deletion for hard delete protection
     */
    protected function updateAdvanceUtilizationsForeignKeys(): void
    {
        try {
            // Drop existing foreign keys if they exist
            $connection = DB::connection();
            $databaseName = $connection->getDatabaseName();

            $foreignKeys = $connection->select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'advance_utilizations' AND CONSTRAINT_NAME LIKE 'fk_%'
            ", [$databaseName]);

            foreach ($foreignKeys as $fk) {
                try {
                    Schema::table('advance_utilizations', function (Blueprint $table) use ($fk) {
                        $table->dropForeign($fk->CONSTRAINT_NAME);
                    });
                } catch (\Exception $e) {
                    // Ignore if foreign key doesn't exist
                }
            }

            // Add foreign keys with restrict on delete
            Schema::table('advance_utilizations', function (Blueprint $table) {
                $table->foreign('supplier_advance_id')->references('id')->on('supplier_advances')->onDelete('restrict');
                $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('restrict');
            });
        } catch (\Exception $e) {
            // Log error but don't fail migration
            \Log::warning('Failed to update advance_utilizations foreign keys: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove columns that were added by this migration
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'grn_type')) {
                $table->dropColumn('grn_type');
            }
            if (Schema::hasColumn('purchase_invoices', 'transaction_flow_id')) {
                // Check if index exists before dropping it
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'purchase_invoices' 
                    AND INDEX_NAME = 'idx_invoice_transaction_flow'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex('idx_invoice_transaction_flow');
                }
                $table->dropColumn('transaction_flow_id');
            }
        });

        Schema::table('supplier_advances', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_advances', 'locked_to_po')) {
                $table->dropColumn('locked_to_po');
            }
            if (Schema::hasColumn('supplier_advances', 'transaction_flow_id')) {
                // Check if index exists before dropping it
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'supplier_advances' 
                    AND INDEX_NAME = 'idx_advance_transaction_flow'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex('idx_advance_transaction_flow');
                }
                $table->dropColumn('transaction_flow_id');
            }
        });

        Schema::table('advance_utilizations', function (Blueprint $table) {
            if (Schema::hasColumn('advance_utilizations', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
            if (Schema::hasColumn('advance_utilizations', 'applied_at')) {
                $table->dropColumn('applied_at');
            }
            if (Schema::hasColumn('advance_utilizations', 'reserved_at')) {
                $table->dropColumn('reserved_at');
            }
            if (Schema::hasColumn('advance_utilizations', 'status')) {
                // Check if index exists before dropping it
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'advance_utilizations' 
                    AND INDEX_NAME = 'status'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex('status');
                }
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('advance_utilizations', 'transaction_flow_id')) {
                // Check if index exists before dropping it
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'advance_utilizations' 
                    AND INDEX_NAME = 'idx_utilization_transaction_flow'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex('idx_utilization_transaction_flow');
                }
                $table->dropColumn('transaction_flow_id');
            }
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('payment_requests', 'idempotency_key')) {
                // Check if unique constraints exist before dropping them
                $constraint1Exists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'payment_requests' 
                    AND INDEX_NAME = 'unique_idempotency_tenant'
                ")[0]->count > 0;
                
                $constraint2Exists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'payment_requests' 
                    AND INDEX_NAME = 'unique_idempotency'
                ")[0]->count > 0;
                
                if ($constraint1Exists) {
                    $table->dropUnique('unique_idempotency_tenant');
                } elseif ($constraint2Exists) {
                    $table->dropUnique('unique_idempotency');
                }
                
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
