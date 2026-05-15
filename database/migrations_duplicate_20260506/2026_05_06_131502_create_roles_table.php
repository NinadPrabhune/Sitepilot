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
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('display_name', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('guard_name', 255)->default('web');
            $table->string('module', 255)->nullable()->default('Base');
            $table->tinyInteger('status')->default(0);
            $table->integer('created_by')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
