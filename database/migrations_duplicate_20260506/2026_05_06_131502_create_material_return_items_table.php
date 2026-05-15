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
        Schema::create('material_return_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('return_id')->index();
            $table->unsignedBigInteger('issue_item_id')->nullable()->index();
            $table->unsignedBigInteger('material_id')->index();
            $table->decimal('quantity', 15);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_return_items');
    }
};
