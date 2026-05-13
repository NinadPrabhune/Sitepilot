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
        Schema::create('ch_notifications', function (Blueprint $table) {
            $table->id();

            // Foreign keys (optional, depending on your app)
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();

            // Notification details
            $table->string('type', 100); // e.g. 'low_stock', 'birthday', 'announcement', 'holiday', 'event'
            $table->string('title', 191);
            $table->text('message');
            $table->json('message_arr')->nullable(); // ✅ JSON column for structured material list
            $table->string('icon_type', 50)->default('info'); // 'info', 'success', 'warning', 'error'

            // Polymorphic relation
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_type', 191)->nullable();

            // Optional action link
            $table->string('action_url', 191)->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('workspace_id');
            $table->index('project_id');
            $table->index('type');
            $table->index(['related_id', 'related_type']); // helpful for polymorphic lookups
            $table->index('created_at');

            // If you want foreign key constraints (optional)
            // $table->foreign('workspace_id')->references('id')->on('work_spaces')->onDelete('cascade');
            // $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ch_notifications');
    }
};
