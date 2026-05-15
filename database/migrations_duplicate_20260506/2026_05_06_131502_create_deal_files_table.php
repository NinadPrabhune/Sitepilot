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
        Schema::create('deal_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('deal_id')->index('deal_files_deal_id_foreign');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_files');
    }
};
