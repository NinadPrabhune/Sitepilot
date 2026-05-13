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
        Schema::dropIfExists('units');

       Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., Meter, Kilogram, Bag
            $table->string('symbol')->nullable(); // e.g., m, kg, bag
            $table->string('description')->nullable(); // Optional: explanation of the unit
            $table->boolean('is_active')->default(true); // For soft disabling units
            $table->unsignedBigInteger('site_id')->nullable()->default(null);
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
        Schema::dropIfExists('units');
    }
};
