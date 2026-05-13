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
        Schema::create('daily_consumption_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('daily_consumption_master_id')->index('daily_consumption_details_daily_consumption_master_id_foreign');
            $table->unsignedBigInteger('material_id')->index('daily_consumption_details_material_id_foreign');
            $table->decimal('quantity', 10)->default(0);
            $table->string('unit')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->decimal('unit_price', 10)->nullable();
            $table->decimal('total_price', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_consumption_details');
    }
};
