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
        Schema::create('daily_consumption_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ledger_entry_id')->nullable()->index('daily_consumption_masters_ledger_entry_id_foreign');
            $table->unsignedBigInteger('supplier_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('daily_progress_report_id')->nullable()->index('fk_daily_consumption_masters_dpr');
            $table->string('consumption_number');
            $table->date('consumption_date');
            $table->enum('consumption_type', ['all', 'fuel'])->nullable();
            $table->enum('machinery_type', ['own', 'rental'])->nullable();
            $table->unsignedBigInteger('machinery_id')->nullable()->index('daily_consumption_masters_machinery_id_foreign');
            $table->unsignedBigInteger('site_id')->nullable()->index('daily_consumption_masters_site_id_foreign');
            $table->unsignedBigInteger('activity_completed_id')->nullable()->index();
            $table->string('consumption_file')->nullable();
            $table->unsignedBigInteger('created_by')->default(0);
            $table->json('warning_overrides')->nullable();
            $table->integer('warning_override_count')->default(0)->index('idx_diesel_warning_count');
            $table->unsignedBigInteger('workspace_id')->default(0)->index('daily_consumption_masters_workspace_id_foreign');
            $table->string('status')->default('0');
            $table->timestamps();

            $table->index(['machinery_id', 'consumption_date'], 'idx_machinery_date');
            $table->index(['site_id', 'consumption_date'], 'idx_site_date');
            $table->unique(['site_id', 'consumption_number'], 'unique_consumption_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_consumption_masters');
    }
};
