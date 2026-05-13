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
        Schema::create('deals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->double('price')->nullable();
            $table->integer('pipeline_id');
            $table->integer('stage_id');
            $table->string('sources', 255)->nullable();
            $table->string('products', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('labels', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->integer('order')->default(0);
            $table->string('phone', 255)->nullable();
            $table->integer('created_by');
            $table->integer('is_active')->default(1);
            $table->integer('workspace_id')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
