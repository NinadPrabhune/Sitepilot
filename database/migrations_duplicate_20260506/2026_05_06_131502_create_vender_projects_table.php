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
        Schema::create('vender_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('vender_id');
            $table->integer('project_id');
            $table->integer('is_active')->default(1);
            $table->text('permission')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vender_projects');
    }
};
