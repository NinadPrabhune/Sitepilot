<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            if (!Schema::hasColumn('payments_module', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->cascadeOnDelete();
            }
            
            if (!Schema::hasColumn('payments_module', 'status')) {
                $table->enum('status', ['completed', 'pending', 'cancelled'])->default('completed')->after('payment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn(['purchase_order_id', 'status']);
        });
    }
};