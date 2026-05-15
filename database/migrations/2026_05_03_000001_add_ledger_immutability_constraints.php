<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add version column
        if (!Schema::hasColumn('daily_consumption_masters', 'version')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->integer('version')->default(1)->after('status');
                $table->index('version');
            });
        }

        // Skip JSON indexes for compatibility
        \Log::info(
            'Skipping JSON indexes due to MySQL version compatibility'
        );

        // Drop existing triggers if rerunning
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_delete');

        // Prevent updates
        DB::unprepared('
            CREATE TRIGGER prevent_machinery_ledger_update
            BEFORE UPDATE ON machinery_ledgers
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE "45000"
                SET MESSAGE_TEXT = "Ledger entries are immutable. Use reversal + new entry pattern.";
            END
        ');

        // Prevent deletes
        DB::unprepared('
            CREATE TRIGGER prevent_machinery_ledger_delete
            BEFORE DELETE ON machinery_ledgers
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE "45000"
                SET MESSAGE_TEXT = "Ledger entries cannot be deleted. Use reversal + new entry pattern.";
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_machinery_ledger_delete');

        if (Schema::hasColumn('daily_consumption_masters', 'version')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->dropIndex(['version']);
                $table->dropColumn('version');
            });
        }
    }
};