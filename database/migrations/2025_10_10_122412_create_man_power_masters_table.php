<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        
        Schema::dropIfExists('man_power_masters');
        
        Schema::create('man_power_masters', function (Blueprint $table) {
            $table->id();
            $table->date('work_date');
            $table->unsignedBigInteger('supplier_id'); 
            $table->integer('total_count')->default(0);
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('workspace_id');            
            $table->unsignedBigInteger('created_by');          
            $table->timestamps();

            // Optional foreign keys
            // $table->foreign('site_id')->references('id')->on('sites');
            // $table->foreign('workspace_id')->references('id')->on('work_spaces');
            // $table->foreign('supplier_id')->references('id')->on('suppliers');
            // $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_masters');
    }
};
