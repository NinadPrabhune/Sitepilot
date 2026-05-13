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
        // Only modify table if it exists
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        $validTypes = ['po', 'grn', 'invoice', 'payment', 'advance', 'adjustment'];

        if ($driver === 'mysql') {
            DB::statement("UPDATE supplier_transactions SET reference_type = 'po' 
                WHERE reference_type IS NULL 
                OR reference_type = '' 
                OR reference_type NOT IN ('" . implode("','", $validTypes) . "')");

            DB::statement("ALTER TABLE supplier_transactions MODIFY reference_type VARCHAR(20) NOT NULL DEFAULT 'po'");

            DB::statement("ALTER TABLE supplier_transactions ADD CONSTRAINT chk_reference_type CHECK (reference_type IN ('po', 'grn', 'invoice', 'payment', 'advance', 'adjustment'))");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE supplier_transactions SET reference_type = 'po' 
                WHERE reference_type IS NULL 
                OR reference_type = '' 
                OR reference_type NOT IN ('" . implode("','", $validTypes) . "')");

            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->string('reference_type')->nullable(false)->default('po')->change();
            });

            DB::statement("ALTER TABLE supplier_transactions ADD CONSTRAINT chk_reference_type CHECK (reference_type IN ('po', 'grn', 'invoice', 'payment', 'advance', 'adjustment'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only modify table if it exists
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Check if constraint exists before dropping it
            $constraintExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND CONSTRAINT_NAME = 'chk_reference_type'
            ");
            
            if ($constraintExists[0]->count > 0) {
                DB::statement("ALTER TABLE supplier_transactions DROP CHECK chk_reference_type");
            }
            DB::statement("ALTER TABLE supplier_transactions MODIFY reference_type VARCHAR(20) DEFAULT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE supplier_transactions DROP CONSTRAINT IF EXISTS chk_reference_type");
            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->string('reference_type')->nullable()->change();
            });
        }
    }
};