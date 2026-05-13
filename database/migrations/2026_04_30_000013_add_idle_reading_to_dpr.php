<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->decimal('machine_idle_reading', 10, 2)->nullable()->after('machine_end_reading')->comment('Idle hours to subtract from billable');
        });
    }

    public function down()
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropColumn('machine_idle_reading');
        });
    }
};
