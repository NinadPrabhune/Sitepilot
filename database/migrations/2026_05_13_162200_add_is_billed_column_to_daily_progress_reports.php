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
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add is_billed column if it doesn't exist
            if (!Schema::hasColumn('daily_progress_reports', 'is_billed')) {
                $table->boolean('is_billed')->default(false)->after('calculated_amount');
            }
            
            // Add billed_at column if it doesn't exist
            if (!Schema::hasColumn('daily_progress_reports', 'billed_at')) {
                $table->timestamp('billed_at')->nullable()->after('is_billed');
            }
            
            // Add payment_request_id column if it doesn't exist
            if (!Schema::hasColumn('daily_progress_reports', 'payment_request_id')) {
                $table->unsignedBigInteger('payment_request_id')->nullable()->after('billed_at');
                $table->index('payment_request_id');
            }
            
            // Add composite index if it doesn't exist
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexListing('daily_progress_reports');
            if (!in_array('daily_progress_reports_is_billed_machinery_id_date_index', $indexes)) {
                $table->index(['is_billed', 'machinery_id', 'date']);
            }
        });

        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Add is_billed column if it doesn't exist
            if (!Schema::hasColumn('machinery_ledgers', 'is_billed')) {
                $table->boolean('is_billed')->default(false)->after('is_settled');
            }
            
            // Add billed_at column if it doesn't exist
            if (!Schema::hasColumn('machinery_ledgers', 'billed_at')) {
                $table->timestamp('billed_at')->nullable()->after('is_billed');
            }
            
            // Add composite index if it doesn't exist
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexListing('machinery_ledgers');
            if (!in_array('machinery_ledgers_is_billed_machinery_id_date_index', $indexes)) {
                $table->index(['is_billed', 'machinery_id', 'date']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropIndex(['is_billed', 'machinery_id', 'date']);
            $table->dropIndex('payment_request_id');
            $table->dropColumn(['is_billed', 'billed_at', 'payment_request_id']);
        });

        Schema::table('machinery_ledgers', function (Blueprint $table) {
            $table->dropIndex(['is_billed', 'machinery_id', 'date']);
            $table->dropColumn(['is_billed', 'billed_at']);
        });
    }
};
