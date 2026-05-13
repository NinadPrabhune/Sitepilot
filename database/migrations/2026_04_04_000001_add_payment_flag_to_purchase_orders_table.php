<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PurchaseOrder;
use App\Models\PaymentsModule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_flag', ['Pending', 'Partial Received', 'Fully Received'])->default('Pending')->nullable()->after('status');
        });

        // Backfill existing POs with payment_flag based on current data
        $this->backfillPaymentFlags();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('payment_flag');
        });
    }

    /**
     * Backfill payment flags for existing POs based on current data.
     */
    private function backfillPaymentFlags(): void
    {
        $pos = PurchaseOrder::with(['items', 'invoices', 'payments'])->get();

        foreach ($pos as $po) {
            $this->calculateAndSetPaymentFlag($po);
        }
    }

    /**
     * Calculate payment flag for a PO based only on grand_total and payments made.
     */
    private function calculateAndSetPaymentFlag(PurchaseOrder $po): void
    {
        $totalPaid = $po->payments()
            ->whereIn('payment_type', [
                PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO,
                PaymentsModule::PAYMENT_TYPE_AGAINST_PO
            ])
            ->sum('amount');

        $grandTotal = (float) $po->grand_total;

        if ($grandTotal <= 0) {
            $po->payment_flag = 'Pending';
        } elseif ($totalPaid >= $grandTotal) {
            $po->payment_flag = 'Fully Received';
        } elseif ($totalPaid > 0) {
            $po->payment_flag = 'Partial Received';
        } else {
            $po->payment_flag = 'Pending';
        }

        $po->saveQuietly();
    }
};