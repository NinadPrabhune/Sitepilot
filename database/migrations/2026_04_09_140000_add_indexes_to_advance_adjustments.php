<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_adjustments', function (Blueprint $table) {
            $table->index(['purchase_invoice_id', 'deleted_at'], 'idx_advance_inv_deleted');
            $table->index(['payment_id', 'deleted_at'], 'idx_advance_payment_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('advance_adjustments', function (Blueprint $table) {
            $table->dropIndex('idx_advance_inv_deleted');
            $table->dropIndex('idx_advance_payment_deleted');
        });
    }
};