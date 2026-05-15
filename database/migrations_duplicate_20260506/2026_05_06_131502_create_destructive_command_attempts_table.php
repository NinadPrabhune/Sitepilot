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
        Schema::create('destructive_command_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('command');
            $table->text('full_command');
            $table->string('user')->index();
            $table->string('ip_address');
            $table->string('environment');
            $table->enum('block_source', ['artisan_kernel', 'ci_pipeline', 'db_permission', 'app_lock'])->default('artisan_kernel');
            $table->boolean('was_blocked')->default(true);
            $table->text('block_reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['environment', 'was_blocked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destructive_command_attempts');
    }
};
