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
        Schema::create('travels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employee_id')->nullable();
            $table->integer('user_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('purpose_of_visit', 255);
            $table->string('place_of_visit', 255);
            $table->longText('description');
            $table->integer('workspace')->nullable();
            $table->string('created_by', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travels');
    }
};
