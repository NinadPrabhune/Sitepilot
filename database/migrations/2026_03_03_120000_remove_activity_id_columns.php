<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove activity_id columns and keep activity_completed_id for the refactored relationship:
     * Activity -> ActivityCompleted -> (ManPowerMaster, DailyProgressReport, DailyConsumptionMaster)
     */
    public function up(): void
    {
        // man_power_masters: drop activity_id, keep activity_completed_id
        Schema::table('man_power_masters', function (Blueprint $table) {
            if (Schema::hasColumn('man_power_masters', 'activity_id')) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            }
        });

        // daily_progress_reports: drop activity_id, keep activity_completed_id
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_progress_reports', 'activity_id')) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            }
        });

        // daily_consumption_masters: drop activity_id, keep activity_completed_id
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            if (Schema::hasColumn('daily_consumption_masters', 'activity_id')) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: add activity_id back to all tables
        Schema::table('man_power_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('man_power_masters', 'activity_id')) {
                $table->unsignedBigInteger('activity_id')->nullable()->after('site_id');
                $table->foreign('activity_id')
                      ->references('id')
                      ->on('activities')
                      ->onDelete('cascade');
            }
        });

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_progress_reports', 'activity_id')) {
                $table->unsignedBigInteger('activity_id')->nullable()->after('site_id');
                $table->foreign('activity_id')
                      ->references('id')
                      ->on('activities')
                      ->onDelete('cascade');
            }
        });

        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_consumption_masters', 'activity_id')) {
                $table->unsignedBigInteger('activity_id')->nullable()->after('site_id');
                $table->foreign('activity_id')
                      ->references('id')
                      ->on('activities')
                      ->onDelete('cascade');
            }
        });
    }
};
