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
        Schema::create('ch_messages', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->string('type', 255);
            $table->bigInteger('from_id');
            $table->bigInteger('to_id');
            $table->bigInteger('project_id')->nullable();
            $table->string('body', 5000)->nullable();
            $table->string('attachment', 255)->nullable();
            $table->boolean('seen')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ch_messages');
    }
};
