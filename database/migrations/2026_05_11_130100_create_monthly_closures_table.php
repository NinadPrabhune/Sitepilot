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
        Schema::create('monthly_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedBigInteger('closed_by');
            $table->timestamp('closed_at')->useCurrent();
            $table->text('remarks')->nullable();
            
            // Unique constraint to prevent duplicate closures
            $table->unique(['workspace_id', 'site_id', 'year', 'month']);
            
            // Indexes for performance
            $table->index(['workspace_id', 'year', 'month']);
            $table->index(['site_id', 'year', 'month']);
            $table->index('closed_at');
            
            // Foreign key constraints
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_closures');
    }
};
