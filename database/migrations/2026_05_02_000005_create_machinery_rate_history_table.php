<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create machinery_rate_history table for historical rate tracking
     */
    public function up(): void
    {
        if (!Schema::hasTable('machinery_rate_history')) {
            Schema::create('machinery_rate_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->decimal('rate', 10, 2);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                
                // Indexes for performance
                $table->index(['machinery_id', 'effective_from'], 'idx_machinery_effective');
                $table->index(['effective_from', 'effective_to'], 'idx_effective_range');
                
                // Foreign key constraints
                $table->foreign('machinery_id')->references('id')->on('machineries')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users');
                
                // Unique constraint to prevent duplicate effective dates
                $table->unique(['machinery_id', 'effective_from'], 'unique_machinery_effective');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_rate_history');
    }
};
