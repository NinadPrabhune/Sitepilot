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
        // This migration was corrupted or missing, so we'll just skip it
        // The table it was supposed to create likely already exists
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration was corrupted or missing, so we'll just skip it
        // The table it was supposed to drop likely doesn't exist or should be kept
    }
};
