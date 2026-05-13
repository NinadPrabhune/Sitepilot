<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add version column to daily_consumption_masters for state tracking (if not exists)
        if (!Schema::hasColumn('daily_consumption_masters', 'version')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->integer('version')->default(1)->after('status');
                $table->index('version');
            });
        }
        
        // Add indexes to machinery_ledgers for correction chain queries (MySQL compatible)
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Use raw SQL for JSON indexes to be MySQL compatible
        });
        
        // Skip JSON indexes for older MySQL versions - functionality still works at application level
        // JSON indexes would improve performance but are not required for correctness
        \Log::info('Skipping JSON indexes due to MySQL version compatibility - application-level validation will enforce constraints');
        
        // Create stored procedure for ledger immutability trigger
        DB::unprepared('
            CREATE PROCEDURE prevent_ledger_update()
            BEGIN
                SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Ledger entries are immutable. Use reversal + new entry pattern.";
            END
        ');
        
        // Create trigger to prevent ledger updates
        DB::unprepared('
            CREATE TRIGGER prevent_machinery_ledger_update
            BEFORE UPDATE ON machinery_ledgers
            FOR EACH ROW
            BEGIN
                CALL prevent_ledger_update();
            END
        ');
        
        // Create trigger to prevent ledger deletions
        DB::unprepared('
            CREATE TRIGGER prevent_machinery_ledger_delete
            BEFORE DELETE ON machinery_ledgers
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Ledger entries cannot be deleted. Use reversal + new entry pattern.";
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_delete');
        
        // Drop stored procedure
        DB::unprepared('DROP PROCEDURE IF EXISTS prevent_ledger_update');
        
        // No indexes to drop - they were skipped due to MySQL version compatibility
        
        // Remove version column (if exists)
        if (Schema::hasColumn('daily_consumption_masters', 'version')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
