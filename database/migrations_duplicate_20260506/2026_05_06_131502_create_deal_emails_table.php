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
        Schema::create('deal_emails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('deal_id')->index('deal_emails_deal_id_foreign');
            $table->string('to', 255);
            $table->string('subject', 255);
            $table->longText('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_emails');
    }
};
