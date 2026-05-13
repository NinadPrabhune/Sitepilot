<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add operator_names field to daily_progress_reports table
     */
    public function up(): void
    {
        if (!Schema::hasColumn('daily_progress_reports', 'operator_names')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->text('operator_names')->nullable()->after('number_of_operators');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('daily_progress_reports', 'operator_names')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->dropColumn('operator_names');
            });
        }
    }
};
