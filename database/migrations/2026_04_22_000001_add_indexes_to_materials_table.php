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
        Schema::table('materials', function (Blueprint $table) {
            // Add index on status column for faster filtering
            $table->index('status', 'idx_materials_status');
            
            // Add index on category_id for faster joins
            $table->index('category_id', 'idx_materials_category_id');
            
            // Add index on unit_id for faster joins
            $table->index('unit_id', 'idx_materials_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex('idx_materials_status');
            $table->dropIndex('idx_materials_category_id');
            $table->dropIndex('idx_materials_unit_id');
        });
    }
};
