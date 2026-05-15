<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * FIX MIGRATION: Convert New Tables to InnoDB
 * ============================================================================
 *
 * MIGRATION: 2026_05_14_200008_convert_to_innodb.php
 *
 * PURPOSE: Convert all new MyISAM tables to InnoDB for:
 * - Transaction support
 * - Foreign key support
 * - Row-level locking
 * - Better crash recovery
 * - ACID compliance (critical for financial tables)
 *
 * SAFETY RATIONALE:
 * - InnoDB is the standard for transactional tables
 * - All existing data preserved
 * - ALTER TABLE is non-destructive
 * - Tables are new/empty, minimal risk
 *
 * PRODUCTION RISK: LOW
 * - Empty or new tables
 * - MyISAM → InnoDB conversion is safe
 * - Brief table lock during conversion
 *
 * ROLLBACK: Could convert back to MyISAM if issues (not recommended)
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            // Financial tables (HIGH PRIORITY - need ACID)
            'ledger_entries',
            'supplier_ledger',
            'supplier_ledger_entries',
            'financial_postings',
            'posting_batches',
            'posting_failures',
            'financial_period_locks',
            'financial_gate_blocks',

            // Machinery tables (HIGH PRIORITY - need ACID)
            'machinery_payment_requests',
            'machinery_payment_request_items',
            'machinery_payment_allocations',
            'machinery_ledger',
            'machinery_bills',
            'machinery_billing_items',
            'machinery_rate_histories',
            'machinery_supplier_rates',
            'machinery_ownerships',
            'machinery_usage_logs',
            'calculation_versions',

            // DPR Workflow tables
            'dpr_edit_history',
            'dpr_anomalies',
            'daily_health_check_logs',
            'workflow_transitions',
            'workflow_state_histories',
            'workflow_audits',

            // Audit tables (MEDIUM PRIORITY)
            'reconciliation_logs',
            'payment_audit_logs',
            'payment_health_logs',
            'payment_calculation_snapshots',
            'payment_request_status_logs',
            'payment_request_histories',
            'payment_reversals',
            'ledger_integrity_logs',
            'transaction_integrity_logs',
            'invariant_logs',
            'destructive_command_attempts',

            // Reference tables
            'item_categories',
            'items',
            'supplier_advances',
            'escalation_requests',
            'financial_escalations',
            'journal_adjustments',
            'material_consumption_audits',
            'material_consumption_versions',
            'usage_calculation_logs',
            'assets_tools_and_equipment_transfer',
        ];

        $converted = 0;
        $errors = 0;

        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} ENGINE = InnoDB");
                echo "Converted: {$table}\n";
                $converted++;
            } catch (\Exception $e) {
                echo "Error converting {$table}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }

        echo "\n";
        echo "=== SUMMARY ===\n";
        echo "Converted: {$converted}\n";
        echo "Errors: {$errors}\n";
    }

    public function down(): void
    {
        // Not recommended to revert, but documented if needed
        echo "Note: Downgrade from InnoDB to MyISAM is not recommended for production.\n";
        echo "If truly needed, manually convert using:\n";
        echo "ALTER TABLE {table_name} ENGINE = MyISAM;\n";
    }
};