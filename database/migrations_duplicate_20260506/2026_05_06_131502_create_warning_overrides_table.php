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
        Schema::create('warning_overrides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('override_id', 50)->unique();
            $table->unsignedBigInteger('user_id')->index('idx_override_user');
            $table->enum('entity_type', ['dpr', 'diesel', 'activity', 'other'])->default('dpr')->index('idx_override_entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('warning_type', 50)->index('idx_override_warning_type');
            $table->text('warning_message');
            $table->text('reason');
            $table->timestamp('created_at')->index('idx_override_created');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['user_id', 'created_at'], 'idx_override_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warning_overrides');
    }
};
