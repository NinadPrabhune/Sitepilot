<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create skipped_numbers table for audit-grade gap tracking.
     * This table logs numbers that were generated but not successfully inserted,
     * providing an audit trail for missing sequence numbers.
     */
    public function up(): void
    {
        Schema::create('skipped_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20); // po, indent, grn, invoice, payment
            $table->integer('site_id');
            $table->string('number', 50); // The skipped number (e.g., IND00005)
            $table->string('reason', 500)->nullable(); // Why it was skipped
            $table->string('exception_message', 1000)->nullable(); // Error details
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for querying
            $table->index(['module', 'site_id'], 'idx_module_site');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skipped_numbers');
    }
};
