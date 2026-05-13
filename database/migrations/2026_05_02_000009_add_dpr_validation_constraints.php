<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add validation constraints to daily_progress_reports table
     */
    public function up(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add validation constraints using raw SQL
            DB::statement('ALTER TABLE daily_progress_reports 
                ADD CONSTRAINT chk_end_ge_start 
                CHECK (machine_end_reading >= machine_start_reading)');
            
            DB::statement('ALTER TABLE daily_progress_reports 
                ADD CONSTRAINT chk_idle_non_negative 
                CHECK (machine_idle_reading >= 0)');
            
            DB::statement('ALTER TABLE daily_progress_reports 
                ADD CONSTRAINT chk_billable_non_negative 
                CHECK (billable_hours >= 0)');
            
            DB::statement('ALTER TABLE daily_progress_reports 
                ADD CONSTRAINT chk_calculated_amount_non_negative 
                CHECK (calculated_amount >= 0)');
        });
    }

    public function down(): void
    {
        // Drop constraints using raw SQL
        DB::statement('ALTER TABLE daily_progress_reports DROP CONSTRAINT chk_end_ge_start');
        DB::statement('ALTER TABLE daily_progress_reports DROP CONSTRAINT chk_idle_non_negative');
        DB::statement('ALTER TABLE daily_progress_reports DROP CONSTRAINT chk_billable_non_negative');
        DB::statement('ALTER TABLE daily_progress_reports DROP CONSTRAINT chk_calculated_amount_non_negative');
    }
};
