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
        Schema::table('payments_module', function (Blueprint $table) {
            // Phase B1.5: Add integration reference for DB-level idempotency
            $table->string('integration_reference_uuid', 64)->nullable()->after('notes');
            
            // Add unique constraint for true idempotency protection
            $table->unique(['source_type', 'source_id', 'integration_reference_uuid'], 
                'payments_module_integration_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropUnique('payments_module_integration_unique');
            $table->dropColumn('integration_reference_uuid');
        });
    }
};
