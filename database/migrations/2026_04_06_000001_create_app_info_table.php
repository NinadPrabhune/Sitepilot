<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_info', function (Blueprint $table) {
            $table->id();
            $table->string('call_us')->nullable();
            $table->string('email_us')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('version')->nullable();
            $table->datetime('last_updated')->nullable();
            $table->text('privacy_policy')->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_info');
    }
};