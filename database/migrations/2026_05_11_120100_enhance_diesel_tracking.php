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
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            // Add diesel rate and total cost fields for frozen pricing
            $table->decimal('diesel_rate', 8, 2)->nullable()->after('diesel_consumed_liters');
            $table->decimal('diesel_total_cost', 12, 2)->nullable()->after('diesel_rate');
            
            // Add indexes for performance
            $table->index(['machinery_id', 'consumption_date', 'diesel_rate']);
            $table->index('diesel_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            // Remove diesel tracking fields
            $table->dropColumn(['diesel_rate', 'diesel_total_cost']);
            
            // Drop indexes
            $table->dropIndex(['machinery_id', 'consumption_date', 'diesel_rate']);
            $table->dropIndex('diesel_rate');
        });
    }
};
