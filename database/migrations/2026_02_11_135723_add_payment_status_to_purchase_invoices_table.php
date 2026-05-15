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

    public function down(): void
    {
        try {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_invoices', 'ac_payment_status')) {
                    $table->dropColumn('ac_payment_status');
                }
                if (Schema::hasColumn('purchase_invoices', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
            });
        } catch (\Exception $e) {
            // ignore
        }
    }
}
