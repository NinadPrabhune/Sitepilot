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
        Schema::create('bill_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('chart_account_id')->default(0);
            $table->double('price')->default(0);
            $table->string('description', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->integer('ref_id')->default(0);
            $table->integer('workspace')->nullable();
            $table->integer('created_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_accounts');
    }
};
