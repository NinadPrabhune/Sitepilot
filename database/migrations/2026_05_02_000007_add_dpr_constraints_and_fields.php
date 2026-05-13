<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add constraints and fields to daily_progress_reports table
     */
    public function up(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add new fields for audit-grade system
            $table->decimal('rate_snapshot', 10, 2)->nullable()->after('calculated_amount');
            $table->string('calculation_hash', 64)->nullable()->after('rate_snapshot');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('calculation_hash');
            $table->boolean('is_locked')->default(false)->after('payment_status');
            $table->unsignedBigInteger('locked_by')->nullable()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->json('audit_log')->nullable()->after('locked_at');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('audit_log');
            $table->timestamp('deleted_at')->nullable()->after('deleted_by');
            
            // Add indexes
            $table->index('calculation_hash', 'idx_calculation_hash');
            $table->index('payment_status', 'idx_payment_status');
            $table->index('is_locked', 'idx_is_locked');
            $table->index(['deleted_at'], 'idx_deleted_at');
        });
        
        // Add foreign key for locked_by
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->foreign('locked_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['locked_by']);
            $table->dropForeign(['deleted_by']);
            
            // Drop indexes
            $table->dropIndex('idx_calculation_hash');
            $table->dropIndex('idx_payment_status');
            $table->dropIndex('idx_is_locked');
            $table->dropIndex('idx_deleted_at');
            
            // Drop columns
            $table->dropColumn([
                'rate_snapshot',
                'calculation_hash',
                'payment_status',
                'is_locked',
                'locked_by',
                'locked_at',
                'audit_log',
                'deleted_by',
                'deleted_at'
            ]);
        });
    }
};
