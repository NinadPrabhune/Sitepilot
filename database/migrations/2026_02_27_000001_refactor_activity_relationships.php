<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Refactor: ManPowerMaster, DailyProgressReport, DailyConsumptionMaster 
     * now belong to ActivityCompleted instead of Activity directly.
     */
    public function up(): void
    {
        // Update man_power_masters table
        Schema::table('man_power_masters', function (Blueprint $table) {
            // Add activity_completed_id column with foreign key
            $table->unsignedBigInteger('activity_completed_id')->nullable()->after('activity_id');
            $table->foreign('activity_completed_id')
                  ->references('id')
                  ->on('activities_completed')
                  ->onDelete('cascade');
            
            // Add index for better query performance
            $table->index('activity_completed_id');
        });

        // Update daily_progress_reports table
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add activity_completed_id column with foreign key
            $table->unsignedBigInteger('activity_completed_id')->nullable()->after('activity_id');
            $table->foreign('activity_completed_id')
                  ->references('id')
                  ->on('activities_completed')
                  ->onDelete('cascade');
            
            // Add index for better query performance
            $table->index('activity_completed_id');
        });

        // Update daily_consumption_masters table
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            // Add activity_completed_id column with foreign key
            $table->unsignedBigInteger('activity_completed_id')->nullable()->after('activity_id');
            $table->foreign('activity_completed_id')
                  ->references('id')
                  ->on('activities_completed')
                  ->onDelete('cascade');
            
            // Add index for better query performance
            $table->index('activity_completed_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert man_power_masters table
        Schema::table('man_power_masters', function (Blueprint $table) {
            $table->dropForeign(['activity_completed_id']);
            $table->dropIndex(['activity_completed_id']);
            $table->dropColumn('activity_completed_id');
        });

        // Revert daily_progress_reports table
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropForeign(['activity_completed_id']);
            $table->dropIndex(['activity_completed_id']);
            $table->dropColumn('activity_completed_id');
        });

        // Revert daily_consumption_masters table
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->dropForeign(['activity_completed_id']);
            $table->dropIndex(['activity_completed_id']);
            $table->dropColumn('activity_completed_id');
        });
    }
};