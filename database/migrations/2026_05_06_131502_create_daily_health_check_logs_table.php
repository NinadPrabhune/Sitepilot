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
        Schema::create('daily_health_check_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('check_date');
            $table->unsignedBigInteger('workspace_id')->index('daily_health_check_logs_workspace_id_foreign');
            $table->unsignedBigInteger('checked_by')->index('daily_health_check_logs_checked_by_foreign');
            $table->string('status');
            $table->integer('orphan_count')->default(0);
            $table->integer('drift_count')->default(0);
            $table->integer('critical_drift_count')->default(0);
            $table->integer('hash_mismatch_count')->default(0);
            $table->boolean('manual_balance_check')->default(false);
            $table->text('manual_balance_notes')->nullable();
            $table->string('action_taken')->nullable();
            $table->text('action_details')->nullable();
            $table->string('issue_category')->nullable();
            $table->timestamp('check_time');
            $table->timestamps();

            $table->index(['check_date', 'workspace_id', 'status']);
            $table->unique(['check_date', 'workspace_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_health_check_logs');
    }
};
