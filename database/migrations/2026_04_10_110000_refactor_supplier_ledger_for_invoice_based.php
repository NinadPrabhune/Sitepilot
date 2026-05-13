<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        // Ensure meta column exists
        if (!Schema::hasColumn('supplier_transactions', 'meta')) {
            Schema::table('supplier_transactions', function ($table) {
                $table->json('meta')->nullable()->after('reference_amount');
            });
        }

        // 1. Update PO entries: set debit = reference_amount and add non_accounting meta
        DB::statement("
            UPDATE supplier_transactions 
            SET debit = COALESCE(reference_amount, 0),
                meta = JSON_SET(COALESCE(meta, JSON_OBJECT()), '$.non_accounting', TRUE)
            WHERE reference_type = 'po' AND debit = 0
        ");

        // 2. Update existing ADVANCE entries: set payment_subtype = 'advance'
        DB::statement("
            UPDATE supplier_transactions 
            SET meta = JSON_SET(COALESCE(meta, JSON_OBJECT()), '$.payment_subtype', 'advance')
            WHERE reference_type = 'advance' 
            AND (meta IS NULL OR JSON_EXTRACT(meta, '$.payment_subtype') IS NULL)
        ");

        // 3. Update existing PAYMENT entries: set payment_subtype = 'invoice_payment'
        DB::statement("
            UPDATE supplier_transactions 
            SET meta = JSON_SET(COALESCE(meta, JSON_OBJECT()), '$.payment_subtype', 'invoice_payment')
            WHERE reference_type = 'payment'
            AND (meta IS NULL OR JSON_EXTRACT(meta, '$.payment_subtype') IS NULL)
        ");

        // 4. Recalculate balances for all suppliers
        $this->recalculateAllBalances();
    }

    protected function recalculateAllBalances(): void
    {
        $supplierIds = DB::table('supplier_transactions')
            ->distinct()
            ->pluck('supplier_id');

        foreach ($supplierIds as $supplierId) {
            $this->recalculateBalanceForSupplier($supplierId);
        }
    }

    protected function recalculateBalanceForSupplier(int $supplierId): void
    {
        $transactions = DB::table('supplier_transactions')
            ->where('supplier_id', $supplierId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $runningBalance = 0;
        $informationalTypes = ['po', 'grn', 'advance'];

        foreach ($transactions as $transaction) {
            $referenceType = $transaction->reference_type;
            $meta = $transaction->meta ? json_decode($transaction->meta, true) : [];
            $isNonAccounting = !empty($meta['non_accounting']);

            if (in_array($referenceType, $informationalTypes) || $isNonAccounting) {
                // Skip advances and non-accounting entries from balance calculation
            } else {
                $runningBalance = $runningBalance + $transaction->debit - $transaction->credit;
            }

            DB::table('supplier_transactions')
                ->where('id', $transaction->id)
                ->update(['balance' => $runningBalance]);
        }
    }

    public function down(): void
    {
        // Reverse operation not supported for data migration
    }
};