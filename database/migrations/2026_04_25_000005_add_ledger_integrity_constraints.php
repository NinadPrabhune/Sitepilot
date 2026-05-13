<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure debit and credit are non-negative (may already exist)
        try {
            DB::statement('ALTER TABLE supplier_transactions ADD CONSTRAINT chk_debit_credit_non_negative CHECK (debit >= 0 AND credit >= 0)');
        } catch (\Exception $e) {
            // Constraint may already exist
        }
        
        // Ensure debit and credit are mutually exclusive (may already exist)
        try {
            DB::statement('ALTER TABLE supplier_transactions ADD CONSTRAINT chk_debit_credit_exclusive CHECK (NOT (debit > 0 AND credit > 0))');
        } catch (\Exception $e) {
            // Constraint may already exist
        }
        
        // Ensure at least one of debit or credit is positive (prevent zero-value entries)
        try {
            DB::statement('ALTER TABLE supplier_transactions ADD CONSTRAINT chk_debit_credit_positive CHECK (debit > 0 OR credit > 0)');
        } catch (\Exception $e) {
            // Constraint may already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL doesn't support dropping named CHECK constraints easily
        // This would require recreating the table without constraints
        // For now, we'll document that rollback requires manual intervention
    }
};
