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
        // Only create table if it doesn't exist
        if (!Schema::hasTable('system_locks')) {
            Schema::create('system_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('work_spaces')->onDelete('cascade');
            $table->boolean('is_locked')->default(false);
            $table->string('lock_reason')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique('workspace_id');
            $table->index('is_locked');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_locks');
    }
};
