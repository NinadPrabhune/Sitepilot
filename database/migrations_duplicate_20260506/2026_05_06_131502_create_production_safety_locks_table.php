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
            $table->bigIncrements('id');
            $table->string('lock_type', 50);
            $table->string('environment');
            $table->boolean('is_locked')->default(true);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('override_token')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lock_type', 'environment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_safety_locks');
    }
};
