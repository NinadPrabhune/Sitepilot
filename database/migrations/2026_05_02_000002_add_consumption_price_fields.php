<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add price fields to daily_consumption_details
     */
    public function up(): void
    {
        Schema::table('daily_consumption_details', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_consumption_details', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'total_price']);
        });
    }
};
