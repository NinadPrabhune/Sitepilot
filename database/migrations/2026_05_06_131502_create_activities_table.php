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
        Schema::create('activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('assign_to', 255)->nullable()->index('activities_user_id_foreign');
            $table->string('reference_file')->nullable();
            $table->string('title');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->text('scope')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('unit')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low');
            $table->tinyInteger('status')->default(0);
            $table->unsignedBigInteger('created_by')->index('activities_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('activities_workspace_id_foreign');
            $table->unsignedBigInteger('site_id')->index('activities_site_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
