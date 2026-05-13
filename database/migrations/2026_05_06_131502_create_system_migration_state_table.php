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
        Schema::create('system_migration_state', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('migration_phase')->unique();
            $table->string('migration_name')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('executed_by')->nullable();
            $table->text('execution_notes')->nullable();
            $table->boolean('locked')->default(false);
            $table->string('checksum')->nullable();
            $table->longText('pre_migration_snapshot')->nullable();
            $table->boolean('staging_approved')->default(false);
            $table->timestamp('staging_approved_at')->nullable();
            $table->integer('staging_approved_by')->nullable();
            $table->boolean('production_approved')->default(false);
            $table->timestamp('production_approved_at')->nullable();
            $table->integer('production_approved_by')->nullable();
            $table->boolean('validation_passed')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_results')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('error_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_migration_state');
    }
};
