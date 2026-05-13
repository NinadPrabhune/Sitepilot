<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove remaining_amount and reserved_amount from supplier_advances (make them derived)
     * Create financial_periods table for month/year closing
     */
    public function up(): void
    {
        // Remove derived columns from supplier_advances if they exist
        Schema::table('supplier_advances', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_advances', 'remaining_amount')) {
                $table->dropColumn('remaining_amount');
            }
            if (Schema::hasColumn('supplier_advances', 'reserved_amount')) {
                $table->dropColumn('reserved_amount');
            }
        });

        // Create financial_periods table
        if (!Schema::hasTable('financial_periods')) {
            Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('site_id');
            $table->integer('period_year');
            $table->tinyInteger('period_month');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'site_id', 'period_year', 'period_month'], 'unique_period');
            $table->index(['workspace_id', 'site_id'], 'idx_workspace_site');
            $table->index(['start_date', 'end_date'], 'idx_dates');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_periods');

        Schema::table('supplier_advances', function (Blueprint $table) {
            $table->decimal('remaining_amount', 15, 2)->after('amount');
            $table->decimal('reserved_amount', 15, 2)->default(0)->after('remaining_amount');
        });
    }
};
