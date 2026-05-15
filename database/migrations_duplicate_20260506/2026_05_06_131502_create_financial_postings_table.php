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
        Schema::create('financial_postings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('posting_id', 50)->unique();
            $table->string('entity_type', 20)->index('idx_posting_entity');
            $table->unsignedBigInteger('entity_id');
            $table->decimal('amount', 15);
            $table->unsignedBigInteger('posted_by')->index('idx_posting_user');
            $table->enum('status', ['posted', 'reversed'])->default('posted')->index('idx_posting_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_postings');
    }
};
