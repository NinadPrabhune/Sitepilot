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
        Schema::create('skipped_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module', 20);
            $table->integer('site_id');
            $table->string('number', 50);
            $table->string('reason', 500)->nullable();
            $table->string('exception_message', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_created_at');

            $table->index(['module', 'site_id'], 'idx_module_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skipped_numbers');
    }
};
