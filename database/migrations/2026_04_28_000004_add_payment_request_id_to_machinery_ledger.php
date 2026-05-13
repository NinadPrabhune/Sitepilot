<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->foreignId('payment_request_id')->nullable()->constrained('machinery_payment_requests')->onDelete('restrict');
            // CRITICAL: Composite index for aggregation queries
            $table->index(['machinery_id', 'workspace_id', 'date', 'payment_request_id'], 'ml_mach_ws_date_pr');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->dropIndex('ml_mach_ws_date_pr');
            $table->dropForeign(['payment_request_id']);
            $table->dropColumn('payment_request_id');
        });
    }
};
