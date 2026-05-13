<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Only add columns that don't exist
            if (!Schema::hasColumn('daily_progress_reports', 'calculated_amount')) {
                $table->decimal('calculated_amount', 15, 2)->nullable()->after('billable_hours');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('ledger_entry_id');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            
            // Add foreign key constraint for ledger entry (if not already exists)
            if (!Schema::hasColumn('daily_progress_reports', 'ledger_entry_id')) {
                $table->unsignedBigInteger('ledger_entry_id')->nullable()->after('calculated_amount');
                $table->foreign('ledger_entry_id')->references('id')->on('machinery_ledger')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropForeign(['ledger_entry_id']);
            $table->dropColumn([
                'calculated_amount',
                'approved_by',
                'approved_at',
                'rejection_reason',
                'rejected_by',
                'rejected_at',
                'ledger_entry_id',
            ]);
        });
    }
};
