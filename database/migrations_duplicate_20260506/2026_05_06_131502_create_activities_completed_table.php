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
        Schema::create('activities_completed', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('activity_id')->index('activities_completed_activity_id_foreign');
            $table->date('completed_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('activities_completed_created_by_foreign');
            $table->string('completed_reference_file')->nullable();
            $table->integer('completed_quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities_completed');
    }
};
