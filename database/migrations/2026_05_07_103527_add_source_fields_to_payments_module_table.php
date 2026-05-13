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
            // Phase A: Add nullable source linking fields
            $table->string('source_type', 50)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            
            // Composite index for performance on source queries
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            // Phase A rollback: Remove source linking fields
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['source_type']);
            $table->dropIndex(['source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
