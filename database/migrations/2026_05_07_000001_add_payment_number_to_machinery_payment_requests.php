<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add payment_number column to machinery_payment_requests table
     */
    public function up(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            $table->string('payment_number', 50)->nullable()->after('id');
            $table->index(['payment_number'], 'idx_machinery_payment_number');
            $table->unique(['payment_number'], 'unique_machinery_payment_number');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_payment_number');
            $table->dropUnique('unique_machinery_payment_number');
            $table->dropColumn('payment_number');
        });
    }
};
