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
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('workspace_id')->index();
            $table->integer('orphan_count')->default(0);
            $table->integer('drift_count')->default(0);
            $table->integer('critical_count')->default(0);
            $table->integer('warning_count')->default(0);
            $table->enum('health_status', ['healthy', 'warning', 'critical'])->default('healthy')->index();
            $table->boolean('block_operations')->default(false);
            $table->integer('total_payment_requests')->default(0);
            $table->integer('verified_payment_requests')->default(0);
            $table->integer('mismatch_payment_requests')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['health_status', 'created_at'], 'idx_status_created');
            $table->index(['workspace_id', 'created_at'], 'idx_workspace_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_health_logs');
    }
};
