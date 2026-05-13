<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create invariant_logs table for audit logging
     */
    public function up(): void
    {
        if (!Schema::hasTable('invariant_logs')) {
            Schema::create('invariant_logs', function (Blueprint $table) {
                $table->id();
                $table->json('log_data');
                $table->string('action_type', 50);
                $table->string('reference_type', 50);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                // Indexes for performance
                $table->index(['reference_type', 'reference_id'], 'idx_reference');
                $table->index('action_type', 'idx_action_type');
                $table->index('user_id', 'idx_user_id');
                $table->index('created_at', 'idx_created_at');
                
                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invariant_logs');
    }
};
