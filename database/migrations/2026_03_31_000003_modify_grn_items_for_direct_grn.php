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
        Schema::table('grn_items', function (Blueprint $table) {
            // 1. Make po_item_id nullable
            $table->foreignId('po_item_id')
                ->nullable()
                ->change();
            
            // 2. Add price fields (for direct GRN)
            $table->decimal('price', 15, 2)
                ->default(0)
                ->after('rejected_qty');
            
            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->after('price');
            
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->after('tax_amount');
            
            // 3. Add GST master reference
            $table->unsignedBigInteger('gst_master_id')
                ->nullable()
                ->after('subtotal');
            
            $table->foreign('gst_master_id')
                ->references('id')
                ->on('gst_masters')
                ->onDelete('set null');
            
            // 4. Add index
            $table->index('gst_master_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            // Remove index
            $table->dropIndex(['gst_master_id']);
            
            // Remove foreign key
            $table->dropForeign(['gst_master_id']);
            
            // Remove fields
            $table->dropColumn([
                'gst_master_id',
                'subtotal',
                'tax_amount',
                'price',
            ]);
            
            // Make po_item_id required again
            $table->foreignId('po_item_id')
                ->nullable(false)
                ->change();
        });
    }
};
