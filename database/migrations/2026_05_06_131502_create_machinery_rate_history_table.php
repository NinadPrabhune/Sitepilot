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
        Schema::create('machinery_rate_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machinery_id');
            $table->decimal('rate', 10);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->index('machinery_rate_history_created_by_foreign');
            $table->timestamps();

            $table->index(['effective_from', 'effective_to'], 'idx_effective_range');
            $table->index(['machinery_id', 'effective_from'], 'idx_machinery_effective');
            $table->unique(['machinery_id', 'effective_from'], 'unique_machinery_effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_rate_history');
    }
};
