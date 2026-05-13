<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add diesel audit fields for transparency in billing calculations
     */
    public function up(): void
    {
        Schema::table('machinery_billing_items', function (Blueprint $table) {
            // Diesel cost breakdown for audit visibility
            $table->decimal('diesel_cost_actual', 12, 2)->default(0)->after('total_diesel')
                ->comment('Full diesel cost calculated from liters × rate');
            $table->decimal('diesel_cost_deducted', 12, 2)->default(0)->after('diesel_cost_actual')
                ->comment('Diesel amount actually deducted from bill (0 if supplier pays)');
            $table->string('diesel_responsibility', 20)->default('supplier')->after('diesel_cost_deducted')
                ->comment('Who pays diesel: company or supplier');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::table('machinery_billing_items', function (Blueprint $table) {
            $table->dropColumn(['diesel_cost_actual', 'diesel_cost_deducted', 'diesel_responsibility']);
        });
    }
};
