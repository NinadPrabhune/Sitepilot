<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        // First, remove duplicates keeping the latest record
        DB::statement("
            DELETE t1 FROM supplier_transactions t1
            INNER JOIN supplier_transactions t2
            WHERE t1.id < t2.id
            AND t1.reference_type = t2.reference_type
            AND t1.reference_id = t2.reference_id
            AND t1.supplier_id = t2.supplier_id
            AND t1.site_id = t2.site_id
        ");

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
