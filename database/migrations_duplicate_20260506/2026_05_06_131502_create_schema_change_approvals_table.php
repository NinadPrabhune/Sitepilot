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
        Schema::create('schema_change_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_id')->unique();
            $table->string('command_type');
            $table->text('command_details');
            $table->string('requested_by')->index();
            $table->string('environment');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('approval_reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('execution_log')->nullable();
            $table->timestamps();

            $table->index(['status', 'environment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_change_approvals');
    }
};
