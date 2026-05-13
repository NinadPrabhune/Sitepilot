<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft delete to machinery_ledger for immutability enforcement
     * 
     * CRITICAL: Financial ledger entries must never be physically deleted.
     * This ensures snapshot integrity and audit trail preservation.
     */
    public function up(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
