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
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            // Add unique constraint for concurrent protection
            $table->unique(['machinery_id', 'billing_month', 'billing_year'], 'unique_machinery_billing_period');
            
            // Add billing month and year columns for the constraint
            $table->unsignedTinyInteger('billing_month')->after('period_end');
            $table->unsignedInteger('billing_year')->after('billing_month');
            
            // Indexes for performance
            $table->index(['machinery_id', 'billing_month', 'billing_year']);
            $table->index(['billing_year', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            $table->dropIndex(['machinery_id', 'billing_month', 'billing_year']);
            $table->dropIndex(['billing_year', 'billing_month']);
            $table->dropUnique('unique_machinery_billing_period');
            $table->dropColumn(['billing_month', 'billing_year']);
        });
    }
};
