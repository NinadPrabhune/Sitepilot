<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix payment_status values that have quotes around them
     */
    public function up()
    {
        // Fix records where payment_status contains the literal string with quotes
        // These are records that incorrectly stored '"unpaid"' instead of 'unpaid'
        
        // Update records with quoted values
        DB::table('purchase_invoices')
            ->where('payment_status', '"unpaid"')
            ->update(['payment_status' => 'unpaid']);
            
        DB::table('purchase_invoices')
            ->where('payment_status', '"paid"')
            ->update(['payment_status' => 'paid']);
            
        DB::table('purchase_invoices')
            ->where('payment_status', '"partially paid"')
            ->update(['payment_status' => 'partially paid']);
            
        DB::table('purchase_invoices')
            ->where('payment_status', '"overpaid"')
            ->update(['payment_status' => 'overpaid']);
            
        // Also handle any records that might have single quotes
        DB::table('purchase_invoices')
            ->where('payment_status', "'unpaid'")
            ->update(['payment_status' => 'unpaid']);
            
        DB::table('purchase_invoices')
            ->where('payment_status', "'paid'")
            ->update(['payment_status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // No rollback needed for this fix
    }
};