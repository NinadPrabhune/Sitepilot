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
        Schema::dropIfExists('daily_consumption_masters');

        Schema::create('daily_consumption_masters', function (Blueprint $table) {
            $table->id();
            $table->string('consumption_number')->unique();
            $table->date('consumption_date');
            $table->enum('consumption_type', ['all', 'fuel'])->nullable();
            $table->enum('machinery_type', ['own', 'rental'])->nullable();
            $table->unsignedBigInteger('machinery_id')->nullable();
            $table->foreign('machinery_id')->references('id')->on('machineries')->onDelete('set null');           
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('consumption_file')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('set null');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('workspace_id')->default(0);
            $table->string('status')->default('0'); // optional workflow status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_consumption_masters');
    }
};
