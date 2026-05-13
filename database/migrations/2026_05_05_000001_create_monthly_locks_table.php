<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('monthly_locks', function (Blueprint $table) {
            $table->id();
            $table->integer('month');
            $table->integer('year');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['month', 'year', 'workspace_id']);
            $table->index(['is_locked', 'workspace_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('monthly_locks');
    }
};
