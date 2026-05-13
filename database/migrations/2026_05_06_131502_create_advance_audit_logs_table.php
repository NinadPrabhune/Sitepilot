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
        Schema::create('advance_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('advance_id')->index();
            $table->enum('action', ['created', 'allocated', 'reversed', 'locked', 'unlocked', 'approved', 'paid'])->index();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->decimal('amount', 15)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('transaction_flow_id', 50)->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['workspace_id', 'site_id'], 'idx_workspace_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_audit_logs');
    }
};
