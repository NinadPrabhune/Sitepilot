<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('daily_progress_reports', 'deleted_at')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};