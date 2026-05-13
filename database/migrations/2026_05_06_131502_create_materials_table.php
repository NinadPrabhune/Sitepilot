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
        Schema::create('materials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('sku')->unique('items_sku_unique');
            $table->string('hsn_sac', 20)->nullable();
            $table->unsignedBigInteger('gst_master_id')->nullable()->index('materials_gst_master_id_foreign');
            $table->unsignedBigInteger('category_id')->index('idx_materials_category_id');
            $table->unsignedBigInteger('unit_id')->index('idx_materials_unit_id');
            $table->text('description')->nullable();
            $table->decimal('price', 10)->default(0);
            $table->integer('reorder_level')->default(10);
            $table->string('status')->default('active')->index('idx_materials_status');
            $table->string('image')->nullable();
            $table->integer('created_by')->default(0);
            $table->timestamps();

            $table->index(['category_id'], 'items_category_id_foreign');
            $table->index(['unit_id'], 'items_unit_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
