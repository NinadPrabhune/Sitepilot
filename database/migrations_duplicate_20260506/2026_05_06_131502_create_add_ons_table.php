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
        Schema::create('add_ons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('monthly_price', 255)->nullable();
            $table->string('yearly_price', 255)->nullable();
            $table->string('image', 255)->nullable();
            $table->boolean('is_enable')->default(false);
            $table->string('package_name', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_ons');
    }
};
