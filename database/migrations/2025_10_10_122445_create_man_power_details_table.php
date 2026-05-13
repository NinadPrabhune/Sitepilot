<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        
        Schema::dropIfExists('man_power_details');
        
        Schema::create('man_power_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('man_power_master_id');
            $table->unsignedBigInteger('man_power_type_id');
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->foreign('man_power_master_id')->references('id')->on('man_power_masters')->onDelete('cascade');
            $table->foreign('man_power_type_id')->references('id')->on('man_power_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_details');
    }
};
