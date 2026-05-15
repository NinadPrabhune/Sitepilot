<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Removes default value from grn_type to prevent feature flag leakage.
     */
    public function up(): void
    {
        // Remove default value from grn_type column to prevent feature flag leakage
        DB::statement('ALTER TABLE purchase_invoices MODIFY COLUMN grn_type ENUM("PO", "DIRECT") NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore default value only if column exists (safe rollback)
        if (Schema::hasColumn('purchase_invoices', 'grn_type')) {
            DB::statement('ALTER TABLE purchase_invoices MODIFY COLUMN grn_type ENUM("PO", "DIRECT") NOT NULL DEFAULT "PO"');
        }
    }
};
