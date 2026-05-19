<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The entry_type column has wrong values (credit/debit instead of reading/diesel/etc)
        // We need to fix this by modifying the enum
        
        // First, add entry_direction column
        if (!Schema::hasColumn('machinery_ledger', 'entry_direction')) {
            Schema::table('machinery_ledger', function (Blueprint $table) {
                $table->enum('entry_direction', ['credit', 'debit'])->default('credit')->after('workspace_id');
            });
        }
        
        // Modify entry_type to have correct values (reading, diesel, etc)
        // SQLite and MySQL handle this differently - use raw SQL for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE machinery_ledger MODIFY COLUMN entry_type ENUM('reading', 'diesel', 'maintenance', 'advance', 'payment', 'transfer', 'opening_balance', 'correction', 'correction_reversal') DEFAULT 'reading'");
        }
        
        // Add reference_type and reference_id (not source_type/source_id)
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'reference_type')) {
                $table->string('reference_type', 50)->nullable()->after('entry_type');
            }
            if (!Schema::hasColumn('machinery_ledger', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
        });
        
        // Copy data from source_type/source_id to reference_type/reference_id
        DB::statement("UPDATE machinery_ledger 
            SET reference_type = COALESCE(source_type, 'DailyProgressReport'), reference_id = source_id
            WHERE reference_type IS NULL AND source_type IS NOT NULL");
        
        // Add dpr_id
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'dpr_id')) {
                $table->unsignedBigInteger('dpr_id')->nullable()->after('reference_id');
            }
        });
        
        // Add reversed_entry_id (not reversed_by_entry_id)  
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'reversed_entry_id')) {
                $table->unsignedBigInteger('reversed_entry_id')->nullable()->after('is_reversal');
            }
        });
        
        // Copy data to reversed_entry_id
        DB::statement("UPDATE machinery_ledger 
            SET reversed_entry_id = reversed_by_entry_id
            WHERE reversed_entry_id IS NULL AND reversed_by_entry_id IS NOT NULL");
        
        // Add dual flow columns
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'entry_source')) {
                $table->string('entry_source', 30)->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('machinery_ledger', 'entry_source_id')) {
                $table->unsignedBigInteger('entry_source_id')->nullable()->after('entry_source');
            }
        });
        
        // Add missing columns from model
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('idempotency_key');
            }
            if (!Schema::hasColumn('machinery_ledger', 'reversal_reference_id')) {
                $table->unsignedBigInteger('reversal_reference_id')->nullable()->after('is_settled');
            }
            if (!Schema::hasColumn('machinery_ledger', 'calculation_snapshot')) {
                $table->json('calculation_snapshot')->nullable()->after('reversal_reference_id');
            }
            if (!Schema::hasColumn('machinery_ledger', 'cost_center')) {
                $table->string('cost_center', 50)->nullable()->after('calculation_snapshot');
            }
        });
        
        // Drop duplicate/conflicting columns
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('machinery_ledger', 'entry_date')) {
                $table->dropColumn('entry_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $columnsToDrop = [
                'entry_direction',
                'reference_type', 
                'reference_id',
                'dpr_id',
                'reversed_entry_id',
                'entry_source',
                'entry_source_id',
                'is_settled',
                'reversal_reference_id',
                'calculation_snapshot',
                'cost_center',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('machinery_ledger', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'entry_date')) {
                $table->date('entry_date')->after('payment_request_id');
            }
        });
    }
};