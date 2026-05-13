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
        Schema::create('dpr_edit_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dpr_id')->index('idx_dpr_edit_dpr_id');
            $table->unsignedBigInteger('user_id')->index('idx_dpr_edit_user_id');
            $table->enum('action', ['created', 'updated', 'verified', 'locked', 'paid', 'reverted']);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['dpr_id', 'created_at'], 'idx_dpr_edit_timeline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpr_edit_history');
    }
};
