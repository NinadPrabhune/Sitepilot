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
            $table->bigIncrements('id');
            $table->string('issue_number');
            $table->unsignedBigInteger('site_id')->index();
            $table->enum('issue_to_type', ['user', 'supplier'])->index();
            $table->unsignedBigInteger('issue_to_id')->index();
            $table->date('issue_date');
            $table->enum('status', ['Completed'])->default('Completed');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index('material_issues_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index();
            $table->timestamps();

            $table->index(['issue_to_type', 'issue_to_id']);
            $table->unique(['site_id', 'issue_number'], 'unique_issue_number_per_site');
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
