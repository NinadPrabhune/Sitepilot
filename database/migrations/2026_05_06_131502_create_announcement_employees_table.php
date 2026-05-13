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
        Schema::create('announcement_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('announcement_id');
            $table->integer('employee_id');
            $table->integer('created_by');
            $table->integer('workspace')->nullable();
            $table->bigInteger('site_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_employees');
    }
};
