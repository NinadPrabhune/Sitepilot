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
        Schema::create('material_returns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('return_number');
            $table->unsignedBigInteger('issue_id')->index();
            $table->unsignedBigInteger('site_id')->index();
            $table->date('return_date');
            $table->enum('status', ['Completed'])->default('Completed');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index('material_returns_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index();
            $table->timestamps();

            $table->unique(['site_id', 'return_number'], 'unique_return_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_returns');
    }
};
