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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Step 1: Ensure workspace_id is NOT NULL
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        // Step 2: Verify no duplicates per workspace (manual check before migration)
        // Run: SELECT workspace_id, po_number, COUNT(*) FROM purchase_orders GROUP BY workspace_id, po_number HAVING COUNT(*) > 1;

        // Step 3: Drop old site-based unique constraint
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('unique_po_number_per_site');
        });

        // Step 4: Add workspace-based unique constraint
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique(['workspace_id', 'po_number'], 'unique_po_number_per_workspace');
        });

        // Step 5: Add performance index for scope-based queries
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['workspace_id', 'id'], 'idx_po_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('unique_po_number_per_workspace');
            $table->dropIndex('idx_po_scope');
            $table->unique(['site_id', 'po_number'], 'unique_po_number_per_site');
        });
    }
};
