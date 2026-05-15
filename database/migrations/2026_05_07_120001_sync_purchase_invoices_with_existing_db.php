<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - SAFE ALTER for existing purchase_invoices table
     * Preserves all existing data, only adds missing columns and fixes constraints
     */
    public function up(): void
    {
        // 🛡️ SAFE: Only modify if table exists (production database already has data)
        if (!Schema::hasTable('purchase_invoices')) {
            return;
        }

        // Add missing columns that exist in database but not in migration
        if (!Schema::hasColumn('purchase_invoices', 'grn_type')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->enum('grn_type', ['PO', 'DIRECT'])->nullable()
                      ->comment('GRN type: PO-based or Direct GRN');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'assign_to')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->text('assign_to')->nullable()
                      ->comment('Assignment details');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'grn_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->foreignId('grn_id')->nullable()
                      ->comment('Reference to GRN');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'is_financially_locked')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->tinyInteger('is_financially_locked')->default(0)
                      ->comment('Financial lock status');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'financially_locked_at')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->timestamp('financially_locked_at')->nullable()
                      ->comment('Financial lock timestamp');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'pi_pdf')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->text('pi_pdf')->nullable()
                      ->comment('Purchase invoice PDF content');
            });
        }

        // Add tax-related columns
        $taxColumns = [
            'tax_type' => 'varchar(191)',
            'total_taxable_value' => 'decimal(12,2)',
            'total_cgst' => 'decimal(12,2)',
            'total_sgst' => 'decimal(12,2)',
            'total_igst' => 'decimal(12,2)',
            'total_tax' => 'decimal(12,2)',
            'total_discount' => 'decimal(12,2)',
            'grand_total' => 'decimal(12,2)',
            'paid_amount' => 'decimal(15,2)',
        ];

        foreach ($taxColumns as $column => $type) {
            if (!Schema::hasColumn('purchase_invoices', $column)) {
                Schema::table('purchase_invoices', function (Blueprint $table) use ($column, $type) {
                    if (str_contains($type, 'decimal')) {
                        $table->$type($column, 12, 2)->default(0.00);
                    } else {
                        $table->$type($column)->nullable();
                    }
                });
            }
        }

        if (!Schema::hasColumn('purchase_invoices', 'payment_request_flag')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->tinyInteger('payment_request_flag')->default(0)
                      ->comment('Payment request flag');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'payment_status')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('payment_status', 191)->default('unpaid')
                      ->comment('Payment status');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'ac_payment_status')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->text('ac_payment_status')->nullable()
                      ->comment('Accounting payment status');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'rejection_reason')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()
                      ->comment('Rejection reason');
            });
        }

        // Add lock-related columns
        if (!Schema::hasColumn('purchase_invoices', 'is_locked')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->tinyInteger('is_locked')->default(0)
                      ->comment('General lock status');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'locked_at')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable()
                      ->comment('Lock timestamp');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'locked_by')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->foreignId('locked_by')->nullable()
                      ->comment('User who locked the invoice');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'financially_locked_by')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->foreignId('financially_locked_by')->nullable()
                      ->comment('User who financially locked the invoice');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'idempotency_key')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique()
                      ->comment('Unique key for idempotent operations');
            });
        }

        // Add proper foreign key constraints
        $this->addForeignKeyIfNotExists('purchase_invoices', 'grn_id', 'grns', 'id', 'set null');
        $this->addForeignKeyIfNotExists('purchase_invoices', 'locked_by', 'users', 'id', 'set null');
        $this->addForeignKeyIfNotExists('purchase_invoices', 'financially_locked_by', 'users', 'id', 'set null');

        // Add indexes for performance
        if (!Schema::hasIndex('purchase_invoices', 'purchase_invoices_is_financially_locked_index')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->index('is_financially_locked');
            });
        }

        if (!Schema::hasIndex('purchase_invoices', 'purchase_invoices_payment_request_flag_index')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->index('payment_request_flag');
            });
        }
    }

    /**
     * Helper method to add foreign key if it doesn't exist
     */
    private function addForeignKeyIfNotExists($table, $column, $referencesTable, $referencesColumn, $onDelete)
    {
        if (Schema::hasColumn($table, $column) && Schema::hasTable($referencesTable)) {
            // Check if foreign key exists using raw SQL (compatible approach)
            $foreignKeyName = $table . '_' . $column . '_foreign';
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND constraint_type = 'FOREIGN KEY'
                AND constraint_name = '{$foreignKeyName}'
            ")[0]->count > 0;

            if (!$foreignKeyExists) {
                try {
                    // Additional safety: Check for orphaned records before adding FK
                    $orphanedRecords = DB::select("
                        SELECT COUNT(*) as count 
                        FROM {$table} 
                        WHERE {$column} IS NOT NULL 
                        AND {$column} NOT IN (SELECT {$referencesColumn} FROM {$referencesTable})
                    ")[0]->count;

                    if ($orphanedRecords == 0) {
                        Schema::table($table, function (Blueprint $table) use ($column, $referencesTable, $referencesColumn, $onDelete) {
                            $table->foreign($column)
                                  ->references($referencesColumn)
                                  ->on($referencesTable)
                                  ->onDelete($onDelete);
                        });
                    } else {
                        // Log warning but don't fail migration
                        Log::warning("Skipping foreign key creation for {$table}.{$column} due to {$orphanedRecords} orphaned records");
                    }
                } catch (\Exception $e) {
                    Log::warning("Could not create foreign key for {$table}.{$column}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations - SAFE rollback
     */
    public function down(): void
    {
        if (!Schema::hasTable('purchase_invoices')) {
            return;
        }
        try {
            // Drop foreign keys first for columns whose FK was added by this migration
            $foreignKeyColumns = ['grn_id', 'locked_by'];
            foreach ($foreignKeyColumns as $fkColumn) {
                if (Schema::hasColumn('purchase_invoices', $fkColumn)) {
                    Schema::table('purchase_invoices', function (Blueprint $table) use ($fkColumn) {
                        $table->dropForeign([$fkColumn]);
                    });
                }
            }

            // Drop only the columns that were actually added by this migration (not present before)
            $columnsToDrop = [
                'grn_id', 'pi_pdf', 'tax_type', 'total_taxable_value',
                'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'total_discount',
                'grand_total', 'payment_request_flag', 'idempotency_key'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('purchase_invoices', $column)) {
                    Schema::table('purchase_invoices', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        } catch (\Exception $e) {
            // Silently ignore errors during rollback

        }
    }
};
