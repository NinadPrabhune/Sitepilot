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
        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ticket_id', 100)->unique();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->integer('category')->nullable();
            $table->string('subject', 255);
            $table->string('status', 255);
            $table->longText('description')->nullable();
            $table->longText('attachments');
            $table->string('user_id', 255);
            $table->longText('note')->nullable();
            $table->integer('workspace')->default(0);
            $table->integer('created_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_tickets');
    }
};
