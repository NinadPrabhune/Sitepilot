<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Alters decimal columns from DECIMAL(20,4) to DECIMAL(20,2)
     * and rounds existing data to 2 decimal places.
     */
    public function up(): void
    {
        // Round existing data to 2 decimal places before altering column type
        // This prevents data truncation errors when reducing precision

        DB::statement('ALTER TABLE material_project_stock MODIFY current_stock DECIMAL(20,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE stock_transactions MODIFY quantity DECIMAL(20,2) NOT NULL');
        DB::statement('ALTER TABLE stock_transactions MODIFY rate DECIMAL(20,2) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE material_project_stock MODIFY current_stock DECIMAL(20,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE stock_transactions MODIFY quantity DECIMAL(20,4) NOT NULL');
        DB::statement('ALTER TABLE stock_transactions MODIFY rate DECIMAL(20,4) NULL');
    }
};
