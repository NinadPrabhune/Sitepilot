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
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('subject', 255);
            $table->integer('user_id');
            $table->integer('pipeline_id');
            $table->integer('stage_id');
            $table->string('sources', 255)->nullable();
            $table->string('products', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('labels', 255)->nullable();
            $table->integer('order')->default(0);
            $table->string('phone', 20)->nullable();
            $table->integer('created_by');
            $table->integer('workspace_id');
            $table->integer('is_active')->default(1);
            $table->integer('is_converted')->default(0);
            $table->date('follow_up_date')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
