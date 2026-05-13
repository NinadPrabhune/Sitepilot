<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->string('ledger_type')->after('entry_type')->nullable();
            $table->string('cost_category')->after('ledger_type')->nullable();
            $table->unsignedBigInteger('dpr_id')->after('reference_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->dropColumn(['ledger_type', 'cost_category', 'dpr_id']);
        });
    }
};
