<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->unsignedBigInteger('daily_progress_report_id')->nullable()->after('id');

            $table->foreign('daily_progress_report_id')
                  ->references('id')
                  ->on('daily_progress_reports')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->dropForeign(['daily_progress_report_id']);
            $table->dropColumn('daily_progress_report_id');
        });
    }
};

