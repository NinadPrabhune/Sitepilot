<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create financial_periods table for period locking
     */
    public function up(): void
    {
        if (!Schema::hasTable('financial_periods')) {
            Schema::create('financial_periods', function (Blueprint $table) {
                $table->id();
                $table->enum('period_type', ['month', 'quarter', 'year']);
                $table->date('period_start');
                $table->date('period_end');
                $table->enum('status', ['open', 'closed', 'locked'])->default('open');
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                
                // Indexes
                $table->unique(['period_type', 'period_start'], 'unique_period');
                $table->index('status', 'idx_status');
                $table->index(['period_start', 'period_end'], 'idx_date_range');
                
                // Foreign keys
                $table->foreign('closed_by')->references('id')->on('users');
                $table->foreign('created_by')->references('id')->on('users');
                
                // Constraints
                $table->check('period_end >= period_start');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
    }
};
