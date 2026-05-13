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
        Schema::create('numbering_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module', 50);
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('prefix', 20)->default('PO');
            $table->integer('starting_number')->default(1);
            $table->integer('padding_length')->default(5);
            $table->timestamps();

            $table->index(['module', 'scope_type', 'scope_id'], 'idx_numbering_lookup');
            $table->unique(['module', 'scope_type', 'scope_id'], 'unique_numbering_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_configs');
    }
};
