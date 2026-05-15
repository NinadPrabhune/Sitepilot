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
            // Add billing month and year columns for the constraint
            $table->unsignedTinyInteger('billing_month')->after('period_end');
            $table->unsignedInteger('billing_year')->after('billing_month');
            
            // Indexes for performance
            $table->index(['machinery_id', 'billing_month', 'billing_year'], 'mp_mach_bill_idx');
            $table->index(['billing_year', 'billing_month'], 'mp_bill_year_idx');
            
            // Add unique constraint for concurrent protection
            $table->unique(['machinery_id', 'billing_month', 'billing_year'], 'unique_machinery_billing_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('machinery_payment_requests', function (Blueprint $table) {
                $table->dropIndex('mp_mach_bill_idx');
                $table->dropIndex('mp_bill_year_idx');
                $table->dropUnique('unique_machinery_billing_period');
                $table->dropColumn(['billing_month', 'billing_year']);
            });
        } catch (\Exception $e) {
            // Silently ignore errors during rollback
        }
    }
};
