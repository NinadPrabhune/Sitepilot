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
        Schema::create('production_safety_locks', function (Blueprint $table) {
            $table->id();
            $table->string('lock_type', 50); // 'schema_changes', 'seeding', 'destructive_commands'
            $table->string('environment'); // 'production', 'staging', etc.
            $table->boolean('is_locked')->default(true);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('override_token')->nullable(); // For emergency overrides
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            
            $table->index(['lock_type', 'environment']);
            $table->index('expires_at');
        });

        Schema::create('schema_change_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('command_type'); // 'migrate', 'seed', 'schema_change'
            $table->text('command_details');
            $table->string('requested_by');
            $table->string('environment');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('approval_reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('execution_log')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'environment']);
            $table->index('requested_by');
        });

        Schema::create('destructive_command_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->text('full_command');
            $table->string('user');
            $table->string('ip_address');
            $table->string('environment');
            $table->enum('block_source', ['artisan_kernel', 'ci_pipeline', 'db_permission', 'app_lock'])->default('artisan_kernel');
            $table->boolean('was_blocked')->default(true);
            $table->text('block_reason')->nullable();
            $table->json('context')->nullable(); // Additional context
            $table->timestamps();
            
            $table->index(['environment', 'was_blocked']);
            $table->index('user');
            $table->index('created_at');
        });

        // Insert default production locks
        DB::table('production_safety_locks')->insert([
            [
                'lock_type' => 'schema_changes',
                'environment' => 'production',
                'is_locked' => true,
                'reason' => 'Production schema changes require explicit approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'seeding',
                'environment' => 'production',
                'is_locked' => true,
                'reason' => 'Production seeding is disabled for data safety',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'destructive_commands',
                'environment' => 'production',
                'is_locked' => true,
                'reason' => 'Destructive commands are blocked in production',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'destructive_commands',
                'environment' => 'staging',
                'is_locked' => true,
                'reason' => 'Destructive commands require approval in staging',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destructive_command_attempts');
        Schema::dropIfExists('schema_change_approvals');
        Schema::dropIfExists('production_safety_locks');
    }
};
