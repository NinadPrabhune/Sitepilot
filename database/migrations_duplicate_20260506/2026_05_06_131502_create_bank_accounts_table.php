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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('holder_name', 255);
            $table->integer('chart_account_id')->default(0);
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_type', 255);
            $table->string('wallet_type', 255)->nullable();
            $table->string('account_number', 255)->nullable();
            $table->float('opening_balance')->default(0);
            $table->string('contact_number', 255)->nullable();
            $table->text('bank_address');
            $table->string('bank_branch', 255)->nullable();
            $table->string('swift', 255)->nullable();
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
        Schema::dropIfExists('bank_accounts');
    }
};
