<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only modify table if it exists
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE supplier_transactions SET reference_amount = 0 WHERE reference_amount IS NULL OR reference_amount = ''");

            DB::statement("ALTER TABLE supplier_transactions MODIFY reference_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE supplier_transactions SET reference_amount = 0 WHERE reference_amount IS NULL OR reference_amount = ''");

            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->decimal('reference_amount', 15, 2)->nullable(false)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        // Only modify table if it exists
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE supplier_transactions MODIFY reference_amount DECIMAL(15,2) DEFAULT NULL");
        } elseif ($driver === 'pgsql') {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->decimal('reference_amount', 15, 2)->nullable()->change();
            });
        }
    }
};