<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rate override fields to daily_progress_reports table
     */
    public function up(): void
    {
        if (!Schema::hasColumn('daily_progress_reports', 'override_rate')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->decimal('override_rate', 10, 2)->nullable()->after('rate_snapshot');
                $table->string('override_reason')->nullable()->after('override_rate');
                $table->unsignedBigInteger('override_by')->nullable()->after('override_reason');
                $table->timestamp('override_at')->nullable()->after('override_by');
                
                // Index for audit queries
                $table->index(['override_rate', 'override_at'], 'idx_rate_override');
                
                // Foreign key for override_by
                $table->foreign('override_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('daily_progress_reports', 'override_rate')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                // Drop foreign key safely
                try {
                    $table->dropForeign(['override_by']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                
                // Drop index by name (same as created in up())
                try {
                    $table->dropIndex('idx_rate_override');
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
                
                // Drop columns
                $table->dropColumn(['override_rate', 'override_reason', 'override_by', 'override_at']);
            });
        }
    }
};
