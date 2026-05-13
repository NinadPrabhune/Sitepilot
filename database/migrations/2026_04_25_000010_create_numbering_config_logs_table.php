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
        Schema::create('numbering_config_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->enum('action_type', ['create', 'update', 'delete'])->default('update');
            $table->boolean('is_rollback')->default(false);
            $table->json('old_value');
            $table->json('new_value');
            $table->unsignedBigInteger('changed_by');
            $table->timestamps();
            
            $table->index(['module', 'scope_type', 'scope_id'], 'idx_audit_lookup');
            $table->index(['created_at'], 'idx_audit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_config_logs');
    }
};
