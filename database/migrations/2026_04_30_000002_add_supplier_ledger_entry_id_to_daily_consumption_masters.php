<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_ledger_entry_id')->nullable()->after('ledger_entry_id');
        });
    }

    public function down()
    {
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->dropColumn('supplier_ledger_entry_id');
        });
    }
};
