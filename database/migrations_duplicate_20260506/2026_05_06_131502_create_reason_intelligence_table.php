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
        Schema::create('reason_intelligence', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reason')->unique('idx_reason_unique');
            $table->string('category', 50)->index('idx_reason_category');
            $table->double('confidence');
            $table->double('weight');
            $table->boolean('is_legitimate')->index('idx_reason_legitimate');
            $table->json('analysis')->nullable();
            $table->integer('usage_count')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reason_intelligence');
    }
};
