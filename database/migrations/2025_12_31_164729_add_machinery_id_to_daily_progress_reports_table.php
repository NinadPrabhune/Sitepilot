<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->foreignId('machinery_id')
                    ->after('id')
                    ->constrained('machineries')
                    ->cascadeOnDelete();
        });
    }

    public function down() {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropForeign(['machinery_id']);
            $table->dropColumn('machinery_id');
        });
    }
};
