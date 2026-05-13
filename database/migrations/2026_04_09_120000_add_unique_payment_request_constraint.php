<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->unique('payment_request_id', 'unique_payment_request');
        });
    }

    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropUnique('unique_payment_request');
        });
    }
};
