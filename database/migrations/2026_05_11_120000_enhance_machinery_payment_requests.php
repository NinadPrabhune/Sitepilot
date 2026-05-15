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
            // Add enhanced breakdown fields
            $table->decimal('gross_amount', 12, 2)->after('net_payable')->nullable();
            $table->decimal('diesel_deduction', 12, 2)->nullable()->after('gross_amount');
            $table->string('calculation_method')->nullable()->after('diesel_deduction');
            $table->json('billing_breakdown')->nullable()->after('calculation_method');
            $table->json('diesel_breakdown')->nullable()->after('billing_breakdown');
            
            // Add indexes for performance
            $table->index('calculation_method');
            $table->index(['status', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('machinery_payment_requests', function (Blueprint $table) {
                // Drop indexes (ignore if not exist)
                $table->dropIndex(['calculation_method']);
                $table->dropIndex(['status', 'period_start', 'period_end']);

                // Drop columns if they exist
                if (Schema::hasColumn('machinery_payment_requests', 'gross_amount')) {
                    $table->dropColumn('gross_amount');
                }
                if (Schema::hasColumn('machinery_payment_requests', 'diesel_deduction')) {
                    $table->dropColumn('diesel_deduction');
                }
                if (Schema::hasColumn('machinery_payment_requests', 'calculation_method')) {
                    $table->dropColumn('calculation_method');
                }
                if (Schema::hasColumn('machinery_payment_requests', 'billing_breakdown')) {
                    $table->dropColumn('billing_breakdown');
                }
                if (Schema::hasColumn('machinery_payment_requests', 'diesel_breakdown')) {
                    $table->dropColumn('diesel_breakdown');
                }
            });
        } catch (\Exception $e) {
            // Silently ignore errors during rollback
        }
    }
};
