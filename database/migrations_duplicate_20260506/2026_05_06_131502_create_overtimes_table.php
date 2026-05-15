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
        Schema::create('overtimes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employee_id');
            $table->string('title', 255);
            $table->string('type', 255)->nullable();
            $table->integer('number_of_days');
            $table->integer('hours');
            $table->integer('rate');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 255)->nullable();
            $table->integer('workspace')->nullable();
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtimes');
    }
};
