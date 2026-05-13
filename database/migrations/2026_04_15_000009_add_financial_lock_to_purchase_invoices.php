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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['financially_locked_by']);
            $table->dropIndex(['is_financially_locked']);
            $table->dropColumn(['is_financially_locked', 'financially_locked_at', 'financially_locked_by']);
        });
    }
};
