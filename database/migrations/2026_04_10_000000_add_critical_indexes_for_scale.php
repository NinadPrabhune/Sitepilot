<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // payment_requests indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pr_status ON payment_requests(status)');
        } catch (\Exception $e) {
            // Index may already exist
        }
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pr_invoice_status ON payment_requests(purchase_invoice_id, status)');
        } catch (\Exception $e) {
            // Index may already exist
        }

        // payments_module indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pm_invoice ON payments_module(purchase_invoice_id)');
        } catch (\Exception $e) {
            // Index may already exist
        }
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pm_po_type ON payments_module(purchase_order_id, payment_type)');
        } catch (\Exception $e) {
            // Index may already exist
        }

        // advance_adjustments indexes
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_aa_payment ON advance_adjustments(payment_id)');
        } catch (\Exception $e) {
            // Index may already exist
        }
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_aa_invoice_deleted ON advance_adjustments(purchase_invoice_id, deleted_at)');
        } catch (\Exception $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS idx_pr_status ON payment_requests'); } catch (\Exception $e) {}
        try { DB::statement('DROP INDEX IF EXISTS idx_pr_invoice_status ON payment_requests'); } catch (\Exception $e) {}
        try { DB::statement('DROP INDEX IF EXISTS idx_pm_invoice ON payments_module'); } catch (\Exception $e) {}
        try { DB::statement('DROP INDEX IF EXISTS idx_pm_po_type ON payments_module'); } catch (\Exception $e) {}
        try { DB::statement('DROP INDEX IF EXISTS idx_aa_payment ON advance_adjustments'); } catch (\Exception $e) {}
        try { DB::statement('DROP INDEX IF EXISTS idx_aa_invoice_deleted ON advance_adjustments'); } catch (\Exception $e) {}
    }
};
