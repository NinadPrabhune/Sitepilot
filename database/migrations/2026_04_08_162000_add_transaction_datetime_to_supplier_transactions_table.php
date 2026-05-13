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
        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->datetime('transaction_datetime')->nullable()->after('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop column if table exists
        if (Schema::hasTable('supplier_transactions')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                // Only drop column if it exists
                if (Schema::hasColumn('supplier_transactions', 'transaction_datetime')) {
                    $table->dropColumn('transaction_datetime');
                }
            });
        }
    }
};
