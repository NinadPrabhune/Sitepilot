<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update existing work_spaces records with default/empty values for new columns.
     */
    public function up(): void
    {
        // Update any NULL website to empty string
        DB::table('work_spaces')
            ->whereNull('website')
            ->update(['website' => '']);

        // Update any NULL cin_no to empty string
        DB::table('work_spaces')
            ->whereNull('cin_no')
            ->update(['cin_no' => '']);

        // Update any NULL logo to empty string (not setting a default image)
        DB::table('work_spaces')
            ->whereNull('logo')
            ->update(['logo' => '']);

        // Update any NULL terms_and_conditions to empty string
        DB::table('work_spaces')
            ->whereNull('terms_and_conditions')
            ->update(['terms_and_conditions' => '']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only updates existing data, no rollback needed
    }
};
