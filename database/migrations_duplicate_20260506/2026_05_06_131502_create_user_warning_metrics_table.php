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
        Schema::create('user_warning_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('date')->index('idx_metrics_date');
            $table->integer('total_overrides')->default(0)->index('idx_metrics_overrides');
            $table->json('warning_types')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'idx_user_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_warning_metrics');
    }
};
