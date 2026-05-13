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
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('report_type', 50)->index('idx_report_type');
            $table->string('report_key', 100);
            $table->date('report_date')->index('idx_report_date');
            $table->json('report_data');
            $table->decimal('total_amount', 15);
            $table->unsignedBigInteger('created_by')->index('report_snapshots_created_by_foreign');
            $table->timestamp('created_at');

            $table->unique(['report_type', 'report_key', 'report_date'], 'idx_report_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
