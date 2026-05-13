<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        // Add unique constraint on (reference_type, reference_id, supplier_id, site_id)
        // This prevents duplicate ledger entries at DB level while allowing:
        // - Multiple payment types (advance vs payment) for same reference
        // - Future adjustments/reversals without breaking constraint
        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id', 'supplier_id', 'site_id'], 'unique_reference');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->dropUnique('unique_reference');
        });
    }
};
