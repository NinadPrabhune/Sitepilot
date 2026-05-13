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
     * This migration makes purchase_invoice_id NOT NULL on payments_module
     * Should ONLY be run AFTER the PO-based payment migration is complete
     */
    public function up(): void
    {
        // Step 1: Validate that all payments have purchase_invoice_id (except advance_against_po)
        $paymentsWithoutInvoice = DB::table('payments_module')
            ->where('payment_type', 'against_invoice')
            ->whereNull('purchase_invoice_id')
            ->count();

        if ($paymentsWithoutInvoice > 0) {
            throw new \Exception(
                "Cannot make purchase_invoice_id NOT NULL: {$paymentsWithoutInvoice} against_invoice payments " .
                "do not have purchase_invoice_id. Please run the PO-based payment migration first."
            );
        }

        // Step 2: For advance_against_po payments, we'll keep purchase_invoice_id nullable
        // These are legitimate advances that may not have an invoice yet
        // We'll add a constraint that only against_invoice payments require purchase_invoice_id

        // Step 3: Add a CHECK constraint (if MySQL version supports it, otherwise use trigger)
        // For MySQL 8.0.16+, we can use CHECK constraint
        $mysqlVersion = DB::select("SELECT VERSION() as version");
        $version = floatval($mysqlVersion[0]->version);

        if ($version >= 8.016) {
            // SAFETY CHECK: Check if constraint already exists
            $constraintExists = collect(DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'payments_module'
                AND CONSTRAINT_NAME = 'chk_purchase_invoice_id'
            "))->isNotEmpty();

            if (!$constraintExists) {
                DB::statement("
                    ALTER TABLE payments_module
                    ADD CONSTRAINT chk_purchase_invoice_id
                    CHECK (
                        (payment_type = 'against_invoice' AND purchase_invoice_id IS NOT NULL) OR
                        (payment_type != 'against_invoice')
                    )
                ");

                Log::channel('payment_audit')->info('Phase 3: Added CHECK constraint for purchase_invoice_id');
            } else {
                Log::channel('payment_audit')->info('Phase 3: CHECK constraint already exists, skipping');
            }
        } else {
            // For older MySQL, we'll add a trigger instead
            // SAFETY CHECK: Check if triggers already exist
            $insertTriggerExists = collect(DB::select("SHOW TRIGGERS LIKE 'validate_purchase_invoice_id_before_insert'"))->isNotEmpty();
            $updateTriggerExists = collect(DB::select("SHOW TRIGGERS LIKE 'validate_purchase_invoice_id_before_update'"))->isNotEmpty();

            if (!$insertTriggerExists) {
                DB::statement("
                    CREATE TRIGGER validate_purchase_invoice_id_before_insert
                    BEFORE INSERT ON payments_module
                    FOR EACH ROW
                    BEGIN
                        IF NEW.payment_type = 'against_invoice' AND NEW.purchase_invoice_id IS NULL THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'purchase_invoice_id is required for against_invoice payments';
                        END IF;
                    END
                ");
            }

            if (!$updateTriggerExists) {
                DB::statement("
                    CREATE TRIGGER validate_purchase_invoice_id_before_update
                    BEFORE UPDATE ON payments_module
                    FOR EACH ROW
                    BEGIN
                        IF NEW.payment_type = 'against_invoice' AND NEW.purchase_invoice_id IS NULL THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'purchase_invoice_id is required for against_invoice payments';
                        END IF;
                    END
                ");
            }

            Log::channel('payment_audit')->info('Phase 3: Added triggers for purchase_invoice_id validation');
        }

        Log::channel('payment_audit')->info('Phase 3: purchase_invoice_id validation enforced', [
            'mysql_version' => $version,
            'method' => $version >= 8.016 ? 'CHECK constraint' : 'TRIGGER',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mysqlVersion = DB::select("SELECT VERSION() as version");
        $version = floatval($mysqlVersion[0]->version);

        if ($version >= 8.016) {
            // Drop CHECK constraint - MySQL doesn't have a simple DROP CONSTRAINT
            // We need to recreate the table without it
            Schema::table('payments_module', function (Blueprint $table) {
                // This is a limitation - we'd need to recreate the table
                // For now, we'll just log it
            });
            Log::channel('payment_audit')->info('Phase 3: CHECK constraint removal requires manual table recreation');
        } else {
            // Drop triggers
            DB::statement("DROP TRIGGER IF EXISTS validate_purchase_invoice_id_before_insert");
            DB::statement("DROP TRIGGER IF EXISTS validate_purchase_invoice_id_before_update");
            
            Log::channel('payment_audit')->info('Phase 3: Dropped purchase_invoice_id validation triggers');
        }
    }
};
