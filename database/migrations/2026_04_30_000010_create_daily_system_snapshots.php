<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_system_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('captured_by')->constrained('users')->onDelete('cascade');
            $table->integer('total_entries')->default(0);
            $table->integer('total_reversals')->default(0);
            $table->string('system_health_status'); // 'healthy', 'warning', 'critical'
            $table->integer('orphan_count')->default(0);
            $table->integer('drift_count')->default(0);
            $table->integer('critical_drift_count')->default(0);
            $table->integer('hash_mismatch_count')->default(0);
            $table->boolean('manual_balance_check')->default(false);
            $table->boolean('manual_balance_matched')->default(true);
            $table->text('manual_balance_notes')->nullable();
            $table->integer('pending_approvals')->default(0);
            $table->integer('oldest_pending_age_hours')->nullable();
            $table->float('reversal_rate_percent')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['snapshot_date', 'workspace_id']);
            $table->index(['snapshot_date', 'workspace_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_system_snapshots');
    }
};
