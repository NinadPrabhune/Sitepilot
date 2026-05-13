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
        Schema::create('numbering_configs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50); // po, indent, grn, invoice, payment
            $table->string('scope_type', 20); // site, workspace
            $table->unsignedBigInteger('scope_id')->nullable(); // null for global
            $table->string('prefix', 20)->default('PO');
            $table->integer('starting_number')->default(1);
            $table->integer('padding_length')->default(5);
            $table->timestamps();
            
            // CRITICAL: Unique constraint with NULL handling
            // MySQL treats NULL as distinct, so we need to handle it differently
            // We'll use a composite unique index and handle NULL via application logic
            $table->unique(['module', 'scope_type', 'scope_id'], 'unique_numbering_config');
            $table->index(['module', 'scope_type', 'scope_id'], 'idx_numbering_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_configs');
    }
};
