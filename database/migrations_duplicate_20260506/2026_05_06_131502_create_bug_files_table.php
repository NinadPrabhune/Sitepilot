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
        Schema::create('bug_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('file', 255);
            $table->string('name', 255);
            $table->string('extension', 255);
            $table->string('file_size', 255);
            $table->integer('bug_id');
            $table->string('user_type', 255);
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_files');
    }
};
