<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->onDelete('set null')->after('purchase_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropForeign(['payment_request_id']);
            $table->dropColumn('payment_request_id');
        });
    }
};