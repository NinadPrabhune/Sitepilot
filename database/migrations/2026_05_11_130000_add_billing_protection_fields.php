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
            // Add billing protection fields
            $table->boolean('is_billed')->default(false)->after('calculated_amount');
            $table->timestamp('billed_at')->nullable()->after('is_billed');
            $table->unsignedBigInteger('payment_request_id')->nullable()->after('billed_at');
            $table->index(['is_billed', 'machinery_id', 'date']);
            $table->index('payment_request_id');
        });

        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Add billing protection to ledger entries
            $table->boolean('is_billed')->default(false)->after('is_settled');
            $table->timestamp('billed_at')->nullable()->after('is_billed');
            $table->index(['is_billed', 'machinery_id', 'date']);
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
