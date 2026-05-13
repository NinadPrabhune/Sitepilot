<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE supplier_transactions ADD CONSTRAINT chk_reference_amount CHECK (
                (reference_type IN ('po', 'invoice', 'payment', 'advance') AND reference_amount > 0)
                OR
                (reference_type = 'grn' AND reference_amount >= 0)
                OR
                (reference_type = 'adjustment' AND reference_amount >= 0)
            )");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Check if constraint exists before dropping it
            $constraintExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND CONSTRAINT_NAME = 'chk_reference_amount'
            ");
            
            if ($constraintExists[0]->count > 0) {
                DB::statement("ALTER TABLE supplier_transactions DROP CHECK chk_reference_amount");
            }
        }
    }
};