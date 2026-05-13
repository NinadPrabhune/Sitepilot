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
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->decimal('machine_start_reading', 10, 2)->nullable()->change();
            $table->decimal('machine_end_reading', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->integer('machine_start_reading')->nullable()->change();
            $table->integer('machine_end_reading')->nullable()->change();
        });
    }
};
