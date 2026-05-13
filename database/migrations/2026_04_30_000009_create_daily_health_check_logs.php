<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_health_check_logs', function (Blueprint $table) {
            $table->id();
            $table->date('check_date');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('checked_by')->constrained('users')->onDelete('cascade');
            $table->string('status'); // 'ok', 'issue_found', 'critical'
            $table->integer('orphan_count')->default(0);
            $table->integer('drift_count')->default(0);
            $table->integer('critical_drift_count')->default(0);
            $table->integer('hash_mismatch_count')->default(0);
            $table->boolean('manual_balance_check')->default(false);
            $table->text('manual_balance_notes')->nullable();
            $table->string('action_taken')->nullable();
            $table->text('action_details')->nullable();
            $table->string('issue_category')->nullable(); // 'critical', 'operational', 'ux'
            $table->timestamp('check_time');
            $table->timestamps();

            $table->unique(['check_date', 'workspace_id']);
            $table->index(['check_date', 'workspace_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_health_check_logs');
    }
};
