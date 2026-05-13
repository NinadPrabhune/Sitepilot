<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('work_spaces')->onDelete('cascade');
            
            // Health metrics
            $table->integer('orphan_count')->default(0);
            $table->integer('drift_count')->default(0);
            $table->integer('critical_count')->default(0);
            $table->integer('warning_count')->default(0);
            $table->enum('health_status', ['healthy', 'warning', 'critical'])->default('healthy');
            $table->boolean('block_operations')->default(false);
            
            // Hash verification results
            $table->integer('total_payment_requests')->default(0);
            $table->integer('verified_payment_requests')->default(0);
            $table->integer('mismatch_payment_requests')->default(0);
            
            // Metadata
            $table->json('details')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('workspace_id');
            $table->index('health_status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_health_logs');
    }
};
