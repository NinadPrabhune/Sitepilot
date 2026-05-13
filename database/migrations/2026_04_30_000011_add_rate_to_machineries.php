<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('machineries', function (Blueprint $table) {
            $table->decimal('rate', 15, 2)->nullable()->after('owned_by')->comment('Hourly rate for machinery work');
        });
    }

    public function down()
    {
        Schema::table('machineries', function (Blueprint $table) {
            $table->dropColumn('rate');
        });
    }
};
