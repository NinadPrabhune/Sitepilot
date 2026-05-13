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
        Schema::dropIfExists('daily_progress_reports');
        
        Schema::create('daily_progress_reports', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->integer('machine_start_reading')->nullable();
        $table->integer('machine_end_reading')->nullable();
        $table->integer('number_of_operators')->nullable();
        $table->text('work_details')->nullable();
        $table->decimal('diesel_consumption', 8, 2)->nullable();
        $table->text('maintenance_notes')->nullable();
        $table->text('machinery_advances')->nullable();
        $table->tinyInteger('status')->default(0);
        
        $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
        $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $table->foreignId('site_id')->constrained('projects')->cascadeOnDelete();
        $table->timestamps();
    });
        
        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_progress_reports');
    }
};
