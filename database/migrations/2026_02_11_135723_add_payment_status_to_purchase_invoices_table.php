<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentStatusToPurchaseInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Change from enum to string, nullable
            $table->string('ac_payment_status')->nullable()->after('total_amount');
            $table->text('rejection_reason')->nullable()->after('ac_payment_status');
        });

    }

    public function down()
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['ac_payment_status', 'rejection_reason']);
        });
    }
}
