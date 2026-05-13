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
        Schema::create('daily_progress_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ledger_entry_id')->nullable()->index('daily_progress_reports_ledger_entry_id_foreign');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('machinery_id')->index('daily_progress_reports_machinery_id_foreign');
            $table->date('date');
            $table->decimal('machine_start_reading', 10)->nullable();
            $table->decimal('machine_end_reading', 10)->nullable();
            $table->decimal('machine_idle_reading', 10)->nullable()->comment('Idle hours to subtract from billable');
            $table->decimal('billable_hours', 10)->nullable();
            $table->decimal('calculated_amount', 15)->nullable();
            $table->decimal('rate_snapshot', 10)->nullable();
            $table->decimal('override_rate', 10)->nullable();
            $table->string('override_reason')->nullable();
            $table->unsignedBigInteger('override_by')->nullable()->index('daily_progress_reports_override_by_foreign');
            $table->timestamp('override_at')->nullable();
            $table->string('calculation_hash', 64)->nullable()->index('idx_calculation_hash');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->index('idx_payment_status');
            $table->boolean('is_locked')->default(false)->index('idx_is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->index('daily_progress_reports_locked_by_foreign');
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->json('audit_log')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->index('daily_progress_reports_deleted_by_foreign');
            $table->softDeletes()->index('idx_deleted_at');
            $table->integer('number_of_operators')->nullable();
            $table->text('operator_names')->nullable();
            $table->text('work_details')->nullable();
            $table->decimal('diesel_consumption')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->text('machinery_advances')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->unsignedBigInteger('created_by')->index('daily_progress_reports_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('daily_progress_reports_workspace_id_foreign');
            $table->unsignedBigInteger('site_id')->index('daily_progress_reports_site_id_foreign');
            $table->enum('lifecycle_state', ['draft', 'verified', 'locked', 'paid'])->default('draft')->index('idx_dpr_lifecycle_state');
            $table->json('warning_overrides')->nullable();
            $table->integer('warning_override_count')->default(0)->index('idx_dpr_warning_count');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('activity_completed_id')->nullable()->index();
            $table->timestamps();

            $table->index(['lifecycle_state', 'date'], 'idx_dpr_state_date');
            $table->index(['machinery_id', 'date'], 'idx_machinery_date');
            $table->index(['override_rate', 'override_at'], 'idx_rate_override');
            $table->index(['site_id', 'date'], 'idx_site_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_progress_reports');
    }
};
