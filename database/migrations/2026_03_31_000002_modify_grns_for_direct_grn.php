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
        Schema::table('grns', function (Blueprint $table) {
            // 1. Make po_id nullable
            $table->foreignId('po_id')
                ->nullable()
                ->change();
            
            // 2. Add grn_type field
            $table->enum('grn_type', ['against_po', 'direct'])
                ->default('against_po')
                ->after('grn_number');
            
            // 3. Add supplier invoice fields (for direct GRN)
            $table->string('supplier_invoice_number')
                ->nullable()
                ->after('grn_date');
            
            $table->date('supplier_invoice_date')
                ->nullable()
                ->after('supplier_invoice_number');
            
            // 4. Add financial fields (for direct GRN)
            $table->decimal('total_amount', 15, 2)
                ->default(0)
                ->after('supplier_invoice_date');
            
            $table->string('tax_type')
                ->nullable()
                ->after('total_amount');
            
            $table->decimal('total_taxable_value', 15, 2)
                ->default(0)
                ->after('tax_type');
            
            $table->decimal('total_cgst', 15, 2)
                ->default(0)
                ->after('total_taxable_value');
            
            $table->decimal('total_sgst', 15, 2)
                ->default(0)
                ->after('total_cgst');
            
            $table->decimal('total_igst', 15, 2)
                ->default(0)
                ->after('total_sgst');
            
            $table->decimal('total_tax', 15, 2)
                ->default(0)
                ->after('total_igst');
            
            // 5. Add indexes
            $table->index('grn_type');
            $table->index('supplier_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['grn_type']);
            $table->dropIndex(['supplier_invoice_number']);
            
            // Remove financial fields
            $table->dropColumn([
                'total_tax',
                'total_igst',
                'total_sgst',
                'total_cgst',
                'total_taxable_value',
                'tax_type',
                'total_amount',
                'supplier_invoice_date',
                'supplier_invoice_number',
                'grn_type',
            ]);
            
            // Make po_id required again
            $table->foreignId('po_id')
                ->nullable(false)
                ->change();
        });
    }
};
