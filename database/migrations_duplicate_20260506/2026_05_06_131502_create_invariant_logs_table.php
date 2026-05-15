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
        Schema::create('invariant_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('log_data');
            $table->string('action_type', 50)->index('idx_action_type');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index('idx_user_id');
            $table->timestamp('created_at')->useCurrent()->index('idx_created_at');

            $table->index(['reference_type', 'reference_id'], 'idx_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invariant_logs');
    }
};
