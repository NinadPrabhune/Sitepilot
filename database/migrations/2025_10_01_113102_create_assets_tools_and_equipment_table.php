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
        Schema::create('assets_tools_and_equipment', function (Blueprint $table) {  
            $table->id();
            // Link to Material master
            $table->unsignedBigInteger('material_id');
            $table->foreign('material_id')->references('id')->on('material')->onDelete('cascade');

            // Quantity tracking
            $table->integer('quantity')->default(1);

            // Operational status
            $table->enum('operational_status', ['active', 'breakdown', 'scrap'])->default('active');

            // Optional assignment/location
            $table->unsignedBigInteger('site_id')->nullable();            

            // Meta
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->string('status')->default(0); // optional workflow status  
            $table->timestamps();            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets_tools_and_equipment');
    }
};
