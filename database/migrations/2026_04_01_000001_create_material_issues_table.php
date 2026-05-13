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
        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number')->unique();
            $table->unsignedBigInteger('site_id');
            $table->enum('issue_to_type', ['user', 'supplier']);
            $table->unsignedBigInteger('issue_to_id');
            $table->date('issue_date');
            $table->enum('status', ['Completed'])->default('Completed');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('workspace_id');
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('work_spaces')->onDelete('cascade');

            $table->index('site_id');
            $table->index('issue_to_type');
            $table->index('issue_to_id');
            $table->index('workspace_id');
            $table->index(['issue_to_type', 'issue_to_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_issues');
    }
};
