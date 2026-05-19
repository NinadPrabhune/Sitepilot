<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add cost_category field to machinery_ledger table
     */
    public function up(): void
    {
        if (!Schema::hasColumn('machinery_ledger', 'cost_category')) {
            Schema::table('machinery_ledger', function (Blueprint $table) {
                $table->enum('cost_category', ['machine', 'diesel', 'maintenance', 'operator', 'advance', 'other'])->default('machine')->after('ledger_type');
                
                // Index for reporting
                $table->index(['ledger_type', 'cost_category'], 'idx_ledger_cost_category');
            });
        }
        
        // Update existing records based on entry type
        DB::statement("
            UPDATE machinery_ledger SET cost_category = CASE 
                WHEN entry_type = 'reading' THEN 'machine'
                WHEN entry_type = 'diesel' THEN 'diesel'
                WHEN entry_type = 'maintenance' THEN 'maintenance'
                WHEN entry_type = 'advance' THEN 'advance'
                ELSE 'other'
            END
            WHERE cost_category = 'machine'
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('machinery_ledger', 'cost_category')) {
            Schema::table('machinery_ledger', function (Blueprint $table) {
                $table->dropIndex('idx_ledger_cost_category');
                $table->dropColumn('cost_category');
            });
        }
    }
};
