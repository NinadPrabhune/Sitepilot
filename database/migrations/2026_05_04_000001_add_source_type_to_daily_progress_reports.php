<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add source_type for dual flow tracking
            $table->string('source_type', 30)
                  ->default('activity')
                  ->after('id')
                  ->comment('activity | machinery_direct | imported | manual_adjustment')
                  ->index();
            
            // DB-level race-condition protection - unique constraint
            $table->unique(['machinery_id', 'date'], 'unique_machine_date_dpr');
            
            // DB-level integrity enforcement
            $table->index(['activity_completed_id', 'source_type'], 'dpr_activity_source_idx');
        });

        // Backfill existing data
        DB::statement("UPDATE daily_progress_reports SET source_type = 'activity' WHERE source_type IS NULL OR source_type = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropIndex('unique_machine_date_dpr');
            $table->dropIndex('dpr_activity_source_idx');
            $table->dropIndex(['source_type']);
            $table->dropColumn('source_type');
        });
    }
};
