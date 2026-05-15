<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_invoices', 'payment_status')) {
                    $table->dropColumn('payment_status');
                }
            });
        } catch (\Exception $e) {
            // ignore
        }
    }
};
