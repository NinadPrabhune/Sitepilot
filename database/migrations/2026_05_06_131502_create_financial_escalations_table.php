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
        Schema::create('financial_escalations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('escalation_id', 50)->unique();
            $table->unsignedBigInteger('user_id')->index('idx_fin_escalation_user');
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('escalation_type', 30)->index('idx_fin_escalation_type');
            $table->text('reason');
            $table->json('requirements')->nullable();
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval')->index('idx_fin_escalation_status');
            $table->unsignedBigInteger('approver_id')->nullable()->index('financial_escalations_approver_id_foreign');
            $table->text('approver_comments')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_escalations');
    }
};
