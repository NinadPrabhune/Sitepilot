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
        
        Schema::dropIfExists('supplier_categories');

        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable(); // Optional: explanation of the supplier_categories            
            $table->integer('site_id')->nullable()->default(null);
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->boolean('is_active')->default(true); // Indicates if the category is active
            $table->string('status')->default('0'); 
            $table->timestamps();
        });
        
       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_categories');
    }
};
