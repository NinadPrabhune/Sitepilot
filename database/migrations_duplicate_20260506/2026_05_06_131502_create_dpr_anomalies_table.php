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
        Schema::create('dpr_anomalies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dpr_id')->index('idx_anomaly_dpr_id');
            $table->enum('anomaly_type', ['excessive_edits', 'consumption_spike', 'timing_mismatch', 'duplicate_entry', 'suspicious_pattern'])->index('idx_anomaly_type');
            $table->text('description');
            $table->json('anomaly_data')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index('idx_anomaly_severity');
            $table->enum('status', ['open', 'investigating', 'resolved', 'false_positive'])->default('open')->index('idx_anomaly_status');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->index('dpr_anomalies_resolved_by_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpr_anomalies');
    }
};
