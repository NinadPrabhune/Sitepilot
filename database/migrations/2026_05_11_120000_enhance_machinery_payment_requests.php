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
            // Remove verification and locked fields for simplified 3-step workflow
            $table->dropColumn(['verified_by', 'verified_at', 'locked_by', 'locked_at']);
            
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
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            // Add back removed columns
            $table->unsignedBigInteger('verified_by')->nullable()->after('submitted_at');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->unsignedBigInteger('locked_by')->nullable()->after('verified_at');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            
            // Remove enhanced fields
            $table->dropColumn(['gross_amount', 'diesel_deduction', 'calculation_method', 'billing_breakdown', 'diesel_breakdown']);
            
            // Drop indexes
            $table->dropIndex(['calculation_method']);
            $table->dropIndex(['status', 'period_start', 'period_end']);
        });
    }
};
