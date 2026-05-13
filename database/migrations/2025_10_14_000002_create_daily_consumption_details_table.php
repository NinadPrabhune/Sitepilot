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
        Schema::dropIfExists('daily_consumption_details');

        Schema::create('daily_consumption_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_consumption_master_id');
            $table->foreign('daily_consumption_master_id')->references('id')->on('daily_consumption_masters')->onDelete('cascade');
            $table->unsignedBigInteger('material_id');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(0.00);
            $table->string('unit')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
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
