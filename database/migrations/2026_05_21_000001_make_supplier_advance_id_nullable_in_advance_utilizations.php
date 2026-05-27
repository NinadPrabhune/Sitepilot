<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make supplier_advance_id nullable to support legacy payments_module advances
     */
    public function up(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['supplier_advance_id']);
            
            // Make the column nullable
            $table->unsignedBigInteger('supplier_advance_id')->nullable()->change();
            
            // Re-add the foreign key with cascade
            $table->foreign('supplier_advance_id')
                ->references('id')
                ->on('supplier_advances')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['supplier_advance_id']);
            
            // Make the column NOT NULL
            $table->unsignedBigInteger('supplier_advance_id')->nullable(false)->change();
            
            // Re-add the foreign key with restrict
            $table->foreign('supplier_advance_id')
                ->references('id')
                ->on('supplier_advances')
                ->onDelete('restrict');
        });
    }
};