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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Add PO and GRN references
            $table->unsignedBigInteger('po_id')->nullable()->after('site_id');
            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('set null');
            
            $table->unsignedBigInteger('grn_id')->nullable()->after('po_id');
            $table->foreign('grn_id')->references('id')->on('grns')->onDelete('set null');
            
            // Add tax type
            $table->string('tax_type')->nullable()->after('grn_id')->comment('cgst or igst');
            
            // Add tax and totals columns
            $table->decimal('total_taxable_value', 12, 2)->default(0.00)->after('tax_type');
            $table->decimal('total_cgst', 12, 2)->default(0.00)->after('total_taxable_value');
            $table->decimal('total_sgst', 12, 2)->default(0.00)->after('total_cgst');
            $table->decimal('total_igst', 12, 2)->default(0.00)->after('total_sgst');
            $table->decimal('total_tax', 12, 2)->default(0.00)->after('total_igst');
            $table->decimal('total_discount', 12, 2)->default(0.00)->after('total_tax');
            $table->decimal('grand_total', 12, 2)->default(0.00)->after('total_discount');
            
            // Add invoice_type if not exists
            if (!Schema::hasColumn('purchase_invoices', 'invoice_type')) {
                $table->string('invoice_type')->default('general_po')->after('grand_total');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Drop foreign keys safely
            try {
                $table->dropForeign(['po_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            try {
                $table->dropForeign(['grn_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            // Drop columns individually with existence checks
            $columnsToDrop = [
                'po_id',
                'grn_id',
                'tax_type',
                'total_taxable_value',
                'total_cgst',
                'total_sgst',
                'total_igst',
                'total_tax',
                'total_discount',
                'grand_total',
                'invoice_type',
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('purchase_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
