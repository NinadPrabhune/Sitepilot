<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->decimal('net_payable_snapshot', 15, 2)->nullable()->after('approved_at');
            $table->decimal('advance_used_snapshot', 15, 2)->nullable()->after('net_payable_snapshot');
            $table->decimal('paid_amount_snapshot', 15, 2)->nullable()->after('advance_used_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn([
                'net_payable_snapshot',
                'advance_used_snapshot',
                'paid_amount_snapshot',
            ]);
        });
    }
};