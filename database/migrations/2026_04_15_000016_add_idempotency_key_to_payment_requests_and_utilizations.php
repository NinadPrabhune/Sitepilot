<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add idempotency key (UUID) to advance_utilizations only
     * This prevents duplicate utilization operations on retry scenarios
     * Note: payment_requests already has idempotency_key column
     */
    public function up(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Add idempotency_key for idempotent utilization operations
            $table->string('idempotency_key', 64)->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->dropUnique('advance_utilizations_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
