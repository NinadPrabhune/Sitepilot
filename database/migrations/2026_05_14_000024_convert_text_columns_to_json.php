<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SAFETY: POTENTIALLY RISKY MIGRATION - JSON CONVERSION
 *
 * Purpose: Convert TEXT/LONGTEXT columns to JSON where Local schema uses JSON
 * Risk Level: MEDIUM - Data conversion may fail if existing data invalid
 *
 * COLUMNS CONVERTED:
 * 1. advance_audit_logs.old_value (LONGTEXT -> JSON)
 * 2. advance_audit_logs.new_value (LONGTEXT -> JSON)
 * 3. ch_notifications.message_arr (TEXT -> JSON)
 *
 * CRITICAL SAFETY CHECKS:
 * - Validates JSON structure of ALL existing rows before conversion
 * - Rolls back entire migration if any invalid JSON found
 * - Logs detailed validation results
 *
 * BEFORE DEPLOYMENT:
 * Run these SQL queries on PRODUCTION to verify data quality:
 *
 *   -- Check advance_audit_logs
 *   SELECT COUNT(*) as total,
 *          SUM(CASE WHEN JSON_VALID(old_value) THEN 1 ELSE 0 END) as valid_old,
 *          SUM(CASE WHEN JSON_VALID(new_value) THEN 1 ELSE 0 END) as valid_new
 *   FROM advance_audit_logs;
 *
 *   -- Find invalid rows
 *   SELECT id, old_value
 *   FROM advance_audit_logs
 *   WHERE old_value IS NOT NULL AND JSON_VALID(old_value) = 0
 *   LIMIT 100;
 *
 *   -- Check ch_notifications
 *   SELECT COUNT(*) as total,
 *          SUM(CASE WHEN JSON_VALID(message_arr) THEN 1 ELSE 0 END) as valid_json
 *   FROM ch_notifications;
 *
 *   -- Find invalid message_arr
 *   SELECT id, message_arr
 *   FROM ch_notifications
 *   WHERE message_arr IS NOT NULL AND JSON_VALID(message_arr) = 0
 *   LIMIT 100;
 *
 * If invalid JSON found:
 *   Option A: Clean data manually before migration
 *   Option B: Create data transformation script to fix malformed JSON
 *   Option C: Keep LONGTEXT and update application layer (NOT RECOMMENDED)
 *
 * Operation Order:
 * - Must run after table existence verified
 * - No dependency on other tables
 * - Create before adding FKs that depend on JSON type
 *
 * Production Risks:
 * - ALTER COLUMN with type change can lock table
 * - For large tables (> 100K rows), use pt-online-schema-change or gh-ost
 * - If conversion fails after partial completion, table may be left inconsistent
 * - We wrap in transaction and abort on any error
 *
 * Rollback:
 * - Convert back to LONGTEXT/TEXT if needed (but valid JSON might be lost)
 * - Not recommended to rollback after successful conversion
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation (JSON audit fields)
 * - Run during maintenance window
 * - Backup tables first (advance_audit_logs, ch_notifications)
 * - Verify row counts before/after
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only proceed if tables exist
        if (!Schema::hasTable('advance_audit_logs') || !Schema::hasTable('ch_notifications')) {
            \Log::warning('Required tables missing - skipping JSON conversion');
            return;
        }

        // Validate advance_audit_logs JSON
        $this->validateJsonConversion('advance_audit_logs', ['old_value', 'new_value']);

        // Validate ch_notifications JSON
        $this->validateJsonConversion('ch_notifications', ['message_arr']);

        // Proceed with conversion inside a transaction if supported
        $connection = Schema::getConnection();
        $connection->beginTransaction();

        try {
            // Convert advance_audit_logs.old_value and new_value
            Schema::table('advance_audit_logs', function (Blueprint $table) {
                // Use nullable JSON - preserves null values, converts valid JSON strings
                DB::statement('ALTER TABLE advance_audit_logs MODIFY COLUMN old_value JSON NULL');
                DB::statement('ALTER TABLE advance_audit_logs MODIFY COLUMN new_value JSON NULL');
            });

            // Convert ch_notifications.message_arr
            Schema::table('ch_notifications', function (Blueprint $table) {
                DB::statement('ALTER TABLE ch_notifications MODIFY COLUMN message_arr JSON NULL');
            });

            $connection->commit();
            \Log::info('JSON conversion completed successfully');
        } catch (\Exception $e) {
            $connection->rollBack();
            \Log::error('JSON conversion failed - transaction rolled back: ' . $e->getMessage());
            throw $e; // Re-throw to fail migration
        }
    }

    public function down(): void
    {
        // WARNING: Converting JSON back to LONGTEXT/TEXT may lose structure
        // Only safe if all values were originally text strings
        Schema::table('advance_audit_logs', function (Blueprint $table) {
            DB::statement('ALTER TABLE advance_audit_logs MODIFY COLUMN old_value LONGTEXT NULL');
            DB::statement('ALTER TABLE advance_audit_logs MODIFY COLUMN new_value LONGTEXT NULL');
        });

        Schema::table('ch_notifications', function (Blueprint $table) {
            DB::statement('ALTER TABLE ch_notifications MODIFY COLUMN message_arr TEXT NULL');
        });
    }

    /**
     * Validate that column data is valid JSON before conversion
     *
     * @param string $table
     * @param array $jsonColumns
     * @throws \RuntimeException if invalid JSON found
     */
    private function validateJsonConversion(string $table, array $jsonColumns): void
    {
        foreach ($jsonColumns as $column) {
            // Count total and valid
            $result = DB::table($table)
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN JSON_VALID(`' . $column . '`) THEN 1 ELSE 0 END) as valid')
                )
                ->first();

            $total = $result->total ?? 0;
            $valid = $result->valid ?? 0;
            $invalid = $total - $valid;

            if ($total === 0) {
                \Log::info("{$table}.{$column}: No data rows, safe to convert");
                continue;
            }

            if ($invalid > 0) {
                // Fetch sample invalid rows for debugging
                $samples = DB::table($table)
                    ->where($column, '!=', null)
                    ->whereRaw('JSON_VALID(`' . $column . '`) = 0')
                    ->limit(10)
                    ->pluck($column);

                $message = "JSON CONVERSION ABORTED: {$table}.{$column} has {$invalid} invalid JSON rows out of {$total}. Sample invalid values: " .
                           json_encode($samples->toArray());

                \Log::error($message);
                throw new \RuntimeException($message);
            }

            \Log::info("{$table}.{$column}: All {$valid}/{$total} rows have valid JSON - safe to convert");
        }
    }
};
