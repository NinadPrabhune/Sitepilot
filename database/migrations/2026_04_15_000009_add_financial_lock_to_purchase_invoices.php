<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add is_financially_locked field to purchase_invoices table
     * This is a database-level lock to prevent invoice edit after advance allocation
     * UI lock ≠ real lock - this prevents API bypass risk
     */
    public function up(): void
    {
        // SAFETY CHECK: Only add columns if they don't exist
        if (!Schema::hasColumn('purchase_invoices', 'is_financially_locked')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                // Financial lock fields
                $table->boolean('is_financially_locked')->default(false)->after('status');
                $table->timestamp('financially_locked_at')->nullable()->after('is_financially_locked');
                $table->foreignId('financially_locked_by')->nullable()->constrained('users')->nullOnDelete()->after('financially_locked_at');

                // Add index for queries filtering by financial lock status
                $table->index('is_financially_locked');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                // Drop foreign key if exists
                $table->dropForeign(['financially_locked_by']);
                // Drop index if exists
                $table->dropIndex(['is_financially_locked']);
                // Drop columns if they exist
                foreach (['is_financially_locked', 'financially_locked_at', 'financially_locked_by'] as $col) {
                    if (Schema::hasColumn('purchase_invoices', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        } catch (\Exception $e) {
            // Silently ignore errors during rollback
        }
    }
};
