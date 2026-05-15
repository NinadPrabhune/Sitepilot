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
        Schema::create('man_power_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('man_power_master_id')->index('man_power_details_man_power_master_id_foreign');
            $table->unsignedBigInteger('man_power_type_id')->index('man_power_details_man_power_type_id_foreign');
            $table->integer('count')->default(0);
            $table->timestamps();
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
