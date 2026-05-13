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
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module', 50);
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->bigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['module', 'scope_type', 'scope_id'], 'unique_sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
