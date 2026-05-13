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
        Schema::dropIfExists('material_transfers');
        
        Schema::create('material_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique();
            $table->date('record_date');
            $table->unsignedBigInteger('from_site_id');
            $table->unsignedBigInteger('to_site_id');
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('workspace_id');
            $table->string('record_file')->nullable();           
            $table->timestamps();

            // Foreign keys (optional, if you have related tables)
            // $table->foreign('from_site_id')->references('id')->on('sites');
            // $table->foreign('to_site_id')->references('id')->on('sites');
            // $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_transfers');
    }
};
