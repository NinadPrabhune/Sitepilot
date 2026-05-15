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
        try {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                // Add diesel quantity column if it doesn't exist
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_consumed_liters')) {
                    $table->decimal('diesel_consumed_liters', 10, 2)->nullable()->after('machinery_id');
                }
                // Add diesel rate and total cost fields for frozen pricing
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_rate')) {
                    $table->decimal('diesel_rate', 8, 2)->nullable()->after('diesel_consumed_liters');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_total_cost')) {
                    $table->decimal('diesel_total_cost', 12, 2)->nullable()->after('diesel_rate');
                }

                // Add indexes for performance
                $table->index(['machinery_id', 'consumption_date', 'diesel_rate']);
                $table->index('diesel_rate');
            });
        } catch (\Exception $e) {
            // Ignore errors (e.g., columns/indexes already exist)
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                // Drop indexes
                $table->dropIndex(['machinery_id', 'consumption_date', 'diesel_rate']);
                $table->dropIndex('diesel_rate');

                // Drop columns if they exist
                if (Schema::hasColumn('daily_consumption_masters', 'diesel_consumed_liters')) {
                    $table->dropColumn('diesel_consumed_liters');
                }
                if (Schema::hasColumn('daily_consumption_masters', 'diesel_rate')) {
                    $table->dropColumn('diesel_rate');
                }
                if (Schema::hasColumn('daily_consumption_masters', 'diesel_total_cost')) {
                    $table->dropColumn('diesel_total_cost');
                }
            });
        } catch (\Exception $e) {
            // Ignore errors
        }
    }
};
