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
        // Update purchase_orders table
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Add new columns only if they don't exist
            if (!Schema::hasColumn('purchase_orders', 'tax_type')) {
                $table->enum('tax_type', ['cgst', 'igst'])->default('cgst')->after('status');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_taxable_value')) {
                $table->decimal('total_taxable_value', 15, 2)->default(0)->after('tax_type');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_cgst')) {
                $table->decimal('total_cgst', 15, 2)->default(0)->after('total_taxable_value');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_sgst')) {
                $table->decimal('total_sgst', 15, 2)->default(0)->after('total_cgst');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_igst')) {
                $table->decimal('total_igst', 15, 2)->default(0)->after('total_sgst');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_tax')) {
                $table->decimal('total_tax', 15, 2)->default(0)->after('total_igst');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'total_discount')) {
                $table->decimal('total_discount', 15, 2)->default(0)->after('total_tax');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'additional_charge')) {
                $table->decimal('additional_charge', 15, 2)->default(0)->after('total_discount');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'additional_deduction')) {
                $table->decimal('additional_deduction', 15, 2)->default(0)->after('additional_charge');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'additional_discount')) {
                $table->decimal('additional_discount', 15, 2)->default(0)->after('additional_deduction');
            }
            
            // Handle grand_total - check if it exists or if total_amount exists
            if (!Schema::hasColumn('purchase_orders', 'grand_total') && !Schema::hasColumn('purchase_orders', 'total_amount')) {
                $table->decimal('grand_total', 15, 2)->default(0)->after('additional_discount');
            } elseif (Schema::hasColumn('purchase_orders', 'total_amount') && !Schema::hasColumn('purchase_orders', 'grand_total')) {
                $table->renameColumn('total_amount', 'grand_total');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('grand_total');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'reference_file')) {
                $table->string('reference_file')->nullable()->after('delivery_date');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'delivery_terms_conditions')) {
                $table->text('delivery_terms_conditions')->nullable()->after('reference_file');
            }
            
            if (!Schema::hasColumn('purchase_orders', 'remark')) {
                $table->text('remark')->nullable()->after('delivery_terms_conditions');
            }

            // Add indexes if they don't exist
            $table->index('status', 'idx_purchase_orders_status');
            $table->index('tax_type', 'idx_purchase_orders_tax_type');
            $table->index('delivery_date', 'idx_purchase_orders_delivery_date');
        });

        // Update purchase_order_items table
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Add new columns only if they don't exist
            if (!Schema::hasColumn('purchase_order_items', 'gst_master_id')) {
                $table->foreignId('gst_master_id')->nullable()->constrained('gst_masters')->nullOnDelete()->after('material_id');
            }
            
            if (!Schema::hasColumn('purchase_order_items', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('gst_master_id');
            }
            
            if (!Schema::hasColumn('purchase_order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Drop indexes only if they exist
            $indexesToDrop = [
                'idx_purchase_orders_status',
                'idx_purchase_orders_tax_type',
                'idx_purchase_orders_delivery_date',
            ];
            
            foreach ($indexesToDrop as $index) {
                if (Schema::hasIndex('purchase_orders', $index)) {
                    $table->dropIndex($index);
                }
            }

            // Drop columns
            $columnsToDrop = [
                'tax_type',
                'total_taxable_value',
                'total_cgst',
                'total_sgst',
                'total_igst',
                'total_tax',
                'total_discount',
                'additional_charge',
                'additional_deduction',
                'additional_discount',
                'grand_total',
                'delivery_date',
                'reference_file',
                'delivery_terms_conditions',
                'remark',
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }

            // Rename back if needed - skip for now to avoid errors
            // if (Schema::hasColumn('purchase_orders', 'grand_total')) {
            //     $table->renameColumn('grand_total', 'total_amount');
            // }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Drop foreign key safely
            try {
                $table->dropForeign(['gst_master_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            // Drop columns individually with existence checks
            $columnsToDrop = ['gst_master_id', 'tax_amount', 'discount_amount'];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
