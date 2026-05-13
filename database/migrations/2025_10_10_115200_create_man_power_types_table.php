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
        
        Schema::dropIfExists('man_power_types');
        
        Schema::create('man_power_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(0);
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('workspace_id');
            $table->timestamps();

            // Optional: Add foreign key constraints if needed
             $table->foreign('site_id')->references('id')->on('sites');
             $table->foreign('created_by')->references('id')->on('users');
             $table->foreign('workspace_id')->references('id')->on('work_spaces');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_types');
    }
};
