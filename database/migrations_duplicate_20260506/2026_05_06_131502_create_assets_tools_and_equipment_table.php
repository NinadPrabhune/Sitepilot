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
        Schema::create('assets_tools_and_equipment', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('material_id')->index('assets_tools_and_equipment_material_id_foreign');
            $table->integer('quantity')->default(1);
            $table->enum('operational_status', ['active', 'breakdown', 'scrap'])->default('active');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->string('status')->default('0');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets_tools_and_equipment');
    }
};
