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
        Schema::create('ch_notification_users', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('user_id');

            // Track read status
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('notification_id')
                  ->references('id')
                  ->on('ch_notifications')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes for performance
            $table->index(['user_id', 'read_at']);
            $table->index(['notification_id', 'user_id']);
            $table->unique(['notification_id', 'user_id']); // prevent duplicate assignment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ch_notification_users');
    }
};
