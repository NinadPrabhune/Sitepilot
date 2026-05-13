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
        Schema::create('login_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_id', 255);
            $table->string('ip', 255);
            $table->dateTime('date');
            $table->text('details');
            $table->string('type', 255);
            $table->integer('created_by');
            $table->integer('workspace')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_details');
    }
};
