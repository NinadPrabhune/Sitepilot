<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Mark legacy advance_against_po payments as LEGACY_ADVANCE_PO
     * This is part of the transition to the new Supplier Advance System
     */
    public function up(): void
    {
        // Update PaymentsModule records where payment_type = 'advance_against_po'
        $updated = DB::table('payments_module')
            ->where('payment_type', 'advance_against_po')
            ->update([
                'payment_type' => 'LEGACY_ADVANCE_PO',
            ]);

        // Add meta flag to mark as legacy (only if meta column exists)
        if ($updated > 0) {
            $hasMetaColumn = Schema::hasColumn('payments_module', 'meta');
            
            if ($hasMetaColumn) {
                DB::table('payments_module')
                    ->where('payment_type', 'LEGACY_ADVANCE_PO')
                    ->whereNull('meta')
                    ->update([
                        'meta' => json_encode([
                            'legacy' => true,
                            'migration_phase' => 'advance_system_refactor',
                            'legacy_payment_type' => 'advance_against_po',
                        ]),
                    ]);

                // Update existing meta to include legacy flag
                DB::table('payments_module')
                    ->where('payment_type', 'LEGACY_ADVANCE_PO')
                    ->whereNotNull('meta')
                    ->update([
                        'meta' => DB::raw("JSON_SET(
                            COALESCE(meta, '{}'),
                            '$.legacy', 'true',
                            '$.migration_phase', 'advance_system_refactor',
                            '$.legacy_payment_type', 'advance_against_po'
                        )"),
                    ]);
            }

            Log::info('Legacy advance payments marked', [
                'count' => $updated,
                'new_payment_type' => 'LEGACY_ADVANCE_PO',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert payment_type back to 'advance_against_po'
        $updated = DB::table('payments_module')
            ->where('payment_type', 'LEGACY_ADVANCE_PO')
            ->update(['payment_type' => 'advance_against_po']);

        // Remove legacy flags from meta (only if meta column exists)
        if ($updated > 0) {
            $hasMetaColumn = Schema::hasColumn('payments_module', 'meta');
            
            if ($hasMetaColumn) {
                DB::table('payments_module')
                    ->where('payment_type', 'advance_against_po')
                    ->whereNotNull('meta')
                    ->update([
                        'meta' => DB::raw("JSON_REMOVE(
                            COALESCE(meta, '{}'),
                            '$.legacy',
                            '$.migration_phase',
                            '$.legacy_payment_type'
                        )"),
                    ]);
            }

            Log::info('Legacy advance payments unmarked', [
                'count' => $updated,
                'restored_payment_type' => 'advance_against_po',
            ]);
        }
    }
};
