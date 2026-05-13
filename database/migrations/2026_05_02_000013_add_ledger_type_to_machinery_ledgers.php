<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add ledger_type field to machinery_ledgers table
     */
    public function up(): void
    {
        if (!Schema::hasColumn('machinery_ledgers', 'ledger_type')) {
            Schema::table('machinery_ledgers', function (Blueprint $table) {
                $table->enum('ledger_type', ['internal_cost', 'payable', 'expense'])->default('payable')->after('entry_type');
                
                // Index for reporting
                $table->index('ledger_type', 'idx_ledger_type');
            });
        }
        
        // Update existing records based on machinery ownership
        DB::statement("
            UPDATE machinery_ledgers ml
            JOIN machineries m ON ml.machinery_id = m.id
            SET ml.ledger_type = CASE 
                WHEN m.owned_by = 'owned' THEN 'internal_cost'
                WHEN m.owned_by = 'rental' THEN 'payable'
                ELSE 'expense'
            END
            WHERE ml.ledger_type = 'payable' AND ml.reference_type = 'DailyProgressReport'
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('machinery_ledgers', 'ledger_type')) {
            Schema::table('machinery_ledgers', function (Blueprint $table) {
                $table->dropIndex('idx_ledger_type');
                $table->dropColumn('ledger_type');
            });
        }
    }
};
