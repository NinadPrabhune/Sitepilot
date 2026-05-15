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
        Schema::create('attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employee_id');
            $table->date('date');
            $table->string('status', 255);
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->time('late')->nullable();
            $table->time('early_leaving')->nullable();
            $table->time('overtime')->nullable();
            $table->time('total_rest')->nullable();
            $table->string('clock_in_latitude', 255)->nullable();
            $table->string('clock_in_longitude', 255)->nullable();
            $table->string('clock_out_latitude', 255)->nullable();
            $table->string('clock_out_longitude', 255)->nullable();
            $table->string('clock_in_image', 255)->nullable();
            $table->string('clock_out_image', 255)->nullable();
            $table->integer('workspace')->nullable();
            $table->bigInteger('site_id')->nullable();
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
