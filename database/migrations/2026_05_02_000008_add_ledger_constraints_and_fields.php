<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add constraints and fields to machinery_ledgers table
     */
    public function up(): void
    {
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Add strict linkage fields only if they don't exist
            if (!Schema::hasColumn('machinery_ledgers', 'dpr_id')) {
                $table->unsignedBigInteger('dpr_id')->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('machinery_ledgers', 'payment_request_id')) {
                $table->unsignedBigInteger('payment_request_id')->nullable()->after('dpr_id');
            }
            if (!Schema::hasColumn('machinery_ledgers', 'dpr_payment_status')) {
                $table->enum('dpr_payment_status', ['unpaid', 'partial', 'paid'])->nullable()->after('payment_request_id');
            }
            if (!Schema::hasColumn('machinery_ledgers', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false)->after('dpr_payment_status');
            }
            if (!Schema::hasColumn('machinery_ledgers', 'reversal_of_id')) {
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('is_reversal');
            }
            
            // Add indexes only if they don't exist
            if (!Schema::hasIndex('machinery_ledgers', 'idx_dpr_id')) {
                $table->index('dpr_id', 'idx_dpr_id');
            }
            if (!Schema::hasIndex('machinery_ledgers', 'idx_payment_request_id')) {
                $table->index('payment_request_id', 'idx_payment_request_id');
            }
            if (!Schema::hasIndex('machinery_ledgers', 'idx_is_reversal')) {
                $table->index('is_reversal', 'idx_is_reversal');
            }
            if (!Schema::hasIndex('machinery_ledgers', 'idx_reversal_of')) {
                $table->index(['reversal_of_id'], 'idx_reversal_of');
            }
            
            // Add unique constraint only if it doesn't exist
            if (!Schema::hasIndex('machinery_ledgers', 'unique_reference_payment')) {
                $table->unique(['reference_type', 'reference_id', 'payment_request_id'], 'unique_reference_payment');
            }
        });
        
        // Add foreign keys only if columns exist
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('machinery_ledgers', 'dpr_id')) {
                try {
                    $table->foreign('dpr_id')->references('id')->on('daily_progress_reports');
                } catch (\Exception $e) {
                    // Foreign key might already exist or table doesn't exist, continue
                }
            }
            if (Schema::hasColumn('machinery_ledgers', 'payment_request_id')) {
                try {
                    $table->foreign('payment_request_id')->references('id')->on('machinery_payment_requests');
                } catch (\Exception $e) {
                    // Foreign key might already exist or table doesn't exist, continue
                }
            }
            if (Schema::hasColumn('machinery_ledgers', 'reversal_of_id')) {
                try {
                    $table->foreign('reversal_of_id')->references('id')->on('machinery_ledgers');
                } catch (\Exception $e) {
                    // Foreign key might already exist or table doesn't exist, continue
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['dpr_id']);
            $table->dropForeign(['payment_request_id']);
            $table->dropForeign(['reversal_of_id']);
            
            // Drop indexes
            $table->dropIndex('idx_dpr_id');
            $table->dropIndex('idx_payment_request_id');
            $table->dropIndex('idx_is_reversal');
            $table->dropIndex('idx_reversal_of');
            $table->dropUnique('unique_reference_payment');
            
            // Drop columns
            $table->dropColumn([
                'dpr_id',
                'payment_request_id',
                'dpr_payment_status',
                'is_reversal',
                'reversal_of_id'
            ]);
        });
    }
};
