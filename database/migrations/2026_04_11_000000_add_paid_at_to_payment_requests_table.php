<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SAFETY CHECK: Only add column if it doesn't exist
        if (!Schema::hasColumn('payment_requests', 'paid_at')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('approved_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
